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
if(!$config->canNotify($event))
	exit(0);

$message = $config->render($event, $torrent, $hash, $comment);
$client = new rTelegramClient();
$result = $client->send($config->token, $config->chatId, $message, 'MarkdownV2');
if(empty($result['ok']))
{
	$error = isset($result['error']) ? $result['error'] : 'Telegram request failed';
	error_log('telegram: '.rTelegram::sanitizeError($error, $config->token));
}
exit(0);
