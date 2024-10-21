<?php

if(empty($pathToExternals['ffmpeg']))	// May be path already defined?
{
	$pathToExternals['ffmpeg'] = '';// Something like /usr/bin/ffmpeg. If empty, will be found in PATH.
}

$extensions = array
(
	"3g2","3gp","4xm","a64","ac3","anm","apc","asf","avi","avm2","avs","bethsoftvid",
	"bink","c93","cavsvideo","cdg","dat","dirac","dnxhd","dsicin","dts","dv","dv1394","dvd","ea","eac3","ffm","film_cpk",
	"filmstrip","flic","flv","gxf","h261","h263","h264","idcin","iff","image2","image2pipe",
	"ingenient","ipmovie","ipod","iss","iv8","ivf","m1v","m2ts","m2v","m4a","m4v","matroska","mj2","mjpeg","mkv","mov",
	"mp2","mp2","mp4","mpeg","mpeg1video","mpeg2video","mpegts","mpegtsraw","mpegvideo","mpg","mpv",
	"msnwctcp","mtv","mvi","mxf","mxf_d10","nc","nsv","nuv","ogg","ogm","psp","psxstr","qt","rawvideo","rm","rmvb","roq","rpl","rtsp",
	"smk","svcd","swf","ts","vcd","video4linux","video4linux2","vob","webm","wmv",
);