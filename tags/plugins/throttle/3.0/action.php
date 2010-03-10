<?php
require_once( 'throttle.php' );

$thr = new rThrottle();
$thr->set();

$content = $thr->get();
if(!ini_get("zlib.output_compression"))
	header("Content-Length: ".strlen($content));
header("Content-Type: application/javascript; charset=UTF-8");
echo $content;
?>
