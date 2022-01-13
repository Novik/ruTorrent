<?php

require_once( 'WebUISettings.php' );

$settings = WebUISettings::load();
$json = $settings->get();
CachedEcho::send($json,"application/json",true);
