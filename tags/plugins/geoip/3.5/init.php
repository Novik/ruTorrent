<?php

eval( getPluginConf( $plugin["name"] ) );

$retrieveCountry = ($retrieveCountry && function_exists("geoip_country_code_by_name"));
$retrieveComments = ($retrieveComments && function_exists("sqlite_open"));

if( $retrieveHost || $retrieveCountry || $retrieveComments )
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	if($retrieveCountry)
		$jResult .= "plugin.retrieveCountry = true;";
	if($retrieveHost)
		$jResult .= "plugin.retrieveHost = true;";
	if($retrieveComments)
		$jResult .= "plugin.retrieveComments = true;";
} 
else
	$jResult .= "plugin.disable();";
