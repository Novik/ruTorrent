<?php
// Configuration for the check_port plugin

$useWebsiteIPv4 = "yougetsignal";	// Valid choices:
									// false - disable IPv4 port check
									// "yougetsignal" - use https://www.yougetsignal.com/tools/open-ports/
									// "portchecker" - use https://portchecker.co/

$useWebsiteIPv6 = "portchecker";	// Valid choices:
									// false - disable IPv6 port check
									// "portchecker" - use https://portchecker.co/ (Known to work for IPv6)
									// Note: yougetsignal does not appear to support IPv6 checks

$checkPortTimeout = 15; // Timeout in seconds for external port checking services
						// (e.g., yougetsignal, portchecker) and for IP detection services (e.g., ipify)
