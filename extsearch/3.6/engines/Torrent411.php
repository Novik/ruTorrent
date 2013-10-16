<?php

class Torrent411Engine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"www.t411.me|uid=XXX;pass=XXX;authKey=XXX;" );

	public $categories = array
	( 
		'all'=>'',
		'Audio'=>'&cat=395',
		'eBook'=>'&cat=404',
		'Emulation'=>'&cat=340',
		'Jeu vidéo'=>'&cat=624',
		'GPS'=>'&cat=392',
		'Application'=>'&cat=233',
		'Film/Vidéo'=>'&cat=210',
	); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.t411.me';

		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'&cat=210', 'music'=>'&cat=395', 'software'=>'&cat=233', 'books'=>'&cat=404' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"ISO-8859-1"));
		for($pg = 0; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents/search/?search='.$what.'&order=seeders&type=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, ">Aucun R�sultat Aucun<")!==false))
				break;

			$res = preg_match_all('`<img src="/images/categories.png" alt="(?P<cat>.*)".*'.
				'<a href=".*www\.t411\.me/torrents/(?P<link>.*)" title="(?P<name>.*)">.*'.
				'<dl>.*<dt>.*</dt>.*<dd>(?P<date>.*)</dd>.*'.
				'<a href="/torrents/nfo/\?id=(?P<id>.*)".*'.
				'<td.*>.*</td>.*<td.*>.*</td>.*<td.*>(?P<size>.*)</td>.*<td.*>.*</td>.*'.
				'<td.*>(?P<seeds>.*)</td>.*<td.*>(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/torrents/download/?id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::toUTF(self::removeTags($matches["cat"][$i]),"ISO-8859-1");
						$item["desc"] = $url."/torrents/".$matches["link"][$i];
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
