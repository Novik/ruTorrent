<?php

// Raw XMLRPC proxy mode for external clients (Prowlarr, Sonarr, etc.)
// Options:
//   "sanitize"           — (default) allow load.* with safe params only,
//                          pass other methods as untrusted
//   "passthrough_unsafe" — send all raw XMLRPC as trusted (DANGEROUS)
//   "off"                — reject all raw XMLRPC pass-through
$XMLRPCProxy = "sanitize";

// Log raw XMLRPC proxy activity (default: true)
// Logs accepted, sanitized, and rejected methods to help diagnose
// external client integration issues.
$XMLRPCProxyLog = true;

// Command prefixes allowed as load.* parameters in sanitize mode.
// External clients (Prowlarr, Sonarr, Radarr, Transdroid) attach these
// to load.start to set labels, directories, priorities, etc.
// Any command param not matching these prefixes will be stripped.
// Add entries here if your external client needs additional commands.
$XMLRPCProxySafeParams = array(
	'd.custom1.set',            // label
	'd.custom2.set',            // custom field
	'd.custom3.set',            // custom field
	'd.custom4.set',            // custom field
	'd.custom5.set',            // used by erasedata
	'd.custom.set',             // generic custom field
	'd.directory.set',          // download directory
	'd.directory_base.set',     // base directory
	'd.priority.set',           // priority
	'd.throttle_name.set',      // throttle group
	'd.views.push_back_unique', // view membership
	'd.delete_tied',            // delete .torrent on remove
);
