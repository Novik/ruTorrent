<?php

class ScCEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"www.sceneaccess.org|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'&cat=0', 'Apps' => '&cat=1', 'DOX' => '&cat=14', 'Games' => "&cat=3&cat=5&cat=24&cat=20&cat=28&cat=6&cat=23",
		'MiSC' => '&cat=21', 'Movies' => "&cat=8&cat=10&cat=22&cat=7", 'TV' => "&cat=27&cat=17&cat=11", 'XXX' => '&cat=12' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.sceneaccess.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0', 'movies'=>'&cat=8&cat=10&cat=22&cat=7', 
				'tv'=>'&cat=27&cat=17&cat=11', 'games'=>'&cat=3&cat=5&cat=24&cat=20&cat=28&cat=6&cat=23&cat=14', 
				'software'=>'&cat=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=seeders&d=DESC&page='.$pg.$cat );
			
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false)
				|| (strpos($cli->results, '>Password:</td>')!==false))
				break;
			$res = preg_match_all('/<img border="0" src=.* alt="(?P<cat>.*)" \/><\/a>'.
				'.*<a href="details.php\?id=(?P<id>\d+)&amp;hit=1".*>(?P<name>.*)<\/a>.*'.
				'<td .*>.*<\/td>.*<\/td>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>(?P<date>.*)<\/td>.*'.
				'<td .*>(?P<size>.*)<\/td>'.
				'.*<td .*>.*<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["id"])==count($matches["cat"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["seeds"])==count($matches["date"]) &&
				count($matches["date"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{

					$link = $url."/downloadbig.php?id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
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

?>