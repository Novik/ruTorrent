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

// The command names a caller may attach to a load.* or to a multicall are
// $XMLRPCProxySafeParams in conf/xmlrpc_proxy.php, which action.php loads
// before this file. One list serves every entry point that fronts rtorrent,
// so a client that works through one works through all of them.
//
// Setting the list here is still honoured, and still wins, because this file
// is evaluated after that one -- but it then applies to this entry point
// alone. Set it here only when this one is meant to differ.
