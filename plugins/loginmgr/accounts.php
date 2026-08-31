<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__)."/../../php/cache.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc");
eval( FileUtil::getPluginConf( 'loginmgr' ) );

class privateData
{
	public $hash = '';
	public $modified = false;
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

	// Prefix-matching the URL string is not the same as matching the site.
	// "https://tracker.example" is a prefix of "https://tracker.example@evil.test/"
	// (the tracker name is userinfo, the host is the attacker's) and of
	// "https://tracker.example.evil.test/" (the tracker name is one label of a
	// longer domain). Either would have handed this account's cookies to
	// whoever controls that host, and the URL does not have to come from the
	// user -- plugins/rss follows links out of a feed.
	public function test($url)
	{
		$site = @parse_url((string) $this->url);
		if(!is_array($site) || empty($site["host"]) || empty($site["scheme"]))
			return(false);
		return(self::urlAddresses($url,array($site["host"]),$site["scheme"]));
	}

	// True when $url's host is one of $hosts, or a subdomain of one, and its
	// scheme is $scheme when a scheme is named. Subclasses that accept several
	// domains, or a whole domain's subdomains, say so here instead of matching
	// the name anywhere in the URL string -- which also accepts
	// "https://evil.test/path/tracker.example/x", whose host is the attacker's.
	static protected function urlAddresses($url,$hosts,$scheme = null,$pathPrefix = null)
	{
		$parts = @parse_url((string) $url);
		if(!is_array($parts) || empty($parts["host"]))
			return(false);
		// The URL may not weaken the site's scheme. An https site matched over
		// http would have this account's cookies put on the wire in clear by
		// the very first request, before any redirect could upgrade it, and a
		// feed link is not written by the user. The other direction is allowed:
		// an https URL to a site still configured http simply fails to
		// connect, and costs nothing to permit.
		if($scheme!==null)
		{
			$urlScheme = strtolower(isset($parts["scheme"]) ? $parts["scheme"] : '');
			if(($urlScheme!==strtolower($scheme)) && ($urlScheme!=='https'))
				return(false);
		}
		if($pathPrefix!==null)
		{
			$path = isset($parts["path"]) ? $parts["path"] : '/';
			if(strncasecmp($path,$pathPrefix,strlen($pathPrefix))!==0)
				return(false);
		}
		$host = strtolower($parts["host"]);
		foreach($hosts as $candidate)
		{
			$candidate = strtolower($candidate);
			if(($host===$candidate) || (substr($host,-strlen($candidate)-1)==='.'.$candidate))
				return(true);
		}
		return(false);
	}

	protected function loadData( $client = null )
	{
		 return(privateData::load( $this->getName(), $client ));
	}

	protected function updateCached($client,&$url,&$method,&$content_type,&$body)
	{
		return(true);
	}

	// Three answers, not two. They call for three different repairs, and this
	// class used to make only two of them:
	//
	//   ANSWER_LIVE   the tracker answered from behind the login wall.
	//   ANSWER_GUEST  the tracker answered, and answered as a guest. The
	//                 session is dead and logging in again is the repair.
	//   ANSWER_NONE   nothing usable arrived. This says NOTHING about the
	//                 session, so logging in again is not a repair: it spends
	//                 a credential POST on a tracker that is already failing
	//                 and then throws away cookies never shown to be stale.
	//
	// Every isOK() under accounts/ tells the first two apart by looking for a
	// guest marker -- a login form, a registration link, a "you must log in"
	// line -- and none of them can answer the third, because a page that never
	// arrived carries no marker either. That is why all three used to collapse
	// into "the session is live", and why a 5xx error page went back to the
	// caller as the page or the torrent it had asked for.
	const ANSWER_NONE = 0;
	const ANSWER_GUEST = 1;
	const ANSWER_LIVE = 2;

