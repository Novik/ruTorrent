<?php

$diskUpdateInterval = 10;	// in seconds
$notifySpaceLimit = 512;	// in Mb

// If we run locally && we the download directory seems to exists
if ( !empty(rTorrentSettings::get()->directory) && (
      stripos($scgi_host, 'unix:///') === 0 ||
      stripos($scgi_host, '127.') === 0 ||
      stripos($scgi_host, '::1') !== FALSE ||
      stripos($scgi_host, 'localhost') === 0
     ) &&file_exists(rTorrentSettings::get()->directory) ) {
  $partitionDirectory = rTorrentSettings::get()->directory; // Then we can show the disk space of the download directory
} else {
  $partitionDirectory = &$topDirectory; // Else, we show $topDirectory by default as fallback
}
