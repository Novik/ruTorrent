<?php

class NyaaEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>30 );
	public $categories = array(
		'All categories'=>'0_0',
		'Anime'=>'1_0',
		'Anime - Anime Music Video'=>'1_32',
		'Anime - English-translated Anime'=>'1_37',
		'Anime - Non-English-translated Anime'=>'1_38',
		'Anime - Raw Anime'=>'1_11',
		'Audio'=>'3_0',
		'Audio - Lossless Audio'=>'3_14',
		'Audio - Lossy Audio'=>'3_15',
		'Literature'=>'2_0',
		'Literature - English-translated Literature'=>'2_12',
		'Literature - Non-English-translated Literature'=>'2_39',
		'Literature - Raw Literature'=>'2_13',
		'Live Action'=>'5_0',
		'Live Action - English-translated Live Action'=>'5_19',
		'Live Action - Live Action Promotional Video'=>'5_22',
		'Live Action - Non-English-translated Live Action'=>'5_21',
		'Live Action - Raw Live Action'=>'5_20',
		'Pictures'=>'4_0',
		'Pictures - Graphics'=>'4_18',
		'Pictures - Photos'=>'4_17',
		'Software'=>'6_0',
		'Software - Applications'=>'6_23',
		'Software - Games'=>'6_24',
		 );

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		return($client);
	}
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.nyaa.se';

		if($useGlobalCats)
			$categories = array(
			'all' => '0_0',
			'movies' => '1_0',
			'music' => '3_0',
			'software' => '6_0',
			'books' => '2_0'
			);
		else
			$categories = &$this->categories;

		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		

		$maxPage = 10;
		for($pg = 1; $pg<=$maxPage; $pg++)
		{
			#Example of research "Fairy Tail" in all category page 2:
			#http://www.nyaa.se/?page=search&cats=0_0&term=fairy+tail&offset=2
			$search = $url . '/?page=search&cats=' . $cat . '&term=' . $what . '&offset=' . $pg;
			$cli = $this->fetch($search);

			if (($cli == false) || (strpos($cli->results, "No torrents found") !== false))
				break;

			#During the first loop only, we retrieve the number of pages. We look a the "go to last page" button.
			#If it exists, we can retrieve the number of pages, otherwise it means there's only one page.
			if ( $pg == 1 )
			{
				preg_match('`<a class="page pagelink" href=".*offset=(?P<totalPage>\d*).*">((&gt;&gt;)|(&#62;&#62;))</a>`',$cli->results,$matches);
				$maxPage = ( empty($matches["totalPage"]) ? 1 : $matches["totalPage"] );
			}

			$res = preg_match_all('`<tr.*>'.
				'<td.*><a.*title="(?P<cat>.*)">.*' . 
				'<td.*><a href="(?P<desc>.*)">(?P<name>.*)</a>.*' .
				'<td.*><a.*href="(?P<link>.*)".*>.*' .
				'<td.*>(?P<size>.*)</td>[^<]*' .
				'((<td[^>]*>(?P<no>Status unknown)</td>)'.
				'|'.
				'(<td.*>(?P<seeds>.*)</td>.*' .
				'<td.*>(?P<peers>.*)</td>)).*' .
				'</tr>`U',$cli->results,$matches);

			if ($res) {
				for ($i = 0; $i < $res; $i++) {
					$link = self::removeTags($matches['link'][$i]);
					if (!array_key_exists($link, $ret)) {
						$item = $this->getNewEntry();
						$item["desc"] = self::removeTags($matches["desc"][$i]);
						$item["name"] = self::toUTF(self::removeTags($matches["name"][$i]),"utf-8");
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));

						#Sometimes, number of peers/seeds are not given and the message "Status unknown" is printed instead.
						#We already handle it in the regexp above and also here.
						if ( empty($matches['no'][$i]) ) {
							$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
							$item["peers"] = intval(self::removeTags($matches["peers"][$i]));
						}
						else
							$item["seeds"] = $itme["peers"] = -1;
						
						$ret[$link] = $item;
						$added++;
						if ($added >= $limit)
							return;
					}
				}
			} else
				break;
		}
	}
}

