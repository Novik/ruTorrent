<?php

require_once(dirname(__FILE__).'/telegram.php');

// Saving credentials and sending a test message are state-changing actions.
// php/util.php (loaded by settings.php) performs the configured Origin/Referer
// check before this file is reached when enableCSRFCheck is enabled. The
// default ruTorrent configuration leaves that optional check disabled.
if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST')
{
	header('HTTP/1.0 405 Method Not Allowed', true, 405);
	exit('Method Not Allowed');
}

$action = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
if($action === '')
	$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
if($action === 'settelegram' || $action === 'set')
{
	$config = rTelegram::load();
	$config->updateFromInput(rTelegram::requestInput());
	$stored = $config->store();
	$handlers = $config->setHandlers();
	$result = $config->get();
	if(!$stored || !$handlers)
		$result .= "noty(theUILang.telegramSaveFailed,'error');";
	else
		$result .= "noty(theUILang.telegramSaved,'info');";
	CachedEcho::send($result, 'application/javascript');
	exit;
}
if($action === 'testtelegram' || $action === 'test')
{
	$config = rTelegram::load();
	$errors = $config->validationErrors();
	if(count($errors))
	{
		CachedEcho::send(JSON::safeEncode(array('ok' => false, 'error' => implode('; ', $errors))), 'application/json');
		exit;
	}
	$message = $config->render('finished', 'Telegram test message', 'test');
	$result = (new rTelegramClient())->send($config->token, $config->chatId, $message, 'MarkdownV2');
	if(empty($result['ok']))
		$result = array('ok' => false, 'error' => isset($result['error']) ? rTelegram::sanitizeError($result['error'], $config->token) : 'Telegram request failed');
	else
		$result = array('ok' => true);
	CachedEcho::send(JSON::safeEncode($result), 'application/json');
	exit;
}

CachedEcho::send(JSON::safeEncode(array('ok' => false, 'error' => 'Unknown action')), 'application/json');
