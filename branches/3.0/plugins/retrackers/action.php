<?php
require_once( 'retrackers.php' );

$trks = new rRetrackers();
$trks->set();

$content = $trks->get();
header("Content-Length: ".strlen($content));
header("Content-Type: application/javascript; charset=UTF-8");
echo $content;
?>
