<?php

$diskUpdateInterval = 10;	// in seconds
$notifySpaceLimit = 512;	// in Mb
$partitionDirectory = null;	// if null, then we will check rtorrent download directory (or $topDirectory if rtorrent is unavailable)
				// otherwise, set this to the absolute path for checked partition. 
// To have multiple mountpoint checks add them in array like this
//$partitionDirectory = [
//    '/mnt/disk1',
//    '/mnt/disk2',
//    '/mnt/disk3'
//];
//
//or like this
//
//$partitionDirectory = array(
//    '/mnt/disk1',
//    '/mnt/disk2',
//    '/mnt/disk3'
//);
$freeBytesInMeter = false;	// show free space instead of %
