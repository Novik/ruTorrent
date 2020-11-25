<?php

class MyAnonamouseEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>20, "auth"=>"1" );
	public $categories = array
	( 
		'all'=>'&tor[cat][]=0', 
		'AudioBooks'=>'&tor[cat][]=39&tor[cat][]=49&tor[cat][]=50&tor[cat][]=83&tor[cat][]=51&tor[cat][]=97&tor[cat][]=40&tor[cat][]=41&tor[cat][]=106&tor[cat][]=42&tor[cat][]=52&tor[cat][]=98&tor[cat][]=54&tor[cat][]=55&tor[cat][]=43&tor[cat][]=99&tor[cat][]=84&tor[cat][]=44&tor[cat][]=56&tor[cat][]=137&tor[cat][]=45&tor[cat][]=57&tor[cat][]=85&tor[cat][]=87&tor[cat][]=119&tor[cat][]=88&tor[cat][]=58&tor[cat][]=59&tor[cat][]=46&tor[cat][]=47&tor[cat][]=53&tor[cat][]=89&tor[cat][]=100&tor[cat][]=108&tor[cat][]=48&tor[cat][]=111&tor[cat][]=126&tor[cat][]=0',
		'E-Books'=>'&tor[cat][]=60&tor[cat][]=71&tor[cat][]=72&tor[cat][]=90&tor[cat][]=61&tor[cat][]=73&tor[cat][]=101&tor[cat][]=62&tor[cat][]=63&tor[cat][]=107&tor[cat][]=64&tor[cat][]=74&tor[cat][]=102&tor[cat][]=76&tor[cat][]=77&tor[cat][]=65&tor[cat][]=103&tor[cat][]=115&tor[cat][]=91&tor[cat][]=66&tor[cat][]=78&tor[cat][]=138&tor[cat][]=67&tor[cat][]=79&tor[cat][]=80&tor[cat][]=92&tor[cat][]=118&tor[cat][]=94&tor[cat][]=120&tor[cat][]=95&tor[cat][]=81&tor[cat][]=82&tor[cat][]=68&tor[cat][]=69&tor[cat][]=75&tor[cat][]=96&tor[cat][]=104&tor[cat][]=109&tor[cat][]=70&tor[cat][]=112&tor[cat][]=0',
		'Musicology'=>'&tor[cat][]=17&tor[cat][]=19&tor[cat][]=20&tor[cat][]=24&tor[cat][]=22&tor[cat][]=113&tor[cat][]=114&tor[cat][]=122&tor[cat][]=26&tor[cat][]=27&tor[cat][]=30&tor[cat][]=31&tor[cat][]=35&tor[cat][]=0',
		'Radio'=>'&tor[cat][]=127&tor[cat][]=130&tor[cat][]=128&tor[cat][]=132&tor[cat][]=0',
	);		

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.myanonamouse.net';

		if($useGlobalCats)
			$categories = array
			( 
				'all'=>'&tor[cat][]=0',
				'tv'=>'&tor[cat][]=127&tor[cat][]=130&tor[cat][]=128&tor[cat][]=132&tor[cat][]=0', 
				'music'=>'&tor[cat][]=17&tor[cat][]=19&tor[cat][]=20&tor[cat][]=24&tor[cat][]=22&tor[cat][]=113&tor[cat][]=114&tor[cat][]=122&tor[cat][]=26&tor[cat][]=27&tor[cat][]=30&tor[cat][]=31&tor[cat][]=35&tor[cat][]=0', 
				'books'=>'&tor[cat][]=60&tor[cat][]=71&tor[cat][]=72&tor[cat][]=90&tor[cat][]=61&tor[cat][]=73&tor[cat][]=101&tor[cat][]=62&tor[cat][]=63&tor[cat][]=107&tor[cat][]=64&tor[cat][]=74&tor[cat][]=102&tor[cat][]=76&tor[cat][]=77&tor[cat][]=65&tor[cat][]=103&tor[cat][]=115&tor[cat][]=91&tor[cat][]=66&tor[cat][]=78&tor[cat][]=138&tor[cat][]=67&tor[cat][]=79&tor[cat][]=80&tor[cat][]=92&tor[cat][]=118&tor[cat][]=94&tor[cat][]=120&tor[cat][]=95&tor[cat][]=81&tor[cat][]=82&tor[cat][]=68&tor[cat][]=69&tor[cat][]=75&tor[cat][]=96&tor[cat][]=104&tor[cat][]=109&tor[cat][]=70&tor[cat][]=112&tor[cat][]=39&tor[cat][]=49&tor[cat][]=50&tor[cat][]=83&tor[cat][]=51&tor[cat][]=97&tor[cat][]=40&tor[cat][]=41&tor[cat][]=106&tor[cat][]=42&tor[cat][]=52&tor[cat][]=98&tor[cat][]=54&tor[cat][]=55&tor[cat][]=43&tor[cat][]=99&tor[cat][]=84&tor[cat][]=44&tor[cat][]=56&tor[cat][]=137&tor[cat][]=45&tor[cat][]=57&tor[cat][]=85&tor[cat][]=87&tor[cat][]=119&tor[cat][]=88&tor[cat][]=58&tor[cat][]=59&tor[cat][]=46&tor[cat][]=47&tor[cat][]=53&tor[cat][]=89&tor[cat][]=100&tor[cat][]=108&tor[cat][]=48&tor[cat][]=111&tor[cat][]=126&tor[cat][]=0',
			);
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		$PER_PAGE_ENTRIES = 100;

		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/tor/js/loadSearchJSONbasic.php?tor[text]='.$what.
				'&dlLink&tor[searchIn]=torrents&tor[sortType]=seedersDesc&tor[searchType]=all&tor[searchIn]=torrents&perpage='.$PER_PAGE_ENTRIES.
				$cat.'&tor[startNumber]='.($pg*$PER_PAGE_ENTRIES) );
			if( !$cli )
				break;
        		$res = json_decode( $cli->results );
			if($res && property_exists($res,"data") && count($res->data))
			{
				foreach($res->data as $torrent)
				{
					$link = $url.'/tor/download.php/'.$torrent->dl;
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url.'/t/'.$torrent->id;
						$item["name"] = $torrent->title;
						$item["size"] = self::formatSize($torrent->size);
						$item["time"] = strtotime($torrent->added);
						$item["seeds"] = $torrent->seeders;
						$item["peers"] = $torrent->leechers;
						$item["cat"] = $torrent->catname;
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
