<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( $rootPath.'/php/cache.php');
require_once( $rootPath.'/php/Snoopy.class.inc');
eval( FileUtil::getPluginConf( 'loginmgr' ) );

class privateData
{
	public $hash = '';
	public $cookies = null;
	public $referer = null;
	public $loaded = false;

	static public function load( $owner, $client = null )
	{
		$rt = new privateData($owner);
		if($client)
		{
			$cache = new rCache('/accounts');
			if($cache->get($rt))
			{
				$client->cookies = $rt->cookies;
//				$client->referer = $rt->referer;
				$rt->loaded = true;
			}
		}
		return($rt);
	}

	public function __construct( $owner )
	{
		$this->hash = $owner.".dat";
		$this->loaded = false;
	}

	public function remove()
	{
		$cache = new rCache('/accounts');
		$cache->remove($this);
	}

	public function store( $client )
	{
	        $this->cookies = $client->cookies;
		$this->referer = $client->referer;
		$cache = new rCache('/accounts');
		return($cache->set($this));
	}

	static public function getModified($owner)
	{
		$rt = new privateData($owner);
		$cache = new rCache('/accounts');
		return($cache->getModified($rt));
	}
}

abstract class commonAccount
{
	public $url = 'http://abstract.com';

	public function getName()
	{
		$className = get_class($this);
		$pos = strpos($className, "Account");
		if($pos!==false)
			$className = substr($className,0,$pos);
		return($className);
	}

	abstract protected function isOK($client);
	abstract protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched);

	public function test($url)
	{
		return( stripos($url,$this->url)===0 );
	}

	protected function loadData( $client = null )
	{
		 return(privateData::load( $this->getName(), $client ));
	}

	protected function updateCached($client,&$url,&$method,&$content_type,&$body)
	{
		return(true);
	}

	protected function isOKPostFetch($client,$url,$method,$content_type,$body)
	{
		return($this->isOK($client));
	}

	public function fetch( $client, $url, $login, $password, $method, $content_type, $body )
	{
	        $is_result_fetched = false;
		$data = $this->loadData($client);
		$ret = ( ($data->loaded &&
				$this->updateCached($client,$url,$method,$content_type,$body) &&
				$client->fetch($url,$method,$content_type,$body) &&
				$this->isOKPostFetch($client,$url,$method,$content_type,$body)) ||
			($this->login($client,$login,$password,$url,$method,$content_type,$body,$is_result_fetched) &&
				$client->status>=200 &&
				$client->status<400 &&
				$this->isOK($client) &&
                                ($is_result_fetched || $client->fetch($url,$method,$content_type,$body)) &&
				$this->isOKPostFetch($client,$url,$method,$content_type,$body) &&
				$data->store($client)) );
		if(!$ret)
			$data->remove();
		return($ret);
	}

	public function check( $client, $login, $password, $auto )
	{
		$modified = privateData::getModified($this->getName());
		if( ($modified===false) || ((time()-$modified)>=$auto))
		{
			$data = $this->loadData();
			if($this->login($client,$login,$password,$url,$method,$content_type,$body,$is_result_fetched) &&
				$client->status>=200 &&
				$client->status<400 &&
				$this->isOK($client))
				$data->store($client);
			else
				$data->remove();
		}
	}
}

class accountManager
{
	public $hash = "loginmgr.dat";
	public $accounts = array();

