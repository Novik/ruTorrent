<?php

// Check if GeoIP exists - assume you installed it properly
$GeoIP = geoip_database_info(GEOIP_COUNTRY_EDITION);

if ( isset($GeoIP) ) {
    // Insert new column
    $jResult .= "utWebUI.GeoIPIndex = 1; ";
    $jResult .= "utWebUI.prsColumns.splice(utWebUI.GeoIPIndex, 0, {'text' : 'Country', 'width' : '60px', 'type' : TYPE_STRING}); ";
    $jResult .= "utWebUI.GeoIPSupported = true; ";
    $theSettings->registerPlugin("geoip");
} else {
    $jResult .= "utWebUI.GeoIPSupported = false; ";
}
    
?>
