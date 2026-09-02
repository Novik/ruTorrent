<?php
// Configuration for the check_port plugin

$useWebsiteIPv4 = "yougetsignal";	// Valid choices:
									// false - disable IPv4 port check
									// "yougetsignal" - use https://www.yougetsignal.com/tools/open-ports/
									// "portchecker" - use https://portchecker.co/

$useWebsiteIPv6 = "portchecker";	// Valid choices:
									// "portchecker" - use https://portchecker.co/ (Known to work for IPv6)
									// Note: yougetsignal does not appear to support IPv6 checks

$checkPortTimeout = 15; // Total timeout budget in seconds for the check,
									// including provider failover and IP detection.

// Providers tried in order when the primary provider
// cannot return a definitive result.
//
// Valid providers:
//   yougetsignal
//   portchecker
//   globalping
$failoverProvidersIPv4 = [
	"portchecker",
	"globalping",
];

$failoverProvidersIPv6 = [
	"globalping",
];

// Globalping's unauthenticated API is rate-limited per source IP.
// If enabled as a provider, use it with an appropriate fallback chain.
