<?php

class HDForeverEngine extends commonEngine
{

	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'Tout'=>'', 'Film'=>'&filter_cat[1]=1', 'Dessin animé'=>'&filter_cat[2]=1', 'Bonus BD'=>'&filter_cat[3]=1', 'Concert'=>'&filter_cat[4]=1' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://hdf.world';
		if($useGlobalCats)
			$categories = array( 'all'=>'' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<10; $pg++)
		{
			$itemsFound = false;
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.'&searchsubmit=1&order_by=seeders&order_way=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Aucun fichier trouvé.</h2>")!==false) ||
				(strpos($cli->results, "<td>Mot de passe&nbsp;</td>")!==false))
				break;

			$res = preg_match_all('/\[ <a href="torrents\.php\?(?P<link>.*)".*>T<\/a>.*'.
				'(?:<span class="team_name">(?P<tname>.*)<\/span>.*)?'.
				'<a href="torrents\.php\?id=(?P<desc>\d+)\&.*>(?P<name>.*)<i.*'.
				'<div class="torrent_info">(?P<cat>.*)<\/div>.*'.
				'<td class="nobr"><span .* title="(?P<date>.*)">.*<\/span><\/td>.*'.
				'<td .*>(?P<size>.*)<\/td>.*'.
				'<td .*>.*<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>'.
				'/siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					if(!$matches["tname"][$i])
						$name = self::removeTags($matches["name"][$i]);
					else
						$name = self::removeTags($matches["name"][$i].'| '.$matches["tname"][$i]);
					$link = $url."/torrents.php?".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrents.php?id=".$matches["desc"][$i];
						$item["name"] = $name;
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
