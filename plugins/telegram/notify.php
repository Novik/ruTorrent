<?php

// This entry point is launched by rTorrent and must never make the daemon
// wait for a web request or a Telegram retry.
if(!chdir(dirname(__FILE__)))
	exit(0);

$event = isset($argv[1]) ? $argv[1] : '';
$torrent = isset($argv[2]) ? $argv[2] : '';
$hash = isset($argv[3]) ? $argv[3] : '';
$comment = isset($argv[4]) ? $argv[4] : '';
if(isset($argv[5]))
	$_SERVER['REMOTE_USER'] = $argv[5];

require_once(dirname(__FILE__).'/telegram.php');

$config = rTelegram::load();
$config->notify($event, $torrent, $hash, $comment);
exit(0);
