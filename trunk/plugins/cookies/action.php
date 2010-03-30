<?php
require_once( 'cookies.php' );

$cookies = new rCookies();
$cookies->set();
header("Content-Type: application/javascript; charset=UTF-8");
cachedEcho($cookies->get());

?>
