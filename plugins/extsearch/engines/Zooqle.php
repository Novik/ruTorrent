<?php
//I am not sure If I copied or used something from someone else

class ZooqleEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>30 );
	public $categories = array("all"=>"all",
                            "movies"=>"Movies",
                            "tv"=>"TV",
                            "music"=>"Music",
                            "games"=>"Games",
                            "anime"=>"Anime",
                            "software"=>"Apps",
                            "books"=>"Books",
                            "other"=>"Other");

	protected function parseTList($results,&$added,&$ret,$limit)
	{
	    $xmlDoc = new DOMDocument();
	    if($xmlDoc->loadXML($results))
		{
		    $x=$xmlDoc->getElementsByTagName('item');
		    $len = $x->count();
		    for ($i=0; $i<$len; $i++) {
                $name   =   $x->item($i)->getElementsByTagName('title')->item(0)->childNodes->item(0)->nodeValue;
                $link   =   $x->item($i)->getElementsByTagName('link')->item(0)->childNodes->item(0)->nodeValue;
                $desc   =   $x->item($i)->getElementsByTagName('description')->item(0)->childNodes->item(0)->nodeValue;
                $size   =   $x->item($i)->getElementsByTagName('contentLength')->item(0)->childNodes->item(0)->nodeValue;
                $seeds  =   $x->item($i)->getElementsByTagName('seeds')->item(0)->childNodes->item(0)->nodeValue;
                $peers  =   $x->item($i)->getElementsByTagName('peers')->item(0)->childNodes->item(0)->nodeValue;
                $infoHash=  $x->item($i)->getElementsByTagName('infoHash')->item(0)->childNodes->item(0)->nodeValue;
                $magnet = "magnet:?xt=urn:btih:".$infoHash."&dn=".$name."&".$this->tracker_list();

                if(!array_key_exists($magnet, $ret))
				{
					$item = $this->getNewEntry();
					//$item["cat"] = 'Video';
					$item["desc"] = $link;
					$item["name"] = $name;
					$item["size"] = $size;
					$item["seeds"] = $seeds;
					$item["peers"] = $peers;
					$ret[$magnet] = $item;
					$added++;
					if($added >= $limit)
						return false ;
            	}
            }
            return true;
		}
		else{
			return false;
		}
	}

	public function tracker_list()
	{
	    $tr_url="https://raw.githubusercontent.com/ngosang/trackerslist/master/trackers_best.txt";
        $cli = $this->fetch($tr_url);
        if($cli!==false)
        {
            $list = $cli->results;
            $list = urlencode(trim($list));
            $list = "tr=".$list;
            $list = str_replace("%0A%0A","&tr=",$list);
            return $list;
        }
        else
        {
            return false;
        }

	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
        	if($useGlobalCats)
			$categories = array
			(
				'all'=>'all',
				'movies'=>'Movies',
				'tv'=>'TV',
				'music'=>'Music',
				'games'=>'Games',
				'software'=>'Apps',
			 );
		else
			$categories = &$this->categories;

		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$added = 0;
		$url = 'https://zooqle.com/search?v=t&sd=d&fmt=rss&q='.$what.' category:'.$cat.'&pg=';
		$cli = $this->fetch($url."1");

		if($cli==false)
			return;

		$xmlDoc = new DOMDocument();
		$this->parseTList($cli->results,$added,$ret,$limit);
		if(($cli!==false) && $xmlDoc->loadXML($cli->results))
		{
	        //$total= $xmlDoc->getElementsByTagName('totalResults')->item(0)->childNodes->item(0)->nodeValue;
			//$max = ceil($total/$defaults["page_size"]);
			$max=10;
			for($pg = 2; $pg<$max; $pg++)
			{
				$cli = $this->fetch($url.$pg);
				if(($cli==false) || !$this->parseTList($cli->results,$added,$ret,$limit))
					break;
			}
		}
	}
}
