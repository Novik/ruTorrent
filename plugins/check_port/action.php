<?php
require_once(dirname(__FILE__) . "/../../php/settings.php");
require_once(dirname(__FILE__) . "/../../php/Snoopy.class.inc");
require_once(dirname(__FILE__) . "/parse.php");
require_once(dirname(__FILE__) . "/providers.php");

// Load the plugin's configuration settings from conf.php
eval(FileUtil::getPluginConf('check_port'));

// Default values for configuration, used if not set in conf.php
$currentCheckPortTimeout = isset($checkPortTimeout) ? (int)$checkPortTimeout : 15;
$currentUseWebsiteIPv4 = isset($useWebsiteIPv4) ? $useWebsiteIPv4 : "yougetsignal";
$currentUseWebsiteIPv6 = isset($useWebsiteIPv6) ? $useWebsiteIPv6 : "portchecker";

$currentFailoverProvidersIPv4 = isset($failoverProvidersIPv4)
	? $failoverProvidersIPv4
	: array("portchecker", "globalping");

$currentFailoverProvidersIPv6 = isset($failoverProvidersIPv6)
	? $failoverProvidersIPv6
	: array("globalping");

/**
 * Gets the public IP address (IPv4 or IPv6) from ipify.org
 * It uses Snoopy (a curl wrapper) to make the request
 *
 * @param string $version '4' for IPv4, '6' for IPv6
 * @param int $timeout Request timeout
 * @return string|null The public IP address or null on failure
 */
function get_public_ip($version, $timeout) {
	if (!Utility::getExternal('curl')) {
		error_log("check_port plugin: 'curl' executable not found");
		return null;
	}

	$snoopy = new Snoopy();
	$snoopy->agent = "ruTorrent CheckPort Plugin/IP Check";
	$snoopy->read_timeout = max(1, (int)floor($timeout));
	$snoopy->proxy_host = "";

	$url = ($version == '6') ? "https://api64.ipify.org/" : "https://api4.ipify.org/";
	@$snoopy->fetch($url);

	if ($snoopy->status == 200 && !empty($snoopy->results)) {
		$ip = trim($snoopy->results);
		$flag = ($version == '6') ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
		if (filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
			return $ip;
		}
	}

	return null;
}

/**
 * Main logic to get an IP and check its port status for a given IP version
 *
 * @param string $ip_version '4' or '6'
 * @param string $use_website Primary checking service
 * @param string $rtorrent_ip The IP address configured in rTorrent (if any)
 * @param int $rtorrent_port The listening port configured in rTorrent
 * @param int $timeout Total time budget for IP detection and the provider chain
 * @return array An associative array with 'ip' and 'status' keys
 */
function get_and_check_ip($ip_version, $use_website, $rtorrent_ip, $rtorrent_port, $timeout) {
	global $checkPortProviders, $currentFailoverProvidersIPv4, $currentFailoverProvidersIPv6;

	$ip_to_check = null;
	$flag = ($ip_version == '6') ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
	$deadline = microtime(true) + max(1, (int)$timeout);

	if (!empty($rtorrent_ip) && filter_var($rtorrent_ip, FILTER_VALIDATE_IP, $flag)) {
		$ip_to_check = $rtorrent_ip;
	} else {
		$remaining = $deadline - microtime(true);
		if ($remaining >= 1) {
			$ip_to_check = get_public_ip($ip_version, $remaining);
		}
	}

	if ($ip_to_check) {
		$version = ($ip_version == '4') ? 'ipv4' : 'ipv6';
		$failover = ($ip_version == '4')
			? $currentFailoverProvidersIPv4
			: $currentFailoverProvidersIPv6;
		$providers = array_unique(array_merge([$use_website], $failover));

		foreach ($providers as $provider) {
			if (
				!isset($checkPortProviders[$provider]) ||
				empty($checkPortProviders[$provider][$version])
			) {
				continue;
			}

			$remaining = $deadline - microtime(true);
			if ($remaining < 1) {
				break;
			}

			// Provider timeout is an integer number of seconds and must never
			// exceed the remaining shared budget.
			$providerTimeout = max(1, (int)floor($remaining));

			$status = call_user_func(
				$checkPortProviders[$provider]["function"],
				$ip_to_check,
				$rtorrent_port,
				$providerTimeout
			);

			if ($status === 1 || $status === 2) {
				return [
					"ip" => $ip_to_check,
					"status" => $status
				];
			}
		}

		return [
			"ip" => $ip_to_check,
			"status" => 0
		];
	}

	return ["ip" => "-", "status" => -1];
}

// --- Main Execution ---
$port = rTorrentSettings::get()->port;
$ip_glob = rTorrentSettings::get()->ip;

if (isset($_REQUEST['setport'])) {
	$newport = (int)$_REQUEST['setport'];
	if ($newport >= 1 && $newport <= 65535) {
		$sreq = new rXMLRPCRequest(new rXMLRPCCommand("network.listen.port.set", array("", $newport)));
		if ($sreq->success())
			$port = $newport;
	}
}

$response = [
	"ipv4" => "-", "ipv4_port" => (int)$port, "ipv4_status" => -1,
	"ipv6" => "-", "ipv6_port" => (int)$port, "ipv6_status" => -1,
];

if ($currentUseWebsiteIPv4 !== false) {
	$ipv4_result = get_and_check_ip('4', $currentUseWebsiteIPv4, $ip_glob, $port, $currentCheckPortTimeout);
	$response["ipv4"] = $ipv4_result["ip"];
	$response["ipv4_status"] = $ipv4_result["status"];
}

if ($currentUseWebsiteIPv6 !== false) {
	$ipv6_result = get_and_check_ip('6', $currentUseWebsiteIPv6, $ip_glob, $port, $currentCheckPortTimeout);
	$response["ipv6"] = $ipv6_result["ip"];
	$response["ipv6_status"] = $ipv6_result["status"];
}

CachedEcho::send(JSON::safeEncode($response), "application/json");
