<?php
    $Action = $_GET[ 'action' ];
    $IP     = $_GET[ "ip" ];
    
    if ( !isset($Action) || !isset($IP) ) {
        echo "null";
        return;
    }
 
    if ( $Action == "dns" ) {
        // TODO: support for IP resolution
        echo( gethostbyaddr($IP) );
    } else if ( $Action == "geoip" ) {
        $CtrC = geoip_country_code_by_name( $IP );
        if ( ! isset($CtrC) || $CtrC == "" ) {
            $CtrC = "unknown";
        } else {
            $CtrC = strtolower( $CtrC );
        }
        echo $CtrC;
    } else {
        echo "Unknown action requested: " . $Action;
    }
?>
