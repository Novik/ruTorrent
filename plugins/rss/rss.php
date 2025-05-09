<?php

require_once( dirname(__FILE__).'/../../php/cache.php');
require_once( dirname(__FILE__).'/../../php/Snoopy.class.inc');
require_once( dirname(__FILE__).'/../../php/rtorrent.php' );
require_once( dirname(__FILE__).'/rss_reader.php' );
eval(FileUtil::getPluginConf('rss'));

function rssFetchURL($url, $cookies = null, $headers = null )
{
	$client = new Snoopy();
	if(is_array($headers) && count($headers))
		$client->rawheaders = $headers;
	if(is_array($cookies) && count($cookies))
		$client->cookies = $cookies;
	@$client->fetchComplex($url);
	return $client;
}

class rRSS
{
	public $items = array();
	public $channel = array();
	public $url = null;
	public $srcURL = null;
	public $hash = null;
	public $modified = false;
	public $cookies = array();
	public $lastModified = null;
	public $etag = null;
	public $encoding = null;
	public $version = 0;
	public $lastErrorMsgs = [];
	private $fetchURL = 'rssFetchURL';

	public function __construct( $url = null, $fetchURL = 'rssFetchURL' )
	{
		$this->fetchURL = $fetchURL;
		$this->version = 1;
		if($url)
		{
			$pos = strpos($url,':COOKIE:');
			if($pos!==false)
			{
				$this->url = substr($url,0,$pos);
				$tmp = explode(";",substr($url,$pos+8));
				foreach($tmp as $item)
				{
					list($name,$val) = explode("=",$item);
					$this->cookies[$name] = $val;
				}
			}
			else
				$this->url = $url;
			$this->url = Snoopy::linkencode($this->url);
			$this->srcURL = $this->url;
			if(count($this->cookies))
			{
				$this->srcURL = $this->srcURL.':COOKIE:';
				foreach($this->cookies as $name=>$val)
					$this->srcURL = $this->srcURL.$name.'='.$val.';';
				$this->srcURL = substr($this->srcURL,0,strlen($this->srcURL)-1);
			}
			$this->hash = md5( $this->srcURL );
		}
	}

	public function getMaskedURL()
	{
		$ret = $this->srcURL;
		if(preg_match("`^(?P<sheme>[^:]*)://.*@(?P<url>.*)$`i",$ret,$matches))
			$ret = $matches["sheme"]."://".$matches["url"];
		$pos = strpos($ret,':COOKIE:');
		if($pos!==false)
			$ret = substr($ret,0,$pos);
		return($ret);
	}

