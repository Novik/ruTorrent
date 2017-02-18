<?php

class TorrentDayEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"www.torrentday.com|pass=XXX;uid=XXX;" );

	public $categories = array( 'all'=>'',
        #misc
        '|--Misc' => '&c29=1&c28=1&c42=1&c20=1&c30=1&c47=1&c43=1',
        '|--|--Anime' => '&c29=1',
        '|--|--Appz/Packs' => '&c28=1',
        '|--|--Audio Books' => '&c42=1',
        '|--|--Books' => '&c20=1',
        '|--|--Documentary' => '&c30=1',
        '|--|--Freeleech' => '&free=on',
        '|--|--Fonts' => '&c47=1',
        '|--|--Mac' => '&c43=1',

        # movies
        '|--Movies' => '&c25=1&c11=1&c5=1&c3=1&c21=1&c22=1&c13=1&c44=1&c48=1&c1=1',
        '|--|--Movies/480p' => '&c25=1',
        '|--|--Movies/Bluray' => '&c11=1',
        '|--|--Movies/Bluray-Full' => '&c5=1',
        '|--|--Movies/DVD-R' => '&c3=1',
        '|--|--Movies/MP4' => '&c21=1',
        '|--|--Movies/Non-English' => '&c22=1',
        '|--|--Movies/Packs' => '&c13=1',
        '|--|--Movies/SD/x264' => '&c44=1',
        '|--|--Movies/x265' => '&c48=1',
        '|--|--Movies/XviD' => '&c1=1',
//        #music
        '|--Music' =>  '&c23=1&c41=1&c16=1&c45=1',
        '|--|--Music/Non-English' => '&c23=1',
        '|--|--Music/Packs' => '&c41=1',
        '|--|--Music/Video' => '&c16=1',
        '|--|--Podcast' => '&c45=1',
        # games
        '|--Games' => '&c4=1&c18=1&c8=1&c10=1&c9=1',
        '|--|--PC/Games' => '&c4=1',
        '|--|--PS3' => '&c18=1',
        '|--|--PSP' => '&c8=1',
        '|--|--Wii' => '&c10=1',
        '|--|--Xbox-360' => '&c9=1',

        #tv
        '|--TV' => '&c24=1&c32=1&c31=1&c33=1&c46=1&c14=1&c26=1&c7=1&c34=1&c2=1',
        '|--|--TV/480p' => '&c24=1',
        '|--|--TV/Bluray' => '&c32=1',
        '|--|--TV/DVD-R' => '&c31=1',
        '|--|--TV/DVD-Rip' => '&c33=1',
        '|--|--TV/Mobile' => '&c46=1',
        '|--|--TV/Packs' => '&c14=1',
        '|--|--TV/SD/x264' => '&c26=1',
        '|--|--TV/x264' => '&c7=1',
        '|--|--TV/x265' => '&c34=1',
        '|--|--TV/XviD' => '&c2=1',

        #pr0n
        '|--XXX' => '&c6=1&c15=1',
        '|--|--XXX/Movies' => '&c6=1',
        '|--|--XXX/Packs' => '&c15=1'
    );

    public function getPattern() {
        return '/<tr class="browse">.*href="browse.php\?cat=(?P<cat>\d+).*"><img border="0".*'.
            '<td class="torrentNameInfo".*class=\'torrentName\' href=\'details.php\?id=(?P<id>\d+)\'>(?P<name>.*)<\/a>.*<\/div>'.
            '.*<span class="ulInfo">(?P<date>.*)<\/span><td class="dlLinksInfo".*' .
            '<a class="index" href="download\.php\/\d+\/(?P<tname>.*)">.*<\/td><td class=.*<\/td>.*'.
            '<td class="sizeInfo".*><a.*>(?P<size>.*)<\/a><\/td>.*'.
            '<td class=".*seedersInfo">(?P<seeds>.*)<\/td>.*'.
            '<td class=".*leechersInfo">(?P<leech>.*)<\/td>/siU';
    }


    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.torrentday.com';
		if($useGlobalCats) {
		    error_log("torrentday: use global cats: " . $cat);
            $categories = array(
                'all' => '',
                'movies' =>
                    '&c25=1' .
                    '&c11=1' .
                    '&c5=1' .
                    '&c3=1' .
                    '&c21=1' .
                    '&c22=1' .
                    '&c13=1' .
                    '&c44=1' .
                    '&c48=1' .
                    '&c1=1',
                'tv' =>
                    '&c24=1' .
                    '&c32=1' .
                    '&c31=1' .
                    '&c33=1' .
                    '&c46=1' .
                    '&c14=1' .
                    '&c26=1' .
                    '&c7=1' .
                    '&c34=1' .
                    '&c2=1'
            ,
                'music' =>
                    '&c23=1' .
                    '&c41=1' .
                    '&c16=1' .
                    '&c45=1',
                'games' =>
                    '&c4=1' .
                    '&c18=1' .
                    '&c8=1' .
                    '&c10=1' .
                    '&c9=1',
                'anime' => '&c29=1',
                'software' => '&c28=1' . '&c47=1' . '&c43=1',
                'books' => '&c42=1' . '&c20=1' . '&c30=1',

            );
        } else {
		    error_log("torrentday uses local category " . $cat);
			$categories = &$this->categories;
        }

		if(!array_key_exists($cat,$categories)) {
			$cat = $categories['all'];
        } else {
		    $cat = $categories[$cat];
        }
		for($pg = 0; $pg<10; $pg++)
		{
            $finalUrl = $url . '/browse.php?search=' . $what . '&sort=6&type=desc&page=' . $pg . $cat;
            $cli = $this->fetch($finalUrl);
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false) ||
				(strpos($cli->results, "<h1>Not logged in!</h1>")!==false))
				break;

			$data = $cli->results;

			$res = preg_match_all($this->getPattern(), $data, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					// check if the link already exists in the search results
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
                        $clearDate = self::clearDate(self::removeTags($matches["date"][$i]));
                        $item["time"] = strtotime($clearDate);
						error_log("torrentday date match: " . $clearDate);
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
