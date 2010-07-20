<?php

class PlayTheNetEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>20, "cookies"=>"www.play-the.net|WebsiteID=XXX;" );

	public $categories = array( 
		'all' => array( 'sd'=>'', 'hd'=>'' ),
                'APPLICATION' => array( 'sd'=>'15', 'hd'=>'31' ),
		'DESSIN ANIME' => array( 'sd'=>'20', 'hd'=>'25' ),
		'DOCUMENTAIRE' => array( 'sd'=>'21', 'hd'=>'27' ),
		'MUSIQUES' => array( 'sd'=>'16', 'hd'=>'28' ),
		'EBOOK' => array( 'sd'=>'17', 'hd'=>null ),
		'IMAGES' => array( 'sd'=>'18', 'hd'=>null ),
		'INCLASSABLE' => array( 'sd'=>'19', 'hd'=>null ),
		'SPECTACLE' => array( 'sd'=>'10', 'hd'=>null ),
		'FILMS VO' => array( 'sd'=>'3', 'hd'=>null ),
		'JEUX' => array( 'sd'=>'14', 'hd'=>null ),
		'TELEVISION' => array( 'sd'=>'8', 'hd'=>null ),
		'PISTE SON' => array( 'sd'=>null, 'hd'=>'29' ),
		'SERIES TV VF' => array( 'sd'=>'12', 'hd'=>'26' ),
		'SERIES TV VOSTF' => array( 'sd'=>'13', 'hd'=>'32' ),
		'SERIE TV VO' => array( 'sd'=>'11', 'hd'=>null ),
		'CAM TS SCREENER' => array( 'sd'=>'1', 'hd'=>null ),
		'DVD SCREENER' => array( 'sd'=>'2', 'hd'=>null ),
		'DVDR' => array( 'sd'=>'7', 'hd'=>null ),
		'DVDRIP' => array( 'sd'=>'4', 'hd'=>null ),
		'DVDRIP VOSTFR' => array( 'sd'=>'5', 'hd'=>null ),
		'VHSRIP' => array( 'sd'=>'9', 'hd'=>null ),
		'R5' => array( 'sd'=>'6', 'hd'=>null ),
		'BD5/BD9' => array( 'sd'=>null, 'hd'=>'24' ),
		'FULL BLU RAY' => array( 'sd'=>null, 'hd'=>'30' ),
		'HD 1080P' => array( 'sd'=>null, 'hd'=>'23' ),
		'HD 720P' => array( 'sd'=>null, 'hd'=>'22' ) );


        public function actionPrim($what,$cat,&$ret,$limit,$addition)
	{
		$added = 0;
		$url = 'https://www.play-the.net';
		for($pg = 1; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/?order_by=seeders&order=DESC&exact=1&name='.$what.$addition.'&page='.$pg.'&parent_cat_id='.$cat );
			if( ($cli==false) || (strpos($cli->results, ">a retourné aucun résultat.</div>")!==false) ||
				(strpos($cli->results, 'type="password"')!==false))
				break;
                        $res = preg_match_all('/<ul class=".*">.*<li class="categories_parent_cat.*"><a href="\?section=.*"><img src="themes\/images\/CAT\/.*" alt="(?P<cat>.*)" \/><\/a><\/li>.*'.
				'<li class="torrents_name.*"><a href="\?section=INFOS&amp;id=(?P<id>.*)">(?P<name>.*)<\/a><\/li>.*'.
				'<li class="torrents_size.*">(?P<size>.*)<\/li>.*'.
				'<li class="torrents_seeders.*">(?P<seeds>.*)<\/li>.*'.
				'<li class="torrents_leechers.*">(?P<leech>.*)<\/li>.*<\/ul>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."?section=DOWNLOAD&id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."?section=INFOS&id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$ret[$link] = $item;
						$added++;
						if($added>=$limit)
							return($added);
					}
				}
			}
			else
				break;
		}
		return($added);
	}
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		if($useGlobalCats)
			$categories = array( 'all'=>array( 'sd'=>'', 'hd'=>'' ), 'music' => array( 'sd'=>'16', 'hd'=>'28' ), 'games'=>array( 'sd'=>'14', 'hd'=>null ),
				'anime'=>array( 'sd'=>'20', 'hd'=>'25' ), 'software'=>array( 'sd'=>'15', 'hd'=>'31' ), 
				'pictures'=>array( 'sd'=>'18', 'hd'=>null ), 'books'=>array( 'sd'=>'17', 'hd'=>null ) );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$added = 0;
		if( !is_null($cat['sd']) )
			$added = $this->actionPrim($what,$cat['sd'],$ret,$limit,'&section=TORRENTS');
		if( !is_null($cat['hd']) && ($added<$limit) )
			$this->actionPrim($what,$cat['hd'],$ret,$limit,'&section=TORRENTS_HD');
	}
}

?>