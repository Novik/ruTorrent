<?php

class ABTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"www.abtorrents.me|uid=XXX;pass=XXX;" );
	public $categories = array(
		"all"=>"0", "Adventure"=>"38", "Biographies & Memoirs"=>"23", "Business "=>"19", "Childrens"=>"30",
		"Comedy"=>"29", "Computers "=>"20", "Erotica"=>"9", "Fantasy-General"=>"26", "Fantasy-Youth"=>"34",
		"Files"=>"39", "Foreign Language"=>"7", "General Fiction"=>"33", "Historical Fiction"=>"32", "History"=>"24",
		"Horror"=>"27", "Literature "=>"25", "Mystery "=>"6", "Non-Fiction"=>"31", "Radio Drama"=>"36", "Romance"=>"17",
		"Science"=>"22", "Science Fiction "=>"4", "Self Improvement"=>"5", "Suspense"=>"28", "Talk Radio"=>"35", "Western"=>"37"
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.abtorrents.me';
		if($useGlobalCats)
			$categories = array( 'all'=>'0' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"ISO-8859-1"));
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/pages/torrents/browse.php?q='.$what.'&sort=seeders&dir=desc&nd=1&page='.$pg.'&cat='.$cat );

			if( ($cli==false) || (strpos($cli->results, ">No Results Found!<")!==false) 
				|| (strpos($cli->results, '>Password:<')!==false))
				break;

			$res = preg_match_all('`<a href="'.$url.'/pages/torrents/browse\.php\?cat=\d*"><img border="0" src="[^"]*" alt="(?P<cat>[^"]*)"'.
				'.*<a href="'.$url.'/pages/torrents/details\.php\?id=(?P<id>\d+)&hit=1">(?P<name>[^<]*)</a>'.
				'.*<td .*>.*</td>'.
				'.*<td .*>.*</td>'.
				'.*<td .*>.*</td>'.
				'.*<td class="row2">(?P<size>.*)</td>'.
				'.*<td .*>.*</td>'.
				'.*<td class="row2">(?P<seeds>.*)</td>'.
				'.*<td class="row2">(?P<leech>.*)</td>'.
				'.*<td class="row2"><acronym title="(?P<date>[^"]*)">'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/pages/torrents/download.php/".$matches["id"][$i]."/dummy.torrent";
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/pages/torrents/details.php?id=".$matches["id"][$i]."&hit=1";
						$item["name"] = self::toUTF(self::removeTags($matches["name"][$i]),"ISO-8859-1");
						$item["size"] = self::formatSize( $matches["size"][$i] );
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
