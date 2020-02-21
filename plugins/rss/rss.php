<?php

require_once( dirname(__FILE__).'/../../php/cache.php');
require_once( dirname(__FILE__).'/../../php/Snoopy.class.inc');
require_once( dirname(__FILE__).'/../../php/rtorrent.php' );
eval(getPluginConf('rss'));

class rRSS
{
	public $items = array();
	public $channel = array();
	public $url = null;
	public $srcURL = null;
	public $hash = null;
	public $cookies = array();
	public $lastModified = null;
	public $etag = null;
	public $encoding = null;
	public $version = 0;
	private $isValid=false;
	private $channeltags = array('title', 'link', 'lastBuildDate');
	private $itemtags = array('title', 'link', 'pubDate', 'enclosure', 'guid', 'source', 'description', 'dc:date');
	private $atomtags = array('title', 'updated');
	private $entrytags = array('title', 'link', 'updated', 'content', 'summary');

	public function __construct( $url = null )
	{
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
		$cli = self::fetchURL(Snoopy::linkencode($href),$this->cookies);
		if($cli && $cli->status>=200 && $cli->status<300)
		{
			$name = $cli->get_filename();
			if($name===false)
				$name = md5($href).".torrent";
			$name = getUniqueUploadedFilename($name);
			$f = @fopen($name,"w");
			if($f===false)
			{
				$name = getUniqueUploadedFilename(md5($href).".torrent");
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
				"time"=>($item['timestamp']>0) ? intval($item['timestamp']) : null,
				"title"=>$item['title'],
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
		$headers = array();
		if($this->etag) 
			$headers['If-None-Match'] = trim($this->etag);
		if($this->lastModified)
	                $headers['If-Last-Modified'] = trim($this->lastModified);
		$cli = self::fetchURL($this->url,$this->cookies,$headers);
		if($cli->status==304)
			return(true);
		$this->etag = null;
		$this->lastModified = null;
		if($cli->status>=200 && $cli->status<300)
		{
			foreach($cli->headers as $h) 
			{
				$h = trim($h);
				if(strpos($h, ": "))
					list($name, $val) = explode(": ", $h, 2);
				else
				{
					$name = $h;
					$val = "";
				}
				if( $name == 'ETag' ) 
					$this->etag = $val;
				else				
				if( $name == 'Last-Modified' )
					$this->lastModified = $val;
			}
			ini_set( "pcre.backtrack_limit", max(strlen($cli->results),100000) );
			$this->encoding = strtolower($this->search("'encoding=[\'\"](.*?)[\'\"]'si", $cli->results));
			if($this->encoding=='')
				$this->encoding = null;
			$this->channel = array();
			$this->items = array();
			if(preg_match("'<channel.*?>(.*?)</channel>'si", $cli->results, $out) || 
				preg_match("'<channel.*?>(.*?)'si", $cli->results, $out))	// for damned lostfilm.tv with broken rss
			{
				$this->isValid=true; //We have a RSS feed here.
				foreach($this->channeltags as $channeltag)
				{
					$temp = $this->search("'<$channeltag.*?>(.*?)</$channeltag>'si", $out[1], ($channeltag=='title'));
					if($temp!='')
						$this->channel[$channeltag] = $temp;
				}
				if( array_key_exists('lastBuildDate',$this->channel) &&
				        (($timestamp = strtotime($this->channel['lastBuildDate'])) !==-1))
					$this->channel['timestamp'] = $timestamp;
				else
					$this->channel['timestamp'] = 0;
						
				$ret = preg_match_all("'<item(| .*?)>(.*?)</item>'si", $cli->results, $items);
				if(($ret!==false) && ($ret>0))
				{
					foreach($items[2] as $rssItem)
					{
						$item = array();
						foreach($this->itemtags as $itemtag)
						{
							if($itemtag=='enclosure')
								$temp = $this->search("'<enclosure.*url\s*=\s*[\"|\'](.*?)[\"|\'].*>'si", $rssItem);
							else
							if($itemtag=='source')
								$temp = $this->search("'<source\s*url\s*=\s*[\"|\'](.*?)[\"|\'].*>.*</source>'si", $rssItem);
							else
							if($itemtag=='title')
							{
								$temp = $this->search("'<$itemtag.*?>(.*?)</$itemtag>'si", $rssItem, true);
								$temp = preg_replace("/\s+/u"," ",$temp);
							}
							else
								$temp = $this->search("'<$itemtag.*?>(.*?)</$itemtag>'si", $rssItem, false);
							if($itemtag=='description')
								$temp = html_entity_decode( $temp, ENT_QUOTES, "UTF-8" );
							if($temp != '')
							{
								$item[$itemtag] = $temp;
								if( (($itemtag=='pubDate') || ($itemtag=='dc:date')) &&
									(($timestamp = strtotime($temp)) !==-1))
									$item['timestamp'] = $timestamp;
							}
						}
						$href = '';
						if(array_key_exists('enclosure',$item))
							$href = $item['enclosure'];
						else
						if(array_key_exists('link',$item))
							$href = $item['link'];
						else
						if(array_key_exists('guid',$item))
							$href = $item['guid'];
						else
						if(array_key_exists('source',$item))
							$href = $item['source'];
						$guid = $href;
						if(array_key_exists('guid',$item) && preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $item['guid']))
							$guid = $item['guid'];
						else
						if(array_key_exists('link',$item) && preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $item['link']))
							$guid = $item['link'];
						$item['guid'] = self::removeTegs( $guid );
						if(!array_key_exists('timestamp',$item))
						{
// hack for iptorrents.com
// Category: Movies/Non-English  Size: 707.38 MB Uploaded: 2009-10-21 07:42:37
							if(array_key_exists('description',$item) && 
								(strlen($item['description'])<255) &&
								(($pos = strpos($item['description'],'Uploaded: '))!==false) &&
								(($timestamp = strtotime(substr($item['description'],$pos+10)))!==-1))
								$item['timestamp'] = $timestamp;
							else
								$item['timestamp'] = 0;
						}
						if(!empty($href))
							$this->items[self::removeTegs( $href )] = $item;
					}
				}
			}
			else	// Atom 
			{
				$urlPrefix = '';
				if(preg_match('#<feed.*\s+xml:base\s*=\s*[\"|\'](.*?)[\"|\'].*>#si', $cli->results, $items))
					$urlPrefix = $items[1];
				foreach($this->atomtags as $atomtag)
				{
					$temp = $this->search("'<$atomtag.*?>(.*?)</$atomtag>'si", $cli->results, ($atomtag=='title'));
					if($temp!='')
					{
						$this->channel[$atomtag] = $temp;
					}
				}
				$validID = $this->search("'<id.*?>(.*?)</id>'si", $cli->results); //If we find an "id" tag, which is mandatory in Atom feed, we assume that it's a valid feed.
				if($validID!='')
				{
					$this->isValid = true;
				}
				if( array_key_exists('updated',$this->channel) &&
				        (($timestamp = strtotime($this->channel['updated'])) !==-1))
					$this->channel['timestamp'] = $timestamp;
				else
					$this->channel['timestamp'] = 0;
				$ret = preg_match_all("'<entry(| .*?)>(.*?)</entry>'si", $cli->results, $items);
				if(($ret!==false) && ($ret>0))
				{
					foreach($items[2] as $rssItem)
					{
						$item = array();
						foreach($this->entrytags as $itemtag)
						{
							if($itemtag=='title')
							{
								$temp = $this->search("'<$itemtag.*?>(.*?)</$itemtag>'si", $rssItem, true);
								$temp = preg_replace("/\s+/u"," ",$temp);
							}
							else
							if($itemtag=='link')
								$temp = $this->search("'<link.*\s+href\s*=\s*[\"|\'](.*?)[\"|\'].*>'si", $rssItem);
							else
								$temp = $this->search("'<$itemtag.*?>(.*?)</$itemtag>'si", $rssItem, false);
							if(($itemtag=='content') || ($itemtag=='summary'))
								$temp = html_entity_decode( $temp, ENT_QUOTES, "UTF-8" );
							if($temp != '')
							{
								$item[$itemtag] = $temp;
								if( ($itemtag=='updated') &&
									(($timestamp = strtotime($temp)) !==-1))
									$item['timestamp'] = $timestamp;
							}
						}
						$href = '';
						if(array_key_exists('link',$item))
						{
							$href = self::removeTegs( $urlPrefix.$item['link'] );
							$item['guid'] = $href;
						}
						if(array_key_exists('content',$item))
							$item['description'] = $item['content'];
						else
						if(array_key_exists('summary',$item))
							$item['description'] = $item['summary'];
						if(!array_key_exists('timestamp',$item))
							$item['timestamp'] = 0;
						if(!empty($href))
							$this->items[$href] = $item;
					}
				}
			}
			if( !empty($this->items) )
			{
				rTorrentSettings::get()->pushEvent( "RSSFetched", array( "rss"=>&$this ) );
				if(!$this->hasIncorrectTimes())
					foreach( $this->items as $href=>$item )
						$history->correct($href,$item['timestamp']);
				return(true);
			}
			else if ($this->isValid)
                		return true;
		}
		return(false);
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

	static public function removeTegs( $s )
	{
		$last = '';
		while($s!=$last)
		{
			$last = $s;
			$s = @html_entity_decode( strip_tags($s), ENT_QUOTES, "UTF-8" );
		}
		return($s);
	}

	protected function search($pattern, $subject, $needTranslate = false)
	{
		preg_match($pattern, $subject, $out);
		if(isset($out[1]))
		{
			$out[1] = strtr($out[1], array('<![CDATA['=>'', ']]>'=>''));
			if($this->encoding && $this->encoding!="utf-8")
				$out[1] = $this->convert($out[1], $needTranslate);
			else
			if($needTranslate)
				$out[1] = self::removeTegs( $out[1] );
			if( isInvalidUTF8( $out[1] ) )
				$out[1] = win2utf($out[1]);
			return(trim($out[1]));
		}
		else
			return('');
	}

	protected function convert($out,$needTranslate = false)
	{
		if($needTranslate)
			$out = self::removeTegs( $out );
		if(function_exists('iconv'))
			$out = iconv($this->encoding, 'UTF-8//TRANSLIT', $out);
		else
                if(function_exists('mb_convert_encoding'))
			$out = mb_convert_encoding($out, 'UTF-8', $this->encoding );
		else
			$out = win2utf($out);
		return($out);
	}



	static protected function quoteInvalidURI($str)
	{
		return( preg_replace("/\s/u"," ",$str) );
	}

	static protected function fetchURL($url, $cookies = null, $headers = null )
	{
		$client = new Snoopy();
		if(is_array($headers) && count($headers))
			$client->rawheaders = $headers;
		if(is_array($cookies) && count($cookies))
			$client->cookies = $cookies;
		@$client->fetchComplex($url);
		return $client;
	}
}

class rRSSHistory
{
	public $hash = "history";
	public $lst = array();
	public $filtersTime = array();
	public $changed = false;
	public $version = 0;

