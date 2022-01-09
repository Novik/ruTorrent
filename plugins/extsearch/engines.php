<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( $rootPath.'/php/cache.php');
require_once( $rootPath.'/php/settings.php');
require_once( $rootPath.'/php/Snoopy.class.inc');
eval( FileUtil::getPluginConf( 'extsearch' ) );

class commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>100 );
	public $categories = array( 'All'=>'' );

	public function action($what,$cat,&$arr,$limit,$useGlobalCats)
	{
	}
	public function getSource()
	{
		$className = get_class($this);
		$pos = strpos($className, "Engine");
		if($pos!==false)
			$className = substr($className,0,$pos);
		return($className);
	}
	public function getNewEntry()
	{
		return( array(
			"time"=>0, 
			"cat"=>'', 
			"size"=>0,
			"desc"=>'',
			"name"=>'',
			"src"=>$this->getSource(),
			"seeds"=>0,
			"peers"=>0,
			));
	}
	public function makeClient($url)
	{
		global $HTTPTimeoutPerSite;
		$client = new Snoopy();
		$client->read_timeout = $HTTPTimeoutPerSite;
		$client->_fp_timeout = $HTTPTimeoutPerSite;
		return($client);
	}
	public function fetch($url, $encode = 1, $method="GET", $content_type="", $body="")
	{
		$client = $this->makeClient($url);
		if($encode)
			$url = Snoopy::linkencode($url);
		$client->fetchComplex($url, $method, $content_type, $body);
		if($client->status>=200 && $client->status<300)
		{
			ini_set( "pcre.backtrack_limit", max(strlen($client->results),100000) );
			return($client);
		}
		return(false);
	}
	public function getTorrent( $url )
	{
		global $profileMask;
		$cli = $this->fetch( $url );
		if($cli)
		{
			$name = $cli->get_filename();
			if($name===false)
				$name = md5($url).".torrent";
			$name = FileUtil::getUniqueUploadedFilename($name);
			$f = @fopen($name,"w");
			if($f!==false)
			{
				@fwrite($f,$cli->results,strlen($cli->results));
				fclose($f);
				@chmod($name,$profileMask & 0666);
				return($name);
			}
		}
		return(false);
	}
	static public function removeTags($s, $charset = "UTF-8")
	{
		return(html_entity_decode( str_replace("&nbsp;"," ",strip_tags($s)), ENT_QUOTES, $charset ));
	}
	static public function formatSize( $item )
	{
		$sz = explode(" ",self::removeTags($item));
		if(count($sz)>1)
		{
			$val = floatval($sz[0]);
			switch(strtolower($sz[1]))
			{
				case "tib":
				case "tb":
				case "tio":
				case "to":
				case "òá":
				case "ÒÁ":
					$val*=1024;
				case "gib":
				case "gb":
				case "gio":
				case "go":
				case "ãá":
				case "ÃÁ":
					$val*=1024;
				case "mib":
				case "mb":
				case "mio":
				case "mo":
				case "ìá":
				case "ÌÁ":
					$val*=1024;
				case "kib":
				case "kb":
				case "kio":
				case "ko":
				case "êá":
				case "ÊÁ":
					$val*=1024;
			}
			return($val);
		}
		return(0);
	}
	static public function fromUTF($out,$encoding)
	{
		if(function_exists('iconv'))
			$out = iconv('UTF-8', $encoding.'//TRANSLIT', $out);
		else
		if(function_exists('mb_convert_encoding'))
		        $out = mb_convert_encoding($out, $encoding, 'UTF-8');
		else
		if(function_exists('utf8_decode'))
		        $out = utf8_decode($out);
		return($out);	
	}
	static public function toUTF($out,$encoding)
	{
		if(function_exists('iconv'))
			$out = iconv($encoding, 'UTF-8//TRANSLIT', $out);
		else
		if(function_exists('mb_convert_encoding'))
		        $out = mb_convert_encoding($out, 'UTF-8', $encoding );
		else
		if(function_exists('utf8_encode'))
		        $out = utf8_encode($out);
		else
		        $out = UTF::win2utf($out);
		return($out);	
	}
	static public function fromJSON($str)
	{
		$ret = json_decode('{ "foo": "'.$str.'" }');
		return($ret ? $ret->foo : $str);
	}
}

class rSearchHistory
{
	public $hash = "extsearch_history.dat";
	public $lst = array();
	public $changed = false;

