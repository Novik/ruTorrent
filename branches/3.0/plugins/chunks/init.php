<?php
    if ( $theSettings->iVersion >= 0x805 ) {
        $theSettings->registerPlugin( "chunks" );
    } else {
        // Inappropriate version of rTorrent, plugin will NOT be installed
        $jResult .= "plugin.disable();";
        $jResult .= "theWebUI.rTorrentVersion = '" . $theSettings->version . "'; ";
    }
?>
