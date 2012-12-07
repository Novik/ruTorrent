<?php

class HDDreamEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>15, "auth"=>1 );

	public $categories = array( 'all'=>'0', 'Animes'=>'9', 'BD5'=>'4', 'BD9'=>'12', 'Bluray'=>'3', 'Clip/Concert/Spectacles HD'=>'15',
		'Covers HD'=>'8', 'Divers'=>'10', 'DOCUMENTAIRES'=>'18', 'Flac'=>'11', 'HD 1080p'=>'14', 'HD 720p'=>'5',
		'Logiciels'=>'7', 'Piste-Son'=>'6', 'REMUX'=>'20', 'Series HD'=>'13', 'XxX HD'=>'16'
		); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://hd-dream.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'anime'=>'9' );
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
			if($cli===false)
				break;
			$res = preg_match_all('/<td width="5%" align="center" rowspan="2">\n\s*<a href="http:\/\/hd-dream\.net\/browse\.php\?browse_categories&amp;category=\d+" target="_self" \/><img src.*alt=".*: (?P<cat>.*)".*'.
				'<a href="#" id="quickmenu\d+" \/>(?P<name>.*)<\/a>.*'.
				'<td width="10%">.*<\/td>.*<\/tr>.*<tr>.*'.
				'<td width="15%"><b>(?P<size>.*)<\/b>.*'.
				'<td width="20%">(?P<seeds>.*) Seeder\(s\) et (?P<leech>.*) Leecher\(s\).*'.
				'<a href="http:\/\/hd-dream\.net\/download.php\?id=(?P<id>.*)"/siU', $cli->results, $matches);
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
