<?php
require_once( 'search.php' );

$sites = new rSearch();
$sites->set();
cachedEcho($sites->get(),"application/javascript");

?>
