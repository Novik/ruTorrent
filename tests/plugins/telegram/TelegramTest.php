<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir().'/rutorrent-telegram-test-'.getmypid();

require_once(__DIR__.'/../../../tests/php/TestCase.php');
require_once(__DIR__.'/../../../plugins/telegram/telegram.php');

class TelegramTest extends TestCase
{
	public function tearDown()
	{
		$settings = FileUtil::getSettingsPath();
		if(is_dir($settings))
		{
			array_map('unlink', glob($settings.'/telegram.dat*'));
			@rmdir($settings);
		}
		@rmdir($_ENV['RU_PROFILE_PATH']);
	}

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
		$this->assertEquals('finished\\|name "with" $danger\\|ABC', $config->render('finished', 'name "with" $danger', 'ABC'), 'placeholders are rendered as plain text, and the template\'s "|" is escaped for MarkdownV2');
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

	public function testTemplateTextIsEscapedForMarkdownV2()
	{
		$config = new rTelegram();
		$config->templates['finished'] = "✅ Done! {TORRENT} (100%).\n{LINK}";
		$this->assertEquals("✅ Done\\! Movie\\_1 \\(100%\\)\\.\n[link](https://example.org/item)", $config->render('finished', 'Movie_1', 'ABC', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem'), 'reserved characters of the template text are escaped, the link stays MarkdownV2');
		$config->templates['added'] = '{unknown} {HASH} - {STATE}';
		$this->assertEquals('\\{unknown\\} ABC \\- added', $config->render('added', 'Movie', 'ABC'), 'an unknown placeholder is escaped as text');
	}

	public function testStorageStaysReadableByTheGroupOnly()
	{
		$this->assertTrue((rTelegram::STORAGE_MODE & 0040) !== 0, 'the rTorrent user can read the settings through the group');
		$this->assertTrue((rTelegram::STORAGE_MODE & 0007) === 0, 'other local users cannot read the bot token');
		$this->assertTrue((rTelegram::STORAGE_MODE & 0022) === 0, 'only the owner can write the settings');
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

	public function testClientReportsTelegramDescription()
	{
		$client = new rTelegramClient(function($url, $payload, $options) {
			return array('http_code' => 400, 'body' => '{"ok":false,"error_code":400,"description":"Bad Request: can\'t parse entities: Character \'!\' is reserved"}');
		});
		$result = $client->send('secret-token', '1', 'hello!');
		$this->assertEquals("HTTP 400: Bad Request: can't parse entities: Character '!' is reserved", $result['error'], 'a refused request reports Telegram\'s description');
		$client = new rTelegramClient(function($url, $payload, $options) {
			return array('http_code' => 502, 'body' => '<html>Bad gateway</html>');
		});
		$result = $client->send('secret-token', '1', 'hello');
		$this->assertEquals('HTTP 502', $result['error'], 'a non-JSON error body still gives the HTTP status');
		$client = new rTelegramClient(function($url, $payload, $options) {
			return array('http_code' => 404, 'body' => '{"ok":false,"description":"Not Found: secret-token"}');
		});
		$result = $client->send('secret-token', '1', 'hello');
		$this->assertTrue(strpos($result['error'], 'secret-token') === false, 'the description is redacted too');
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

	public function testAClearedTemplateUsesTheDefault()
	{
		$config = new rTelegram();
		$config->updateFromInput(array('template_finished' => '', 'template_added' => " \n "));
		$this->assertEquals(rTelegram::defaultTemplate('finished'), $config->templates['finished'], 'an empty template is saved as the default one');
		$this->assertEquals(rTelegram::defaultTemplate('added'), $config->templates['added'], 'a template of only spaces is saved as the default one');
		$this->assertEquals('Torrent finished: Movie', $config->render('finished', 'Movie', 'ABC'), 'the message is not empty');
		$config->templates['removed'] = '';
		$config->normalize();
		$this->assertEquals(rTelegram::defaultTemplate('removed'), $config->templates['removed'], 'an empty template already stored also falls back to the default one');
	}

	public function testTheCutNeverLeavesALoneBackslashOrHalfALink()
	{
		$config = new rTelegram();
		$config->templates['finished'] = '{TORRENT}';
		$message = $config->render('finished', str_repeat('a', 4095).'!', 'ABC');
		$this->assertEquals(str_repeat('a', 4095), $message, 'an escape that does not fit is left out as a whole');
		$config->templates['finished'] = "{TORRENT}\n{LINK}";
		$message = $config->render('finished', str_repeat('a', 4080), 'ABC', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem');
		$this->assertEquals(str_repeat('a', 4080)."\n", $message, 'a link that does not fit is left out as a whole');
		$message = $config->render('finished', 'a'.str_repeat('_', 3000), 'ABC');
		$this->assertTrue(strlen($message) <= rTelegram::MAX_TEMPLATE_LENGTH, 'the message stays within the limit');
		$this->assertTrue((strlen($message) - strlen(rtrim($message, '\\'))) % 2 === 0, 'the message never ends on a lone backslash');
	}

	public function testStoredSettingsAreReadableByTheGroupOnly()
	{
		FileUtil::makeDirectory(FileUtil::getSettingsPath());
		$name = FileUtil::getSettingsPath().'/telegram.dat';
		$config = new rTelegram();
		$config->token = 'secret-token';
		$this->assertTrue($config->store(), 'the settings are stored');
		clearstatcache();
		$this->assertEquals('640', sprintf('%o', fileperms($name) & 0777), 'the stored file is readable by the group, not by other users');
		chmod($name, 0666);
		rTelegram::load();
		clearstatcache();
		$this->assertEquals('640', sprintf('%o', fileperms($name) & 0777), 'loading the settings protects a file stored with wider rights');
		unlink($name);
	}

	public function testAFailedNotificationIsLogged()
	{
		global $log_file;
		$saved = $log_file;
		$log_file = sys_get_temp_dir().'/rutorrent-telegram-test-'.getmypid().'.log';
		@unlink($log_file);
		$config = new rTelegram();
		$config->enabled = true;
		$config->token = 'secret-token';
		$config->chatId = '1';
		$config->events['finished'] = true;
		$client = new rTelegramClient(function($url, $payload, $options) {
			return array('http_code' => 400, 'body' => '{"ok":false,"description":"Bad Request: chat secret-token not found"}');
		});
		$result = $config->notify('finished', 'Movie', 'ABC', '', $client);
		$this->assertTrue(!$result['ok'], 'the refused notification is reported');
		$log = is_file($log_file) ? file_get_contents($log_file) : '';
		$this->assertTrue(strpos($log, 'telegram: finished notification failed: HTTP 400: Bad Request: chat [redacted] not found') !== false, 'the ruTorrent log gets Telegram\'s explanation');
		$this->assertTrue(strpos($log, 'secret-token') === false, 'the log does not get the bot token');
		$sent = false;
		$client = new rTelegramClient(function($url, $payload, $options) use (&$sent) {
			$sent = true;
			return array('http_code' => 200, 'body' => '{"ok":true}');
		});
		$this->assertTrue($config->notify('added', 'Movie', 'ABC', '', $client) === null && !$sent, 'a disabled event sends nothing');
		@unlink($log_file);
		$log_file = $saved;
	}
}
