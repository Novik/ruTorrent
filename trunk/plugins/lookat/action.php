<?php
require_once( 'lookat.php' );

$look = new rLook();
$look->set();
cachedEcho($look->get(),"application/javascript");
