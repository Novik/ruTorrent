<?php
require_once( 'autotools.php' );

$at = new rAutoTools();
$at->set();
header("Content-Type: application/javascript; charset=UTF-8");
cachedEcho($at->get());

?>
