<?php
require_once( 'retrackers.php' );

$trks = new rRetrackers();
$trks->set();
CachedEcho::send($trks->get(),"application/javascript");
