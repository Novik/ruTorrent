<?php

class theBoxEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>25, "cookies"=>"thebox.bz|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'', 
		'Adverts / Idents'=>"&c27=1", 'Arts & Culture'=>"&c45=1", 'Big Brother'=>"&c43=1", 'Comedy'=>"&c14=1",
		'Doctor Who'=>"&c36=1",	'Documentary / Educational'=>"&c2=1", 'Drama'=>"&c18=1", 'Entertainment'=>"&c21=1",
		'Food & Drink'=>"&c23=1", 'Game Shows'=>"&c12=1", 'Gardening'=>"&c44=1", 'Home & Property'=>"&c1=1",
		'Horizon'=>"&c50=1", 'Kids'=>"&c16=1", 'Magazine'=>"&c19=1", 'Motoring'=>"&c24=1",
		'Music'=>"&c29=1", 'Mystery / Crime Fiction'=>"&c31=1", 'News'=>"&c17=1", 'Occult / Horror'=>"&c37=1",
		'QuizComedy'=>"&c46=1", 'Radio: Arts'=>"&c49=1", 'Radio: Audio Comedy'=>"&c39=1", 'Radio: Audio Drama'=>"&c40=1",
		'Radio: Audio Sci-fi'=>"&c38=1", 'Radio: General'=>"&c11=1", 'Reality'=>"&c10=1", 'Sci-Fi'=>"&c8=1",
		'Soaps'=>"&c4=1", 'Special Events'=>"&c34=1", 'Special Interest'=>"&c20=1", 'Sport'=>"&c13=1",
		'Style & Fashion'=>"&c26=1", 'Talkshow'=>"&c22=1", 'Trains, Planes'=>"&c47=1", 'Travel'=>"&c25=1",
		'Wildlife & Nature'=>"&c32=1",
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://thebox.bz';

		if($useGlobalCats)
			$categories = array( 'all'=>'', 'music'=>'&c29=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&titleonly=1&incldead=0&sort=seeders&d=DESC&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, ">Nothing found<")!==false) ||
				(strpos($cli->results, ">Password:<")!==false))
				break;

			$res = preg_match_all('`<tr class=ttable>.*src="pic/default/categories/.*alt="(?P<cat>[^"]*)".*'.
				'<td align=left style="border-right:none"><a title="(?P<name>[^"]*)" href="details\.php\?id=(?P<id>\d+)">.*href="download\.php/(?P<link>[^"]*)">.*'.
				'<td align="center">.*</td>.*'.
				'<td align="center">.*</td>.*'.
				'<td.*>(?P<date>.*)</td>.*'.
				'<td.*>(?P<size>.*)</td>.*'.
				'<td.*>.*</td>.*'.
				'<td.*>(?P<seeds>.*)</td>.*'.
				'<td.*>(?P<leech>.*)</td>.*</tr>'.
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
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim(str_replace("<br>"," ",$matches["size"][$i])));
						$item["time"] = strtotime(self::removeTags(trim(str_replace("<br />"," ",$matches["date"][$i]))));
						$item["seeds"] = intval(trim(self::removeTags($matches["seeds"][$i])));
						$item["peers"] = intval(trim(self::removeTags($matches["leech"][$i])));
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
