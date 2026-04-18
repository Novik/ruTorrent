<?php
	// configuration parameters

	$retrieveCountry = true;
	$retrieveHost = true;
	$retrieveComments = true;

	// For retrieve hosts

	$dnsResolver = '1.1.1.1';	// use gethostbyaddr, if null
	$dnsResolverTimeout = 1;	// timeout in seconds

	// GeoIP2 MaxMindDB configuration
	$geoip2Autoloader = '/usr/local/share/GeoIP/vendor/autoload.php';
	$geoip2CityDb = '/usr/local/share/GeoIP/GeoIP2-City.mmdb';
	$geoip2IspDb = '/usr/local/share/GeoIP/GeoIP2-ISP.mmdb';
