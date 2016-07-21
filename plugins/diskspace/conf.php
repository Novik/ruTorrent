<?php

$diskUpdateInterval = 10;	// in seconds
$notifySpaceLimit = 512;	// in Mb

// If we run locally && we the download directory seems to exists
if ( isLocalMode() && rTorrentSettings::get()->linkExist && file_exists(rTorrentSettings::get()->directory) ) {
  $partitionDirectory = rTorrentSettings::get()->directory; // Then we can show the disk space of the download directory
} else {
  $partitionDirectory = &$topDirectory; // Else, we show $topDirectory by default as fallback
}
