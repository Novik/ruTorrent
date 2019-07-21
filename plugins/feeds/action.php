<?php
require_once( '../../php/util.php' );
require_once( '../../php/settings.php' );
eval( getPluginConf( 'feeds' ) );

$lang = (isset($_REQUEST['lang']) && 
	preg_match('/^[A-Za-z]{2}(\-[A-Za-z]{2}|)$/', $_REQUEST['lang']) && 
	is_file('lang/'.$_REQUEST['lang'].'.php')) ? $_REQUEST['lang'] : 'en';
$theUILang = array();
require_once( 'lang/'.$lang.'.php' );

function bytes( $bt )
{
	global $theUILang;
	$a = array($theUILang["bytes"], $theUILang['KB'], $theUILang['MB'], $theUILang['GB'], $theUILang['TB'], $theUILang['PB']);
	$ndx = 0;
	if($bt == 0)
		$ndx = 1;
	else
	{
		if($bt < 1024)
		{
			$bt = $bt / 1024;
			$ndx = 1;
		}
		else
		{
			while($bt >= 1024)
			{
       	    			$bt = $bt / 1024;
      				$ndx++;
	         	}
		}
	}
	return((floor($bt*10)/10)." ".$a[$ndx]);
}

function speed($bt)
{
	global $theUILang;
	return(($bt>0) ? bytes($bt)."/".$theUILang["s"] : "");
}

function eta($tm)
{
	global $theUILang;
	if($tm >= 2419200)
		return("∞");
	$val = $tm % (604800 * 52);
	$w = intval($val / 604800);
	$val = $val % 604800;
	$d = intval($val / 86400);
	$val = $val % 86400;
	$h = intval($val / 3600);
	$val = $val % 3600;
	$m = intval($val / 60);
	$val = intval($val % 60);
	$v = 0;
	$ret = "";
	if($w > 0)
	{	
		$ret = $w.$theUILang["time_w"];
		$v++;
	}
	if($d > 0)
	{
		$ret .= $d.$theUILang["time_d"];
		$v++;
	}
	if(($h > 0) && ($v < 2))
	{
		$ret .= $h.$theUILang["time_h"];
      		$v++;
	}
	if(($m > 0) && ($v < 2))
	{	
		$ret .= $m.$theUILang["time_m"];
		$v++;
	}
	if($v < 2)
		$ret .= $val.$theUILang["time_s"];
	return( substr($ret,0,-1) );
}

function formatItemDescription($torrent)
{
	global $theUILang;
	$desc = '<font size=2><fieldset><legend>'.$theUILang["Transfer"].'</legend>'.'<strong>'.$theUILang["Size"].": </strong>".bytes($torrent["size"])."<br>";
	if($torrent["downloaded"]!=$torrent["size"])
		$desc.='<strong>'.$theUILang["Downloaded"].": </strong>".bytes($torrent["downloaded"])."<br>";
	if($torrent["eta"]>0)
		$desc.='<strong>'.$theUILang["Remaining"].": </strong>".eta($torrent["eta"])."<br>";
	if($torrent["dl"]>=1024)
		$desc.='<strong>'.$theUILang["Down_speed"].": </strong>".speed($torrent["dl"])."<br>";
	if($torrent["uploaded"]>0)
		$desc.='<strong>'.$theUILang["Uploaded"].": </strong>".bytes($torrent["uploaded"])."<br>";
	if($torrent["ul"]>=1024)
		$desc.='<strong>'.$theUILang["Ul_speed"].": </strong>".speed($torrent["ul"])."<br>";
	$desc.='<strong>'.$theUILang["Share_ratio"].": </strong>".$torrent["ratio"]."<br>";
        if($torrent["seeds"]!="0 (0)")
		$desc.='<strong>'.$theUILang["Seeds"].": </strong>".$torrent["seeds"]."<br>";
        if($torrent["peers"]!="0 (0)")
		$desc.='<strong>'.$theUILang["Peers"].": </strong>".$torrent["peers"]."<br>";
	$desc.="</fieldset>";
	if(!empty($torrent["mesage"]))
		$desc.='<fieldset><legend>'.$theUILang["Track_status"].'</legend>'.htmlspecialchars($torrent["mesage"],ENT_COMPAT,"UTF-8").'</fieldset>';
	if(!empty($torrent["comment"]))
		$desc.='<fieldset><legend>'.$theUILang["Comment"].'</legend>'.htmlspecialchars($torrent["comment"],ENT_COMPAT,"UTF-8").'</fieldset>';
	return($desc);
}

function sortByPubDate( $a, $b )
{
	return( (intval($a["pubDate"]) > intval($b["pubDate"])) ? -1 : ((intval($a["pubDate"]) < intval($b["pubDate"])) ? 1 : strcmp($a["title"],$b["title"])) );
}

