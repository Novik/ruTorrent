<?php

require_once( dirname(__FILE__)."/../../php/settings.php");

class rXmpp
{
	public $hash = "xmpp.dat";
	public $jabberHost = "";
	public $jabberPort = 5222;
	public $jabberLogin = "";
	public $jabberServer = "";
	public $jabberPasswd = "";
	public $useEncryption = 1;
	public $advancedSettings = 0;
	public $jabberFor = "";
	protected $message_templ = "Torrent '{TORRENT}' has been downloaded.";
	public $message = "";

	static public function load()
	{
		$cache = new rCache();
		$at = new rXmpp();
		$cache->get( $at );
		return $at;
	}
	public function store()
	{
		$cache = new rCache();
		return $cache->set( $this );
	}

	public function set()
	{
		if( !isset( $HTTP_RAW_POST_DATA ) )
			$HTTP_RAW_POST_DATA = file_get_contents( "php://input" );
		if( isset( $HTTP_RAW_POST_DATA ) )
		{
			$vars = explode( '&', $HTTP_RAW_POST_DATA );
			$this->jabberHost = "";
			$this->jabberPort = 5222;;
			$this->jabberLogin = "";
			$this->jabberServer = "";
			$this->jabberPasswd = "";
			$this->useEncryption = 1;
			$this->advancedSettings = 0;
			$this->jabberFor = "";
			$this->message = $this->message_templ;
			foreach( $vars as $var )
			{
				$parts = explode( "=", $var );
				if( $parts[0] == "jabberHost" )
				{
					$jabberHost = $parts[1];
				}
				else if( $parts[0] == "jabberPort" )
				{
					$jabberPort = $parts[1];
				}
				else if( $parts[0] == "jabberJid" )
				{
					$jid = explode( "@", $parts[1]);
					$this->jabberLogin = $jid[0];
					$this->jabberServer = count($jid) > 1 ? $jid[1] : "";
				}
				else if( $parts[0] == "jabberPasswd" )
				{
					$this->jabberPasswd = $parts[1];
				}
				else if( $parts[0] == "useEncryption" )
				{
					$useEncryption = $parts[1];
				}
				else if( $parts[0] == "advancedSettings" )
				{
					$this->advancedSettings = $parts[1];
				}
				else if ( $parts[0] == "jabberFor" )
				{
					$this->jabberFor = $parts[1];
				}
				else if ( $parts[0] == "message" )
				{
					if ($parts[1])
					{
					    $this->message = $parts[1];
					}
				}
			}
			if ($this->advancedSettings)
			{
			    if ($jabberHost)
			    {
				$this->jabberHost = $jabberHost;
			    }
			    if ($jabberPort)
			    {
				$this->jabberPort = $jabberPort;
			    }
			    $this->useEncryption = $useEncryption;
			}
			$this->setHandlers();
		}
		$this->store();
	}

	public function get()
	{
		$ret  = "theWebUI.xmpp = { ";
		$ret .= "JabberHost: '".$this->jabberHost."'";
		$ret .= ", JabberPort: ".$this->jabberPort;
		$jid = "";
		if ($this->jabberLogin && $this->jabberServer)
		{
		    $jid = $this->jabberLogin."@".$this->jabberServer;
		}
		$ret .= ", JabberJID: '".$jid."'";
		$ret .= ", JabberPasswd: '".$this->jabberPasswd."'";
		$ret .= ", UseEncryption: ".$this->useEncryption;
		$ret .= ", AdvancedSettings: ".$this->advancedSettings;
		$ret .= ", JabberFor: '".$this->jabberFor."'";
		$ret .= ", Message: '".addslashes($this->message ? $this->message : $this->message_templ)."'";
		return $ret." };\n";
	}

	public function setHandlers()
	{
		$theSettings = rTorrentSettings::get();
		$pathToXmpp = dirname(__FILE__);
		$req = new rXMLRPCRequest();
		if ( $this->message !== '' && isset($this->jabberServer) && isset($this->jabberLogin) && isset($this->jabberPasswd) && isset($this->jabberFor))
		{
		    $cmd = $theSettings->getOnFinishedCommand(array('xmpp'.getUser(), 
			    getCmd('execute.nothrow.bg').'={'.getPHP().','.$pathToXmpp.'/notify.php,"$'.getCmd('d.name').'=","'.getUser().'"}'
			    ));
		}
		else
		    $cmd = $theSettings->getOnFinishedCommand(array('xmpp'.getUser(), getCmd('cat=')));
		$req->addCommand($cmd);
		return($req->success());
	}
}
