<?php
require_once( 'ratio.php' );

$rat = new rRatio();
$rat->set();

$content = $rat->get();
header("Content-Length: ".strlen($content));
header("Content-Type: application/javascript; charset=UTF-8");
echo $content;
?>
