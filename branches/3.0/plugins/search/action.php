<?php
require_once( 'search.php' );

$sites = new rSearch();
$sites->set();

$content = $sites->get();
header("Content-Length: ".strlen($content));
header("Content-Type: application/javascript; charset=UTF-8");
echo $content;
?>