	public function getTorrent( $href )
	{
		if(strpos($href,"magnet:")===0)
			return("magnet");
		global $profileMask;
		$cli = call_user_func($this->fetchURL, Snoopy::linkencode($href),$this->cookies);
		if($cli && $cli->status>=200 && $cli->status<300)
		{
			$name = $cli->get_filename();
			if($name===false)
				$name = md5($href).".torrent";
			$name = FileUtil::getUniqueUploadedFilename($name);
			$f = @fopen($name,"w");
			if($f===false)
			{
				$name = FileUtil::getUniqueUploadedFilename(md5($href).".torrent");
				$f = @fopen($name,"w");
			}
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

	public function getItemTimestamp( $href )
	{
		return( array_key_exists($href,$this->items) ? $this->items[$href]["timestamp"] : 0 );
	}

	public function getContents($label,$auto,$enabled,$history)
	{
		$ret = array(
			"label"=>self::quoteInvalidURI($label),
			"auto"=>intval($auto),
			"enabled"=>intval($enabled),
			"hash"=>$this->hash,
			"url"=>self::quoteInvalidURI($this->srcURL),
			"items"=>array() );
		foreach($this->items as $href=>$item)
		{
			$ret["items"][] = array(
				"time"=>intval($item['timestamp']),
				"title"=>empty($item['title']) ? '<No Title>' : $item['title'],
				"href"=>self::quoteInvalidURI($href),
				"guid"=>self::quoteInvalidURI($item['guid']),
				"errcount"=>$history->getCounter($href),
				"hash"=>$history->getHash($href) 
				);
		}
		return($ret);
	}

	public function fetch( $history )
	{
		$headers = [];
		$this->lastErrorMsgs = [];
		if($this->etag)
			$headers['If-None-Match'] = $this->etag;
		if($this->lastModified)
			$headers['If-Last-Modified'] = $this->lastModified;
		$cli = call_user_func($this->fetchURL, $this->url, $this->cookies, $headers);
		if($cli->status<200 || $cli->status>=300) {
			if ($cli->status !== 304) {
                                $msg = $cli->status == -100
                                        ? '[RSS-Timeout]'
                                        : ($cli->status < 100
                                                ? '[RSS-Connection-Error] ' . $cli->error
                                                : '[RSS-HTTP-Error] Status: ' . $cli->status);

                                $this->lastErrorMsgs[] = $msg;
                                return false;
                        }
			// true if feed not modified
			return true;
		}

		// remember etag and lastModified
		$this->etag = null;
		$this->lastModified = null;
		foreach($cli->headers as $header)
		{
			$field = explode(': ', trim(strtolower($header)), 2);
			$value = count($field) > 1 ? $field[1] : '';
			switch($field[0]) {
				case 'etag':
					$this->etag = $value;
					break;
				case 'last-modified':
					$this->lastModified = $value;
			}
		}
		// map data from Atom or RSS feeds
		list($xText, $xIter, $errors) = rssXpath($cli->results);

		$xFirst = function($path) use (&$xIter) {
			foreach($xIter($path) as $i){
				return $i;
			}
			return null;
		};

		// assign values to this
		$this->items = [];
		$this->channel = [];
		if (($rss = $xFirst('/rss/channel|/channel')) !== null) {
			$this->channel = [
				'title'=>$xText('title', $rss),
				'link'=>$xText('link', $rss),
				'timestamp'=>strtotime($xText('lastBuildDate', $rss)),
			];
			if ($this->channel['timestamp'] === false) {
				$this->channel['timestamp'] = 0;
			}

			foreach( $xIter('item', $rss) as $i) {
				$item = [
					'title'=>$xText('title', $i),
					'timestamp'=>strtotime($xText(['pubDate', 'dc:date'], $i)),
					'link'=> $xText(['enclosure/@url', 'link', 'guid', 'source/@url'], $i),
					'guid'=> $xText('guid', $i),
					'description'=> $xText('description', $i),
				];
				if($item['timestamp'] === false)
				{
					// hack for iptorrents.com
					// Category: Movies/Non-English  Size: 707.38 MB Uploaded: 2009-10-21 07:42:37
					if((strlen($item['description'])<255) &&
						(($pos = strpos($item['description'],'Uploaded: '))!==false) &&
						(($timestamp = strtotime(substr($item['description'],$pos+10)))!==-1))
						$item['timestamp'] = $timestamp;
					else
						$item['timestamp'] = 0;
				}
				// expect permalink in guid and normal link in url
				$httpLinkExpr = '|^http(s)?://[a-z0-9-]+(\.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i';
				$validPermalink = preg_match($httpLinkExpr, $item['guid']);
				if (preg_match($httpLinkExpr, $item['link']) ) {
					if (!$validPermalink) {
						$item['guid'] = $item['link'];
					}
				} elseif ($validPermalink) {
						$item['link'] = $item['guid'];
				}
				$link = $item['link'];
				if (!empty($link)) {
					$this->items[$link] = $item;
				}
			}
		} elseif (($atom = $xFirst('/feed')) !== null) {
			$urlPrefix = $xText('@xml:base', $atom);
			$this->channel = [
				'title'=>$xText('title', $atom),
				'link'=>$urlPrefix.$xText('link/@href', $atom),
				'timestamp'=>strtotime($xText('updated', $atom)),
			];
			if ($this->channel['timestamp'] === false) {
				$this->channel['timestamp'] = 0;
			}
			foreach( $xIter('entry', $atom) as $i) {
				$description = [];
				if (($summary = $xText('summary', $i)) != '') {
					$description[] = $summary;
				}
				if (($content = $xText('content', $i)) != '') {
					$description[] = "[Content]\n".$content;
				}
				$item = [
					'title'=> $xText('title', $i),
					'timestamp'=>strtotime($xText('updated', $i)) ?? false,
					'link'=> $urlPrefix.$xText('link/@href', $i),
					'description'=> join("\n\n", $description),
				];
				$item['guid'] = $item['link'];
				// only add items with an url
				$link = $item['link'];
				if (!empty($link)) {
					$this->items[$link] = $item;
				}
			}
		} else {
			$this->lastErrorMsgs[] = '<feed> or <rss><channel> not found!';
			return(false);
		}

		$errs = $errors();
		foreach ($errs as $error) {
			$this->lastErrorMsgs[] = $error;
		}

		if( !empty($this->items) )
		{
			rTorrentSettings::get()->pushEvent( "RSSFetched", array( "rss"=>&$this ) );
			if(!$this->hasIncorrectTimes())
				foreach( $this->items as $url=>$item )
					$history->correct($url, $item['timestamp'], $item['guid']);
		}
		return(true);
	}

	protected function hasIncorrectTimes()
	{
		global $feedsWithIncorrectTimes;
		$ret = false;
		$uparts = @parse_url($this->url);
		$host = $uparts['host'];
		foreach( $feedsWithIncorrectTimes as $url )
		{
			if( stripos($host,$url)!==false )
			{
				$ret = true;
				break;
			}
		}
		return($ret);
	}

	static protected function quoteInvalidURI($str)
	{
		return( preg_replace("/\s/u"," ",$str) );
	}

}

class rRSSHistory
{
	public $hash = "history";
	public $modified = false;
	public $lst = array();
	public $filtersTime = array();
	public $changed = false;
	public $version = 0;

	public function __construct()
	{
		$this->version = 2;
	}

	public function add( $url, $hash, $timestamp, $guid )
	{
		$cnt = 0;
		if(array_key_exists($url,$this->lst))
			$cnt = ($this->lst[$url]["time"]==$timestamp) ? $this->lst[$url]["cnt"] : 0;
		$this->lst[$url] = array( "hash" => $hash, "time" => $timestamp, "cnt" => $cnt,	"guid" => $guid );
		if($hash=='Failed')
			$this->lst[$url]["cnt"] = $cnt+1;
		$this->changed = true;
	}
	public function correct( $url, $timestamp, $guid )
	{
		if( array_key_exists($url,$this->lst) && 
			($guid && !empty($this->lst[$url]["guid"]) && $this->lst[$url]["guid"] !== $guid) )
		{
			unset($this->lst[$url]);
			$this->changed = true;			
		}
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
	public function getCounter( $url )
	{
		if(array_key_exists($url,$this->lst))
			return(intval($this->lst[$url]["cnt"]));
		return(0);
	}
	public function wasLoaded( $url, $guid)
	{
		$ret = false;
		if(array_key_exists($url,$this->lst))
		{
			if($guid && !empty($this->lst[$url]["guid"]))
			{
				$ret = ($this->lst[$url]["guid"] === $guid);
			}
			if(!$ret)
			{
				$ret = ($this->lst[$url]["hash"]!=='Failed') || ($this->getCounter( $url )>HISTORY_MAX_TRY);
			}
		}
		return($ret);
	}
	public function getHash( $url )
	{
		if(array_key_exists($url,$this->lst))
			return($this->lst[$url]["hash"]);
		return("");
	}
	public function clear()
	{
	        $this->changed = (count($this->lst)>0);
		$this->lst = array();
	}
	public function isOverflow()
	{
		return( count($this->lst) > HISTORY_MAX_COUNT );
	}
	public function applyFilter( $filterNo )
	{
		$this->changed = true;
		$this->filtersTime[$filterNo] = time();
	}
	public function mayBeApplied( $filterNo, $filterInterval )
	{
		return( ($filterInterval<0) ||
	                !array_key_exists($filterNo,$this->filtersTime) ||
			(($filterInterval>0) && (time()>$this->filtersTime[$filterNo]+$filterInterval*3600)) );
	}
	public function clearFilterTime( $filterNo )
	{
		if(array_key_exists($filterNo,$this->filtersTime))
		{
			unset($this->filtersTime[$filterNo]);
			$this->changed = true;
		}
	}
	public function removeOldFilters( $filters )
	{
	        $newFiltesTime = array();
		foreach($filters->lst as $filter)
			if(array_key_exists($filter->no,$this->filtersTime))
                        	$newFiltesTime[$filter->no] = $this->filtersTime[$filter->no];
		if(count($newFiltesTime)!=count($this->filtersTime))
		{
			$this->changed = true;
			$this->filtersTime = $newFiltesTime;
		}
	}
}

class rRSSFilter
{
	public $name;
	public $pattern;
	public $exclude = '';
	public $enabled;
	public $rssHash;
	public $start;
	public $addPath;
	public $directory = null;
	public $label = null;
	public $throttle = null;
	public $ratio = null;
	public $titleCheck = 1; 
	public $descCheck = 0;
	public $linkCheck = 0;
	public $no = -1;
	public $interval = -1;
	public $matches = array();
	private static $search = array
	( 
		null,
		'${1}', '${2}', '${3}', '${4}', '${5}', '${6}', '${7}', '${8}', '${9}', '${10}',
		'${11}', '${12}', '${13}', '${14}', '${15}', '${16}', '${17}', '${18}', '${19}', '${20}',
		'${21}', '${22}', '${23}', '${24}', '${25}', '${26}', '${27}', '${28}', '${29}', '${30}',
	);

	public function	__construct( $name, $pattern = '', $exclude = '', $enabled = 0, $rssHash = '', 
		$start = 0, $addPath = 1, $directory = null, $label = null, 
		$titleCheck = 1, $descCheck = 0, $linkCheck = 0,
		$throttle = null, $ratio = null, $no = -1, $interval = -1 )
	{
		$this->name = $name;
		$this->pattern = $pattern;
		$this->exclude = $exclude;
		$this->enabled = $enabled;
		$this->rssHash = $rssHash;
		$this->start = $start;
		$this->addPath = $addPath;
		$this->directory = $directory;
		$this->label = $label;
		$this->titleCheck = $titleCheck;
		$this->descCheck = $descCheck;
		$this->linkCheck = $linkCheck;
		$this->throttle = $throttle;
		$this->ratio = $ratio;
		$this->no = $no;
		$this->interval = $interval;
		$this->matches = array();
	}
	public function isApplicable( $rss, $history, $groups )
	{
		return(($this->enabled==1) && 
  	                (($this->titleCheck == 1) || ($this->descCheck == 1) || ($this->linkCheck == 1)) &&
			(!$this->rssHash || (strlen($this->rssHash)==0) || ($this->rssHash==$rss->hash) || $groups->hashPresent( $this->rssHash, $rss->hash )) &&
			$history->mayBeApplied( $this->no, $this->interval )
			);
	}
	public function getDirectory()
	{
		return(str_replace(self::$search,$this->matches,$this->directory));
	}
	public function getLabel()
	{
		return(str_replace(self::$search,$this->matches,$this->label));
	}	
	protected function isOK( $string )
	{
		$this->matches = array();
		return(	(($this->pattern!='') || ($this->exclude!='')) &&
			(($this->pattern=='') || (@preg_match($this->pattern.'u',$string,$this->matches)==1)) &&
			(($this->exclude=='') || (@preg_match($this->exclude.'u',$string)!=1)));
	}
	public function checkItem( $href, $rssItem )
	{
		$content = '';
                if(($this->titleCheck == 1) && 
			array_key_exists('title',$rssItem))
			$content = $rssItem['title'];
		if(($this->descCheck == 1) && 
			array_key_exists('description',$rssItem))
		{
			$temp = preg_replace("/\s+/u"," ", $rssItem['description']);
			if(!empty($content))
				$content.=' ';
			$content.=$temp;
		}	
		if($this->linkCheck == 1)
		{
			if(!empty($content))
				$content.=' ';
			$content.=$href;
		}
		return($this->isOK( $content ));
	}
	public function isCorrect()
	{
		return(($this->pattern=='') || @preg_match($this->pattern.'u','')!==false);
	}
	public function isCorrectExclude()
	{
		return(($this->exclude=='') || @preg_match($this->exclude.'u','')!==false);
	}
	public function getContents()
	{
		return( array( 	
				"name"=>$this->name,
				"enabled"=>intval($this->enabled),
				"pattern"=>$this->pattern,
				"label"=>$this->label,
				"exclude"=>$this->exclude,
				"throttle"=>$this->throttle,
				"ratio"=>$this->ratio,
				"hash"=>$this->rssHash,
				"start"=>intval($this->start),
				"add_path"=>intval($this->addPath),
				"chktitle"=>intval($this->titleCheck),
				"chkdesc"=>intval($this->descCheck),
				"chklink"=>intval($this->linkCheck),
				"no"=>intval($this->no),
				"interval"=>intval($this->interval),
				"dir"=>$this->directory 
			));
	}
}

class rRSSFilterList
{
	public $hash = "filters";
	public $modified = false;
        public $lst = array();
	
	public function add( $filter )
	{
		$this->lst[] = $filter;
	}
	protected static function sortByName( $a, $b )
	{
		return(strcmp($a->name, $b->name));
	}
	public function sort()
	{
		usort($this->lst, array(__CLASS__,"sortByName"));
	}
	public function getContents()
	{
		$ret = array();
		foreach( $this->lst as $item )
			$ret[] = $item->getContents();
		return( $ret );
	}
}

class rRSSGroup
{
	public $name;
	public $hash;
	public $modified = false;
	public $lst = array();

	public function	__construct( $name, $hash = null )
	{
		$this->name = $name;
		if(is_null($hash))
			$this->hash = 'grp_'.uniqid(time());
		else
			$this->hash = $hash;
	}
	public function check( $rssList )	
	{
		$changed = false;
		foreach( $this->lst as $ndx=>$item )
			if(!array_key_exists( $item, $rssList->lst ))
			{
				unset($this->lst[$ndx]);
				$changed = true;
			}
		if($changed)
			$this->lst = array_merge($this->lst);
		return($changed);
	}
	public function hashPresent( $rssHash )
	{
		return(in_array($rssHash, $this->lst));
	}
}

class rRSSGroupList
{
	public $hash = "groups";
	public $modified = false;
        public $lst = array();
	
	public function add( $grp )
	{
		$this->lst[$grp->hash] = $grp;
	}
	protected static function sortByName( $a, $b )
	{
		return(strcmp($a->name, $b->name));
	}
	public function sort()
	{
		uasort($this->lst, array(__CLASS__,"sortByName"));
	}
	public function getContents()
	{
		$ret = array();
		foreach( $this->lst as $item )
			$ret[$item->hash] = array( "name"=>$item->name, "lst"=>$item->lst );
		return( $ret );
	}
	public function check( $rssList )
	{
		$changed = false;
		foreach( $this->lst as $item )
			if($item->check($rssList))
				$changed = true;
		return($changed);
	}
	public function get( $hash )
	{
		return(array_key_exists($hash,$this->lst) ? $this->lst[$hash] : null);
	}
	public function remove( $hash )
	{
		if(array_key_exists($hash,$this->lst))
			unset($this->lst[$hash]);
	}
	public function hashPresent( $grpHash, $rssHash )
	{
		$grp = $this->get($grpHash);
		if($grp)
			return($grp->hashPresent($rssHash));
		return(false);
	}	
}

class rRSSMetaList
{
	public $hash = "info";
	public $modified = false;
	public $lst = array();
	public $updatedAt = 0;
	public $err = array();
	public $loadedErrors = 0;

	public function merge($instance,$mergeErrorsOnly)
	{
		if($this->isErrorsOccured())
		{
			$mergedErrors = $instance->err;
			for($i = $this->loadedErrors; $i<count($this->err); $i++)
				$mergedErrors[] = $this->err[$i];
			$this->err = $mergedErrors;
		}
		else
			$this->err = $instance->err;
		if($mergeErrorsOnly)
			$this->lst = $instance->lst;
		return(true);
	}
	public function resetErrors()
	{
		$this->loadedErrors = count($this->err);
	}
	public function isErrorsOccured()
	{
		return($this->loadedErrors < count($this->err));
	}
	public function isExist( $rss )
	{
		return(is_object($rss) ? array_key_exists($rss->hash,$this->lst) : array_key_exists($rss,$this->lst));
	}
	public function touch()
	{
		$this->updatedAt = time();
	}
	public function add( $rss, $label, $auto, $enabled )
	{
		$this->lst[$rss->hash] = array( 'label'=>$label, 'auto'=>$auto, 'enabled'=>$enabled, 'url'=>$rss->srcURL );
	}
	public function change( $rss, $label, $auto )
	{
		$this->lst[$rss->hash]['label'] = $label;
		$this->lst[$rss->hash]['auto'] = $auto;
		$this->lst[$rss->hash]['url'] = $rss->srcURL;
	}
	public function toggleStatus( $hash )
	{
		if(array_key_exists($hash,$this->lst))
		{
			if($this->lst[$hash]['enabled']==1)
				$this->lst[$hash]['enabled'] = 0;
			else
				$this->lst[$hash]['enabled'] = 1;
		}
	}
	public function getEnabled( $rss )
	{
		if($this->isExist( $rss ))
			return($this->lst[$rss->hash]['enabled']);
		return(0);
	}
	public function remove( $rss )
	{
		unset($this->lst[$rss->hash]);
	}
	public function formatErrors()
	{
		return($this->err);
	}
	public function addError( $desc, $prm = null )
	{
		$e = array( 'time'=>time(), 'desc'=>$desc, 'prm'=>'' );
		if($prm)
			$e['prm'] = $prm;
		$this->err[] = $e;
	}
	public function clearErrors()
	{
		$this->err = array();
	}
}

class rRSSData
{
	public $hash = "data";
	public $modified = false;
	public $interval = 30;
	public $delayErrorsUI = true;
}

class rRSSManager
{
	public $cache = null;
	public $history = null;
	public $rssList = null;
	public $groups = null;
	public $data = null;

	public function __construct()
	{
		$this->cache  = new rCache( '/rss/cache' );
		$this->rssList = new rRSSMetaList();
		$this->cache->get($this->rssList);
		$this->rssList->resetErrors();
		$this->history = new rRSSHistory();
		$this->cache->get($this->history);
		$this->groups = new rRSSGroupList();
		$this->cache->get($this->groups);
		$this->data = new rRSSData();
		$this->cache->get($this->data);
	}
	public function setSettings($interval, $delay_err_ui)
	{
		// setInterval
		global $minInterval;
		if(!isset($minInterval))
			$minInterval = 2;
		if($interval<$minInterval)
			$interval = $minInterval;
		$this->data->interval = $interval;
		// set delay ui errors
		$this->data->delayErrorsUI = (bool) $delay_err_ui;
		$this->cache->set($this->data);
		$this->setHandlers();
	}
	public function setHandlers()
	{
	        $startAt = 0;
		$req = new rXMLRPCRequest( rTorrentSettings::get()->getScheduleCommand("rss",$this->data->interval,
			getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg(dirname(__FILE__).'/update.php').' '.escapeshellarg(User::getUser()).' & exit 0}', $startAt) );
		if($req->success())
		{
			$this->setStartTime($startAt);
			return(true);
		}
		return(false);
	}
	public function getModified($obj = null)
	{
		return($this->cache->getModified($obj));
	}
	protected function changeFiltersHash($oldHash,$newHash)
	{
		$flts = new rRSSFilterList();
                $this->cache->get($flts);
		$changed = false;
		foreach($flts->lst as $filter)
		{
			if($filter->rssHash==$oldHash)
			{
				$filter->rssHash = $newHash;
				$changed = true;
			}
		}
		if($changed)
			$this->cache->set($flts);
	}
        public function loadFilters()
	{
		$flts = new rRSSFilterList();
                $this->cache->get($flts);
		$changed = false;
		foreach($flts->lst as $filter)
		{
			if(!empty($filter->rssHash) &&
				!$this->rssList->isExist($filter->rssHash) &&
				!$this->groups->get( $filter->rssHash ))
			{
				$filter->rssHash = '';
				$changed = true;
			}
		}
		if($changed)
			$this->cache->set($flts);
		return($flts);
	}
        public function getFilters()
	{
		return($this->loadFilters()->getContents());
	}
	public function setFilters($flts)
	{
	        $flts->sort();
                $this->cache->set($flts);
		foreach($this->rssList->lst as $hash=>$info)
		{
			$rss = new rRSS();
			$rss->hash = $hash;
			if($this->cache->get($rss) && $info['enabled'])
				$this->checkFilters($rss,$flts);
		}
		$this->history->removeOldFilters($flts);
		$this->saveHistory();
	}
	public function clearHistory()
	{
		$this->history->clear();
                $this->saveHistory();
	}
	public function setHistoryState( $urls, $times, $state )
	{
		foreach( $urls as $ndx=>$url )
		{
			if($state)
				$this->history->add($url, 'Loaded', $times[$ndx], '');
			else
				$this->history->del($url);
		}
                $this->saveHistory();
	}
	public function checkFilters($rss,$filters = null)
	{
		if($filters===null)
			$filters = $this->loadFilters();
		foreach($filters->lst as $filter)
		{
			foreach($rss->items as $href=>$item)
			{
				if(!$filter->isApplicable( $rss, $this->history, $this->groups ))
					break;
				if( !$this->history->wasLoaded($href, $item['guid']) && $filter->checkItem($href, $item) )
				{
					self::log("Filter [".$filter->name."] of channel [".$rss->url."] was applied for [$href]");
					$this->history->applyFilter( $filter->no );
					rTorrentSettings::get()->pushEvent( "RSSAutoLoad", array( "rss"=>&$rss, "href"=>&$href, "item"=>&$item, "filter"=>&$filter ) );
					$this->getTorrents( $rss, $href, 
						$filter->start, $filter->addPath, $filter->getDirectory(), $filter->getLabel(), $filter->throttle, $filter->ratio, false );
					if(WAIT_AFTER_LOADING)
						sleep(WAIT_AFTER_LOADING);
				}
			}
		}
	}
	public function testOneFilter($filter,$hash)
	{
		$rss = new rRSS();
		$rss->hash = $hash;
		$hrefs = array();
		if($this->rssList->isExist($rss) && $this->cache->get($rss))
		{
			foreach($rss->items as $href=>$item)
			{
				if( $filter->checkItem($href, $item) )
				{
					$hrefs[$href] = array( 'label'=>$filter->getLabel(), 'dir'=>$filter->getDirectory() );
				}
			}
		}
		else
			$this->rssList->addError("theUILang.rssDontExist");
		return($hrefs);
	}
	public function testFilter($filter,$hash = null)
	{
		$hrefs = array();
		if(!$filter->isCorrect())
			$this->rssList->addError("theUILang.rssIncorrectFilter",$filter->pattern);
		else
		if(!$filter->isCorrectExclude())
			$this->rssList->addError("theUILang.rssIncorrectFilter",$filter->exclude);
		else
		{
			if($hash)
			{
				$grp = $this->groups->get($hash);
				if($grp)
					foreach($grp->lst as $hsh)
						$hrefs = array_merge($hrefs,$this->testOneFilter($filter,$hsh));
				else
					$hrefs = $this->testOneFilter($filter,$hash);
			}
			else
			{
				foreach($this->rssList->lst as $hsh=>$info)
					$hrefs = array_merge($hrefs,$this->testOneFilter($filter,$hsh));
				$hash = '';
			}
		}
		return(array
		( 
			"errors"=>$this->rssList->formatErrors(), 
			"rss"=>$hash, 
			"count"=>count($hrefs),
			"list"=>$hrefs, 
		));
	}
	public function updateRSSGroup($hash)
	{
		$grp = $this->groups->get($hash);
		if($grp)
			$this->updateRSS($grp->lst);
	}
	private function tryFetch($rss) {
		$success = $rss->fetch($this->history) && $this->cache->set($rss);
		if (!$success) {
			$this->rssList->addError( "theUILang.cantFetchRSS + ' - ".join("; ", $rss->lastErrorMsgs)."'", $rss->getMaskedURL() );
		}
		return($success);
	}

	public function updateRSS($hash)
	{
		$filters = $this->loadFilters();
		$rss = new rRSS();
		if(!is_array($hash))
			$hash = array( $hash );
		foreach( $hash as $item )
		{
			$rss->hash = $item;
			if($this->rssList->isExist($rss))
			{
				$info = $this->rssList->lst[$item];
	                        if($this->cache->get($rss) && $info['enabled'])
				{
					if($this->tryFetch($rss))
					{
						$this->checkFilters($rss,$filters);
						$this->saveHistory();
					}
				}
			}
			else
				$this->rssList->addError("theUILang.rssDontExist");
		}
	}
	public function setStartTime( $startAt )
	{
		$this->rssList->updatedAt = time()+($startAt-$this->data->interval*60);
		$this->saveState(false);
	}
        public function update( $manual = false )
	{
		$filters = $this->loadFilters();
		foreach($this->rssList->lst as $hash=>$info)
		{
			$rss = new rRSS();
			$rss->hash = $hash;
			if($this->cache->get($rss) && $info['enabled'])
			{
				if($this->tryFetch($rss))
				{
					$this->checkFilters($rss,$filters);
				}
			}
		}
		if(!$manual)
		{
			$this->rssList->touch();
                	$this->saveState(true);
		}
		$this->saveHistory();
	}
	public function getSettings()
	{
		return([
			"updatedAt"=>$this->rssList->updatedAt,
			"interval"=>$this->data->interval,
			"delayerrui"=>$this->data->delayErrorsUI
		]);
	}
	public function get()
	{
		$corrected = false;
		$ret = array( "errors"=>$this->rssList->formatErrors(), "list"=>array(), "groups"=>array() );
		foreach($this->rssList->lst as $hash=>$info)
		{
			$rss = new rRSS(array_key_exists('url',$info) ? $info['url'] : null);
			$rss->hash = $hash;
			if(!$this->cache->get($rss) && !empty($rss->srcURL) && $rss->fetch($this->history))
			{
				$this->cache->set($rss);
				$this->saveHistory();
			}
			if(!empty($rss->srcURL))
			{
				$ret["list"][] = $rss->getContents($info['label'],$info['auto'],$info['enabled'],$this->history);
				if(!array_key_exists('url',$info))
				{
					$this->rssList->lst[$hash]['url'] = $rss->srcURL;
					$corrected = true;
				}
			}
			else
			{
				$corrected = true;
				$this->remove($hash,false);
			}
		}
		if($corrected)
			$this->cache->set($this->rssList);
		if($this->groups->check($this->rssList))
			$this->saveGroups();
		$ret["groups"] = $this->groups->getContents();
		return($ret);
	}
	public function getDescription( $hash, $href )
	{
		$rss = new rRSS();
		$rss->hash = $hash;
		if($this->rssList->isExist($rss) &&
			$this->cache->get($rss) &&
			array_key_exists($href,$rss->items))
			return($rss->items[$href]['description']);
		return('');
	}
	public function removeGroup( $hash )
	{
		$this->groups->remove($hash);
		$this->saveGroups();
	}
	public function removeGroupContents( $hash )
	{
		$grp = $this->groups->get($hash);
		if($grp)
			$this->remove($grp->lst);
		$this->removeGroup($hash);
		if($this->groups->check($this->rssList))
			$this->saveGroups();
	}
	public function remove( $hash, $needFlush = true )
	{
		if(is_array($hash))
		{
			foreach($hash as $item)
				$this->remove($item,false);
			$this->saveState(false);
		}
		else
		{
			$rss = new rRSS();
			$rss->hash = $hash;
			if($this->rssList->isExist($rss))
			{
				$this->rssList->remove($rss);
				if($needFlush)
					$this->saveState(false);
        			$this->cache->remove($rss);
			}
		}
		return(true);
	}
	public function setStatusGroup( $hash, $enable )
	{
		$grp = $this->groups->get($hash);
		if($grp)
		{
			foreach( $grp->lst as $item )
				$this->rssList->lst[$item]['enabled'] = $enable;
			$this->saveState(false);
		}
	}
	public function toggleStatus( $hash )
	{
		$this->rssList->toggleStatus( $hash );
		$this->saveState(false);
	}
	public function changeGroup( $hash, $label, $rssList )
	{
		$grp = $this->groups->get($hash);
		if($grp)
		{
			$grp->name = $label;
			$grp->lst = $rssList;
			$grp->check($this->rssList);
			$this->saveGroups();	
		}
	}
	public function change( $hash, $rssURL, $rssLabel, $rssAuto = 1 )
	{
		$rssOld = new rRSS();
		$rssOld->hash = $hash;
		$rssLabel = trim($rssLabel);
		if(!$this->rssList->isExist($rssOld))
		{
			return($this->add($rssURL, $rssLabel, $rssAuto, 1));
		}
		else
		{
			$rssNew = new rRSS($rssURL);
			if($rssNew->hash==$hash)
			{
				$this->rssList->change($rssNew,$rssLabel,$rssAuto);
				$this->saveState(false);
				return(true);
			}
			else
			{
				$enabled = $this->rssList->getEnabled($rssOld);
				$this->remove($hash);
				$this->changeFiltersHash($hash,$rssNew->hash);
				$this->add($rssURL, $rssLabel, $rssAuto, $enabled);
			}
		}
	}
	public function addGroup( $label, $rssList )
	{
		$grp = new rRSSGroup( $label );
		$grp->lst = $rssList;
		$grp->check($this->rssList);
		$this->groups->add($grp);
		$this->saveGroups();			
	}
	public function add( $rssURL, $rssLabel = null, $rssAuto = 0, $enabled = 1 )
	{
		$rss = new rRSS($rssURL);
		if(!$this->rssList->isExist($rss))
		{
			if($this->tryFetch($rss))
			{
			        if($rssLabel)
			        	$rssLabel = trim($rssLabel);
				if(!$rssLabel || !strlen($rssLabel))
				{
					if(array_key_exists('title',$rss->channel))
						$rssLabel = $rss->channel['title'];
					else
						$rssLabel = 'New RSS';
				}
				$this->rssList->add($rss,$rssLabel,$rssAuto,$enabled);
               	               	$this->saveState(false);
				$this->checkFilters($rss);
				$this->saveHistory();
			}
		}
		else
			$this->rssList->addError( "theUILang.rssAlreadyExist", $rss->getMaskedURL() );
	}
	public function getTorrents( $rss, $url, $isStart, $isAddPath, $directory, $label, $throttle, $ratio, $needFlush = true )
	{
		if(!self::isDryRun())
		{
			self::log("Load torrent [$url]");
			$thash = 'Failed';
			$ret = $rss->getTorrent( $url );
			if($ret!==false)
			{
				$addition = array();
				if(!empty($throttle))
					$addition[] = getCmd("d.set_throttle_name=").$throttle;
				if(!empty($ratio))
					$addition[] = getCmd("view.set_visible=").$ratio;
				global $saveUploadedTorrents;
				$thash = ($ret==='magnet') ?
					rTorrent::sendMagnet($url, $isStart, $isAddPath, $directory, $label, $addition) :
					rTorrent::sendTorrent($ret, $isStart, $isAddPath, $directory, $label, $saveUploadedTorrents, false, true, $addition);
				if($thash===false)
				{
					$thash = 'Failed';
					@unlink($ret);
					$ret = false;
				}
			}
			if($ret===false)
				$this->rssList->addError( "theUILang.rssCantLoadTorrent", $url );
			$this->history->add($url, $thash, $rss->getItemTimestamp($url), $rss->items[$url]['guid']);
			if($needFlush)
				$this->saveHistory();
		}
		else
		{
			self::log("Load torrent [$url] (dry-run mode, no real action applied)");
		}
	}
	public function saveHistory()
	{
		if($this->history->isOverflow())
		{
			$urls = array();
			foreach($this->rssList->lst as $hash=>$info)
			{
				$rss = new rRSS();
				$rss->hash = $hash;
				if($this->cache->get($rss))
				{
					foreach($rss->items as $href=>$item)
						$urls[$href] = true;
				}
			}
			if(count($urls))
				foreach($this->history->lst as $href=>$hash)
				{
					if(!array_key_exists($href,$urls) && $this->history->lst[$href]["hash"] === 'Failed')
						$this->history->del($href);
				}
		}
		if($this->history->isChanged())
		{
			$this->history->changed = false;
			$this->cache->set($this->history);
		}
	}
	public function isErrorsOccured()
	{
		return($this->rssList->isErrorsOccured());
	}
	public function hasErrors()
	{
		return(count($this->rssList->err)>0);
	}
	public function clearErrors()
	{
		$this->rssList->clearErrors();
	}
	public function saveGroups()
	{
		$this->groups->sort();
		$this->cache->set($this->groups);
	}
	public function saveState($mergeErrorsOnly)
	{
		$this->cache->set($this->rssList,$mergeErrorsOnly);
		$this->rssList->resetErrors();
	}
	public function clearFilterTime( $filterNo )
	{
		$this->history->clearFilterTime( $filterNo );
		$this->saveHistory();
	}

	protected static function log( $msg )
	{
		global $rss_debug_enabled;
		if( $rss_debug_enabled ) 
		{
			FileUtil::toLog("RSS: $msg");
		}
	}

	protected static function isDryRun()
	{
		global $rss_debug_enabled;
		return( $rss_debug_enabled === 'dry-run' );
	}
}
