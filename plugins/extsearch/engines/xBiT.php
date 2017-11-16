<?php
class xBiTEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>100 );
	public $categories = array( 'all'=>'' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
        	$added = 0;
       		$cli = $this->fetch( 'https://xbit.pw/api?limit='.$limit.'&search='.$what );
		if( $cli && ($obj = json_decode($cli->results)) && property_exists($obj,"dht_results") )
		{
			foreach( $obj->dht_results as $torrent )
			{
				if( property_exists($torrent,"MAGNET") && !array_key_exists($torrent->MAGNET, $ret) )
				{
					$item = $this->getNewEntry();
					$item["name"] = $torrent->NAME;
					$sz = strval(floatval($torrent->SIZE));
					$item["size"] = self::formatSize($sz." ".substr($torrent->SIZE,strlen($sz)));
					$item["time"] = strtotime($torrent->DISCOVERED." +0200");
					$ret[$torrent->MAGNET] = $item;
					$added++;
					if($added >= $limit)
						return;
            			}
        		}
		}
	}
}
