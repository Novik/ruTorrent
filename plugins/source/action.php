<?php
require_once( '../../php/rtorrent.php' );

if(isset($_REQUEST['result']))
	cachedEcho('noty(theUILang.cantFindTorrent,"error");',"text/html");
if(isset($_REQUEST['hash']))
{
	$torrent = rTorrent::getSource($_REQUEST['hash']);
	if($torrent)
		$torrent->send();
}
header("HTTP/1.0 302 Moved Temporarily");
header("Location: ".$_SERVER['PHP_SELF'].'?result=0');
