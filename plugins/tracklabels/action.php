<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );

ignore_user_abort(true);
set_time_limit(0);

if(isset($_REQUEST["label"]))
{
	$label = function_exists('mb_strtolower')
		? mb_strtolower(rawurldecode($_REQUEST["label"]), 'utf-8')
		: strtolower(rawurldecode($_REQUEST["label"]));
	$name = FileUtil::getSettingsPath().'/labels';
	if(!is_dir($name))
		FileUtil::makeDirectory($name);
	$name.=('/'.$label.".png");
	if(is_readable($name))
	{
		SendFile::send( $name, "image/png" );
		exit;
	}
	$name = dirname(__FILE__)."/labels/".$label.".png";
	if(is_readable($name))
	{
		SendFile::send( $name, "image/png" );
		exit;
	}
}

if(isset($_REQUEST["tracker"]))
{
	$tracker = rawurldecode($_REQUEST["tracker"]);
	$name = dirname(__FILE__)."/trackers/".$tracker.".png";
	if(is_readable($name))
	{
		SendFile::send( $name, "image/png" );
		exit;
	}
	$name = FileUtil::getSettingsPath().'/trackers';
	if(!is_dir($name))
		FileUtil::makeDirectory($name);
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
			SendFile::send( $name, "image/x-icon" );
			exit;
		}
	}
}

// If we can't find an image, send a generic unknown image and cache for 30 days
SendFile::sendCachedImage("./trackers/unknown.png", "image/png", "2592000");
