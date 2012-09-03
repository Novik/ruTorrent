<?php

class WorldInHDEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>25, "auth"=>1 );

	public $categories = array( 'all'=>'0', 
		'Animations\Dessins Animes'=>'6', 
		'AudioHD'=>'27',
		'Bandes Sons'=>'25',
		'Bluray'=>'12',
		'Documentaires\reportages'=>'9',
		'Films'=>'3',
		'Logiciels\Applications'=>'20',
		'PlayStation 3'=>'18',
		'Series HD'=>'15',
		'Videos Clips'=>'30'
		); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://world-in-hd.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'3', 'tv'=>'15', 'music'=>'27', 'anime'=>'6', 'software'=>'20' );
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
			if($cli==false || (strpos($cli->results, 'type="password"')!==false)) 
				break;
			$res = preg_match_all('`<tr.*>.*<td width="1"><a href="https://world-in-hd\.net/.*" target="_self" /><img src="https://world-in-hd\.net/pic/categories/.*" border="0" alt=".*: (?P<cat>.*)".*/></a></td>.*'.
				'<td align="left">.*/><b>(?P<name>.*)</a>.*<b>.*:</b>(?P<date>.*)(<br />|</td>).*'.
				'<td align="center"><div id="seeders_(?P<id>\d+)">(?P<seeds>.*)</div></td>.*'.
				'<td align="center"><div id="leechers_\d+">(?P<leech>.*)</div></td>.*'.
				'<td align="center"><img src=.*></td>.*'.
				'<td align="center">.*</td>.*'.
				'<td align="center">(?P<size>.*)<br />'.
				'`siU', $cli->results, $matches);
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
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
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
