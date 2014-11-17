<?php

/*
 @author AceP1983
*/

class MMATrackerEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"www.mma-tracker.net|pass=XXX;uid=XXX;" );
	public $categories = array( 'all'=>'', 'Audio'=>'&c21=1', 'BigPacks'=>'&c33=1', 'Career'=>'&c43=1', 'Compilation'=>'&c44=1', 'Documentary'=>'&c41=1', 'E-Book'=>'&c39=1', 'Event'=>'&c2=1', 'Fight'=>'&c34=1', 'Highlight'=>'&c45=1', 'Instructional'=>'&c5=1', 'Interview'=>'&c46=1', 'Other'=>'&c27=1', 'Promo'=>'&c20=1', 'TV Show'=>'&c6=1', 'Weigh-ins'=>'&c47=1' );



	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.mma-tracker.net';
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
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&incldesc=0&sort=7&type=desc&page='.$pg.$cat.'#tabletop' );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false)
				|| (strpos($cli->results, '>Password:<')!==false))
				break;
			$res = preg_match_all('/<a href="browse.php\?tag1=.*">(?P<cat>.*)<\/a>'.
				'.*<a style=.* href="details.php\?id=(?P<id>\d+)&amp;hit=1".*>(?P<name>.*)<\/a>.*'.
                '<div .*><\/div><div .*>.*<\/div>.*'.
                '<div .*>(?P<leech>.*)<\/div>.*'.
                '<div .*>(?P<seeds>.*)<\/div>.*'.
                '<div .*>.*<\/div><div .*>(?P<size>.*)<\/div>.*'.
                '<div .*>.*<\/div>.*'.
                '<a href="download.php\/\d+\/(?P<tname>.*)">/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
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
