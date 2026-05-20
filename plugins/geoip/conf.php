<?php
// GeoIP2 Plugin Configuration
//
// This plugin requires MaxMind GeoLite2 or GeoIP2 database files (.mmdb).
// The GeoIP2 PHP library is bundled (no additional installation needed).
//
// Setup:
//   1. Register for a free MaxMind account: https://www.maxmind.com/en/geolite2/signup
//   2. Download GeoLite2-City.mmdb (and optionally GeoLite2-ASN.mmdb)
//   3. Place them in /usr/share/GeoIP/ or update the paths below
//   4. For automatic updates, install geoipupdate: https://github.com/maxmind/geoipupdate

$retrieveCountry = true;
$retrieveHost = true;
$retrieveComments = true;

// DNS resolver for reverse hostname lookups (null = system default)
$dnsResolver = '1.1.1.1';
$dnsResolverTimeout = 1;	// timeout in seconds

// GeoIP2 database paths
$geoip2CityDb = '/usr/share/GeoIP/GeoLite2-City.mmdb';
$geoip2IspDb = '/usr/share/GeoIP/GeoLite2-ASN.mmdb';
