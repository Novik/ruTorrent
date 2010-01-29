<?php

eval( getPluginConf( 'geoip' ) );

if( $retrieveHost || ($retrieveCountry && function_exists("geoip_database_info")) )
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
