<?php

eval( getPluginConf( 'geoip' ) );

$retrieveCountry = ($retrieveCountry && function_exists("geoip_country_code_by_name"));

if( $retrieveHost || $retrieveCountry )
{
	$theSettings->registerPlugin("geoip");
	if($retrieveCountry)
		$jResult .= "plugin.retrieveCountry = true;";
	if($retrieveHost)
		$jResult .= "plugin.retrieveHost = true;";
} 
else
	$jResult .= "plugin.disable();";
?>
