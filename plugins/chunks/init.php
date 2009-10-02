<?php
    $req = new rXMLRPCRequest( new rXMLRPCCommand("system.client_version") );
    if ( $req->run() ) {
        $Version = $req->strings[0];
        $jResult .= "utWebUI.rTorrentVersion = '" . $Version . "'; ";
        $Parts = explode('.', $Version );
        if ( $Parts[0] == 0 ) {
            if ( $Parts[1] >= 8 ) {
                if ( $Parts[2] >= 5 ) {
                    $jResult .= "utWebUI.ChunksEnabled = true; ";
                } else {
                    $jResult .= "utWebUI.ChunksEnabled = false; ";
                }
            } else {
                $jResult .= "utWebUI.ChunksEnabled = false; ";
            }
        } else {
            $jResult .= "utWebUI.ChunksEnabled = true; ";
        }
        $theSettings->registerPlugin("chunks");
    } else {
        // Cannot run XMLRPC command required to determine version
        // of rTorrent, plugin will NOT be installed
        $jResult .= "utWebUI.ChunksEnabled = false; ";
    }
?>
