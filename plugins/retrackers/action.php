<?php
require_once( 'retrackers.php' );

$trks = new rRetrackers();
$trks->set();
header("Content-Type: application/javascript; charset=UTF-8");
cachedEcho($trks->get());
?>