	static public function load()
	{
		$cache = new rCache();
		$ar = new accountManager();
		return($cache->get($ar) ? $ar : false);
	}

	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}

	public function obtain( $dir = '../plugins/loginmgr/accounts' )
	{
		$oldAccounts = $this->accounts;
		$this->accounts = array();
		if( $handle = opendir($dir) )
		{
			while(false !== ($file = readdir($handle)))
			{
				if(is_file($dir.'/'.$file))
				{
					$name = basename($file,".php");
					$this->accounts[$name] = array( "name"=>$name, "path"=>FileUtil::fullpath($dir.'/'.$file), "object"=>$name."Account", "login"=>'', "password"=>'', "enabled"=>0, "auto"=>0 );
					if(array_key_exists($name,$oldAccounts) && array_key_exists("login",$oldAccounts[$name]))
					{
						$this->accounts[$name]["login"] = $oldAccounts[$name]["login"];
						$this->accounts[$name]["password"] = $oldAccounts[$name]["password"];
						$this->accounts[$name]["enabled"] = $oldAccounts[$name]["enabled"];
						if(array_key_exists("auto",$oldAccounts[$name]))
							$this->accounts[$name]["auto"] = $oldAccounts[$name]["auto"];
					}
				}
			}
			closedir($handle);
	        }
		ksort($this->accounts);
		$this->store();
		$this->setHandlers();
	}

	public function get()
	{
                $ret = "theWebUI.theAccounts = {";
		foreach( $this->accounts as $name=>$nfo )
			$ret.="'".$name."': { login: ".Utility::quoteAndDeslashEachItem($nfo["login"]).", password: ".Utility::quoteAndDeslashEachItem($nfo["password"]).", enabled: ".$nfo["enabled"].", auto: ".$nfo["auto"]." },";
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."};\n");
	}

	public function set()
	{
		foreach( $this->accounts as $name=>$nfo )
		{
			if(isset($_REQUEST[$name."_enabled"]))
				$this->accounts[$name]["enabled"] = $_REQUEST[$name."_enabled"];
			if(isset($_REQUEST[$name."_login"]))
				$this->accounts[$name]["login"] = $_REQUEST[$name."_login"];
			if(isset($_REQUEST[$name."_password"]))
				$this->accounts[$name]["password"] = $_REQUEST[$name."_password"];
			if(isset($_REQUEST[$name."_auto"]))
				$this->accounts[$name]["auto"] = intval($_REQUEST[$name."_auto"]);
			$data = new privateData( $name );
			$data->remove();
		}
		$this->store();
		$this->setHandlers();
	}

	public function getAccount( $url )
	{
		foreach( $this->accounts as $name=>$nfo )
		{
			if($nfo["enabled"])
			{
				require_once( $nfo["path"] );
				$object = new $nfo["object"]();
				if($object->test($url))
					return( $name );
			}
		}
		return(false);
	}

        public function fetch( $acc, $client, $url, $method="GET", $content_type="", $body="" )
	{
		if(array_key_exists($acc,$this->accounts))
		{
			$nfo = $this->accounts[$acc];
			require_once( $nfo["path"] );
			$object = new $nfo["object"]();
			return($object->fetch( $client, $url, $nfo["login"], $nfo["password"], $method, $content_type, $body ));
		}
		return(false);
	}

	public function getInfo()
	{
		$ret = array();
		foreach( $this->accounts as $name=>$nfo )
		{
			require_once( $nfo["path"] );
			$nfo["name"] = $name;
			$object = new $nfo["object"]();
			$nfo["url"] = $object->url;
			unset($nfo["object"]);
			unset($nfo["path"]);
			$ret[] = $nfo;
		}
		return($ret);
	}

	public function hasAuto()
	{
		foreach( $this->accounts as $name=>$nfo )
			if($nfo["enabled"] && !empty($nfo["auto"]))
				return(true);
		return(false);
	}

	public function setHandlers()
	{
		if(rTorrentSettings::get()->linkExist)
		{
			$req =  new rXMLRPCRequest( $this->hasAuto() ?
				rTorrentSettings::get()->getAbsScheduleCommand("loginmgr",86400,
					getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg(dirname(__FILE__).'/update.php').' '.escapeshellarg(User::getUser()).' & exit 0}' ) :
				rTorrentSettings::get()->getRemoveScheduleCommand("loginmgr") );
			$req->success();
		}
	}

	public function checkAuto()
	{
		foreach( $this->accounts as $name=>$nfo )
		{
			if($nfo["enabled"] && !empty($nfo["auto"]))
			{
				require_once( $nfo["path"] );
				$object = new $nfo["object"]();
				$object->check( new Snoopy(), $nfo["login"], $nfo["password"], $nfo["auto"] );
			}
		}
	}
}
