<?php
require_once( 'cookies.php' );

$cookies = new rCookies();
$cookies->set();

$content = $cookies->get();
header("Content-Length: ".strlen($content));
header("Content-Type: application/javascript; charset=UTF-8");
echo $content;
?>
