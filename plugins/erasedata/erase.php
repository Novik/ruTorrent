<?php

// CLI entry point for "erase this download and delete its data", for callers
// that run inside rtorrent and have no PHP context of their own -- the ratio
// group commands built in plugins/ratio/ratio.php.
//
// Usage: php erase.php <hash> [force] [user]
//   force: 1 = delete the download's own files (default), 2 = delete the whole
//          base path, honoured only when $enableForceDeletion is on.
//   user:  the ruTorrent user, on multi-user installs. Trails the other
//          arguments because it is empty on a single-user install.
//
// The file list is read over RPC and recorded for the garbage collector before
// the download is erased, which is the sequence the web UI's "Remove and delete
// data" takes as well.

$hash = isset($argv[1]) ? $argv[1] : "";
$force = (isset($argv[2]) && ($argv[2] !== "")) ? $argv[2] : "1";
$user = isset($argv[3]) ? $argv[3] : "";

if(!preg_match('/^[0-9A-Fa-f]{40}$/', $hash))
	exit(1);
if($user !== "")
	$_SERVER['REMOTE_USER'] = $user;

require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( dirname(__FILE__)."/removewithdata.php" );

// A ratio group command runs on every check until the download is gone, so a
// hash already recorded is left to the garbage collector.
if(is_file(FileUtil::getSettingsPath()."/erasedata/".$hash.".list"))
	exit(0);

exit((erasedataRemoveWithData(array($hash), $force) === false) ? 1 : 0);
