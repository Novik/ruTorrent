<?php
require_once( 'ratio.php' );

$rat = new rRatio();
$rat->set();
cachedEcho($rat->get(),"application/javascript");
