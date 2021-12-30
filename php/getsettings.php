<?php

require_once( 'WebUISettings.php' );

$settings = WebUISettings::load();
$json = $settings->get();
cachedEcho($json,"application/json",true);
