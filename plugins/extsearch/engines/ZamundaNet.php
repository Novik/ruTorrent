<?php

class ZamundaNetEngine extends commonEngine
{
    public $defaults = array( "public"=>false, "page_size"=>20, "cookies"=>"zamunda.ch|pass=XXX;uid=XXX" );

	public $categories = array( 'all'=>'', 
				'Movies'=>'c42=1&c25=1&c35=1&c46=1&c20=1&c19=1&c5=1&c24=1&c31=1&c28=1&t=movie', 
				'--Bluray'=>'c42=1',
				'--Animation/Anime'=>"c25=1",
				'--Video/HD'=>'c35=1',
				'--Movies/3D'=>'c46=1',
				'TV/Serial'=>'c7=1&c33=1&t=tv', 
				'Games'=>'c39=1&c4=1&c21=1&c17=1&c40=1&c12=1&c54=1&t=game',
				'--Games/Mac'=>'c39=1&t=game',
                '--Games/PC ISO'=>'c4=1&t=game',
                '--Games/PC Rip'=>'c21=1&t=game',
                '--Games/PS'=>'c17=1&t=game',
                '--Games/Xbox'=>'c40=1&t=game',
                '--Games/Console'=>'c12=1&t=game',
                '--Games/Linux'=>'c54=1&t=game',
				'--Software'=>'c38=1&c1=1&c22=1&t=others',
				'Music'=>'c6=1&c30=1&c29=1&c51=1&c34=1&t=music',
				'--Music'=>'c6=1&t=music',
				'--DTS'=>'c30=1&t=music',
				'--DVD-R'=>'c29=1&t=music',
				'--Hi-Res/Vinyl'=>'c51=1&t=music',
				'--Lossless'=>'c34=1&t=music',
			    'Other'=>'c36=1&c52=1&c53=1&c26=1&c23=1&c32=1&c37=1&t=others',
			    '--Mobile/GSM'=>'c36=1&t=others',
			    '--Android/Games'=>'c52=1&t=others',
			    '--Android/Apps'=>'c53=1&t=others',
			    '--Other'=>'c26=1&t=others',
			    '--Clips'=>'c23=1&t=others',
			    '--Books/Comic'=>'c32=1&t=others',
			    '--Dox'=>'c37=1&t=others');
				
    protected function parseTList($results,&$added,&$ret,$limit)
	{

	    $url = 'http://zamunda.ch';
        $re1 = '/<tr onmouseover[^>]*>[\s\S]*?<\/tr>/';
		$res1 = preg_match_all($re1, $results, $matches);
		
		if($res1 && strpos($results,"nothing found")===false)
		{
		    $re2 = '/(?P<link>\/download\.php\/(?P<id>\d*)\/(?P<name>.*)\.torrent)[\s\S]*?(?P<date>\d\d\d\d-\d\d-\d\d)<br.*(?P<time>\d\d:\d\d:\d\d)<\/nobr>.*\s<td [^>]*>(?P<size>.*<br>[K|G|M|T]B).*\s.*times<\/td>\s.*<td [^>]*>.*>(?P<seeders>[0-9]{1,})<.*\s.*<td [^>]*>.*(?P<peers>[0-9]{1,})/m';
			for($i=0; $i<$res1; $i++)
			{
			    $str = $matches[0][$i];

                $res2 = preg_match($re2, $str, $matches2);
                $link = $url.$matches2["link"];
				if(isset($ret[$link])===false)
				{
					$item = $this->getNewEntry();
					$item["cat"] = $matches2["cat"];
					$item["desc"] = "http://zamunda.ch/banan?id=".$matches2["id"];
					$item["name"] = urldecode($matches2["name"]);
					$item["size"] = self::formatSize(str_replace("<br>"," ",$matches2["size"]));
					$item["time"] = $matches2["date"]. " ". $matches2["time"];
					$item["seeds"] = intval($matches2["seeds"]);
					$item["peers"] = intval($matches2["peers"]);
					$ret[$link] = $item;
					$added++;
					if($added>=$limit)
					    return(false);
				}
			}
			return(true);
		}
		else
			return(false);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://zamunda.ch';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 
				'movies'=>'c42=1&c25=1&c35=1&c46=1&c20=1&c19=1&c5=1&c24=1&c31=1&c28=1', 
				'tv'=>'c7=1&c33=1', 
				'games'=>'c39=1&c4=1&c21=1&c17=1&c40=1&c12=1&c54=1',
				'software'=>'c38=1&c1=1&c22=1',
				'music'=>'c6=1&c30=1&c29=1&c51=1&c34=1');
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = '&'.$categories['all'];
		else
			$cat = '&'.$categories[$cat];
        $url_ =   $url.'/bananas?search='.$what.'&field=name'.$cat;

        $this->fetch($url."/langchange.php?lang=en"); //to switch english language
        $this->fetch($url."/bananas?view=list"); //for list view
		$cli = $this->fetch( $url_);
        $parseStatus = $this->parseTList($cli->results,$added,$ret,$limit);
		if($cli!==false && $parseStatus!==false)
		{
		   $res = preg_match('/<a href=\"(.*page=)1\".*class=\"gotonext\">/m', $cli->results, $last);
		   $pagelink = html_entity_decode($last[1]);
		   for($pg=1;$cli!==false;$pg++)
		   {
		        if($parseStatus===false)
                    break;
                $full_url = $url.$pagelink.$pg;
                $cli = $this->fetch($full_url);
                $parseStatus = $this->parseTList($cli->results,$added,$ret,$limit);
		   }
		}
	}


}
?>