	public function add( $url, $hash )
	{
		$this->lst[$url] = array( "hash"=>$hash, "time"=>time() );
		$this->changed = true;
	}
	public function del( $href )
	{
		if(array_key_exists($href,$this->lst))
		{
			unset($this->lst[$href]);
			$this->changed = true;
		}
	}
	public function isChanged()
	{
		return($this->changed);
	}
	public function getHash( $url )
	{
		if(array_key_exists($url,$this->lst))
			return($this->lst[$url]["hash"]);
		return("");
	}
	public function isOverflow()
	{
		global $searchHistoryMaxCount;
		return( count($this->lst) > $searchHistoryMaxCount );
	}
	public function pack()
	{
		uasort($this->lst, array("Utility", "sortArrayTime"));
		$cnt = count($this->lst)/2;
		$i=0;
		foreach( $this->lst as $key=>$value )
		{
			unset($this->lst[$key]);
			if(++$i>=$cnt)
				break;
		}
	}
}

class engineManager
{
	public $hash = "extsearch.dat";
	public $limit = 1000;
	public $engines = array();

	static public function load()
	{
		$cache = new rCache();
		$ar = new engineManager();
		return($cache->get($ar) ? $ar : false);
	}

	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}

	public function obtain( $dir = '../plugins/extsearch/engines' )
	{
		$oldEngines = $this->engines;
		$this->engines = array();
		if( $handle = opendir($dir) )
		{
			while(false !== ($file = readdir($handle)))
			{
				if(is_file($dir.'/'.$file))
				{
					$name = basename($file,".php");
					$this->engines[$name] = array( "name"=>$name, "path"=>FileUtil::fullpath($dir.'/'.$file), "object"=>$name."Engine", "enabled"=>true, "global"=>true, "limit"=>100 );
					$obj = $this->getObject($name);
					$this->engines[$name]["enabled"] = intval($obj->defaults["public"]);
					$this->engines[$name]["public"] = intval($obj->defaults["public"]);
					$this->engines[$name]["limit"] = $obj->defaults["page_size"];
					$this->engines[$name]["cats"] = $obj->categories;
					$this->engines[$name]["cookies"] = (array_key_exists("cookies",$obj->defaults) ? $obj->defaults["cookies"] : '');
					$this->engines[$name]["auth"] = (array_key_exists("auth",$obj->defaults) ? 1 : 0);
					if(array_key_exists("disabled",$obj->defaults) && $obj->defaults["disabled"])
						$this->engines[$name]["enabled"] = 0;
					if(array_key_exists($name,$oldEngines) && array_key_exists("limit",$oldEngines[$name]))
					{
						$this->engines[$name]["enabled"] = intval($oldEngines[$name]["enabled"]);
						$this->engines[$name]["global"] = intval($oldEngines[$name]["global"]);
						$this->engines[$name]["limit"] = intval($oldEngines[$name]["limit"]);
					}

					if(!rTorrentSettings::get()->isPluginRegistered('cookies') && 
						$this->engines[$name]["enabled"] && 
						!empty($this->engines[$name]["cookies"]))
						$this->engines[$name]["enabled"] = 0;
					if(!rTorrentSettings::get()->isPluginRegistered('loginmgr') && 
						$this->engines[$name]["enabled"] && 
						$this->engines[$name]["auth"])
						$this->engines[$name]["enabled"] = 0;
				}
			} 
			closedir($handle);		
	        }
		ksort($this->engines);
		$this->store();
	}

	public function get()
	{
                $ret = "theSearchEngines.globalLimit = ".$this->limit."; theSearchEngines.sites = {";
		foreach( $this->engines as $name=>$nfo )
		{
			$ret.="'".$name."': { enabled: ".intval($nfo["enabled"]). ", global: ".intval($nfo["global"]).
				", auth: ".intval($nfo["auth"]).", limit: ".$nfo["limit"].", public: ".intval($nfo["public"]). ", cookies: ".Utility::quoteAndDeslashEachItem($nfo["cookies"]).", cats: [";
			foreach( $nfo["cats"] as $cat=>$prm )
			{
				$ret.=Utility::quoteAndDeslashEachItem($cat);
				$ret.=',';
			}
			$len = strlen($ret);
			if($ret[$len-1]==',')
				$ret = substr($ret,0,$len-1);
			$ret.=']},';
		}
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."};\n");
	}

	public function set()
	{
		foreach( $this->engines as $name=>$nfo )
		{
			if(isset($_REQUEST[$name."_enabled"]))
				$this->engines[$name]["enabled"] = intval($_REQUEST[$name."_enabled"]);
			if(isset($_REQUEST[$name."_global"]))
				$this->engines[$name]["global"] = intval($_REQUEST[$name."_global"]);
			if(isset($_REQUEST[$name."_limit"]))
				$this->engines[$name]["limit"] = intval($_REQUEST[$name."_limit"]);
		}
		if(isset($_REQUEST["limit"]))
			$this->limit = intval($_REQUEST["limit"]);
		$this->store();
	}

	static public function loadHistory( $withRSS = false )
	{
		$cache = new rCache();
		$history = new rSearchHistory();
		$cache->get($history);
		if($withRSS)
		{
			if(rTorrentSettings::get()->isPluginRegistered("rss"))
			{
				global $rootPath;
				require_once( $rootPath.'/plugins/rss/rss.php');
				$cache  = new rCache( '/rss/cache' );
				$rssHistory = new rRSSHistory();
				if($cache->get($rssHistory))
				{
					foreach($rssHistory->lst as $url=>$info)
					{
						if(strlen($info["hash"])==40)
							$history->add($url,$info["hash"]);
					}
				}
			}
		}
		return($history);
	}

	static public function saveHistory( $history )
	{
		if($history->isChanged())
		{
			if($history->isOverflow())
				$history->pack();
			$cache = new rCache();
			return($cache->set($history));
		}
		return(true);
	}

	public function getObject( $eng )
	{
		if(array_key_exists($eng,$this->engines))
		{
			$nfo = $this->engines[$eng];
			require_once( $nfo["path"] );
			$object = new $nfo["object"]();
		}
		else
			$object = new commonEngine();
		return($object);
	}

	static protected function correctItem(&$nfo)
	{
		if(empty($nfo["time"]))
			$nfo["time"] = 0;
		if(empty($nfo["size"]))
			$nfo["size"] = 0;
		if(empty($nfo["seeds"]))
			$nfo["seeds"] = 0;
		if(empty($nfo["peers"]))
			$nfo["peers"] = 0;
		if( UTF::isInvalidUTF8( $nfo["name"] ) )
			$nfo["name"] = commonEngine::toUTF($nfo["name"],"ISO-8859-1");
	}

	static protected function sortBySeeds( $a, $b )
	{
		return( (intval($a["seeds"]) > intval($b["seeds"])) ? -1 : ((intval($a["seeds"]) < intval($b["seeds"])) ? 1 : 0) );
	}

	public function action( $eng, $what, $cat = "all" )
	{
		$arr = array();
		$what = rawurlencode($what);
		switch($eng)
		{
			case "public":
			case "private":
			case "all":
			{
				foreach( $this->engines as $name=>$nfo )
				{
					if(($nfo["global"] && $nfo["enabled"]) &&
						(($nfo["public"] && ($eng=="public")) || (!$nfo["public"] && ($eng=="private")) || ($eng=="all")))
					{
						require_once( $nfo["path"] );
						$object = new $nfo["object"]();
						$object->action($what,$cat,$arr,$nfo["limit"],true);
					}
				}
				break;
			}
			default:
			{
				$object = $this->getObject($eng);
				$object->action($what,$cat,$arr,$this->limit,false);
			}
		}
		uasort($arr, array(__CLASS__,"sortBySeeds"));
		$cnt = 0;		
		$history = self::loadHistory(true);

		$ret = array( "eng"=>$eng, "cat"=>$cat, "data"=>array() );
		foreach( $arr as $href=>$nfo )
		{
			self::correctItem($nfo);
			$nfo["link"] = $href;
			$nfo["hash"] = $history->getHash( $href );
			$ret["data"][] = $nfo;
			$cnt++;
			if($cnt>=$this->limit)
				break;
		}
		return($ret);
	}

	public function getTorrents( $engs, $urls, $isStart, $isAddPath, $directory, $label, $fast )
	{
		$ret = array();
		$history = self::loadHistory();
		for( $i=0; $i<count($urls); $i++ )
		{
			$url = $urls[$i];
			$success = false;
			if(strpos($url,"magnet:")===0)
			{
				if($success = rTorrent::sendMagnet($url, $isStart, $isAddPath, $directory, $label))
					$history->add($url,$success);
			}
			else
			{
				$object = $this->getObject($engs[$i]);
        			$torrent = $object->getTorrent( $url, $object );
				if($torrent!==false)
				{
					global $saveUploadedTorrents;
					if(($success = rTorrent::sendTorrent($torrent, $isStart, $isAddPath, $directory, $label, $saveUploadedTorrents, $fast))===false)
						@unlink($torrent);
					else
						$history->add($url,$success);
				}
			}
			$ret[] = $success;
		}
		self::saveHistory($history);
		return($ret);
	}
}
