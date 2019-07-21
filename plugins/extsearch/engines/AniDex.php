<?php

class AniDexEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>50 );

	public $categories = array(
		'All'=>'0',
		'> Anime'=>'1,2,3',
		'--A-- Sub'=>'1',
		'--A-- Raw'=>'2',
		'--A-- Dub'=>'3',
		'> Live Action'=>'4,5',
		'--LA-- Sub'=>'4',
		'--LA-- Raw'=>'5',
		'> Light Novel'=>'6',
		'> Manga'=>'7,8',
		'--M-- TLed'=>'7',
		'--M-- Raw'=>'8',
		'> Music'=>'9,10,11',
		'--♫-- Lossy'=>'9',
		'--♫-- Lossless'=>'10',
		'--♫-- Video'=>'11',
		'> Games'=>'12',
		'> Applications'=>'13',
		'> Pictures'=>'14',
		'> Adult Video'=>'15',
		'> Other'=>'16'
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://anidex.info';

		if($useGlobalCats)
			$categories = array(
			'all' => '0',
			'anime' => '1,2,3',
			'live action' => '4,5',
			'books' => '6,7,8',
			'music' => '9,10,11',
			'games' => '12',
			'software' => '13',
			'pictures' => '14'
			);
		else
			$categories = &$this->categories;

		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		$maxOffset = 450;

		for($offset = 0; $offset<=$maxOffset; $offset+=50)
		{
			$search = $url . '/?page=search&id=' . $cat . '&q=' . $what . '&s=seeders&o=desc&offset=' . $offset;
			$cli = $this->fetch($search);
			if (($cli == false) || (strpos($cli->results, "No torrents.</div>") !== false))
				break;
			$res = preg_match_all('`<td class="text-center">.*<div .*>(?P<cat>.*)</div></a><img .* title=\'(?P<lang>.*)\' /></td>.*'.
				'<a class="torrent" .* href="(?P<desc>.*)">.*<span .* title="(?P<name>.*)">.*</span>.*'.
				'<td class="text-center"><a href="(?P<link>.*)">.*<td .*>.*</td>.*'.
				'<td .*>(?P<size>.*)</td>.*'.
				'<td .* title="(?P<date>.*)">.*</td>.*'.
				'<td .*>(?P<seeds>.*)</td>.*'.
				'<td .*>(?P<peers>.*)</td>'.
				'`siU',$cli->results,$matches);

			if ($res) {
				for ($i = 0; $i < $res; $i++) {
					$link = $url.$matches['link'][$i];
					if (!array_key_exists($link, $ret)) {
						$item = $this->getNewEntry();
						$item["desc"] = $url.$matches["desc"][$i];
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
						$item["name"] = self::toUTF(self::removeTags($matches["name"][$i]),"utf-8");
						$item["size"] = self::formatSize(str_replace(",","",$matches["size"][$i]));
						$item["cat"] = self::removeTags($matches["cat"][$i].' | '.$matches["lang"][$i]);
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
