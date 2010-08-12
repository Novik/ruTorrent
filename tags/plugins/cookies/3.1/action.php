<?php
require_once( 'cookies.php' );

$cookies = new rCookies();
$cookies->set();
cachedEcho($cookies->get(),"application/javascript");

?>