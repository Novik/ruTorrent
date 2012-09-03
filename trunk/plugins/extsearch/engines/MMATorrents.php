<?php

class MMATorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"mma-torrents.com|uid=XXX;pass=XXX;" );
	public $categories = array(
		'all'=>'&cat=69','Audio Learning'=>'&cat=93','Audio Radio'=>'&cat=92','Bellator All'=>'&cat=89','Boxing All'=>'&cat=71',
      		'Classic MMA All'=>'&cat=61','Documentary MMA'=>'&cat=86','DREAM Events'=>'&cat=102','DREAM Retro'=>'&cat=68','HD HD/x264 Bellator'=>'&cat=109',
		'HD HD/x264 Boxing'=>'&cat=105','HD HD/x264 DREAM'=>'&cat=108','HD HD/x264 K-1'=>'&cat=99','HD HD/x264 KOTC'=>'&cat=106','HD HD/x264 Packs'=>'&cat=91',
		'HD HD/x264 Strikeforce'=>'&cat=100','HD HD/x264 TUF'=>'&cat=97','HD HD/x264 TV-Show'=>'&cat=101','HD HD/x264 UFC'=>'&cat=96','HD HD/x264 Unsorted'=>'&cat=70',
      		'HD HD/x264 WEC'=>'&cat=98','K-1 Events'=>'&cat=107','K-1 Retro'=>'&cat=72','Kickboxing All'=>'&cat=84','KOTC All'=>'&cat=78',
		'Learning E-book'=>'&cat=73','Learning Fitness'=>'&cat=83','Learning Technique'=>'&cat=60','M-1 Challenge'=>'&cat=80','M-1 Global'=>'&cat=90',
       		'Magazines MMA'=>'&cat=111','Misc All'=>'&cat=58','MMA Events Other Events'=>'&cat=47','Movies MMA Related'=>'&cat=62','Muay Thai All'=>'&cat=77',
		'Packs Career'=>'&cat=110','Packs MMA'=>'&cat=64','Pride All'=>'&cat=66','Pride Packs'=>'&cat=95','Sengoku All'=>'&cat=65',
		'Special Misc'=>'&cat=63','Strikeforce All'=>'&cat=81','TUF Episodes'=>'&cat=104','TUF Packs'=>'&cat=59','TV Show HDNet Shows'=>'&cat=52',
		'TV Show InsideMMA'=>'&cat=50','TV Show MMA Live'=>'&cat=85','TV Show Other Shows'=>'&cat=94','UFC All'=>'&cat=103','UFC Retro'=>'&cat=67'
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://mma-torrents.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=69' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"ISO-8859-1"));
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?search='.$what.'&incldead=0&sort=seeders&order=desc&page='.$pg.$cat );

			if( ($cli==false) || (strpos($cli->results, "Nothing Found</font>")!==false) 
				|| (strpos($cli->results, '><b>Password')!==false))
				break;
			$res = preg_match_all('/<img border="0"src="http\:\/\/mma\-torrents\.com\/images\/categories\/.*" alt="(?P<cat>[^"]*)"'.
				'.*href="torrents-details\.php\?id=(?P<id>.*)"><b>(?P<name>.*)<\/b>.*<BR><B>Size<\/B>: (?P<size>.*)<BR><B>Speed:<\/B>.*<BR><B>Added:<\/B> (?P<date>.*)<BR>'.
				'.*href="download\.php\?id=(?P<tname>[^"]*)">'.
				'.*<td .*>.*<\/td>'.
				'.*<td .*>.*<\/td>.*'.
				'.*<td .*>.*<\/td>'.
				'.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php?id=".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrents-details.php?id=".self::removeTags($matches["id"][$i]);
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
