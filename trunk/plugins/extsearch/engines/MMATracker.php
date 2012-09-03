<?php

/*
 @author Matt Porter
*/

class MMATrackerEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"mma-tracker.net|pass=XXX;uid=XXX;" );
	public $categories = array( 'all'=>'', 'Audio'=>'&c10=1', 'BigPacks'=>'&c83=1', 'Career'=>'&c84=1', 'Compilation'=>'&c47=1', 'Documentary'=>'&c11=1', 'E-Book'=>'&c12=1', 'Event'=>'&c7=1', 'Fight'=>'&c53=1', 'Highlight'=>'&c15=1', 'Instructional'=>'&c9=1', 'Other'=>'&c16=1', 'Promo'=>'&c40=1', 'TV Show'=>'&c8=1' );



	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://mma-tracker.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=7&&type=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false)
				|| (strpos($cli->results, "<label>Password:</label>")!==false))
				break;
			$res = preg_match_all("/<td class=tb-type><a href=.*>(?P<cat>.*)<\/a>".
				'.*<a href="details.php\?id=(?P<id>\d+)&amp;hit=1".*>(?P<name>.*)<\/a>.*'.
				'<script type=.*>.*checkDate = "(?P<date>\d+)".*<\/script>.*'.
				'<a class=linkdownlo .*href="download.php\/\d+\/(?P<tname>.*)">.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>(?P<size>.*)<\/td><td .*>.*<td .*>(?P<seeds>.*)<\/td>.*'.
				'<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["id"])==count($matches["cat"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["seeds"])==count($matches["date"]) &&
				count($matches["seeds"])==count($matches["tname"]) &&
				count($matches["date"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
							$sz = $matches["size"][$i];
							$sz = preg_replace('/(\d+)([A-Za-z])/', '\\1 \\2', $sz);
						$item["size"] = self::formatSize($sz);
						$item["time"] = substr($matches["date"][$i], 0, -3);
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
