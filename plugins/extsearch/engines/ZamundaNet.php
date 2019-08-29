<?php

class ZamundaNetEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>20, "auth"=>1 );

	public $categories = array
	( 
		'all'=>'', 
		'Movies'=>'c42=1&c25=1&c35=1&c46=1&c20=1&c19=1&c5=1&c24=1&c31=1&c28=1&t=movie', 
		'--Bluray'=>'c42=1',
		'--Animation/Anime'=>"c25=1&t=animation",
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
		'--Dox'=>'c37=1&t=others'
	);

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		$client->referer = $this->search;
		return($client);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://zamunda.ch';
		if($useGlobalCats)
		{
			$categories = array
			( 
				'all'=>'', 
				'movies'=>'c42=1&c25=1&c35=1&c46=1&c20=1&c19=1&c5=1&c24=1&c31=1&c28=1', 
				'tv'=>'c7=1&c33=1', 
				'games'=>'c39=1&c4=1&c21=1&c17=1&c40=1&c12=1&c54=1',
				'software'=>'c38=1&c1=1&c22=1',
				'music'=>'c6=1&c30=1&c29=1&c51=1&c34=1'
			);
		}
		else
		{
			$categories = &$this->categories;
		}
		if(!array_key_exists($cat,$categories))
		{
			$cat = '&'.$categories['all'];
		}
		else
		{
			$cat = '&'.$categories[$cat];
		}
		for( $page = 0; $page < 10; $page++ )
		{ 
		        $this->search = "$url/bananas?search=$what&field=name&sort=9&type=desc$cat&page=$page";
		        $cli = $this->fetch($url."/bananas?view=list");
		        $res = preg_match_all('`'.
			        '<img border="0" src=".*" alt=\'(?P<cat>.*)\'.*'.
				'\/download\.php\/(?P<id>\d+)\/(?P<tname>.*)\.torrent.*'.
				"fbShare\('.*', '(?P<name>.*)'.*".
				"<nobr>(?P<date>.*)</nobr></td>.*".
				"<td align=center class='td_clear td_newborder'>(?P<size>.*)</td>.*".
				"td_newborder tdseeders'>(?P<seeds>.*)</td>.*".
				"td_newborder tdleechers'>(?P<peers>.*)</td>.*".
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i].".torrent";
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags( self::toUTF($matches["cat"][$i],"CP1251") );
						$item["desc"] = $url."/banan?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$size = str_replace("<br>"," ",$matches["size"][$i]);
						$item["size"] = self::formatSize($size);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$added++;
						if(($item["seeds"] == 0) || ($added>=$limit))
						{
							return;
						}
						$ret[$link] = $item;
					}
				}
			}
			else
				break;			
		}
	}
}