<?php
require_once( '../../php/util.php' );
require_once( '../../php/settings.php' );

$url = strtolower(substr($_SERVER['SERVER_PROTOCOL'],0,-4))."://".$_SERVER['HTTP_HOST'].str_replace('/plugins/feeds/action.php','/',$_SERVER['PHP_SELF']);
$title = (isset($_REQUEST['title'])) ? $_REQUEST['title'] : 'Torrents feed';
$ret = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>'.$title.'</title><link>'.
	$url.'</link><description>'.$title.'</description>';

if(isset($_REQUEST['mode']))
{
	$view = '';	// all, inactive
	switch($_REQUEST['mode'])
	{
		case 'downloading':
		{
			$view = 'incomplete';
			break;
		}
		case 'completed':
		{
			$view = 'complete';
			break;
		}
		case 'error':
		case 'active':
		{
			$view = 'active';
			break;
		}
	}

	$req = new rXMLRPCRequest( new rXMLRPCCommand("d.multicall", array($view,
		getCmd("d.get_hash="),
		getCmd("d.get_name="),
		getCmd("d.get_message="),
		getCmd("d.get_up_rate="),
		getCmd("d.get_down_rate="),
		getCmd("d.get_custom1="),
		getCmd("d.get_custom2="),
		(rTorrentSettings::get()->iVersion >= 0x806) ? getCmd('d.get_custom').'=seedingtime' : getCmd('cat=')
		)) );
	if($req->success())
	{
		$items = array();
		for($i = 0; $i<count($req->val); $i+=8)
		{
			$item = array(
				"guid"=>$req->val[$i],
				"title"=>$req->val[$i+1],
				"category"=>rawurldecode($req->val[$i+5]),
				"link"=>$url.'plugins/data/action.php?hash='.$req->val[$i].'&no=0&readable=1',
				"pubDate"=>(empty($req->val[$i+7]) ? 0 : floatval($req->val[$i+7])) );
			if($_REQUEST['mode']=='error')
			{
	                        if(empty($req->val[$i+2]))
					continue;
				else
					$item["description"] = $req->val[$i+2];
			}
			else
			{
				$item["description"] = rawurldecode(substr($req->val[$i+6],10));
				if($_REQUEST['mode']=='active')
				{
					if( floatval($req->val[$i+3])<1024 && floatval($req->val[$i+4])<1024 )
						continue;
				}
				else
				if($_REQUEST['mode']=='inactive')
				{
					if( floatval($req->val[$i+3])>=1024 || floatval($req->val[$i+4])>=1024 )
						continue;
				}
			}
			$items[] = $item;
		}
                usort( $items, create_function( '$a,$b', 'return( (intval($a["pubDate"]) > intval($b["pubDate"])) ? -1 : ((intval($a["pubDate"]) < intval($b["pubDate"])) ? 1 : strcmp($b["title"],$b["title"])) );'));
		foreach( $items as $item )
		{
			$ret.="\r\n<item>";
			foreach( $item as $key=>$val )
			{
				if($key=="guid")
					$ret.="\r\n\t<guid isPermaLink=\"false\">".$val.'</guid>';
				else
				if($key=="pubDate")
				{
					if(empty($val))
						continue;
					$ret.="\r\n\t<pubDate>".gmstrftime('%a, %d %b %Y %T %Z',$val).'</pubDate>';
				}
				else
					$ret.="\r\n\t<".$key.'>'.htmlentities($val,ENT_COMPAT,"UTF-8").'</'.$key.'>';
			}
			$ret.="\r\n</item>";
		}
	}
}

cachedEcho($ret."</channel></rss>","application/rss+xml",true);

?>