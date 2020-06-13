<?php
require_once( dirname(__FILE__)."/../../php/settings.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );
eval( getPluginConf( 'check_port' ) );

$port = rTorrentSettings::get()->port;
$ip_glob = rTorrentSettings::get()->ip;

if($useWebsite=="yougetsignal")
{
	$url = "https://www.yougetsignal.com/tools/open-ports/";
	$ipMatch = '/<p style="font-size: 1.4em;">(?P<ip>.*)</';
	$checker = "https://ports.yougetsignal.com/check-port.php";
	$closed = "closed";
	$open = "open";
}
else
if($useWebsite=="portchecker")
{
	$url = "https://portchecker.co/";
	$ipMatch = '/data-ip="(?P<ip>.*)"/';
	$checker = $url;
	$closed = ">closed<";
	$open = ">open<";
}
else
	cachedEcho('{ "port": '.$port.', "status": 0 }',"application/json");

function get_ip($url,$ipMatch)
{
	$client = new Snoopy();
	$client->proxy_host = "";

	@$client->fetch($url);

	if($client->status==200)
	{
		if(preg_match($ipMatch, $client->results, $match))
			return $match["ip"];
	}
}

function check_port($ip,$port,$checker,$closed,$open)
{
	global $useWebsite;

	if($useWebsite=="yougetsignal")
		$parse = "remoteAddress=".$ip."&portNumber=".$port;
	if($useWebsite=="portchecker")
		$parse = "target_ip=".$ip."&port=".$port;

	$ret = 0;

	$client = new Snoopy();
	$client->proxy_host = "";

	@$client->fetch($checker, "POST", "application/x-www-form-urlencoded", $parse);

	if($client->status==200)
	{
		if(strpos($client->results,$closed)!==false)
			$ret = 1;
		else
		if(strpos($client->results,$open)!==false)
			$ret = 2;
	}

	cachedEcho('{ "port": '.$port.', "status": '.$ret.' }',"application/json");
}

if(!empty($ip_glob) && $ip_glob != '0.0.0.0')
	check_port($ip_glob,$port,$checker,$closed,$open);
else
{
	session_start();
	if(isset($_REQUEST['init']))
		unset($_SESSION['ip']);
	if(isset($_SESSION['ip']))
		check_port($_SESSION['ip'],$port,$checker,$closed,$open);
	else
	{
		$_SESSION['ip'] = get_ip($url,$ipMatch);
		if(isset($_SESSION['ip']))
			check_port($_SESSION['ip'],$port,$checker,$closed,$open);
		else
			cachedEcho('{ "port": '.$port.', "status": 0 }',"application/json");
	}
}
