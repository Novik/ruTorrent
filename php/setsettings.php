<?php

require_once( 'WebUISettings.php' );

$json = $_POST['v'];
if(isset($json))
{
	$settings = WebUISettings::load();
	$settings->set($json);
}
