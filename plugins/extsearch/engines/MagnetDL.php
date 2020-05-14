<?php

class MagnetDLEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>40 );

	public $categories = array( 'All'=>'' );

	protected static $seconds = [
		'min'	=> 60,
		'hour'	=> 3600,
		'day'	=> 86400,
		'month'	=> 2592000,
		'year'	=> 31536000
	];

	protected static function getTime($now,$ago,$unit)
	{
		$delta = (array_key_exists($unit,self::$seconds) ? self::$seconds[$unit] : 0);
		return ($now - ($ago * $delta));
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.magnetdl.com';
		$now = time();

		// Search engine requires all lowercase
		$what = function_exists('mb_strtolower')
			? mb_strtolower(urldecode($what), 'utf-8')
			: strtolower(urldecode($what));

		// Search engine doesn't understand special characters
		$what = preg_replace("/^[^a-z0-9]+/", "", $what);

		// Search engine requires "-" character to replace spaces
		$what = preg_replace("/[^a-z0-9-]+/", "-", $what);

		// Search engine requires to have the first letter/number of what we search
		$firstWhatChar = $what[0];

		if($useGlobalCats)
			$categories = array(
			'all'=>''
			);
		else
			$categories = &$this->categories;

		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		$maxPage = 10;

		for($page = 1; $page <= $maxPage; $page++)
		{
			$search = $url . '/' . $firstWhatChar . '/' . $what . '/se/desc/' . $page . '/';
			$cli = $this->fetch($search);

			if (($cli == false) || (strpos($cli->results, "Found <strong>0</strong>") !== false))
				break;

			$res = preg_match_all('`<td class="m"><a href="magnet:(?P<link>[^"]*)" .*>.*</td>'.
				'<td class="n"><a href="(?P<desc>.*)" title="(?P<name>.*)">.*</td>'.
				'<td>(?P<ago1>\d+) (?P<ago2>min|hour|day|month|year).*</td>'.
				'<td .*>(?P<cat>.*)</td>'.
				'<td>.*</td>'.
				'<td>(?P<size>.*)</td>'.
				'<td class="s">(?P<seeds>.*)</td>'.
				'<td class="l">(?P<peers>.*)</td>'.
				'`siU',$cli->results,$matches);

			if ($res) {
				for ($i = 0; $i < $res; $i++) {
					$link = "magnet:".$matches["link"][$i];
					if (!array_key_exists($link, $ret)) {
						$item = $this->getNewEntry();
						$item["desc"] = $url.$matches["desc"][$i];
						$item["time"] = self::getTime($now,$matches["ago1"][$i],$matches["ago2"][$i]);
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace(",","",$matches["size"][$i]));
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["peers"][$i]));
						$ret[$link] = $item;
						$added++;
						if ($added >= $limit)
							return;
					}
				}
			}
			else
				break;
		}
	}
}
