<?php
require_once(dirname(__FILE__) . "/../../php/settings.php");
require_once(dirname(__FILE__) . "/../../php/Snoopy.class.inc");

// Load the plugin's configuration settings from conf.php
eval(FileUtil::getPluginConf('check_port'));

// Default values for configuration, used if not set in conf.php
$currentCheckPortTimeout = isset($checkPortTimeout) ? (int)$checkPortTimeout : 15;
$currentUseWebsiteIPv4 = isset($useWebsiteIPv4) ? $useWebsiteIPv4 : "yougetsignal";
$currentUseWebsiteIPv6 = isset($useWebsiteIPv6) ? $useWebsiteIPv6 : "portchecker";

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
	// Initialize the Snoopy client
	$snoopy = new Snoopy();
	$snoopy->agent = "ruTorrent CheckPort Plugin/IP Check";
	// Set a timeout for the request, with a minimum of 5 seconds
	$snoopy->read_timeout = max(5, (int)($timeout / 2));
	$snoopy->proxy_host = ""; // Do not use a proxy for this external IP check

	// Select the correct ipify API URL based on the requested IP version
	$url = ($version == '6') ? "https://api64.ipify.org/" : "https://api4.ipify.org/";
	@$snoopy->fetch($url); // Fetch the URL

	// Check if the request was successful and returned content
	if ($snoopy->status == 200 && !empty($snoopy->results)) {
		$ip = trim($snoopy->results);
		// Validate the returned IP address to ensure it's a valid IPv4 or IPv6
		$flag = ($version == '6') ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
		if (filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
			return $ip; // Return the valid IP
		}
		error_log("check_port plugin: {$url} returned invalid IP: " . $ip);
	} else {
		error_log("check_port plugin: Failed to fetch from {$url}. Status: {$snoopy->status}, Error: {$snoopy->error}");
	}
	return null; // Return null on failure
}

/**
 * Checks port status using yougetsignal.com
 *
 * @param string $ip The IP address to check
 * @param int $port The port number to check
 * @param int $timeout Request timeout in seconds
 * @return int Status code (0: unknown, 1: closed, 2: open)
 */
function check_port_yougetsignal($ip, $port, $timeout) {
	$client = new Snoopy();
	$client->read_timeout = (int)$timeout;
	$client->proxy_host = ""; // Do not use a proxy for this check
	$post_data = "remoteAddress=" . urlencode($ip) . "&portNumber=" . urlencode($port);

	// Make a POST request to the port checking service
	@$client->fetch("https://ports.yougetsignal.com/check-port.php", "POST", "application/x-www-form-urlencoded", $post_data);

	// Parse the response to determine port status
	if ($client->status == 200) {
		if (stripos($client->results, "is closed") !== false) return 1; // Port is closed
		if (stripos($client->results, "is open") !== false) return 2; // Port is open
		error_log("check_port: yougetsignal response indicators not found for IP {$ip}. Response: " . substr($client->results, 0, 500));
	} else {
		error_log("check_port: Failed fetch from yougetsignal for IP {$ip}. Status: {$client->status}, Error: {$client->error}");
	}
	return 0;
}

/**
 * Checks port status using portchecker.co
 *
 * @param string $ip The IP address to check
 * @param int $port The port number to check
 * @param int $timeout Request timeout in seconds
 * @return int Status code (0: unknown, 1: closed, 2: open)
 */
