<?php

// Toloka.to support by ReMMeR github@r3mm3r.net

class TolokaEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>100 );
	public $categories = array( 'all'=>'' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$cli = $this->fetch( 'https://toloka.to/api.php?search='.$what );
		if( $cli && ($obj = json_decode($cli->results)) )
		{
			foreach( $obj as $torrent )
			{
				if( property_exists($torrent,"link") && !array_key_exists($torrent->link, $ret) )
				{	
					$item = $this->getNewEntry();
					$item["name"] = $torrent->title;
					$item["seeds"] = $torrent->seeders;
					$item["peers"] = $torrent->leechers;
					$item["size"] = self::formatSize($torrent->size);
					$item["desc"] = $torrent->link;
					$item["cat"] = $torrent->forum_name;
					$ret[$torrent->link] = $item;
					$added++;
					if($added >= $limit)
					return;
				}
			}
		}
	}
}
