<?php

// Check if GeoIP exists - assume you installed it properly
$GeoIP = geoip_database_info(GEOIP_COUNTRY_EDITION);

if ( isset($GeoIP) ) {

    // Variable $GeoIPMode supports the following values:
    // "name" - peer GUI will have additional column next to IP, showing country's code
    // "code" - country's flag will be shown in front of IP address
    
    $GeoIPMode = "code"; 
    
    if ( $GeoIPMode == "name" ) {
        $jResult .= "utWebUI.GeoIPIndex = 1; ";
        // Insert new column
        $jResult .= "utWebUI.prsColumns.splice(utWebUI.GeoIPIndex, 0, {'text' : 'Country', 'width' : '60px', 'type' : TYPE_STRING}); ";
    } else {
        $jResult .= "utWebUI.GeoIPIndex = 0; ";
    }
    $jResult .= "utWebUI.GeoIPSupported = true; ";
    $theSettings->registerPlugin("geoip");
} else {
    $jResult .= "utWebUI.GeoIPSupported = false; ";
}
    
?>