function check_port_portchecker($ip, $port, $timeout) {
	$client = new Snoopy();
	$client->read_timeout = (int)$timeout;
	$client->proxy_host = ""; // Do not use a proxy for this check

	// Fetch the main page to acquire a CSRF token and session cookie
	@$client->fetch("https://portchecker.co/");
	if ($client->status != 200) {
		error_log("check_port: Could not fetch portchecker.co main page. Status: {$client->status}");
		return 0;
	}
	$client->setcookies(); // Store cookies to be sent in the next request

	// Extract the CSRF token from the page content
	$csrf_token = '';
	if (preg_match('/name="_csrf" value="(?P<csrf>[^"]+)"/', $client->results, $match)) {
		$csrf_token = $match["csrf"];
	}
	// If no token is found, the check cannot proceed
	if (empty($csrf_token)) {
		error_log("check_port: CSRF token not found from portchecker.co for IP: {$ip}");
		return 0;
	}

	// Prepare the POST data for the port check request, including the CSRF token
	$post_data = "target_ip=" . urlencode($ip) . "&port=" . urlencode($port) . "&_csrf=" . urlencode($csrf_token);
	$client->referer = "https://portchecker.co/"; // Set the referer header

	// Make the actual port check request to the API endpoint
	@$client->fetch("https://portchecker.co/check-v0", "POST", "application/x-www-form-urlencoded", $post_data);

	// Parse the JSON response to determine port status
	if ($client->status == 200) {
		if (stripos($client->results, 'is <span class="red">closed</span>') !== false) return 1; // Port is closed
		if (stripos($client->results, 'is <span class="green">open</span>') !== false) return 2; // Port is open
		error_log("check_port: portchecker response indicators not found for IP {$ip}. Response: " . substr($client->results, 0, 500));
	} else {
		error_log("check_port: Failed fetch from portchecker endpoint for IP {$ip}. Status: {$client->status}, Error: {$client->error}");
	}
	return 0; // Status is unknown
}

/**
 * Main logic to get an IP and check its port status for a given IP version
 *
 * @param string $ip_version '4' or '6', for IPv4 or IPv6
 * @param string $use_website The checking service to use ('yougetsignal' or 'portchecker')
 * @param string $rtorrent_ip The IP address configured in rTorrent (if any)
 * @param int $rtorrent_port The listening port configured in rTorrent
 * @param int $timeout The request timeout in seconds
 * @return array An associative array with 'ip' and 'status' keys
 */
function get_and_check_ip($ip_version, $use_website, $rtorrent_ip, $rtorrent_port, $timeout) {
	$ip_to_check = null;
	$flag = ($ip_version == '6') ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;

	if (!empty($rtorrent_ip) && filter_var($rtorrent_ip, FILTER_VALIDATE_IP, $flag)) {
		$ip_to_check = $rtorrent_ip;
		// If rTorrent's IP is not set or invalid for the version, fetch the public IP
	} else {
		$ip_to_check = get_public_ip($ip_version, $timeout);
	}

	// If an IP was determined, proceed to check the port
	if ($ip_to_check) {
		$status = 0;
		// Call the appropriate checking function based on the selected service
		if ($use_website == "yougetsignal") {
			$status = check_port_yougetsignal($ip_to_check, $rtorrent_port, $timeout);
		} elseif ($use_website == "portchecker") {
			$status = check_port_portchecker($ip_to_check, $rtorrent_port, $timeout);
		}
		return ["ip" => $ip_to_check, "status" => $status];
	}
	// Return a default "not available" state if no IP could be determined
	return ["ip" => "-", "status" => 0];
}

// --- Main Execution ---
// Get rTorrent's listening port and configured IP from settings
$port = rTorrentSettings::get()->port;
$ip_glob = rTorrentSettings::get()->ip;

// Initialize the response structure that will be sent to the client
$response = [
	"ipv4" => "-", "ipv4_port" => (int)$port, "ipv4_status" => 0,
	"ipv6" => "-", "ipv6_port" => (int)$port, "ipv6_status" => 0,
];

// Perform the IPv4 check if it's enabled in conf.php
if ($currentUseWebsiteIPv4 !== false) {
	$ipv4_result = get_and_check_ip('4', $currentUseWebsiteIPv4, $ip_glob, $port, $currentCheckPortTimeout);
	$response["ipv4"] = $ipv4_result["ip"];
	$response["ipv4_status"] = $ipv4_result["status"];
}

// Perform the IPv6 check if it's enabled in conf.php
if ($currentUseWebsiteIPv6 !== false) {
	$ipv6_result = get_and_check_ip('6', $currentUseWebsiteIPv6, $ip_glob, $port, $currentCheckPortTimeout);
	$response["ipv6"] = $ipv6_result["ip"];
	$response["ipv6_status"] = $ipv6_result["status"];
}

// Send the final JSON response to the client
CachedEcho::send(JSON::safeEncode($response), "application/json");
