<?php

if ( function_exists("geoip_database_info") ) {
    $theSettings->registerPlugin("geoip");
} else {
    $jResult .= "plugin.disable();";
}
    
?>
