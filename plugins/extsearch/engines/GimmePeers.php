<?php

class GimmePeersEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"www.gimmepeers.com|pass=XXX;uid=XXX;" );
	public $categoryNames = array(
		'1' => 'Anime',
		'2' => 'App-MAC',
		'3' => 'Tutorials',
		'4' => 'App-WIN',
		'5' => 'Books (e)',
		'6' => 'Mobile',
		'7' => 'Music',
		'8' => 'Other',
		'9' => 'Game-NIN',
		'10' => 'Game-WIN',
		'11' => 'Game-PS',
		'12' => 'Game-XBOX',
		'13' => 'Movie-3D',
		'14' => 'Movie-Bluray',
		'15' => 'Movie-DVDR',
		'16' => 'Movie-x264',
		'17' => 'Movie-x265',
		'18' => 'Movie-Packs',
		'19' => 'Movie-XVID',
		'20' => 'TV-HD',
		'21' => 'TV-SD',
		'22' => 'TV-x265',
		'23' => 'TV-Packs',
		'24' => 'TV-Retail-SD',
		'25' => 'TV-Retail-HD',
		'26' => 'Movie-4K',
		'27' => 'App-LINUX',
		'28' => 'Sports',
		'29' => 'Books (a)',
		'49' => 'xXx-SD',
		'50' => 'xXx-HD'
	);
	public $categories = array( 'all'=>'',
		#misc
        '|--Misc' => '&c1=1&c8=1&c28=1&c3=1',
		'|--|--Anime' => '&c1=1',
		'|--|--Other' => '&c8=1',
		'|--|--Sports' => '&c28=1',
		'|--|--Tutorials' => '&c3=1',
		#Apps
		'|--Apps' => '&c27=1&c2=1&c4=1&c6=1',
		'|--|--App-LINUX' => '&c27=1',
		'|--|--App-MAC' => '&c2=1',
		'|--|--App-WIN' => '&c4=1',
		'|--|--App-Mobile' => '&c6=1',
		#books
        '|--Books' => '&c29=1&c5=1',
		'|--|--Books (a)' => '&c29=1',
		'|--|--Books (e)' => '&c5=1',
		#music
        '|--Music' => '&c7=1',
		#games
        '|--Games' => '&c9=1&c10=1&c11=1&c12=1',
		'|--|--Game-NIN' => '&c9=1',
		'|--|--Game-PS' => '&c11=1',
		'|--|--Game-WIN' => '&c10=1',
		'|--|--Game-XBOX' => '&c12=1',
		#Movies
        '|--Movies' => '&c13=1&c26=1&c14=1&c15=1&c18=1&c16=1&c17=1&c19=1',
		'|--|--Movie-3D' => '&c13=1',
		'|--|--Movie-4K' => '&c26=1',
		'|--|--Movie-Bluray' => '&c14=1',
		'|--|--Movie-DVDR' => '&c15=1',
		'|--|--Movie-Packs' => '&c18=1',
		'|--|--Movie-x264' => '&c16=1',
		'|--|--Movie-x265' => '&c17=1',
		'|--|--Movie-XVID' => '&c19=1',
		#tv
        '|--TV' => '&c20=1&c21=1&c25=1&c24=1&c23=1&c22=1',
		'|--|--TV-HD' => '&c20=1',
		'|--|--TV-SD' => '&c21=1',
		'|--|--TV-Retail-HD' => '&c25=1',
		'|--|--TV-Retail-SD' => '&c24=1',
		'|--|--TV-Packs' => '&c23=1',
		'|--|--TV-x265' => '&c22=1',
		#pr0n
        '|--pr0n' => '&c50=1&c49=1',
		'|--|--xXx-HD' => '&c50=1',
		'|--|--xXx-SD' => '&c49=1'
    );

    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.gimmepeers.com';
		if($useGlobalCats) {
		    error_log("torrentday: use global cats: " . $cat);
            $categories = array(
                'all' => '',
                'movies' =>
                    '&c13=1' .
					'&c26=1' .
					'&c14=1' .
					'&c15=1' .
					'&c18=1' .
					'&c16=1' .
					'&c17=1' .
					'&c19=1',
                'tv' =>
                    '&c20=1' .
					'&c21=1' .
					'&c25=1' .
					'&c24=1' .
					'&c23=1' .
					'&c22=1'
            ,
                'music' =>
                    '&c7=1',
                'games' =>
					'&c9=1' .
					'&c11=1' .
					'&c10=1' .
					'&c12=1',
                'anime' => '&c1=1',
                'software' => '&c27=1' . '&c2=1' . '&c4=1' . '&c6=1',
                'books' => '&c29=1' . '&c5=1',

            );
        } else {
		    error_log("gimmepeers uses local category " . $cat);
			$categories = &$this->categories;
        }

		if(!array_key_exists($cat,$categories)) {
			$cat = $categories['all'];
        } else {
		    $cat = $categories[$cat];
        }
		$catNames = &$this->categoryNames;
		for($pg = 0; $pg<10; $pg++)
		{
            $finalUrl = $url . '/browse.php?search=' . $what . '&sort=1&type=title&page=' . $pg . $cat;
			error_log("gimmepeers url: ".$finalUrl);
            $cli = $this->fetch($finalUrl);
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false) ||
				(strpos($cli->results, "<h1>Not logged in!</h1>")!==false))
				break;
			$data = $cli->results;
			
			$res = preg_match_all('`<td><a href="browse.php\?cat=(?P<cat>[^"]*)"><img.*'.
	        		'<td align=left>&nbsp;<a href="details.php\?id=(?P<id>.*)&amp;hit=1"><b.*'.
        			'<td align=center><a title=\'Download Link\' href="download\.php\/\d+\/(?P<tname>.*)\?passkey=(?P<passkey>.*)">.*down.png><\/a><\/td>.*'.
					'<td align=center><font class=lvpurple_text>&nbsp;(?P<size>.*)<\/font><\/td>.*'.
                	'<td align=center><nobr>&nbsp;(?P<date>.*)<\/nobr><\/td>.*'.
	                'filelist=1.*<td align=center>(?P<seeds>.*)</td>.*>(?P<leech>.*)</td>'.
	                '`siU', $cli->results, $matches);
			if($res)
			{
				error_log("gimmepeers res has data");
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i]."?passkey=".$matches["passkey"][$i];
					// check if the link already exists in the search results
					$catName = "GimmePeers";
					if (array_key_exists(self::removeTags($matches["cat"][$i]), $catNames))
					{
						$catName = $catNames[self::removeTags($matches["cat"][$i])];
					}
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = str_replace("|--", "", $catName);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["tname"][$i]);
						$item["size"] = self::formatSize(str_replace("&nbsp;<br>"," ",$matches["size"][$i]));
                        $clearDate = self::clearDate(str_replace("&nbsp;<br>"," ",$matches["date"][$i]));
                        $item["time"] = strtotime($clearDate);
						error_log("gimmepeers date match: " . $clearDate);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));

						// only add torrent if its still alive
						if($item["seeds"] > 0 || $item["peers"] > 0) {
                            $ret[$link] = $item;
                            $added++;
                            if($added>=$limit)
                                return;
                        }
					}
				}
			}
			else
				error_log("gimmepeers returned no matches");
				break;
		}
	}

	function clearDate($inputDate) {
        $result = $inputDate;
        // removes potential content before the date starts (at the moment, this is mainly 720p, 1080p, etc.)
        do {
            $pipePosition = strpos($result, '|');
            if($pipePosition !== FALSE) {
                $result = trim(substr($result, $pipePosition+1));
            }
        } while($pipePosition !== FALSE);
        return $result;
    }
}
