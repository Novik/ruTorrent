<?php

class ZamundaNetEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>20, "auth"=>1 );

	public $categories = array
	(
		'All'=>'',
		'Movies'=>'&c5=1&c19=1&c20=1&c24=1&c25=1&c28=1&c31=1&c35=1&c42=1&c46=1',
		'Serial'=>'&c7=1&c33=1',
		'Music'=>'&c6=1&c29=1&c30=1&c34=1&c51=1',
		'Games'=>'&c4=1&c12=1&c17=1&c21=1&c39=1&c40=1&c54=1',
		'Software'=>'&c1=1&c22=1&c38=1',
		'Sport'=>'&c41=1&c43=1',
		'Other'=>'&c23=1&c26=1&c32=1&c36=1&c37=1&c52=1&c53=1',
		'XXX'=>'&c9=1&c27=1&c48=1&c49=1'
	);

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		$client->referer = $this->search;
		return($client);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://zamunda.net';
		if($useGlobalCats)
		{
			$categories = array
			(
				'all'=>'',
				'movies'=>'&c5=1&c19=1&c20=1&c24=1&c25=1&c28=1&c31=1&c35=1&c42=1&c46=1',
				'tv'=>'&c7=1&c33=1',
				'music'=>'&c6=1&c29=1&c30=1&c34=1&c51=1',
				'games'=>'&c4=1&c12=1&c17=1&c21=1&c39=1&c40=1&c54=1',
				'anime'=>'&c25=1',
				'software'=>'&c1=1&c22=1&c38=1',
				'books'=>'&c32=1'
			);
		}
		else
		{
			$categories = &$this->categories;
		}
		if(!array_key_exists($cat,$categories))
		{
			$cat = '&'.$categories['all'];
		}
		else
		{
			$cat = '&'.$categories[$cat];
		}
		for( $page = 0; $page < 10; $page++ )
		{
			$this->search = "$url/bananas?search=$what&field=name&sort=9&type=desc$cat&page=$page";
			$cli = $this->fetch($url."/bananas?view=list");
			sleep( 1 );
			$res = preg_match_all('`<a href="list\?cat=.*"><img .* title=\'(?P<cat>.*)\' /></a>.*'.
				'<a .*\'><b>(?P<name>.*)</b></a>.*'.
				'<a .*/download\.php/(?P<id>\d+)/(?P<tname>.*)\.torrent.*</a>.*'.
				'<td .*>.*</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*>(?P<date>.*)</td>.*'.
				'<td .*>(?P<size>.*)</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*>(?P<seeds>.*)</td>.*'.
				'<td .*>(?P<peers>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i].".torrent";
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags( self::toUTF($matches["cat"][$i],"CP1251") );
						$item["desc"] = $url."/banan?id=".$matches["id"][$i];
						$item["name"] = self::removeTags( self::toUTF($matches["name"][$i],"CP1251") );
						$size = str_replace("<br>"," ",$matches["size"][$i]);
						$item["size"] = self::formatSize($size);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["peers"][$i]));
						$added++;
						if(($item["seeds"] == 0) || ($added>=$limit))
						{
							return;
						}
						$ret[$link] = $item;
					}
				}
				if(strpos($cli->results, ' class="gotonext">')===false)
				{
					break;
				}
			}
			else
				break;
		}
	}
}