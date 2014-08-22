<?php

/*
 *@author Matt Porter
*/


class BitMeEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"www.bitme.org|pass=XXX;uid=XXX;" );
	public $categories = array( 'all'=>'', '3D'=>'20', 'AppDev'=>'1', 'Art'=>'14', 'Audio'=>'2', 'CBT'=>'3', 'Dating'=>'21', 
		'DIY'=>'29', 'Documentaries'=>'5', 'e-Books'=>'6', 'KeyStone'=>'7', 'Languages'=>'8', 'LearnKey'=>'9', 'Lynda.com'=>'10',
		'Magic'=>'19', 'Math'=>'30', 'Medical'=>'18', 'Misc'=>'11', 'Misc e-Learning'=>'12', 'Music Learning'=>'22',
		'Photography'=>'28', 'Political'=>'23', 'Religion'=>'24', 'Self Improvement'=>'25', 'SFX'=>'16', 'Sports'=>'26', 
		'PhotoStock'=>'17', 'Total Training'=>'13', 'TTC'=>'4', 'VideoStock'=>'27' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.bitme.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'');
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=seeders&d=DESC&incldead=0&page='.$pg."&cat=".$cat );
			if( ($cli==false) || (strpos($cli->results, "<h3>Nothing found!</h3>")!==false) ||
				(strpos($cli->results, "<tr><td><b>Password:</b></td>")!==false))
				break;
			$res = preg_match_all('/<img border="0" src=.* alt="(?P<cat>.*)" \/><\/a>'.
				'.*<a href="details.php\?id=(?P<id>\d+)&amp;hit=1".*>(?P<name>.*)<\/a><br><font size=1 color=999999><br>(?P<date>.*)<\/font>.*'.
				'<td .*>.*<a .*href="download.php\/\d+\/(?P<tname>.*)".*<\/a><\/td>.*'.
				'<td .*>.*<\/td>.*<td .*>.*<\/td>.*<td .*>.*<\/td>.*'.
				'<td .*>(?P<size>.*)<\/td>'.
				'.*<td .*>.*<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
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
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
						$tm = self::removeTags($matches["date"][$i]);
						if( (($pos1=strpos($tm,","))!==false) &&
						    (($pos2=strpos($tm," at"))!==false))
						    $item["time"] = strtotime(substr($tm,$pos1+2,$pos2-$pos1-2).substr($tm,$pos2+3));
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
