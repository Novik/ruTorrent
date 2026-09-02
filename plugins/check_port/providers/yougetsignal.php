<?php
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

	// The API is the one the site's own front end calls, and it is reached
	// with the headers a browser on that site would send.
	$client->rawheaders['Origin'] = "https://www.yougetsignal.com";
	$client->referer = "https://www.yougetsignal.com/";

	// Obtain a session device ID cookie required by the API
	@$client->fetch("https://api.connected.app/deviceId");
	if ($client->status != 204 && $client->status != 200) {
		error_log("check_port: Failed to obtain yougetsignal device ID. Status: {$client->status}");
		return 0;
	}
	$client->setcookies();

	// Run the port check via the GraphQL API
	$query = 'mutation NetworkToolRunPortCheck($input: NetworkToolPortCheckInput!) { networkToolRunPortCheck(input: $input) { output } }';
	$post_data = json_encode([
		'query' => $query,
		'variables' => ['input' => ['host' => $ip, 'port' => (int)$port]],
	]);
	@$client->fetch("https://api.connected.app/graphql", "POST", "application/json", $post_data);

	if ($client->status == 200) {
		$status = check_port_parse_yougetsignal($client->results);
		if ($status)
			return $status;
		error_log("check_port: yougetsignal unexpected response for IP {$ip}. Response: " . substr($client->results, 0, 500));
	} else {
		error_log("check_port: Failed fetch from yougetsignal for IP {$ip}. Status: {$client->status}, Error: {$client->error}");
	}
	return 0;
}
