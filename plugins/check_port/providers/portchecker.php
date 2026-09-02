<?php
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
