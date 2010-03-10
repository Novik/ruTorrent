<?php
require_once( 'scheduler.php' );

$sch = rScheduler::load();
$sch->set();

$content = $sch->get();
if(!ini_get("zlib.output_compression"))
	header("Content-Length: ".strlen($content));
header("Content-Type: application/javascript; charset=UTF-8");
echo $content;
?>