	public function __construct()
	{
		$this->version = 2;
	}

	public function add( $url, $hash, $timestamp )
	{
		$cnt = 0;
		if(array_key_exists($url,$this->lst))
			$cnt = ($this->lst[$url]["time"]==$timestamp) ? $this->lst[$url]["cnt"] : 0;
		$this->lst[$url] = array( "hash"=>$hash, "time"=>$timestamp, "cnt"=>$cnt );
		if($hash=='Failed')
			$this->lst[$url]["cnt"] = $cnt+1;
		$this->changed = true;
	}
        public function correct( $url, $timestamp )
	{
		if( array_key_exists($url,$this->lst) && 
			($this->lst[$url]["time"]!=$timestamp) )
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
	public function wasLoaded( $url )
	{
		$ret = false;
		if(array_key_exists($url,$this->lst))
			$ret = ($this->lst[$url]["hash"]!=='Failed') || ($this->getCounter( $url )>HISTORY_MAX_TRY);
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
			$temp = rRSS::removeTegs( $rssItem['description'] );
			$temp = preg_replace("/\s+/u"," ",$temp);
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
	public $interval = 30;
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
	public function setInterval($interval)
	{
		global $minInterval;
		if(!isset($minInterval))
			$minInterval = 2;
		if($interval<$minInterval)
			$interval = $minInterval;
		$this->data->interval = $interval;
		$this->cache->set($this->data);
		$this->setHandlers();
	}
	public function setHandlers()
	{
	        $startAt = 0;
		$req = new rXMLRPCRequest( rTorrentSettings::get()->getScheduleCommand("rss",$this->data->interval,
			getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg(dirname(__FILE__).'/update.php').' '.escapeshellarg(getUser()).' & exit 0}', $startAt) );
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
				$this->history->add($url,'Loaded',$times[$ndx]);
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
				if(     !$this->history->wasLoaded($href) &&
					$filter->checkItem($href, $item) )
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
					if($rss->fetch($this->history) && $this->cache->set($rss))
					{
						$this->checkFilters($rss,$filters);
						$this->saveHistory();
					}
					else
						$this->rssList->addError( "theUILang.cantFetchRSS", $rss->getMaskedURL() );
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
				if($rss->fetch($this->history) && $this->cache->set($rss))
					$this->checkFilters($rss,$filters);
				else
					$this->rssList->addError( "theUILang.cantFetchRSS", $rss->getMaskedURL() );
			}
		}
		if(!$manual)
		{
			$this->rssList->touch();
                	$this->saveState(true);
		}
		$this->saveHistory();
	}
	public function getIntervals()
	{
		$nextTouch = $this->data->interval*60;
		if($this->rssList->updatedAt)
			$nextTouch = $nextTouch-(time()-$this->rssList->updatedAt)+45;
		return(array( "next"=>$nextTouch, "interval"=>$this->data->interval ));
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
			array_key_exists($href,$rss->items) &&
			array_key_exists('description',$rss->items[$href]))
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
			if($rss->fetch($this->history) && $this->cache->set($rss))
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
			else
				$this->rssList->addError( "theUILang.cantFetchRSS", $rss->getMaskedURL() );
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
			$this->history->add($url,$thash,$rss->getItemTimestamp( $url ));
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
					if(!array_key_exists($href,$urls))
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
			toLog("RSS: $msg");
		}
	}

	protected static function isDryRun()
	{
		global $rss_debug_enabled;
		return( $rss_debug_enabled === 'dry-run' );
	}
}
