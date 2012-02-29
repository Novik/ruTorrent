<?php

eval( getPluginConf( $plugin["name"] ) );

$retrieveCountry = ($retrieveCountry && function_exists("geoip_country_code_by_name"));

if( $retrieveHost || $retrieveCountry )
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	if($retrieveCountry)
		$jResult .= "plugin.retrieveCountry = true;";
	if($retrieveHost)
		$jResult .= "plugin.retrieveHost = true;";
} 
else
	$jResult .= "plugin.disable();";
?>