<?php

require_once( "sqlite.php" );

eval( FileUtil::getPluginConf( $plugin["name"] ) );

// GeoIP2: check that the bundled library and at least the city database exist
$geoip2Available = (
	file_exists(dirname(__FILE__).'/geoip2.phar') &&
	isset($geoip2CityDb) && file_exists($geoip2CityDb)
);
$retrieveCountry = ($retrieveCountry && $geoip2Available);
$retrieveComments = ($retrieveComments && sqlite_exists());

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
