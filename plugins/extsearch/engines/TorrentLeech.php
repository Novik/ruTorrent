<?php

class TorrentLeechEngine extends commonEngine
{

	public $defaults = array( "public"=>false, "page_size"=>35, "auth"=>1 );

	public $categories = array( 'all'=>'',
		'Movies'=>'/categories/8,9,11,12,13,14,15,29,37,43,47',
		'TV'=>'/categories/26,27,32',
		'Games'=>'/categories/17,18,19,20,21,22,28,30,39,40,42,48',
		'Apps'=>'/categories/23,24,25,33',
		'Education'=>'/categories/38',
		'Animation'=>'/categories/34,35',
		'Books'=>'/categories/45,46',
		'Music'=>'/categories/16,31',
		'Foreign'=>'/categories/36,44'
		);

	protected static function getInnerCategory($cat)
	{
		$categories = array
		(
			'8'=>'Movies','9'=>'Movies','11'=>'Movies','12'=>'Movies','13'=>'Movies','14'=>'Movies','15'=>'Movies','29'=>'Movies','37'=>'Movies','43'=>'Movies','47'=>'Movies',
			'26'=>'TV','27'=>'TV','32'=>'TV',
			'17'=>'Games','18'=>'Games','19'=>'Games','20'=>'Games','21'=>'Games','22'=>'Games','28'=>'Games','30'=>'Games','39'=>'Games','40'=>'Games','42'=>'Games','48'=>'Games',
			'23'=>'Apps','24'=>'Apps','25'=>'Apps','33'=>'Apps',
			'38'=>'Education',
			'34'=>'Animation','35'=>'Animation',
			'45'=>'Books','46'=>'Books',
			'16'=>'Music','31'=>'Music',
			'36'=>'Foreign','44'=>'Foreign'
		);
		return(array_key_exists($cat,$categories) ? $categories[$cat] : '');
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.torrentleech.org';
		if($useGlobalCats)
		{
			$categories = array
			(
				'all'=>'',
				'movies'=>'/categories/8,9,11,12,13,14,15,29,36,37,43,47',
				'tv'=>'/categories/26,27,32,44',
				'music'=>'/categories/16,31',
				'games'=>'/categories/17,18,19,20,21,22,28,30,39,40,42,48',
				'anime'=>'/categories/34',
				'software'=>'/categories/23,24,25,33',
				'books'=>'/categories/45,46'
			);
		}
		else
		{
			$categories = &$this->categories;
		}
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for( $pg = 1; $pg < 11; $pg++ )
		{
			$cli = $this->fetch( Snoopy::linkencode($url.'/torrents/browse/list/query/'.$what.'/orderby/seeders/order/desc/page/'.$pg).$cat, false );
                        if( ($cli==false) ||
                        	!( $data = json_decode($cli->results) ) ||
                        	!property_exists($data,'torrentList') )
			{
				break;
			}
			foreach( $data->torrentList as $torrent )
			{
				$link = "$url/download/{$torrent->fid}/{$torrent->filename}";
				if(!array_key_exists($link,$ret))
				{
					$item = $this->getNewEntry();
					$item["cat"] = self::getInnerCategory($torrent->categoryID);
					$item["desc"] = "$url/torrent/{$torrent->fid}";
					$item["name"] = $torrent->name;
					$item["size"] = $torrent->size;
					$item["time"] = strtotime($torrent->addedTimestamp);
					$item["seeds"] = $torrent->seeders;
					$item["peers"] = $torrent->leechers;
					$ret[$link] = $item;
					$added++;
					if($added>=$limit)
						return;
				}
			}

			if( $pg * $data->perPage >= $data->numFound )
			{
				break;
			}
		}
	}
}
