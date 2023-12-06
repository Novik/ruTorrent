<?php
require_once( 'autotools.php' );

$at = new rAutoTools();
$at->set();
CachedEcho::send($at->get(),"application/javascript");
