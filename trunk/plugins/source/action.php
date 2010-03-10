<?php
require_once( '../../php/rtorrent.php' );

if(isset($_REQUEST['result']))
{
	$content = 'log(theUILang.cantFindTorrent);';
	if(!ini_get("zlib.output_compression"))
		header("Content-Length: ".strlen($content));
	header("Content-Type: text/html");
	exit($content);
}
if(isset($_REQUEST['hash']))
{
	$torrent = rTorrent::getSource($_REQUEST['hash']);
	if($torrent)
		$torrent->send();
}
header("Location: ".$_SERVER['PHP_SELF'].'?result=0');
?>
