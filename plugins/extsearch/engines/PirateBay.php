<?php

class PirateBayEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>30 );
	public $categories = array( 'all'=>'100,200,300,400,500,600', 'Audio'=>'100', 'Video'=>'200', 'Applications'=>'300', 'Games'=>'400', 'Porn'=>'500', 'Other'=>'600' );

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		return($client);
	}
	// The "Uploaded" column reads "MM-DD HH:MM" for this year, "MM-DD YYYY",
	// "Today HH:MM", "Y-day HH:MM" or "N mins ago".
	public static function uploadedTime($tms)
	{
		if(strpos($tms,":")!==false)
		{
			$tm = self::scanTime($tms,"m-d H:M");
			if($tm===false)
			{
				$tms = str_replace( "Y-day", "-1 day", $tms );
				$tm = strtotime($tms);
				if($tm!==false)
					$tm = localtime($tm,true);
			}
			else
				$tm["tm_year"] = date("Y")-1900;
		}
		else
		{
			if(preg_match( '/^(\d+) mins? ago$/i', $tms, $match ))
			{
				$tms = "-".$match[1]." minute";
				$tm = strtotime($tms);
				if($tm!==false)
					$tm = localtime($tm,true);
			}
			else
				$tm = self::scanTime($tms,"m-d Y");
		}
		if($tm===false)
			return(false);
		return(mktime( $tm["tm_hour"], $tm["tm_min"], $tm["tm_sec"], $tm["tm_mon"]+1, $tm["tm_mday"], $tm["tm_year"]+1900 ));
	}

	// strptime(), deprecated as of PHP 8.1, for the numeric fields above:
	// in $format, 'm', 'd', 'H', 'M' and 'Y' stand for "%m" and so on, a
	// space for any run of white space, and anything else for itself. Each
	// number is read as the C library reads it: leading white space
	// skipped, and a second digit taken only while the value can still be
	// in range.
	private static function scanTime($str,$format)
	{
		$fields = array(
			'm'=>array(1,12,2,'tm_mon',1),
			'd'=>array(1,31,2,'tm_mday',0),
			'H'=>array(0,23,2,'tm_hour',0),
			'M'=>array(0,59,2,'tm_min',0),
			'Y'=>array(0,9999,4,'tm_year',1900) );
		$tm = array( 'tm_sec'=>0, 'tm_min'=>0, 'tm_hour'=>0, 'tm_mday'=>0, 'tm_mon'=>0, 'tm_year'=>0 );
		$space = " \t\n\v\f\r";
		$pos = 0;
		$len = strlen($str);
		foreach(str_split($format) as $f)
		{
			if($f==' ')
			{
				$pos += strspn($str,$space,$pos);
				continue;
			}
			if(!array_key_exists($f,$fields))
			{
				if(($pos>=$len) || ($str[$pos]!==$f))
					return(false);
				$pos++;
				continue;
			}
			list($from,$to,$digits,$key,$base) = $fields[$f];
			$pos += strspn($str,$space,$pos);
			if(!strspn($str,'0123456789',$pos,1))
				return(false);
			$val = 0;
			do
			{
				$val = $val*10 + intval($str[$pos++]);
			}
			while((--$digits>0) && ($val*10<=$to) && strspn($str,'0123456789',$pos,1));
			if(($val<$from) || ($val>$to))
				return(false);
			$tm[$key] = $val-$base;
		}
		return($tm);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://thepiratebay.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'100,200,300,400,500,600', 'movies'=>'200', 'tv'=>'205', 'music'=>'100', 'games'=>'400', 'anime'=>'0', 'software'=>'300', 'pictures'=>'603', 'books'=>'601' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$maxPage = 10;
		for($pg = 0; $pg<$maxPage; $pg++)
		{
			$cli = $this->fetch( $url . '/search/' . $what . '/' . $pg . '/7/' . $cat );
			if(($cli==false) || (strpos($cli->results, "</span>&nbsp;No hits.")!==false))
				break;
			$res = preg_match_all('`<td class="vertTh">.*'.
				'<a .*>(?P<cat>.*)</a>.*<a .*>(?P<subcat>.*)</a>.*'.
				'<a href="(?P<desc>.*)" .*>(?P<name>.*)</a>.*'.
				'<a href="magnet:(?P<link>[^"]*)" .*>.*<font .*>Uploaded (?P<date>.*), Size (?P<size>.*), .*</font>.*'.
				'<td align="right">(?P<seeds>.*)</td>.*'.
				'<td align="right">(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<count($matches["link"]); $i++)
				{
					$link = "magnet:".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i].' > '.$matches["subcat"][$i]);
						$item["desc"] = $url.$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));

						$tm = self::uploadedTime(self::removeTags($matches["date"][$i]));
						if($tm!==false)
							$item["time"] = $tm;
						$ret[$link] = $item;
						$added++;
						if($added>=$limit)
							return;
					}
				}
			}
			else
				break;
		}
	}
}
