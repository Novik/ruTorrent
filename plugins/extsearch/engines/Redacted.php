<?php

class RedactedEngine extends commonEngine
{

	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'all'=>'', 'Music'=>'&filter_cat[1]=1', 'Applications'=>'&filter_cat[2]=1', 'E-Books'=>'&filter_cat[3]=1',
		'Audiobooks'=>'&filter_cat[4]=1', 'E-Learning Videos'=>'&filter_cat[5]=1', 'Comedy'=>'&filter_cat[6]=1', 'Comics'=>'&filter_cat[7]=1' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://redacted.ch';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'music'=>'&filter_cat[1]=1', 'software'=>'&filter_cat[2]=1', 'books'=>'&filter_cat[3]=1&filter_cat[4]=1&filter_cat[6]=1&filter_cat[7]=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<10; $pg++)
		{
			$itemsFound = false;
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.'&tags_type=1&searchsubmit=1&order_by=seeders&order_way=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Your search did not match anything.</h2>")!==false) ||
				(strpos($cli->results, "<td>Password&nbsp;</td>")!==false))
				break;

			$res = preg_match_all('/<tr class="torrent">.*<a href="torrents\.php\?(?P<link>.*)".*>DL<\/a>.*'.
				'<\/span>(?P<artist>.*)<a href="torrents\.php\?id=(?P<desc>\d+)\&.*>(?P<title>.*)<div class="torrent_info">(?P<cat>.*)<\/div>.*'.
				'<td class="nobr"><span .* title="(?P<date>.*)">.*<\/span>.*<td .*>(?P<size>.*)<\/td>.*'.
				'<td .*>.*<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>'.
				'/siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/torrents.php?".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrents.php?id=".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["artist"][$i].$matches["title"][$i]);
						$item["size"] = self::formatSize(str_replace(",","",$matches["size"][$i]));
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
