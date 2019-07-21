<?php

if(empty($pathToExternals['ffmpeg']))	// May be path already defined?
{
	$pathToExternals['ffmpeg'] = '';// Something like /usr/bin/ffmpeg. If empty, will be found in PATH.
}

$extensions = array
(
	"3g2","3gp","4xm","iff","iss","mtv","roq","a64","ac3","anm","apc","asf","avi","avm2","avs","bethsoftvid",
	"bink","c93","cavsvideo","cdg","dirac","dnxhd","dsicin","dts","dv","dv1394","dvd","ea","eac3","ffm","film_cpk",
	"filmstrip","flic","flv","gxf","h261","h263","h264","idcin","image2","image2pipe",
	"ingenient","ipmovie","ipod","iv8","ivf","m4v","matroska","mjpeg","mov","m4a","mj2",
	"mp2","mp4","mpeg","mpeg1video","mpeg2video","mpegts","mpegtsraw","mpegvideo",
	"msnwctcp","mvi","mxf","mxf_d10","nc","nsv","nuv","ogg","psp","psxstr","rawvideo","rm","rpl","rtsp",
	"smk","svcd","swf","vcd","video4linux","video4linux2","vob","webm","wmv",
	"mkv","ogm","mpg","mpv","m1v","m2v","mp2","qt","rmvb","dat","ts"
);