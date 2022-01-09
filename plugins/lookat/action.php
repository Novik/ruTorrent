<?php
require_once( 'lookat.php' );

$look = new rLook();
$look->set();
CachedEcho::send($look->get(),"application/javascript");
