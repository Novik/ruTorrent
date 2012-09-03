<?php
require_once( 'autotools.php' );

$at = new rAutoTools();
$at->set();
cachedEcho($at->get(),"application/javascript");
