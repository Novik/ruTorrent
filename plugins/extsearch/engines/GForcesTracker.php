<?php

/*
 *@author Matt Porter
*/


class GForcesTrackerEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"www.thegft.org|c_secure_pass=XXX;c_secure_uid=XXX;c_secure_login=XXX;" );
	public $categories = array( 'all'=>'&cat=0', '0DAY'=>'&cat=2', 'Anime'=>'&cat=16', 'APPS'=>'&cat=1', 'Carrib Corner'=>'&cat=33', 'E-Learning'=>'&cat=9', 'Games/NDS'=>'&cat=32', 'Games/PC'=>'&cat=6', 'Games/PS2'=>'&cat=34', 'Games/PS3'=>'&cat=35', 'Games/PSP'=>'&cat=29', 'Games/WII'=>'&cat=23', 'Games/XBOX360'=>'&cat=12', 'GFT Gems'=>'&cat=15', 'MAC'=>'&cat=27', 'Misc'=>'&cat=11', 'Movies-DVDR'=>'&cat=8', 'Movies-X264'=>'&cat=18', 'Movies-XVID'=>'&cat=7', 'Music'=>'&cat=5', 'MVID'=>'&cat=13', 'Mobile/PDA'=>'&cat=26', 'TV-DVDRIP'=>'&cat=19', 'TV-X264'=>'&cat=17', 'TV-XVID'=>'&cat=4', 'XXX-0DAY'=>'&cat=22', 'XXX-DVDR'=>'&cat=25', 'XXX-HD'=>'&cat=20', 'XXX-XVID'=>'&cat=3' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.thegft.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&blah=0&sort=7&type=desc&page='.$pg.$cat );
			
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false) ||
				(strpos($cli->results, ">Password:</td>")!==false))
				break;
			$res = preg_match_all('/<img border="0" src=.* alt="(?P<cat>.*)" \/><\/a>'.
				'.*<td .*>.*href="details.php\?id=(?P<id>\d+)&hit=1".*>(?P<name>.*)<\/a>.*'.
				'<td .*>.*href="download.php\?id=\d+&name=(?P<tname>.*)">.*'.
				'<td .*>.*<\/td>.*<td .*>.*<\/td>.*'.
				'<td .*>(?P<seeds>.*)<\/td>.*'.
				'<td .*>(?P<leech>.*)<\/td>.*'.
				'<td .*>(?P<date>.*)<\/td>.*'.
				'<td .*>(?P<size>.*)<\/td>/siU', $cli->results, $matches);
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
					$link = $url."/download.php?id=".$matches["id"][$i]."&name=".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
						$sz = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
						$item["size"] = self::removeTags($sz);
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
