<?php
require_once( 'ratio.php' );

$rat = new rRatio();
$rat->set();
CachedEcho::send($rat->get(),"application/javascript");
