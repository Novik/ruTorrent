<?php

class NyaaSukebeEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>30 );
	public $categories = array(
		'All categories'=>'0_0',
		'> Art'=>'7_0',
		'-- Anime'=>'7_25',
		'-- Doujinshi'=>'7_33',
		'-- Games'=>'7_27',
		'-- Manga'=>'7_26',
		'-- Pictures'=>'7_28',
		'> Reallife'=>'8_0',
		'-- Photobooks & Pictures'=>'8_31',
		'-- Videos'=>'8_30',
		 );

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		return($client);
	}
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://sukebei.nyaa.se';

		if($useGlobalCats)
			$categories = array(
			'all' => '0_0',
			'art' => '7_0',
			'reallife' => '8_0',
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
			#Example of research "Fairy Tail" in all categories page 2:
			#http://www.nyaa.se/?page=search&cats=0_0&term=fairy+tail&offset=2
			$search = $url . '/?page=search&cats=' . $cat . '&term=' . $what . '&offset=' . $pg;
			$cli = $this->fetch($search);

			if (($cli == false) || (strpos($cli->results, "No torrents found") !== false))
				break;

			#During the first loop only, one retrieve the number of pages. We look a the "go to last page" button.
			#If it exists, one retrieve the number of pages, otherwise it means there's only one page.
			if ( $pg == 1 )
			{
				preg_match('`<a class="page pagelink" href=".*offset=(?P<totalPage>\d*).*">((&gt;&gt;)|(&#62;&#62;)|(>>))</a>`',$cli->results,$matches);
				$maxPage = ( empty($matches["totalPage"]) ? 1 : $matches["totalPage"] );
			}

			$res = preg_match_all('`<tr.*>'.
				'<td.*><a.*title="(?P<cat>.*)">.*' . 
				'<td.*><a href="(?P<desc>.*)">(?P<name>.*)</a>.*' .
				'<td.*><a.*href="(?P<link>.*)".*>.*' .
				'<td.*>(?P<size>.*)</td>[^<]*' .
				'((<td[^>]*>(?P<noseedspeersinfo>Status unknown)</td>)'.
				'|'.
				'(<td.*>(?P<seeds>.*)</td>.*' .
				'<td.*>(?P<peers>.*)</td>)).*' .
				'</tr>`U',$cli->results,$matches);

			if ($res) {
				for ($i = 0; $i < $res; $i++) {
					$link = self::removeTags($matches['link'][$i]);
					if (strpos($link, '//') === 0)
						$link = 'http:' . $link;
					if (!array_key_exists($link, $ret)) {
						$item = $this->getNewEntry();
						$desc = self::removeTags($matches["desc"][$i]);
						if (strpos($desc, '//') === 0)
							$desc = 'http:' . $desc;
						$item["desc"] = $desc;
						$item["name"] = self::toUTF(self::removeTags($matches["name"][$i]),"utf-8");
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["cat"] = self::removeTags($matches["cat"][$i]);

						#Sometimes, number of peers/seeds are not given and the message "Status unknown" is showed instead.
						#We already handle it in the regexp above and also here.
						if ( empty($matches['noseedspeersinfo'][$i]) ) {
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

