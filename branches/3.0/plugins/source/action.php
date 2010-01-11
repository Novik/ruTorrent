<?php
require_once( '../../php/rtorrent.php' );

if(isset($_REQUEST['hash']))
{
	$torrent = rTorrent::getSource($_REQUEST['hash']);
	if($torrent)
		$torrent->send();
}

$content = 'log(theUILang.cantFindTorrent);';
header("Content-Length: ".strlen($content));
header("Content-Type: text/html");
echo $content;
?>
