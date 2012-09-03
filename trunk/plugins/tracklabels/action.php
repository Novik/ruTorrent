<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );

ignore_user_abort(true);
set_time_limit(0);

if(isset($_REQUEST["tracker"]))
{
	$tracker = rawurldecode($_REQUEST["tracker"]);
	$name = dirname(__FILE__)."/trackers/".$tracker.".png";
	if(is_readable($name))
	{
		sendFile( $name, "image/png" );
		exit;
	}
	$name = getSettingsPath().'/trackers';
	if(!is_dir($name))
		makeDirectory($name);
	$name.='/';
	$name.=$tracker;
	$name.='.ico';
	if(!is_readable($name))
	{
		$url = Snoopy::linkencode("http://".$tracker."/favicon.ico");
		$client = new Snoopy();
		@$client->fetchComplex($url);
		if($client->status==200)
			file_put_contents($name,$client->results);
	}
	if(is_readable($name))
	{
		sendFile( $name, "image/x-icon" ); 
		exit;
	}
}

header("HTTP/1.0 302 Moved Temporarily");
header("Location: ".dirname($_SERVER['PHP_SELF']).'/trackers/unknown.png');
exit();
