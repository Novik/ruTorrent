<?php
require_once( 'ratio.php' );

$rat = new rRatio();
$rat->set();
header("Content-Type: application/javascript; charset=UTF-8");
cachedEcho($rat->get());
?>
