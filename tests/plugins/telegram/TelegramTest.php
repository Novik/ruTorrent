<?php

require_once(__DIR__.'/../../../tests/php/TestCase.php');
require_once(__DIR__.'/../../../plugins/telegram/telegram.php');

class TelegramTest extends TestCase
{
	public function testDefaultsAndSecretsAreNotExported()
	{
		$config = new rTelegram();
		$config->token = '123456:super-secret';
		$export = $config->get();
		$this->assertTrue(strpos($export, 'super-secret') === false, 'the bot token is never exported to JavaScript');
		$this->assertTrue(strpos($export, 'tokenConfigured') !== false, 'the browser receives only token configuration status');
		$this->assertEquals(3, count(rTelegram::eventNames()), 'all supported event types are declared');
	}

	public function testInputAndTemplateRendering()
	{
		$config = new rTelegram();
		$config->updateFromInput(array(
			'enabled' => '1',
			'token' => ' token ',
			'chat_id' => '-100',
			'event_finished' => '1',
			'template_finished' => '{STATE}|{TORRENT}|{HASH}',
		));
		$this->assertTrue($config->enabled, 'enabled is parsed');
		$this->assertEquals('token', $config->token, 'token is trimmed');
		$this->assertTrue($config->events['finished'], 'selected events are parsed');
		$this->assertEquals('finished|name "with" $danger|ABC', $config->render('finished', 'name "with" $danger', 'ABC'), 'placeholders are rendered as plain text');
		$this->assertTrue(!$config->events['added'], 'unchecked events stay disabled');
	}

	public function testDefaultMessagesMatchTheTelegramScript()
	{
		$config = new rTelegram();
		$this->assertEquals('Torrent added: Movie', $config->render('added', 'Movie', 'ABC'), 'added default uses the common structure');
		$this->assertEquals('Torrent finished: Movie', $config->render('finished', 'Movie', 'ABC'), 'finished default matches the script');
		$this->assertEquals("Torrent finished: Movie\n[link](https://example.org/item)", $config->render('finished', 'Movie', 'ABC', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem'), 'finished default adds a comment link');
		$this->assertEquals("Torrent added: Movie\n[link](https://example.org/item)", $config->render('added', 'Movie', 'ABC', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem'), 'added default adds a comment link');
		$this->assertEquals('Torrent removed: Movie', $config->render('removed', 'Movie', 'ABC'), 'removed default matches the script');
		$this->assertEquals("Torrent removed: Movie\n[link](https://example.org/item)", $config->render('removed', 'Movie', 'ABC', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem'), 'removed default adds a comment link when available');
		$this->assertEquals("Torrent finished: Movie\n[link](https://example.org/a%28b%29c)", $config->render('finished', 'Movie', 'ABC', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fa%28b%29c'), 'link parentheses are encoded for MarkdownV2');
		$this->assertEquals('Torrent finished: Movie', $config->render('finished', 'Movie', 'ABC', 'VRS24mrkerjavascript%3Aalert(1)'), 'non-HTTP comments are not rendered as links');
	}

	public function testMarkdownV2EscapesTorrentNames()
	{
		$config = new rTelegram();
		$this->assertEquals('Torrent finished: a\\_b \\[c\\] \\(d\\)\\!', $config->render('finished', 'a_b [c] (d)!', 'ABC'), 'torrent names escape MarkdownV2 punctuation');
	}

	public function testAnEmptyTokenPreservesTheExistingSecret()
	{
		$config = new rTelegram();
		$config->token = 'keep-me';
		$config->updateFromInput(array('token' => '', 'chat_id' => '1'));
		$this->assertEquals('keep-me', $config->token, 'the blank browser token does not erase the stored secret');
		$config->updateFromInput(array('token_clear' => '1'));
		$this->assertEquals('', $config->token, 'an explicit clear request erases the token');
	}

	public function testMessageLimitDoesNotSplitUtf8()
	{
		$message = rTelegram::truncateMessage('a'.str_repeat('я', 5000));
		$this->assertEquals(4095, strlen($message), 'truncation backs up over a partial UTF-8 code point');
		$this->assertTrue(strlen($message) <= rTelegram::MAX_TEMPLATE_LENGTH, 'messages are bounded to Telegram size');
		$this->assertTrue(preg_match('//u', $message) === 1, 'message truncation keeps valid UTF-8');
	}

	public function testClientAcceptsTelegramSuccess()
	{
		$client = new rTelegramClient(function($url, $payload, $options) {
			return array('http_code' => 200, 'body' => '{"ok":true,"result":{"message_id":1}}');
		});
		$result = $client->send('secret-token', '1', 'hello');
		$this->assertTrue($result['ok'], 'a successful Telegram response is accepted');
	}

	public function testClientRejectsFailuresWithoutLeakingToken()
	{
		$cases = array(
			array('http_code' => 401, 'body' => '{"ok":false,"description":"Unauthorized"}'),
			array('http_code' => 500, 'body' => 'server error'),
			array('http_code' => 200, 'body' => 'not json'),
			array('http_code' => 200, 'body' => '{"ok":false,"description":"bad token secret-token"}'),
		);
		foreach($cases as $case)
		{
			$client = new rTelegramClient(function($url, $payload, $options) use ($case) { return $case; });
			$result = $client->send('secret-token', '1', 'hello');
			$this->assertTrue(!$result['ok'], 'invalid API responses are rejected');
			$this->assertTrue(strpos(isset($result['error']) ? $result['error'] : '', 'secret-token') === false, 'API errors redact the bot token');
		}
	}

	public function testClientRejectsTransportExceptions()
	{
		$client = new rTelegramClient(function($url, $payload, $options) {
			throw new Exception('network secret-token failure');
		});
		$result = $client->send('secret-token', '1', 'hello');
		$this->assertTrue(!$result['ok'], 'transport exceptions become a failed result');
		$this->assertTrue(strpos($result['error'], 'secret-token') === false, 'transport errors redact the bot token');
	}

	public function testNotificationCommandUsesBackgroundArgumentPassing()
	{
		$_SERVER['REMOTE_USER'] = 'Test User';
		$settings = rTorrentSettings::get();
		$settings->aliases['d.get_name'] = array('name' => 'd.name', 'prm' => 0);
		$settings->aliases['d.get_hash'] = array('name' => 'd.hash', 'prm' => 0);
		$settings->aliases['d.get_custom2'] = array('name' => 'd.custom2', 'prm' => 0);
		$config = new rTelegram();
		$command = $config->notificationCommand('finished');
		$this->assertTrue(strpos($command, 'execute.nothrow') !== false, 'notifications use execute.nothrow');
		$this->assertTrue(strpos($command, '\\$@') !== false, 'torrent data is passed through shell positional arguments');
		$this->assertTrue(strpos($command, 'notify.php') !== false, 'the background command launches notify.php');
		$this->assertTrue(strpos($command, '(cat,(d.name))') !== false, 'the command uses mapped name substitution');
		$this->assertTrue(strpos($command, '(cat,(d.hash))') !== false, 'the command uses mapped hash substitution');
		$this->assertTrue(strpos($command, '(cat,(d.custom2))') !== false, 'the command uses mapped comment substitution');
		$this->assertTrue(strpos($command, '$d.name=') === false, 'the command does not use inert name substitution');
		$this->assertTrue(strpos($command, '$d.get_hash=') === false, 'the command does not use inert hash substitution');
		$this->assertTrue(strpos($command, '$d.get_custom2=') === false, 'the command does not use inert comment substitution');
	}
}
