<?php
class YTSEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>75 );
	public $categories = array( 'all'=>'' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
        	$added = 0;
       		$cli = $this->fetch( 'https://yts.ag/api/v2/list_movies.json?query_term='.$what );
		if( $cli && ($obj = json_decode($cli->results)) && property_exists($obj,"data") )
		{
			for( $i=0; $i<$obj->data->movie_count; $i++ )
			{
				$movie = $obj->data->movies[$i];
				$torrent_count = count($movie->torrents);
				for( $j=0; $j<$torrent_count; $j++ )
				{
					$torrent = $movie->torrents[$j];
					if(!array_key_exists($torrent->url, $ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = 'Video';
						$item["desc"] = $movie->url;
						$item["name"] = $torrent->quality.": ".$movie->title;
						$item["size"] = self::formatSize($torrent->size);
						$item["seeds"] = $torrent->seeds;
						$item["peers"] = $torrent->peers;
						$ret[$torrent->url] = $item;
						$added++;
						if($added >= $limit)
							return;
                			}
            			}
        		}
		}
	}
}
