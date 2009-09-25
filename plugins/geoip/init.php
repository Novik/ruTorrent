<?php

// Check if GeoIP exists - assume you installed it properly
$GeoIP = geoip_database_info(GEOIP_COUNTRY_EDITION);

if ( isset($GeoIP) ) {
    $jResult .= "utWebUI.prsColumns.unshift( {'text' : 'Country', 'width' : '60px', 'type' : TYPE_STRING}); ";
    $jResult .= "utWebUI.GeoIPSupported = true; ";
    $theSettings->registerPlugin("geoip");
} else {
    $jResult .= "utWebUI.GeoIPSupported = false; ";
}
    
?>
