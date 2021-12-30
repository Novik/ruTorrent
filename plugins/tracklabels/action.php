<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );

ignore_user_abort(true);
set_time_limit(0);

if(isset($_REQUEST["label"]))
{	
	$request = rawurldecode($_REQUEST["label"]);
	$cookie = str_replace(' ', '', $request);
	if (!isset($_COOKIE[$cookie]))
	{	
		$label = function_exists('mb_strtolower')
			? mb_strtolower($request, 'utf-8')
			: strtolower($request);
		$name = getSettingsPath().'/labels';
		if(!is_dir($name))
			makeDirectory($name);
		$name.=('/'.$label.".png");
		if(is_readable($name))
		{
			sendFile( $name, "image/png" );
			exit;
		}
		$name = dirname(__FILE__)."/labels/".$label.".png";
		if(is_readable($name))
		{
			sendFile( $name, "image/png" );
			exit;
		}
		
		// set a cookie for 30 days to speed up label img requests
		setcookie($cookie, '1', time() + 86400 * 30);
	}
}
elseif(isset($_REQUEST["tracker"]) && !isset($_COOKIE[$_REQUEST["tracker"]]))
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
	if(strlen($tracker))
	{
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
	
	// set a cookie for 30 days to speed up tracker img requests
	setcookie($_REQUEST["tracker"], '1', time() + 86400 * 30);
}

$name = dirname(__FILE__)."/trackers/unknown.png";
sendFile($name, "image/png");

exit();
