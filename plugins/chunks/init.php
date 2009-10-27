<?php
    if ( $theSettings->iVersion >= 0x805 ) {
        $jResult .= "utWebUI.ChunksEnabled = true; ";
        $theSettings->registerPlugin( "chunks" );
    } else {
        // Inappropriate version of rTorrent, plugin will NOT be installed
        $jResult .= "utWebUI.ChunksEnabled = false; ";
        $jResult .= "utWebUI.rTorrentVersion = '" . $theSettings->version . "'; ";
    }
?>
