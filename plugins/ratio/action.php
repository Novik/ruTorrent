<?php
require_once( 'ratio.php' );

$rat = rRatio::load();
$rat->set();
CachedEcho::send($rat->get(),"application/javascript");
