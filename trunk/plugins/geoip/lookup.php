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
    } else if ( $Action == "geoip" ) {
        $CtrN = geoip_country_name_by_name( $IP );
        $CtrC = geoip_country_code_by_name( $IP );
        if ( ! isset($CtrC) || $CtrC == "" ) {
            $CtrC = "unknown";
        } else {
            $CtrC = strtolower( $CtrC );
        }
        if ( $Lang != "en" ) {
            $Res = $Countries[ $CtrN ];
            if ( ! isset($Res) || $Res == "" ) {
                // No localized name for given country
                $Res = $CtrN;
            }
        } else {
            if ( ! isset($CtrN) || $CtrN == "" ) {
                $Res = "Unknown";
            } else {
                $Res = $CtrN;
            }
        }
        echo $CtrC . "|" . $Res;
    } else {
        echo "Unknown action requested: " . $Action;
    }
?>
