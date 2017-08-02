<?php

class NyaaEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>75 );

	public $categories = array(
		'All categories'=>'0_0',
		'> Anime'=>'1_0',
		'--A-- Anine Music Video'=>'1_1',
		'--A-- English-translated'=>'1_2',
		'--A-- Non-English-translated'=>'1_3',
		'--A-- Raw'=>'1_4',
		'> Audio'=>'2_0',
		'-- Lossless'=>'2_1',
		'-- Lossy'=>'2_2',
		'> Literature'=>'3_0',
		'--L-- English-translated'=>'3_1',
		'--L-- Non-English-translated'=>'3_2',
		'--L-- Raw'=>'3_3',
		'> Live Action'=>'4_0',
		'--LA-- English-translated'=>'4_1',
		'--LA-- Idol/Promotional Video'=>'4_2',
		'--LA-- Non-English-translated'=>'4_3',
		'--LA-- Raw'=>'4_4',
		'> Pictures'=>'5_0',
		'-- Graphics'=>'5_1',
		'-- Photos'=>'5_2',
		'> Software'=>'6_0',
		'-- Applications'=>'6_1',
		'-- Games'=>'6_2'
		);

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		return($client);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://nyaa.si';

		if($useGlobalCats)
			$categories = array(
			'all' => '0_0',
			'anime' => '1_0',
			'music' => '2_0',
			'books' => '3_0',
			'live action' => '4_0',
			'pictures' => '5_0',
			'software' => '6_1',
			'games' => '6_2'
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
			$search = $url . '/?c=' . $cat . '&q=' . $what . '&s=seeders&o=desc&p=' . $pg;
			$cli = $this->fetch($search);

			if (($cli == false) || (strpos($cli->results, ">No results found<") !== false))
				break;

			$res = preg_match_all('`<tr class.*>.*'.
				'<td.*>.*<a.*title="(?P<cat>.*)">.*'.
				'<td.*>.*<a href="/view/(?P<id>\d+)".*>(?P<name>.*)</a>.*'.
				'<td.*>.*<a href="(?P<link>magnet.*)">.*'.
				'<td.*>(?P<size>.*)</td>.*'.
				'<td.*>(?P<date>.*)</td>.*'.
				'<td.*>(?P<seeds>.*)</td>.*'.
				'<td.*>(?P<peers>.*)</td>'.
				'`siU',$cli->results,$matches);

			if ($res) {
				for ($i = 0; $i < $res; $i++) {
					$link = self::removeTags($matches['link'][$i]);
					if (!array_key_exists($link, $ret)) {
						$item = $this->getNewEntry();
						$item["desc"] = $url."/view/".$matches["id"][$i];
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]).' '."UTC");
						$item["name"] = self::toUTF(self::removeTags($matches["name"][$i]),"utf-8");
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["peers"][$i]));
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
