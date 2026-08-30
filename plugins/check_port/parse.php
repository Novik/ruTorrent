<?php

/**
 * Reads the port state out of a yougetsignal GraphQL answer.
 *
 * Kept apart from action.php, which cannot be loaded from a test: it requires
 * settings.php and evaluates the plugin's conf.php at top level. The shape of
 * this answer belongs to a third party and has already changed once, so it is
 * the part worth pinning.
 *
 * @param string $body The raw response body
 * @return int Status code (0: unknown, 1: closed, 2: open)
 */
function check_port_parse_yougetsignal($body)
{
	// A body that is not an answer at all -- the HTML page the old endpoint
	// serves now, or nothing -- decodes to a scalar or null, and the lookup
	// below yields null for all of them.
	$json = json_decode($body, true);

	$parsed = $json['data']['networkToolRunPortCheck']['output']['parsed'] ?? null;
	if (($parsed['kind'] ?? '') !== 'success')
		return 0;

	// nmap's own states. "filtered" means nothing answered, which for a port
	// check is the same news as closed.
	$state = $parsed['port']['state'] ?? '';
	if ($state === 'closed' || $state === 'filtered')
		return 1;
	if ($state === 'open')
		return 2;

	return 0;
}