$mode = (isset($_REQUEST['mode'])) ? $_REQUEST['mode'] : 'all';
$url = (empty($_SERVER['HTTPS']) ? "http" : "https")."://".$_SERVER['HTTP_HOST'].str_replace('/plugins/feeds/action.php','/',$_SERVER['PHP_SELF']);
$server = explode(':',rTorrentSettings::get()->server);
$ret = '<?xml version="1.0" encoding="UTF-8"?>'.
	'<rss version="2.0"><channel><description>*** rTorrent '.
	rTorrentSettings::get()->version.'/'.rTorrentSettings::get()->libVersion." - ".$server[0].' ***</description><link>'.
	$url.'</link><title>'.$server[0].': '.$theUILang[$mode].'</title>'.
	'<image><url>'.$url.'images/logo.png</url><link>'.$url.'</link><title>ruTorrent</title></image>';

$view = '';	// all, inactive
switch($mode)
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

$prm = array($view,
	getCmd("d.get_hash="),
	getCmd("d.get_name="),
	getCmd("d.get_message="),
	getCmd("d.get_up_rate="),
	getCmd("d.get_down_rate="),
	getCmd("d.get_custom1="),
	(rTorrentSettings::get()->iVersion >= 0x806) ? getCmd('d.get_custom').'=seedingtime' : getCmd('cat='));
if($showItemDescription)
	$prm = array_merge($prm, array(
		getCmd("d.get_custom2="),
		getCmd("d.get_completed_chunks="),
		getCmd("d.get_size_chunks="),
		getCmd("d.get_size_bytes="),
		getCmd("d.get_bytes_done="),
		getCmd("d.get_up_total="),
		getCmd("d.get_ratio="),
		getCmd("d.get_up_rate="),
		getCmd("d.get_down_rate="),
		getCmd("d.get_chunk_size="),
		getCmd("d.get_peers_not_connected="),
		getCmd("d.get_peers_connected="),
		getCmd("d.get_peers_accounted="), 
		getCmd("d.get_peers_complete=")));
$req = new rXMLRPCRequest( new rXMLRPCCommand("d.multicall", $prm) );

if($req->success())
{
	$items = array();
	for($i = 0; $i<count($req->val); $i+=21)
	{
		$item = array(
			"guid"=>$req->val[$i],
			"title"=>$req->val[$i+1],
			"category"=>rawurldecode($req->val[$i+5]),
			"pubDate"=>(empty($req->val[$i+7]) ? 0 : floatval($req->val[$i+6])) );
		if(rTorrentSettings::get()->isPluginRegistered('data'))
			$item["link"] = $url.'plugins/data/action.php?hash='.$req->val[$i].'&no=0&readable=1';
		if($_REQUEST['mode']=='error')
		{
                        if(empty($req->val[$i+2]))
				continue;
		}
		else
		{
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

		if($showItemDescription)
		{
			$get_completed_chunks = floatval($req->val[$i+8]);
			$get_size_chunks = floatval($req->val[$i+9]);
			$get_chunk_size = floatval($req->val[$i+16]);
			$get_peers_not_connected = floatval($req->val[$i+17]);
			$get_peers_connected = floatval($req->val[$i+18]);
			$get_peers_all = $get_peers_not_connected+$get_peers_connected;

			$torrent = array(
				"downloaded" => floatval($req->val[$i+11]),
				"uploaded" => floatval($req->val[$i+12]),
				"size" => floatval($req->val[$i+10]),
				"ratio" => floatval($req->val[$i+13])/1000,
				"ul" => floatval($req->val[$i+14]),
				"dl" =>  floatval($req->val[$i+15]),
				"comment" => rawurldecode(substr($req->val[$i+7],10)),
				"hash" => $req->val[$i],
				"peers" => $req->val[$i+19]." (".$get_peers_all.")",
				"seeds" => $req->val[$i+20]." (".$get_peers_all.")",
				"message" => $req->val[$i+2],
				);
			$torrent["eta"] = ($torrent["dl"]>0) ? floor(($get_size_chunks-$get_completed_chunks)*$get_chunk_size/$torrent["dl"]) : -1;
			$item["description"] = formatItemDescription( $torrent );
		}
		$items[] = $item;
	}
	usort( $items, "sortByPubDate");
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
			if($key=="description")
				$ret.="\r\n\t<description><![CDATA[".$val.']]></description>';
			else
				$ret.="\r\n\t<".$key.'>'.htmlspecialchars($val,ENT_COMPAT,"UTF-8").'</'.$key.'>';
		}
		$ret.="\r\n</item>";
	}
}

ob_clean();
cachedEcho($ret."</channel></rss>","application/rss+xml",true);