	// What the client is holding after a fetch of the caller's own URL.
	protected function classifyAnswer($client)
	{
		if(!is_object($client))
			return(self::ANSWER_NONE);
		$status = (int) $client->status;
		// A conditional request the server honoured. It answered, it answered
		// the authenticated request, and it sent no body on purpose: the
		// caller asked for that (plugins/rss sends If-None-Match and reads
		// $client->status itself). Judging it by its absent body would turn
		// every unchanged poll of an authenticated feed into a fresh login.
		if($status===304)
			return(self::ANSWER_LIVE);
		// Only 2xx carries a body meant for the caller. A 3xx reaching here is
		// a redirect Snoopy did not follow -- a followed chain ends at the
		// status of its last hop -- and the boilerplate body a server puts on
		// one carries no guest marker either, so it would read as a live
		// session for want of evidence.
		if(($status<200) || ($status>=300))
			return(self::ANSWER_NONE);
		if(!$this->hasReadableBody($client))
			return(self::ANSWER_NONE);
		return($this->isOK($client) ? self::ANSWER_LIVE : self::ANSWER_GUEST);
	}

	// True when $client->results is something a strpos() marker test can read.
	// Snoopy does not always leave a string there: when a gzipped body arrives
	// on a build without gzinflate() it shells out to gzip, and if the
	// decompressed file is missing the body is lost. strpos() on a non-string
	// is fatal in PHP 8, and every marker test under accounts/ is a strpos().
	protected function hasReadableBody($client)
	{
		return(is_object($client) && is_string($client->results) && ($client->results!==''));
	}

	// The login answer is a narrower question than the caller's answer: a
	// login endpoint may legitimately answer with no body at all -- a 30x to
	// the landing page, or a 200 whose whole content is Set-Cookie -- and it
	// is the fetch that follows which proves whether the session took. So the
	// marker test is applied only when there is a body to apply it to, and the
	// status range stays the one this class has always used here.
	protected function loginWasNotRefused($client)
	{
		if(!is_object($client))
			return(false);
		$status = (int) $client->status;
		if(($status<200) || ($status>=400))
			return(false);
		return(!$this->hasReadableBody($client) || $this->isOK($client));
	}

	protected function isOKPostFetch($client,$url,$method,$content_type,$body)
	{
		return($this->classifyAnswer($client)===self::ANSWER_LIVE);
	}

	public function fetch( $client, $url, $login, $password, $method, $content_type, $body )
	{
		$is_result_fetched = false;
		$data = $this->loadData($client);
		if($data->loaded &&
			$this->updateCached($client,$url,$method,$content_type,$body))
		{
			if(!$client->fetch($url,$method,$content_type,$body))
				return(false);
			// Taken before isOKPostFetch(), because an override may fetch
			// further pages onto this client and the question here is about
			// the answer to the caller's own URL.
			$answer = $this->classifyAnswer($client);
			if($this->isOKPostFetch($client,$url,$method,$content_type,$body))
				return(true);
			// Only a guest answer is evidence that the session died. Anything
			// else leaves that unproven, so report the failure and keep the
			// cookies rather than log in again against a tracker that is not
			// answering: one outage would otherwise cost a credential POST per
			// call for every caller looping over a torrent list, which is how
			// an account gets locked out.
			if($answer!==self::ANSWER_GUEST)
				return(false);
		}
		$ret = ( $this->login($client,$login,$password,$url,$method,$content_type,$body,$is_result_fetched) &&
			$this->loginWasNotRefused($client) &&
			($is_result_fetched || $client->fetch($url,$method,$content_type,$body)) &&
			$this->isOKPostFetch($client,$url,$method,$content_type,$body) &&
			$data->store($client) );
		if(!$ret)
			$data->remove();
		return($ret);
	}

	public function check( $client, $login, $password, $auto )
	{
		$modified = privateData::getModified($this->getName());
		if( ($modified===false) || ((time()-$modified)>=$auto))
		{
			// login() takes these by reference and several accounts read them
			// before writing: undeclared, they arrived as null, and an account
			// whose login() starts by fetching $url could never authenticate
			// from here at all.
			$url = $this->url;
			$method = "GET";
			$content_type = "";
			$body = "";
			$is_result_fetched = false;
			$data = $this->loadData();
			if($this->login($client,$login,$password,$url,$method,$content_type,$body,$is_result_fetched) &&
				$this->loginWasNotRefused($client))
				$data->store($client);
			// A login that did not come back is not evidence that the stored
			// session is stale, and this job exists to REFRESH a session:
			// deleting one it merely failed to renew leaves the user worse off
			// than not running at all. A session that really has died is
			// discovered, and replaced, by the next fetch().
		}
	}
}

class accountManager
{
	public $hash = "loginmgr.dat";
	public $modified = false;
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
				rTorrentSettings::get()->getAlignedScheduleCommand("loginmgr",86400,
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
