<?php
require_once( 'retrackers.php' );

$trks = new rRetrackers();
$trks->set();
cachedEcho($trks->get(),"application/javascript");
