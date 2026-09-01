<?php

// CLI entry point for "erase this download and delete its data", for callers
// that run inside rtorrent and have no PHP context of their own. (Its one
// caller today is the group command built in plugins/ratio/ratio.php.)
//
// Usage: php erase.php <hash> [force] [user]
//   force: 1 = delete the download's own files (default), 2 = delete the whole
//          base path, honoured only when $enableForceDeletion is on.
//   user:  the ruTorrent user, on multi-user installs. Trails the other
//          arguments because it is empty on a single-user install.
//
// This records the request, then drains the queue if no other firing is already
// doing so.

$hash = isset($argv[1]) ? $argv[1] : "";
$force = (isset($argv[2]) && ($argv[2] !== "")) ? $argv[2] : "1";
$user = isset($argv[3]) ? $argv[3] : "";

if(!preg_match('/^[0-9A-Fa-f]{40}$/', $hash))
	exit(1);
if($user !== "")
	$_SERVER['REMOTE_USER'] = $user;

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__)."/pending.php" );
eval(FileUtil::getPluginConf('erasedata'));

$listPath = FileUtil::getSettingsPath()."/erasedata";
@FileUtil::makeDirectory($listPath);

if(!erasedataQueueRequest($listPath, $hash, $force))
	exit(1);

erasedataDrainQueue($listPath,
	isset($erasePendingMaxAttempts) ? intval($erasePendingMaxAttempts) : 10);
exit(0);
