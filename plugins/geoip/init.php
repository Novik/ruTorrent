<?php

if ( function_exists("geoip_database_info") ) {
    $jResult .= "utWebUI.prsColumns.unshift( {'text' : 'Country', 'width' : '60px', 'type' : TYPE_STRING}); ";
    $jResult .= "utWebUI.GeoIPSupported = true; ";
    $theSettings->registerPlugin("geoip");
    $jResult .= "var gplugin = new rPlugin(\"geoip\"); ";
    $jResult .= "gplugin.loadLanguages(); ";
    $jResult .= "gplugin.loadMainCSS(); ";
} else {
    $jResult .= "utWebUI.GeoIPSupported = false; ";
}
    
?>
