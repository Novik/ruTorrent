<?php
require_once( dirname(__FILE__)."/../../php/settings.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );

$ret = 0;
$port = rTorrentSettings::get()->port;
$client = new Snoopy();
$client->read_timeout = 15;
$client->use_gzip = HTTP_USE_GZIP;
@$client->fetch("http://www.canyouseeme.org","POST","application/x-www-form-urlencoded","port=".$port."&submit=Check+Your+Port");
if($client->status==200)
{
	if(strpos($client->results,">Error:<")!==false)
		$ret = 1;
	else
	if(strpos($client->results,">Success:<")!==false)
		$ret = 2;
}

cachedEcho('{ "port": '.$port.', "status": '.$ret.' }',"application/json");
