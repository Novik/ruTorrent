<?php
require_once( 'plugins/search/search.php');
$theSettings->registerPlugin("search");
$sites = rSearch::load();
$jResult.=$sites->get();
?>
