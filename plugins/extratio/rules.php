<?php

require_once( dirname(__FILE__).'/../../php/cache.php');
require_once( dirname(__FILE__).'/../../php/util.php');
require_once( dirname(__FILE__).'/../../php/settings.php');

define( 'RR_LABEL_CONTAIN', 0 );
define( 'RR_TRACKER_CONTAIN', 1 );
define( 'RR_TRACKER_PRIVATE', 2 );
define( 'RR_TRACKER_PUBLIC', 3 );

class rRatioRule
{
	public $name;
	public $reason;
	public $pattern;
	public $enabled;
	public $no;	// deprecated

	public $ratio;
	public $channel;

	public function	__construct( $name, $reason = RR_LABEL_CONTAIN, $pattern = '', $enabled = 0, $no = 0, $ratio = '', $channel = '' )
	{
		$this->name = $name;
		$this->reason = $reason;
		$this->pattern = $pattern;
		$this->enabled = $enabled;
		$this->ratio = $ratio;
		$this->channel = $channel;
		$this->no = $no;
	}
	static public function isTrackerPrivate( $trackers )
	{
		$trks = explode( '#', $trackers );
		foreach( $trks as $trk )
		{
			$ret = null;
			rTorrentSettings::get()->pushEvent( "CheckTracker", array( "announce"=>$trk, "result"=>&$ret ) );
			if( $ret ||
				(is_null($ret) &&
					(preg_match( '`^(http|https|udp)://(?:[0-9]{1,3}\.){3}[0-9]{1,3}((:(\d){2,5})|).*(/a.*(\?.+=.+|/.+)|\?.+=.+)`i', $trk ) ||
					preg_match( '`^(http|https|udp)://(?:[0-9]{1,3}\.){3}[0-9]{1,3}((:(\d){2,5})|)/.*[0-9a-z]{8,32}/a`i', $trk ) ||
					preg_match( '`^(http|https|udp)://[a-z0-9-\.]+\.[a-z]{2,253}((:(\d){2,5})|).*(/a.*(\?.+=.+|/.+)|\?.+=.+)`i', $trk ) ||
					preg_match( '`^(http|https|udp)://[a-z0-9-\.]+\.[a-z]{2,253}((:(\d){2,5})|)/.*[0-9a-z]{8,32}/a`i', $trk ))) )
				return(true);
		}
		return(false);
	}
	public function isApplicable( $label, $trackers )
	{
		$ret = false;
		if($this->enabled==1)
		{
			switch($this->reason)
			{
				case RR_LABEL_CONTAIN:
				{
					$ret = !is_null($label) && ((stripos( $label, $this->pattern )!==false) || (($label==='') && ($this->pattern==='')));
					break;
				}
				case RR_TRACKER_CONTAIN:
				{
					$ret = !is_null($trackers) && (stripos( $trackers, $this->pattern )!==false);
					break;
				}
				case RR_TRACKER_PUBLIC:
				{
					$ret = !is_null($trackers) && !self::isTrackerPrivate($trackers);
					break;
				}
				case RR_TRACKER_PRIVATE:
				{
					$ret = !is_null($trackers) && self::isTrackerPrivate($trackers);
					break;
				}
			}
		}
		return($ret);
	}
}

class rRatioRulesList
{
	public $hash = "ratiorules.dat";
        public $lst = array();

