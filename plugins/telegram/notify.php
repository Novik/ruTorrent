<?php

// This entry point is launched by rTorrent and must never make the daemon
// wait for a web request or a Telegram retry.
if(!chdir(dirname(__FILE__)))
	exit(0);

$queueDirectory = isset($argv[1]) ? $argv[1] : '';
$event = isset($argv[2]) ? $argv[2] : '';
$torrent = isset($argv[3]) ? $argv[3] : '';
$hash = isset($argv[4]) ? $argv[4] : '';
$comment = isset($argv[5]) ? $argv[5] : '';
// An omitted profile argument must select the default profile even if the
// inherited CLI environment happens to contain REMOTE_USER.
$_SERVER['REMOTE_USER'] = isset($argv[6]) ? (string)$argv[6] : '';

// Load Telegram settings at file scope so deployment config variables (notably
// $profilePath) are initialized globally before the queue worker reads them.
require_once(dirname(__FILE__).'/telegram.php');
require_once(dirname(__FILE__).'/queue.php');
$config = rTelegram::load();

$queue = new rTelegramQueue($queueDirectory);
if(!$queue->enqueue($event, $torrent, $hash, $comment))
	exit(0);
$queue->drain($config);
exit(0);
