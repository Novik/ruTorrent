<?php
class YTSEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>75 );
	public $categories = array(
		"All" => "All",
		"720p" => "720p",
		"1080p" => "1080p",
		"2160p" => "2160p",
		"3D" => "3D",
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
			$cli = $this->fetch( 'https://yts.mx/api/v2/list_movies.json?query_term='.$what.'&quality='.$cat );
		if( $cli && ($obj = json_decode($cli->results)) && property_exists($obj,"data") )
		{
			for( $i=0; $i<$obj->data->movie_count; $i++ )
			{
				$movie = isset($obj->data->movies[$i]) ? $obj->data->movies[$i] : null;
				if( is_object($movie) && isset($movie->torrents) )
				{
					$torrent_count = count($movie->torrents);
					for( $j=0; $j<$torrent_count; $j++ )
					{
						$torrent = $movie->torrents[$j];
						if(is_object($torrent) && isset($torrent->url) && !array_key_exists($torrent->url, $ret))
						{
							$item = $this->getNewEntry();
							$item["cat"] = 'Video';
							$item["desc"] = $movie->url;
							$item["name"] = $torrent->quality . "." . $torrent->type . ": " .$movie->title_long;
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
}