	static public function load()
	{
		$cache = new rCache();
		$ar = new rRatioRulesList();
		$cache->get($ar);
		return($ar);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	public function add( $rule )
	{
		$this->lst[] = $rule;
	}
        public function set()
	{
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		$this->lst = array();
		$rule = null;
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = explode('&', $HTTP_RAW_POST_DATA);
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				if($parts[0]=="name")
				{
					if($rule)
						$this->add($rule);
					$rule = new rRatioRule(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="pattern")
				{
					if($rule)
						$rule->pattern = trim(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="reason")
				{
					if($rule)
						$rule->reason = intval($parts[1]);
				}
				else
				if($parts[0]=="enabled")
				{
					if($rule)
						$rule->enabled = intval($parts[1]);
				}
				else
				if($parts[0]=="no")
				{
					if($rule)
						$rule->no = intval($parts[1]);
				}
				else
				if($parts[0]=="ratio")
				{
					if($rule)
						$rule->ratio = $parts[1];
				}
				else
				if($parts[0]=="channel")
				{
					if($rule)
						$rule->channel = $parts[1];
				}
  	                }
			if($rule)
				$this->add($rule);
			$this->store();
			$this->setHandlers();
		}
	}
	public function getContents()
	{
		return($this->lst);
	}
	public function getRule( $label, $trackers )
	{
		foreach( $this->lst as $item )
		{
			if($item->isApplicable( $label, $trackers ))
				return($item);
		}
		return(null);
	}
	public function checkLabels( $hashes )
	{
		$req = new rXMLRPCRequest();
		foreach( $hashes as $hash )
		{
			$req->addCommand( new rXMLRPCCommand( "d.get_custom1", $hash ) ); 
			$req->addCommand( new rXMLRPCCommand( "d.get_state", $hash ) );
			$req->addCommand( new rXMLRPCCommand("branch", array
			(
				$hash,
				'',
				getCmd("cat").'=$'.getCmd("d.views="),
				getCmd("cat").'=$'.getCmd("d.views="),
			)));
			$req->addCommand( new rXMLRPCCommand( "d.get_throttle_name", $hash ) );
		}
		if($req->getCommandsCount() && $req->success())
		{
			$out = new rXMLRPCRequest();
			foreach( $hashes as $ndx=>$hash )
			{
				$label = rawurldecode($req->val[$ndx*4]);
				$state = !empty($req->val[$ndx*4+1]);
				$ratio = null;
				if( preg_match( '`rat_(\d+)`',$req->val[$ndx*4+2],$matches ) )
					$ratio = 'rat_'.$matches[1];	
				$throttle = $req->val[$ndx*4+3];

				$trackers = '';
			        $req1 = new rXMLRPCRequest( array(
					new rXMLRPCCommand("t.multicall", 
						array($hash,"",getCmd("t.get_url=")))));
				if($req1->success())
					$trackers = implode( '#', $req1->val );
				$rule = $this->getRule( $label, $trackers );
				if($rule)
				{
					if(!empty($rule->channel) && ($rule->channel!=$throttle))
					{
						if($state)
							$out->addCommand( new rXMLRPCCommand('d.stop', $hash) );
						$out->addCommand( new rXMLRPCCommand('d.set_throttle_name', array($hash,$rule->channel)) );
						if($state)
							$out->addCommand( new rXMLRPCCommand('d.start', $hash) );
					}
					if(!empty($rule->ratio) && ($rule->ratio!=$ratio))
					{
						if(!is_null($ratio))
						{
							$out->addCommand( new rXMLRPCCommand('view.set_not_visible', array($hash, $ratio) ) );
							$out->addCommand( new rXMLRPCCommand('d.views.remove', array($hash, $ratio) ) );
						}
						$out->addCommand( new rXMLRPCCommand('d.views.push_back_unique', array($hash, $rule->ratio) ) );
						$out->addCommand( new rXMLRPCCommand('view.set_visible', array($hash, $rule->ratio) ) );
					}
				}
			}
			if($out->getCommandsCount())
				$out->run();
		}
	}
	public function setHandlers()
	{
		global $rootPath;
	        $throttleRulesExist = false;
	        $ratioRulesExist = false;
		foreach( $this->lst as $item )
		{
			if($item->ratio!='')
				$ratioRulesExist = true;
			if($item->channel!='')		
				$throttleRulesExist = true;
		}
		if($ratioRulesExist)
		{
			eval(FileUtil::getPluginConf('ratio'));
			$insCmd = '';
			for($i=0; $i<MAX_RATIO; $i++)
				$insCmd .= (getCmd('d.views.has=').'rat_'.$i.',,');
			$ratCmd = 
                                getCmd('d.set_custom').'=x-extratio1,"$'.getCmd('execute_capture').
                                '={'.Utility::getPHP().','.$rootPath.'/plugins/extratio/update.php,\"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#\",$'.getCmd('d.get_custom1').'=,ratio,'.User::getUser().'}" ; '.
                                getCmd('branch').'=$'.getCmd('not').'=$'.getCmd('d.get_custom').'=x-extratio1,,'.$insCmd.
                                getCmd('view.set_visible').'=$'.getCmd('d.get_custom').'=x-extratio1';
		}
		else
			$ratCmd = getCmd('cat=');
		if($throttleRulesExist)
			$thrCmd = 
                                getCmd('d.set_custom').'=x-extratio2,"$'.getCmd('execute_capture').
                                '={'.Utility::getPHP().','.$rootPath.'/plugins/extratio/update.php,\"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#\",$'.getCmd('d.get_custom1').'=,channel,'.User::getUser().'}" ; '.
                                getCmd('branch').'=$'.getCmd('not').'=$'.getCmd('d.get_custom').'=x-extratio2,,'.
                                getCmd('d.set_throttle_name').'=$'.getCmd('d.get_custom').'=x-extratio2';
		else
			$thrCmd = getCmd('cat=');
		$req = new rXMLRPCRequest( array(
			rTorrentSettings::get()->getOnInsertCommand( array('_exratio1'.User::getUser(), $ratCmd ) ),
			rTorrentSettings::get()->getOnInsertCommand( array('_exratio2'.User::getUser(), $thrCmd ) ),
			));
		return($req->success());
	}
}
