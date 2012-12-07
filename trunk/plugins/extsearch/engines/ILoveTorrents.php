<?php

class ILoveTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "auth"=>1 );
	public $categories = array(
		'all'=>'&cat=0','Anime'=>'&cat=23','Appz/Misc'=>'&cat=22','Appz/PC ISO'=>'&cat=1','Ebooks'=>'&cat=24',
		'Games/PC ISO'=>'&cat=4','Games/PC Rips'=>'&cat=21','Games/PS2'=>'&cat=17','Games/PS3'=>'&cat=38','Games/Wii'=>'&cat=43',
		'Games/Xbox360'=>'&cat=12','KIDS-Zone'=>'&cat=31','Movies/DVD-R'=>'&cat=20','Movies/Other'=>'&cat=27','Movies/x264'=>'&cat=41',
		'Movies/XviD'=>'&cat=19','Music Videos'=>'&cat=29','Music/Albums'=>'&cat=6','Other/Stuff'=>'&cat=28','PSP/Handheld'=>'&cat=30',
		'TV/Packs'=>'&cat=5','TV/x264'=>'&cat=8','TV/XviD'=>'&cat=7','XXX'=>'&cat=9'
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.ilovetorrents.me';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0', 'anime'=>'&cat=23', 'books'=>'&cat=24' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"ISO-8859-1"));
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=7&type=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, ">Nothing found!<")!==false) 
				|| (strpos($cli->results, '>Not logged in!<')!==false))
				break;
			$res = preg_match_all('`<tr>\n<td width=46 height=42 class=rowhead style=\'padding: 0px\'><a href="browse.php\?cat=[^"]*"><img border="0" src="/pic/[^"]*" alt="(?P<cat>[^"]*)" /></a></td>\n'.
				'<td class=rowhead align=left><a href="details.php\?id=(?P<id>[^"]*)"><b>(?P<name>.*)</b></a><br>\n'.
				'<td align=center><a href="download\.php/(?P<link>[^"]*)"><img src=http://www\.ilovetorrents\.me/pic/dl\.gif></a></td>\n'.
				'<td align=center><a href=bookmarks\.php\?op=add&id=\d*><img style=border:none src=http://www\.ilovetorrents\.me/pic/bookmark\.gif></a></td>\n'.
				'</td>\n<td class=rowhead align="right"><b><a href="details.php\?id=\d*&amp;hit=1&amp;filelist=1">\d*</a></b></td>\n'.
				'<td class=rowhead align=center>.*</td>\n'.
				'<td class=rowhead align=center><nobr>(?P<date>.*)</nobr></td>\n'.
				'<td class=rowhead align=center>(?P<size>.*)</td>\n'.
				'<td class=rowhead align=center>.*</td>\n'.
				'<td class="rowhead" align=center>(?P<seeds>.*)</td>\n'.
				'<td class=rowhead align=center>(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".self::removeTags($matches["id"][$i]);
						$item["name"] = self::toUTF(self::removeTags($matches["name"][$i]),"ISO-8859-1");
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
						$item["time"] = strtotime(self::removeTags(str_replace("<br />"," ",$matches["date"][$i])));
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
