<?php

	// Policy for raw XMLRPC pass-through, shared by every entry point that
	// fronts rtorrent: the httprpc plugin's proxy and rpc2.php.
	//
	// It lives here rather than in plugins/httprpc/conf.php so that there is
	// one policy rather than one per caller. plugins/httprpc/conf.php still
	// works and still wins where it sets something, so an existing edit is not
	// lost — but new deployments should edit this file.

	// "sanitize"           — (default) rebuild what can be rebuilt, refuse the
	//                        execution primitives, pass the rest to rtorrent
	//                        untrusted and let its own allowlist decide
	// "passthrough_unsafe" — send everything as trusted (DANGEROUS)
	// "off"                — reject all raw XMLRPC pass-through
	$XMLRPCProxy = "sanitize";

	// Log what the proxy decided and why.
	$XMLRPCProxyLog = true;

	// Command names allowed as a command parameter of load.* and of the
	// multicalls. Full names, matched exactly: 'd.custom' does NOT cover
	// 'd.custom1.set'. A parameter naming anything else is dropped from a
	// load.* (the torrent is still added) and makes a multicall go to rtorrent
	// untouched and untrusted.
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
		'd.delete_tied',            // delete the .torrent on remove

		// Actions a client applies across a view: "pause all", "resume all".
		'd.open', 'd.close', 'd.start', 'd.stop',
	);

	// Let a caller name a path on rtorrent's own filesystem in load.start or
	// load.normal (default: false).
	//
	// rtorrent treats a load URI that is not an http/https/ftp or magnet URI as
	// a path on its own machine: it opens that file and records it as the
	// download's tied file, which d.delete_tied later unlinks. A remote client
	// has no way to know what is on that filesystem and does not need this —
	// clients send a URL, a magnet, or the torrent itself via load.raw_start.
	//
	// Turn it on only if something you run posts server-local paths through the
	// proxy, and note that it lets that caller choose which file d.delete_tied
	// removes. ruTorrent's own "add torrent" does not go through the proxy, so
	// this does not affect it.
	$XMLRPCProxyAllowLocalPaths = false;

	// Allow "/" as the boundary for where a caller may have a download written
	// (default: false).
	//
	// d.directory.set and d.directory_base.set name the directory rtorrent
	// writes a download into, and the caller supplies the torrent, so they name
	// the file too. They are confined to $topDirectory from conf/config.php,
	// which is the same boundary correctDirectory() already holds the panel to.
	//
	// Stock ruTorrent ships $topDirectory = "/", which confines nothing. A check
	// that is present but permits everything is worse than none, so with that
	// setting this endpoint refuses to serve until somebody has decided which it
	// is: either set $topDirectory to the directory downloads belong under, or
	// set this to true and accept that a caller may write anywhere the rtorrent
	// user can. On a single-user box where the only caller is you, that may be
	// exactly what you want -- but say it on purpose.
	$XMLRPCProxyAllowRootDirectory = false;
