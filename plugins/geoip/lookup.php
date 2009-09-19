<?php
    $Action = $_GET[ 'action' ];
    $IP     = $_GET[ "ip" ];
    $Lang   = $_GET[ "lang" ];
    
    if ( !isset($Action) || !isset($IP) ) {
        echo "null";
        return;
    }
    if ( !isset($Lang) ) {
        $Lang = "en";
    }
    if ( ! include_once( "lang/" . $Lang . ".php" ) ) {
        // No such language file available, default to English
        $Lang = "en";
    }
    
    if ( $Action == "dns" ) {
        // Support for IP resolution
        echo( gethostbyaddr($IP) );
    } else if ( $Action == "geoip-name" || $Action == "geoip-code" ) {
        if ( $Action == "geoip-name" ) {
            $Ctr = geoip_country_name_by_name( $IP );
            if ( $Lang != "en" ) {
                $Res = $Countries[ $Ctr ];
                if ( isset($Res) ) {
                    echo $Res;
                } else {
                    echo $Ctr;
                }
            }
        } else {
            $Ctr = geoip_country_code_by_name( $IP );
            if ( ! isset($Ctr) || $Ctr == '' ) {
                echo "unknown";
            } else {
                echo strtolower( $Ctr );
            }
        }
    } else {
        echo "null";
    }
?>
