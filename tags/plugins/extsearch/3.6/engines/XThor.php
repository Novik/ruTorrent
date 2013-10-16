<?php

class XThorEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>30, "cookies"=>"www.xthor.net|c_secure_uid=XXX;c_secure_pass=XXX;ts_language=english" );

	public $categories = array( 'all'=>'0', 'Appz/misc'=>'1', 'Documentaire'=>'37', 'DVD XXX'=>'19', 'DVD9'=>'25', 'DVDR NTSC'=>'3',
		'DVDR PAL'=>'10', 'DVDRIP'=>'9', '|-- DVDRIP STFR'=>'35', 'DVDRSCR - R5'=>'21', 'Ebooks'=>'13', 'Games PC'=>'5',
		'HDRIP-BRRIP'=>'34', '|-- 1080P'=>'44', '|-- 720P'=>'43', '|-- BD9,BD5'=>'45', 'Jeunesse'=>'36', 'Jeux psp DS'=>'41',
		'Mangas/Animés'=>'38', 'Movies CAM/TS/TC'=>'6', 'Music'=>'7', 'Music Video'=>'26', 'Pictures'=>'12', 'Porn'=>'20',
		'|-- XXX HD'=>'48', '|-- XxX Porno'=>'49', 'Serie TV'=>'4', '|-- Serie-TV HD'=>'47', '|-- TV-FRENCH'=>'33', '|-- VOSTFR'=>'32',
		'SPORT'=>'42', 'WII'=>'39', 'Xbox 360'=>'40'
		); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.xthor.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'tv'=>'4', 'music'=>'7', 'anime'=>'38', 'software'=>'1', 'pictures'=>'12', 'books'=>'13' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"ISO-8859-1"));
		for($pg = 1; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?do=search&keywords='.$what.'&search_type=t_name&sort_order=yes&sortby=seeders&orderby=DESC&page='.$pg.'&category='.$cat );
			if($cli==false || (strpos($cli->results, '<input type="password" name="password" class="inputPassword"')!==false)) 
				break;

			$res = preg_match_all('/<tr>.*<td width="1"><a href="http:\/\/www\.xthor\.net\/browse\.php\?browse_categories&amp;category=\d+" target="_self" \/><img src="http:\/\/www\.xthor\.net\/pic\/categories\/.*" border="0" alt=".*: (?P<cat>.*)".*\/><\/a><\/td>.*'.
				'<td align="left">.*\/><b>(?P<name>.*)<\/a>.*<b>.*:<\/b>(?P<date>.*)<\/td>.*'.
				'<td align="center"><a href="http:\/\/www\.xthor\.net\/download\.php\?id=(?P<id>.*)".*'.
				'<td align="center"><div id="seeders_\d+">(?P<seeds>.*)<\/div><\/td>.*'.
				'<td align="center"><div id="leechers_\d+">(?P<leech>.*)<\/div><\/td>.*'.
				'<td align="center">.*<\/td>.*'.
				'<td align="center">(?P<size>.*)<br \/>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::toUTF(self::removeTags($matches["cat"][$i]),"ISO-8859-1");
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::toUTF(self::removeTags($matches["name"][$i]),"ISO-8859-1");
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(self::removeTags(str_replace("-", "/",$matches["date"][$i])));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
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
