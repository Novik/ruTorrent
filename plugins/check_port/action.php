<?php
require_once( dirname(__FILE__)."/../../php/settings.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );
eval( FileUtil::getPluginConf( 'check_port' ) );

$port = rTorrentSettings::get()->port;
$ip_glob = rTorrentSettings::get()->ip;

if($useWebsite=="yougetsignal")
{
	$url = "https://www.yougetsignal.com/tools/open-ports/";
	$ipMatch = '/<p style="font-size: 1.4em;">(?P<ip>[^<]+)/';
	$checker = "https://ports.yougetsignal.com/check-port.php";
	$closed = "closed";
	$open = "open";
}
else
if($useWebsite=="portchecker")
{
	$url = "https://portchecker.co/";
	$ipMatch = '/data-ip="(?P<ip>[^"]+)/';
	$checker = "https://portchecker.co/check-v0";
	// Indicators will be constructed with $port, so define them before calling check_port
	$closed = 'Port ' . $port . ' is <span class="red">closed</span>.';
	$open = 'Port ' . $port . ' is <span class="green">open</span>.';
}
else
{
	if(!empty($ip_glob) && $ip_glob != '0.0.0.0')
		CachedEcho::send('{ "ip": "'.$ip_glob.'", "port": '.$port.', "status": 0 }',"application/json");
	else
		CachedEcho::send('{ "ip": "?.?.?.?", "port": '.$port.', "status": 0 }',"application/json");
}

function get_ip($url,$ipMatch)
{
	global $useIpv4;

	$client = new Snoopy();
	$client->proxy_host = "";
	$client->useIpv4 = $useIpv4;

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
	global $useIpv4;
	global $url; // Needed for portchecker's initial GET request

	$client = new Snoopy();
	$client->proxy_host = "";
	$client->useIpv4 = $useIpv4;

	if($useWebsite=="yougetsignal")
	{
		$parse = "remoteAddress=".$ip."&portNumber=".$port;
	}
	else if($useWebsite=="portchecker")
	{
		// Step 1: Fetch initial page (defined by global $url) to get cookies and CSRF token
		@$client->fetch($url); // $url is https://portchecker.co/
		$csrf_token = '';
		if ($client->status == 200) {
			$client->setcookies();
			if (preg_match('/name="_csrf" value="(?P<csrf>[^"]+)"/', $client->results, $match_csrf)) {
				$csrf_token = $match_csrf["csrf"];
			} elseif (preg_match('/<meta\s+name="csrf-token"\s+content="(?P<csrf_meta>[^"]+)"/i', $client->results, $match_meta_csrf)) {
				$csrf_token = $match_meta_csrf['csrf_meta'];
			}
		}

		if (empty($csrf_token)) {
			// CSRF token not found, port check will likely fail or be inaccurate.
			CachedEcho::send('{ "ip": "'.$ip.'", "port": '.$port.', "status": 0 }',"application/json");
			return; // Exit function
		}

		// Prepare POST data
		$parse = "target_ip=".urlencode($ip)."&port=".urlencode($port)."&selectPort=".urlencode($port)."&_csrf=".urlencode($csrf_token);

		// Set Referer and other headers for the POST request
		$client->referer = $url; // $url is https://portchecker.co/
		$client->rawheaders = []; // Clear any previous rawheaders
		$client->rawheaders["Accept"] = "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8";
		$client->rawheaders["Accept-Language"] = "en-US,en;q=0.5";
		$url_parts_pc = parse_url($url);
		if (isset($url_parts_pc['scheme']) && isset($url_parts_pc['host'])) {
			$client->rawheaders["Origin"] = $url_parts_pc['scheme'] . '://' . $url_parts_pc['host'];
		}
		$client->rawheaders["Connection"] = "keep-alive";
		$client->rawheaders["Upgrade-Insecure-Requests"] = "1";
		$client->rawheaders["Sec-Fetch-Dest"] = "document";
		$client->rawheaders["Sec-Fetch-Mode"] = "navigate";
		$client->rawheaders["Sec-Fetch-Site"] = "same-origin";
		$client->rawheaders["Sec-Fetch-User"] = "?1";
	}
	else // Should not be reached if initial $useWebsite check is done properly
	{
		CachedEcho::send('{ "ip": "'.$ip.'", "port": '.$port.', "status": 0 }',"application/json");
		return;
	}

	$ret = 0;
	@$client->fetch($checker, "POST", "application/x-www-form-urlencoded", $parse);
	if($client->status==200)
	{
		if(strpos($client->results,$closed)!==false)
			$ret = 1;
		else
		if(strpos($client->results,$open)!==false)
			$ret = 2;
	}

	CachedEcho::send('{ "ip": "'.$ip.'", "port": '.$port.', "status": '.$ret.' }',"application/json");
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
			CachedEcho::send('{ "ip": "?.?.?.?", "port": '.$port.', "status": 0 }',"application/json");
	}
}
