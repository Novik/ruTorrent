<?php
class TorrentechEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>25, "auth"=>1 );


	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentech.org';
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/index.php?forums=all&act=search&CODE=01&search_in=titles&result_type=topics&torrents-only=1&keywords='.$what.'&st='.($pg*25) );
			if($cli==false || (strpos($cli->results, ' type="password"')!==false)) 
				break;

			$res = preg_match_all("`preview_it\((?P<id>\d+), event\)' onmouseout='preview_hide\(\)'>(?P<name>.*)</a>.*".
					        '<span class="desc">(?P<subname>.*)<.*'.
					        '<td align="center" class=".*" nowrap="nowrap".*><span .*>(?P<seeds>.*)</span> &middot; (?P<leech>.*) &middot.*'.
					        '<td class="row1"><span class="(desc|lastaction)">(?P<date>.*)<'.
					        '`siU', $cli->results, $matches);
			if($res)
			{

				$myhash = '';
				if( preg_match( "`hash'>(?P<authkey>.*)</div>`",$cli->results, $matches1 ) )
					$myhash = $matches1["authkey"];

				for($i=0; $i<$res; $i++)
				{
					$link = $url."/index.php?act=attach&type=post&passkey=".$myhash."&id=".$matches["id"][$i].".torrent";

					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/index.php?showtopic=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]).' / '.self::removeTags($matches["subname"][$i]);
						$item["time"] = strtotime( str_replace(' - ',' ',str_replace('.','-',$matches["date"][$i])) );
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
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
