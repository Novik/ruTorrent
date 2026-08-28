<?php

// Raw XMLRPC proxy mode for external clients (Prowlarr, Sonarr, etc.)
// Options:
//   "sanitize"           — (default) allow load.* with safe params only,
//                          pass other methods as untrusted
// Passing a method as untrusted only restricts it on rtorrent 0.16.10 and
// later, which is where the UNTRUSTED_CONNECTION header is honoured. Older
// versions ignore it and run those calls with full trust.
//   "passthrough_unsafe" — send all raw XMLRPC as trusted (DANGEROUS)
//   "off"                — reject all raw XMLRPC pass-through
$XMLRPCProxy = "sanitize";

// Log raw XMLRPC proxy activity (default: true)
// Logs accepted, sanitized, and rejected methods to help diagnose
// external client integration issues.
$XMLRPCProxyLog = true;

// Allow a caller to name a path on rtorrent's own filesystem in load.start or
// load.normal (default: false).
//
// rtorrent treats a second parameter that is not an http/https/ftp or magnet
// URI as a path on its own machine: it opens that file and records it as the
// download's tied file, which d.delete_tied later unlinks. A remote client has
// no way to know what is on that filesystem and does not use this -- clients
// send a URL, a magnet, or the torrent itself through load.raw_start.
//
// Turn it on only if something you run posts server-local paths through this
// proxy, and note that it lets that caller choose which file d.delete_tied
// removes. ruTorrent's own "add torrent" does not go through here, so this
// does not affect it.
$XMLRPCProxyAllowLocalPaths = false;

// Command names allowed as load.* parameters in sanitize mode.
// External clients (Prowlarr, Sonarr, Radarr, Transdroid, cross-seed)
// attach these to load.start / load.raw_start to set labels, directories,
// priorities, etc. Quoted values (d.custom1.set="cross-seed") are accepted.
// Entries are full command names and are matched exactly: 'd.custom' does
// NOT cover 'd.custom1.set'. A param whose command name is not listed is
// stripped and logged. Add entries here if your external client needs
// additional commands.
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

	// Actions (not setters):
	'd.delete_tied',            // delete .torrent on remove
);
