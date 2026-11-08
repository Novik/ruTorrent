<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-telegram-test-' . getmypid();

require_once(__DIR__ . '/../../../tests/php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/telegram/telegram.php');
require_once(__DIR__ . '/../../../plugins/telegram/queue.php');

// Inspect the temporary file while serialize() runs, before any token bytes
// have been written. A final-mode-only assertion misses the exposure window.
class TelegramStorageProbe extends rTelegram
{
    public $temporaryMode = null;

    public function __sleep()
    {
        foreach (glob(FileUtil::getSettingsPath() . '/telegram.dat.*.tmp') as $file) {
            clearstatcache(true, $file);
            $this->temporaryMode = fileperms($file) & 0777;
        }
        return array_keys(get_object_vars($this));
    }
}

class TelegramEmptyRenderer extends rTelegram
{
    public function render($event, $torrent, $hash, $comment = '', $details = [])
    {
        return " \n ";
    }
}

class TelegramTest extends TestCase
{
    public function tearDown()
    {
        $settings = FileUtil::getSettingsPath();
        $queueDir = $settings . '/telegram';
        if (is_dir($queueDir)) {
            foreach (scandir($queueDir) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    @unlink($queueDir . '/' . $entry);
                }
            }
            @rmdir($queueDir);
        }
        if (is_dir($settings)) {
            array_map('unlink', glob($settings . '/telegram.dat*'));
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
        $this->assertEquals(4, count(rTelegram::eventNames()), 'all supported event types are declared');
    }

    public function testInputAndTemplateRendering()
    {
        $config = new rTelegram();
        $config->updateFromInput([
            'enabled' => '1',
            'token' => ' token ',
            'chat_id' => '-100',
            'event_finished' => '1',
            'template_finished' => '{STATE}|{TORRENT}|{HASH}',
        ]);
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

    public function testSizeLabelAndRatioPlaceholders()
    {
        $config = new rTelegram();
        $config->templates['finished'] = '{TORRENT} ({SIZE}) - {LABEL} - ratio {RATIO}';
        $details = ['size' => '4563402752', 'label' => rawurlencode('Films & séries [HD] v1.0!'), 'ratio' => '1250'];
        $this->assertEquals('Movie \\(4\\.2 GiB\\) \\- Films & séries \\[HD\\] v1\\.0\\! \\- ratio 1\\.250', $config->render('finished', 'Movie', 'ABC', '', $details), 'size, label and ratio are filled in and escaped for MarkdownV2');
        $this->assertEquals('Movie \\(\\) \\-  \\- ratio ', $config->render('finished', 'Movie', 'ABC'), 'without details the placeholders are left empty');
        $this->assertEquals('Torrent finished: Movie', (new rTelegram())->render('finished', 'Movie', 'ABC', '', $details), 'the default template does not change');
    }

    public function testSizesAreShownAsInTheWebUi()
    {
        $config = new rTelegram();
        $config->templates['added'] = '{SIZE}';
        $sizes = [
            '0' => '0.0 KiB',
            '512' => '0.5 KiB',
            '1023' => '0.9 KiB',
            '1024' => '1.0 KiB',
            '1572864' => '1.5 MiB',
            '4563402752' => '4.2 GiB',
            '1099511627776' => '1.0 TiB',
            '1152921504606846976' => '1024.0 PiB',
        ];
        foreach ($sizes as $bytes => $shown) {
            $this->assertEquals(str_replace('.', '\\.', $shown), $config->render('added', 'Movie', 'ABC', '', ['size' => (string) $bytes]), $bytes . ' bytes are shown as ' . $shown);
        }
        foreach (['', '-1', '1e3', '12 ', 'abc'] as $bytes) {
            $this->assertEquals('Torrent added: Movie', $config->render('added', 'Movie', 'ABC', '', ['size' => $bytes]), 'a size that is not a byte count is left empty: "' . $bytes . '"');
        }
    }

    public function testRatiosAreShownAsInTheWebUi()
    {
        $config = new rTelegram();
        $config->templates['removed'] = '{RATIO}';
        foreach (['0' => '0\\.000', '1250' => '1\\.250', '999' => '0\\.999', '12345' => '12\\.345'] as $thousandths => $shown) {
            $this->assertEquals($shown, $config->render('removed', 'Movie', 'ABC', '', ['ratio' => (string) $thousandths]), 'd.ratio ' . $thousandths . ' is shown with three decimals');
        }
        foreach (['-1', '1.5', 'abc'] as $ratio) {
            $this->assertEquals('Torrent removed: Movie', $config->render('removed', 'Movie', 'ABC', '', ['ratio' => $ratio]), 'a ratio that is not d.ratio is left empty: "' . $ratio . '"');
        }
    }

    public function testLabelsAreDecodedAsInTheWebUi()
    {
        $config = new rTelegram();
        $config->templates['added'] = '[{LABEL}]';
        $this->assertEquals('\\[TV\\]', $config->render('added', 'Movie', 'ABC', '', ['label' => 'TV']), 'a plain label is kept');
        $this->assertEquals('\\[a\\+b c\\_d\\]', $config->render('added', 'Movie', 'ABC', '', ['label' => 'a+b%20c_d%20']), 'the label is URL-decoded as decodeURIComponent() does, "+" stays, and trimmed');
        $this->assertEquals('\\[日本 🎬\\]', $config->render('added', 'Movie', 'ABC', '', ['label' => rawurlencode('日本 🎬')]), 'a UTF-8 label is decoded');
        $message = $config->render('added', 'Movie', 'ABC', '', ['label' => 'bad%FF%FE']);
        $this->assertEquals('\\[\\]', $message, 'a label that does not decode to UTF-8 is left out, as in the web UI');
        $this->assertTrue(preg_match('//u', $message) === 1, 'the message stays UTF-8, which Telegram requires');
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
        $config->updateFromInput(['token' => '', 'chat_id' => '1']);
        $this->assertEquals('keep-me', $config->token, 'the blank browser token does not erase the stored secret');
        $config->updateFromInput(['token_clear' => '1']);
        $this->assertEquals('', $config->token, 'an explicit clear request erases the token');
    }

    public function testMessageLimitDoesNotSplitUtf8()
    {
        $message = rTelegram::truncateMessage('a' . str_repeat('я', 5000));
        $this->assertEquals(4095, strlen($message), 'truncation backs up over a partial UTF-8 code point');
        $this->assertTrue(strlen($message) <= rTelegram::MAX_TEMPLATE_LENGTH, 'messages are bounded to Telegram size');
        $this->assertTrue(preg_match('//u', $message) === 1, 'message truncation keeps valid UTF-8');
    }

    public function testClientAcceptsTelegramSuccess()
    {
        $client = new rTelegramClient(function ($url, $payload, $options) {
            return ['http_code' => 200, 'body' => '{"ok":true}'];
        });
        $result = $client->send('secret-token', '1', 'hello');
        $this->assertTrue($result['ok'], 'a successful Telegram response is accepted');
    }

    public function testClientRejectsFailuresWithoutLeakingToken()
    {
        $cases = [
            ['http_code' => 401, 'body' => '{"ok":false,"description":"Unauthorized"}'],
            ['http_code' => 500, 'body' => 'server error'],
            ['http_code' => 200, 'body' => 'not json'],
            ['http_code' => 200, 'body' => '{"ok":false,"description":"bad token secret-token"}'],
        ];
        foreach ($cases as $case) {
            $client = new rTelegramClient(function ($url, $payload, $options) use ($case) {
                return $case;
            });
            $result = $client->send('secret-token', '1', 'hello');
            $this->assertTrue(!$result['ok'], 'invalid API responses are rejected');
            $this->assertTrue(strpos(isset($result['error']) ? $result['error'] : '', 'secret-token') === false, 'API errors redact the bot token');
        }
    }

    public function testClientReportsTelegramDescription()
    {
        $client = new rTelegramClient(function ($url, $payload, $options) {
            return ['http_code' => 400, 'body' => '{"ok":false,"error_code":400,"description":"Bad Request: can\'t parse entities: Character \'!\' is reserved"}'];
        });
        $result = $client->send('secret-token', '1', 'hello!');
        $this->assertEquals("HTTP 400: Bad Request: can't parse entities: Character '!' is reserved", $result['error'], 'a refused request reports Telegram\'s description');
        $client = new rTelegramClient(function ($url, $payload, $options) {
            return ['http_code' => 502, 'body' => '<html>Bad gateway</html>'];
        });
        $result = $client->send('secret-token', '1', 'hello');
        $this->assertEquals('HTTP 502', $result['error'], 'a non-JSON error body still gives the HTTP status');
        $client = new rTelegramClient(function ($url, $payload, $options) {
            return ['http_code' => 404, 'body' => '{"ok":false,"description":"Not Found: secret-token"}'];
        });
        $result = $client->send('secret-token', '1', 'hello');
        $this->assertTrue(strpos($result['error'], 'secret-token') === false, 'the description is redacted too');
    }

    public function testClientRejectsTransportExceptions()
    {
        $client = new rTelegramClient(function ($url, $payload, $options) {
            throw new Exception('network secret-token failure');
        });
        $result = $client->send('secret-token', '1', 'hello');
        $this->assertTrue(!$result['ok'], 'transport exceptions become a failed result');
        $this->assertTrue(strpos($result['error'], 'secret-token') === false, 'transport errors redact the bot token');
    }

    public function testNotificationCommandEnqueuesWithoutStartingPhp()
    {
        $_SERVER['REMOTE_USER'] = 'Test User';
        $settings = rTorrentSettings::get();
        $settings->aliases['d.get_name'] = ['name' => 'd.name', 'prm' => 0];
        $settings->aliases['d.get_hash'] = ['name' => 'd.hash', 'prm' => 0];
        $settings->aliases['d.get_custom2'] = ['name' => 'd.custom2', 'prm' => 0];
        $settings->aliases['d.get_size_bytes'] = ['name' => 'd.size_bytes', 'prm' => 0];
        $settings->aliases['d.get_custom1'] = ['name' => 'd.custom1', 'prm' => 0];
        $settings->aliases['d.get_ratio'] = ['name' => 'd.ratio', 'prm' => 0];
        $config = new rTelegram();
        $command = $config->notificationCommand('finished');
        $this->assertTrue(strpos($command, 'execute.nothrow=') === 0, 'rTorrent serializes the short enqueue helpers instead of forking a burst');
        $this->assertTrue(strpos($command, 'execute.nothrow.bg') === false, 'event enqueue does not run in the background');
        $this->assertTrue(strpos($command, 'sh,-c') === false, 'torrent metadata is not interpreted as shell source');
        $this->assertTrue(strpos($command, 'enqueue.sh') !== false && strpos($command, '.php') === false, 'the event handler never starts PHP');
        $this->assertTrue(strpos($command, '(cat,(system.time_usec))') !== false, 'microsecond timestamps preserve event ordering');
        $this->assertTrue(strpos($command, 'settings/telegram') !== false, 'the command passes the per-profile queue path');
        $this->assertTrue(strpos($command, '(cat,(d.name))') !== false, 'the command uses mapped name substitution');
        $this->assertTrue(strpos($command, '(cat,(d.hash))') !== false, 'the command uses mapped hash substitution');
        $this->assertTrue(strpos($command, '(cat,(d.custom2))') !== false, 'the command uses mapped comment substitution');
        $arguments = '"finished",(cat,(d.name)),(cat,(d.hash)),(cat,(d.custom2)),(cat,(system.time_usec)),(cat,(d.size_bytes)),(cat,(d.custom1)),(cat,(d.ratio))}';
        $this->assertEquals($arguments, substr($command, -strlen($arguments)), 'size, label and ratio come in the same call, after the timestamp that ended the older arguments');
        $this->assertEquals(1, substr_count($command, 'execute'), 'the details add no process: still one execute per event');
        $this->assertTrue(strpos($command, '$d.name=') === false, 'the command does not use inert name substitution');
        $this->assertTrue(strpos($command, '$d.get_hash=') === false, 'the command does not use inert hash substitution');
        $this->assertTrue(strpos($command, '$d.get_custom2=') === false, 'the command does not use inert comment substitution');
    }

    public function testRTorrentTestsTheFailedCheckItself()
    {
        $_SERVER['REMOTE_USER'] = 'Test User';
        $settings = rTorrentSettings::get();
        foreach (['name', 'hash', 'custom2', 'size_bytes', 'custom1', 'ratio', 'complete', 'message'] as $field) {
            $settings->aliases['d.get_' . $field] = ['name' => 'd.' . $field, 'prm' => 0];
        }
        $config = new rTelegram();
        $this->assertEquals($config->notificationCommand('added'), $config->eventHandlerCommand('added'), 'the other events queue every time');
        $command = $config->eventHandlerCommand('checkfailed');
        $condition = 'branch=((and,((not,((d.complete)))),((match,((d.message)),".*hash check returned unfinished chunks.*")))),';
        $this->assertEquals($condition, substr($command, 0, strlen($condition)), 'rTorrent itself tests that the check found a complete torrent incomplete');
        $this->assertEquals('"' . addcslashes($config->notificationCommand('checkfailed'), '\\"') . '"', substr($command, strlen($condition)), 'only then does it run the usual enqueue command, quoted as one argument');
        $this->assertEquals(1, substr_count($command, 'execute'), 'one process at most, and none for a check that passes');
    }

    public function testTheFailedCheckIsOffUntilTurnedOn()
    {
        $config = new rTelegram();
        $config->events = ['added' => true, 'finished' => true, 'removed' => true];
        $config->templates = [];
        $config->normalize();
        $this->assertTrue($config->events['checkfailed'] === false, 'settings saved by an older version get the new event turned off');
        $this->assertEquals('Torrent check failed: Movie', $config->render('checkfailed', 'Movie', 'ABC'), 'the default template reads "check failed"');
        $config->templates['checkfailed'] = '{STATE} - {TORRENT} ({SIZE})';
        $this->assertEquals('check failed \\- Movie \\(4\\.2 GiB\\)', $config->render('checkfailed', 'Movie', 'ABC', '', ['size' => '4563402752']), '{STATE} reads "check failed" too');
    }

    public function testScheduledWorkerUsesBackgroundArgumentPassing()
    {
        $_SERVER['REMOTE_USER'] = 'Test User';
        $command = (new rTelegram())->workerCommand();
        $this->assertTrue(strpos($command, 'execute.nothrow.bg=') === 0, 'only the scheduled sender launches background PHP');
        $this->assertTrue(strpos($command, 'notify.php') !== false, 'the sender launches notify.php');
        $this->assertTrue(strpos($command, ',"' . User::getUser() . '"}') !== false, 'the sender receives the normalized profile as one argument');
        $this->assertTrue(strpos($command, 'sh,-c') === false, 'the sender does not build shell source');
        $this->assertTrue(rTelegram::WORKER_INTERVAL_SECONDS >= 3, 'scheduled starts are bounded independently of event count');
    }

    public function testAClearedTemplateUsesTheDefault()
    {
        $config = new rTelegram();
        $config->updateFromInput(['template_finished' => '', 'template_added' => " \n "]);
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
        $message = $config->render('finished', str_repeat('a', 4095) . '!', 'ABC');
        $this->assertEquals(str_repeat('a', 4095), $message, 'an escape that does not fit is left out as a whole');
        $config->templates['finished'] = "{TORRENT}\n{LINK}";
        $message = $config->render('finished', str_repeat('a', 4080), 'ABC', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem');
        $this->assertEquals(str_repeat('a', 4080) . "\n", $message, 'a link that does not fit is left out as a whole');
        $message = $config->render('finished', 'a' . str_repeat('_', 3000), 'ABC');
        $this->assertTrue(strlen($message) <= rTelegram::MAX_TEMPLATE_LENGTH, 'the message stays within the limit');
        $this->assertTrue((strlen($message) - strlen(rtrim($message, '\\'))) % 2 === 0, 'the message never ends on a lone backslash');
    }

    public function testStoredSettingsAreReadableByTheGroupOnly()
    {
        FileUtil::makeDirectory(FileUtil::getSettingsPath());
        $name = FileUtil::getSettingsPath() . '/telegram.dat';
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

    public function testCredentialFilesArePrivateBeforeSerializationAndPublication()
    {
        global $profileMask;
        $oldProfileMask = $profileMask;
        $oldMask = umask(0);
        $profileMask = 0777;
        $config = new TelegramStorageProbe();
        $config->token = 'secret-token';
        try {
            $this->assertTrue($config->store(), 'credential settings are stored with a permissive profile mask and umask');
            $this->assertEquals(0640, $config->temporaryMode, 'the temporary file is already private before serialization');
            $name = FileUtil::getSettingsPath() . '/telegram.dat';
            clearstatcache(true, $name);
            $this->assertEquals(0640, fileperms($name) & 0777, 'publication preserves the private mode');
            $this->assertEquals(0, umask(), 'the cache restores the process umask');
            $this->assertEquals(0777, $profileMask, 'credential storage does not change the global profile mask');
        } finally {
            umask($oldMask);
            $profileMask = $oldProfileMask;
        }
    }

    public function testEmptyRenderedTemplatesFallBackToTheDefault()
    {
        $config = new rTelegram();
        foreach (['{LINK}', " \n{LINK} \n", '{TORRENT}{HASH}'] as $template) {
            $config->templates['added'] = $template;
            $this->assertEquals('Torrent added: ', $config->render('added', '', ''), 'a template that renders empty falls back to the default');
        }
        $config->templates['added'] = '{LINK}';
        $this->assertEquals('Torrent added: Movie', $config->render('added', 'Movie', 'HASH'), 'a missing link does not produce an empty message');
        $this->assertEquals('[link](https://example.org/item)', $config->render('added', 'Movie', 'HASH', 'VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem'), 'a nonempty link-only message still uses the custom template');
    }

    public function testQueueDrainsLinkOnlyTemplatesWithoutComments()
    {
        $queue = new rTelegramQueue();
        $config = new rTelegram();
        $config->enabled = true;
        $config->token = 'secret-token';
        $config->chatId = '1';
        $config->events['added'] = true;
        $config->templates['added'] = '{LINK}';
        $this->assertTrue($this->enqueue($queue, 'added', 'Movie', 'HASH'), 'a link-less event is queued');
        $messages = [];
        $client = new rTelegramClient(function ($url, $payload, $options) use (&$messages) {
            $messages[] = $payload['text'];
            return ['http_code' => 200, 'body' => '{"ok":true}'];
        });
        $this->assertTrue($queue->drain($config, $client, 0, 0), 'draining terminates and sends the fallback');
        $this->assertEquals(['Torrent added: Movie'], $messages, 'the queued event uses the default message');
        $this->assertEquals(0, $queue->getPendingCount(), 'no empty-message item is stranded');
    }

    public function testQueueDiscardsEmptyMessagesDefensively()
    {
        $queue = new rTelegramQueue();
        $config = new TelegramEmptyRenderer();
        $config->enabled = true;
        $config->token = 'secret-token';
        $config->chatId = '1';
        $config->events['added'] = true;
        $this->assertTrue($this->enqueue($queue, 'added', 'Movie', 'HASH'), 'an event is queued for the empty renderer');
        $method = new ReflectionMethod('rTelegramQueue', 'renderChunks');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $this->assertEquals([], $method->invoke($queue, $config), 'no empty chunks are sent');
        $this->assertEquals(0, $queue->getPendingCount(), 'an unexpectedly empty rendering cannot make the drain loop spin');
    }

    public function testAFailedNotificationIsLogged()
    {
        global $log_file;
        $saved = $log_file;
        $log_file = sys_get_temp_dir() . '/rutorrent-telegram-test-' . getmypid() . '.log';
        @unlink($log_file);
        $config = new rTelegram();
        $config->enabled = true;
        $config->token = 'secret-token';
        $config->chatId = '1';
        $config->events['finished'] = true;
        $client = new rTelegramClient(function ($url, $payload, $options) {
            return ['http_code' => 400, 'body' => '{"ok":false,"description":"Bad Request: chat secret-token not found"}'];
        });
        $result = $config->notify('finished', 'Movie', 'ABC', '', $client);
        $this->assertTrue(!$result['ok'], 'the refused notification is reported');
        $log = is_file($log_file) ? file_get_contents($log_file) : '';
        $this->assertTrue(strpos($log, 'telegram: finished notification failed: HTTP 400: Bad Request: chat [redacted] not found') !== false, 'the ruTorrent log gets Telegram\'s explanation');
        $this->assertTrue(strpos($log, 'secret-token') === false, 'the log does not get the bot token');
        $sent = false;
        $client = new rTelegramClient(function ($url, $payload, $options) use (&$sent) {
            $sent = true;
            return ['http_code' => 200, 'body' => '{"ok":true}'];
        });
        $this->assertTrue($config->notify('added', 'Movie', 'ABC', '', $client) === null && !$sent, 'a disabled event sends nothing');
        @unlink($log_file);
        $log_file = $saved;
    }

    public function testQueueBatchesBurstsIntoTelegramSizedMessages()
    {
        FileUtil::makeDirectory(FileUtil::getSettingsPath());
        $queue = new rTelegramQueue();
        $config = new rTelegram();
        $config->enabled = true;
        $config->token = 'secret-token';
        $config->chatId = '1';
        $config->events['removed'] = true;
        $queued = true;
        for ($i = 0; $i < 200; $i++) {
            $queued = $this->enqueue($queue, 'removed', sprintf('Bulk torrent %03d', $i), 'HASH' . $i) && $queued;
        }
        $this->assertTrue($queued, 'all events are queued');

        $queuedFiles = glob($queue->getDirectory() . '/*.event');
        $this->assertEquals(200, count($queuedFiles), 'each event is stored as a separate durable queue item');
        clearstatcache();
        $this->assertEquals('700', sprintf('%o', fileperms($queue->getDirectory()) & 0777), 'the queue directory is private');
        $this->assertEquals('600', sprintf('%o', fileperms($queuedFiles[0]) & 0777), 'queue records are private');
        $this->assertTrue(strpos(file_get_contents($queuedFiles[0]), 'secret-token') === false, 'the bot token is not stored in queue records');

        $messages = [];
        $client = new rTelegramClient(function ($url, $payload, $options) use (&$messages) {
            $messages[] = $payload['text'];
            return ['http_code' => 200, 'body' => '{"ok":true}'];
        });
        $this->assertTrue($queue->drain($config, $client, 0, 0), 'the single worker drains the queue');
        $this->assertTrue(count($messages) > 0 && count($messages) < 200, 'a burst is coalesced into fewer Telegram requests');
        $allMessages = implode("\n", $messages);
        $allEventsRetained = true;
        foreach (range(0, 199) as $i) {
            $allEventsRetained = (strpos($allMessages, sprintf('Bulk torrent %03d', $i)) !== false) && $allEventsRetained;
        }
        $this->assertTrue($allEventsRetained, 'the batches retain every torrent notification');
        $messagesWithinLimit = true;
        foreach ($messages as $message) {
            $messagesWithinLimit = (strlen($message) <= rTelegram::MAX_TEMPLATE_LENGTH) && $messagesWithinLimit;
        }
        $this->assertTrue($messagesWithinLimit, 'each batch respects Telegram message size');
        $this->assertEquals(0, $queue->getPendingCount(), 'successfully sent queue items are removed');
    }

    public function testDrainWithoutLoadedProfileConfigKeepsItemsForRetry()
    {
        FileUtil::makeDirectory(FileUtil::getSettingsPath());
        $queue = new rTelegramQueue();
        $this->assertTrue($this->enqueue($queue, 'added', 'Movie', 'HASH'), 'an event is queued');
        $this->assertTrue(!$queue->drain(null, null, 0, 0), 'the worker refuses to drain without profile config loaded at file scope');
        $this->assertEquals(1, $queue->getPendingCount(), 'the queued item is kept for a correctly initialized worker');
    }

    public function testQueueUsesOneNonblockingSenderLock()
    {
        FileUtil::makeDirectory(FileUtil::getSettingsPath());
        $queue = new rTelegramQueue();
        $config = new rTelegram();
        $config->enabled = true;
        $config->token = 'secret-token';
        $config->chatId = '1';
        $config->events['added'] = true;
        $this->assertTrue($this->enqueue($queue, 'added', 'Movie', 'HASH'), 'an event is queued');

        $lock = fopen($queue->getDirectory() . '/worker.lock', 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB), 'the test holds the worker lock');
        $sent = 0;
        $client = new rTelegramClient(function ($url, $payload, $options) use (&$sent) {
            $sent++;
            return ['http_code' => 200, 'body' => '{"ok":true}'];
        });
        $this->assertTrue(!$queue->drain($config, $client, 0, 0), 'a second sender exits instead of waiting');
        $this->assertEquals(0, $sent, 'the locked-out process does not send');
        flock($lock, LOCK_UN);
        fclose($lock);
        $this->assertTrue($queue->drain($config, $client, 0, 0), 'the lock holder can drain after release');
        $this->assertEquals(1, $sent, 'the queued notification is sent once');
    }

    public function testShellEnqueuePreservesMetadataWithoutEvaluatingIt()
    {
        $queue = new rTelegramQueue();
        $marker = sys_get_temp_dir() . '/telegram-injection-' . getmypid();
        $torrent = 'name";touch ' . $marker . ';$(touch ' . $marker . ')`touch ' . $marker . '`' . "\nsecond line";
        $comment = "VRS24mrkerhttps%3A%2F%2Fexample.org%2Fitem\nextra";
        $this->assertTrue($this->enqueue($queue, 'removed', $torrent, 'HASH', $comment), 'shell metacharacters and newlines are queued as data');
        $files = glob($queue->getDirectory() . '/*.event');
        $record = explode("\0", file_get_contents($files[0]));
        $this->assertEquals(['removed', $torrent, 'HASH', $comment, '', '', '', ''], $record, 'NUL-separated records preserve every argument');
        $this->assertTrue(!file_exists($marker), 'no shell injection marker is created');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function testShellEnqueueStoresSizeLabelAndRatio()
    {
        $queue = new rTelegramQueue();
        $label = 'a;$(touch x)`id`' . "\nb%20c";
        $this->assertTrue($this->enqueue($queue, 'removed', 'Movie', 'HASH', '', ['4563402752', $label, '1250']), 'the current handler\'s nine arguments are queued');
        $files = glob($queue->getDirectory() . '/*.event');
        $this->assertEquals(['removed', 'Movie', 'HASH', '', '4563402752', $label, '1250', ''], explode("\0", file_get_contents($files[0])), 'size, label and ratio are stored verbatim after the comment');
        unlink($files[0]);
        $this->assertTrue($this->enqueue($queue, 'removed', 'Movie', 'HASH'), 'the six arguments of a handler an older version registered are still queued');
        $files = glob($queue->getDirectory() . '/*.event');
        $this->assertEquals(['removed', 'Movie', 'HASH', '', '', '', '', ''], explode("\0", file_get_contents($files[0])), 'that event is stored with an empty size, label and ratio');
        unlink($files[0]);
        $this->assertTrue(!$this->enqueue($queue, 'removed', 'Movie', 'HASH', '', ['1']) && !$this->enqueue($queue, 'removed', 'Movie', 'HASH', '', ['1', '2']), 'another number of arguments is refused');
        $this->assertEquals(0, $queue->getPendingCount(), 'nothing is queued for a refused event');
    }

    public function testQueueRendersEachEventWithItsOwnDetails()
    {
        $queue = new rTelegramQueue();
        $config = new rTelegram();
        $config->enabled = true;
        $config->token = 'secret-token';
        $config->chatId = '1';
        $config->events['removed'] = true;
        $config->templates['removed'] = '{TORRENT} | {SIZE} | {LABEL} | {RATIO}';
        $this->assertTrue($this->enqueue($queue, 'removed', 'First', 'HASH1', '', ['1572864', 'TV%20%26%20Films', '2500']), 'a first event is queued');
        $this->assertTrue($this->enqueue($queue, 'removed', 'Second', 'HASH2', '', ['4563402752', '', '0']), 'a second event is queued');
        // A record queued by the previous version, before an update: four fields.
        file_put_contents($queue->getDirectory() . '/9999999999999999.old.event', "removed\0Third\0HASH3\0\0");
        $messages = [];
        $client = new rTelegramClient(function ($url, $payload, $options) use (&$messages) {
            $messages[] = $payload['text'];
            return ['http_code' => 200, 'body' => '{"ok":true}'];
        });
        $this->assertTrue($queue->drain($config, $client, 0, 0), 'the queue is drained');
        $this->assertEquals(["First \\| 1\\.5 MiB \\| TV & Films \\| 2\\.500\n\nSecond \\| 4\\.2 GiB \\|  \\| 0\\.000\n\nThird \\|  \\|  \\| "], $messages, 'one batch, in which each event shows its own size, label and ratio, and a record from before the update is still sent');
        $this->assertEquals(0, $queue->getPendingCount(), 'every record is sent');
    }

    public function testAFailedCheckIsQueuedAndSent()
    {
        $queue = new rTelegramQueue();
        $config = new rTelegram();
        $config->enabled = true;
        $config->token = 'secret-token';
        $config->chatId = '1';
        $config->events['checkfailed'] = true;
        $this->assertTrue($this->enqueue($queue, 'checkfailed', 'Movie', 'HASH', '', ['1048576', 'TV', '0']), 'enqueue.sh accepts the failed check');
        $messages = [];
        $client = new rTelegramClient(function ($url, $payload, $options) use (&$messages) {
            $messages[] = $payload['text'];
            return ['http_code' => 200, 'body' => '{"ok":true}'];
        });
        $this->assertTrue($queue->drain($config, $client, 0, 0), 'the queue is drained');
        $this->assertEquals(['Torrent check failed: Movie'], $messages, 'the sender sends it');
    }

    public function testShellEnqueueEnforcesQueueCapacityAndRejectsInvalidEvents()
    {
        $queue = new rTelegramQueue();
        $this->assertTrue(!$this->enqueue($queue, 'unknown', 'Movie', 'HASH'), 'invalid events are refused');
        $this->assertTrue($this->enqueue($queue, 'added', 'Movie', 'HASH'), 'a valid event creates the queue');
        $directory = $queue->getDirectory();
        for ($i = $queue->getPendingCount(); $i < rTelegramQueue::MAX_QUEUE_ITEMS; $i++) {
            file_put_contents($directory . '/capacity-' . $i . '.event', "added\0Movie\0HASH\0\0");
        }
        $this->assertTrue(!$this->enqueue($queue, 'added', 'Overflow', 'HASH'), 'the shell helper refuses events once the queue is full');
        $this->assertEquals(rTelegramQueue::MAX_QUEUE_ITEMS, $queue->getPendingCount(), 'the queue never exceeds its capacity');
        foreach (glob($directory . '/*.event') as $file) {
            unlink($file);
        }
    }

    public function testScheduledContenderExitsBeforeLoadingTheProfile()
    {
        $queue = new rTelegramQueue();
        $lock = $queue->acquireWorkerLock();
        $this->assertTrue(is_resource($lock), 'the active sender holds the lock before PHP configuration loads');
        $profile = sys_get_temp_dir() . '/telegram-skipped-profile-' . getmypid();
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../../../plugins/telegram/notify.php', $queue->getDirectory(), 'contender'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['RU_PROFILE_PATH' => $profile],
        );
        try {
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertEquals(0, proc_close($process), 'the scheduled contender exits successfully without waiting');
            $this->assertEquals('', $output, 'the skipped contender produces no bootstrap output');
            $this->assertTrue(!is_dir($profile), 'the losing sender never initializes the profile');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testClientReturnsTelegramRetryAfter()
    {
        $client = new rTelegramClient(function ($url, $payload, $options) {
            return ['http_code' => 429, 'body' => '{"ok":false,"error_code":429,"description":"Too Many Requests","parameters":{"retry_after":7}}'];
        });
        $result = $client->send('secret-token', '1', 'hello');
        $this->assertTrue(!$result['ok'], 'a rate-limited response is a failure');
        $this->assertEquals(7, $result['retry_after'], 'the worker can honor Telegram retry_after');
    }
    public function testShellEnqueueKeepsFullMicrosecondTimestamp()
    {
        $queue = new rTelegramQueue();
        $before = microtime(true) * 1000000;
        if (!$this->enqueue($queue, 'added', 'Timestamp test', 'HASH')) {
            throw new RuntimeException('The shell helper rejected the generated microsecond timestamp');
        }
        $after = microtime(true) * 1000000;
        $files = glob($queue->getDirectory() . '/*.event');
        $this->assertEquals(1, count($files), 'the timestamp test creates one event record');
        if (count($files) !== 1) {
            return;
        }
        $timestamp = strstr(basename($files[0]), '.', true);
        $this->assertTrue(preg_match('/^[0-9]{16,}$/D', $timestamp) === 1, 'the filename retains the full decimal microsecond timestamp on every word size');
        $this->assertTrue((float) $timestamp >= floor($before) && (float) $timestamp <= ceil($after), 'the timestamp represents the enqueue time without integer overflow');
        unlink($files[0]);
    }

    /** Without $details, the arguments of a handler that an older version registered. */
    private function enqueue($queue, $event, $torrent, $hash, $comment = '', $details = null)
    {
        // Epoch microseconds exceed a 32-bit integer; format directly as a string.
        $timestamp = sprintf('%.0f', microtime(true) * 1000000);
        $process = proc_open(array_merge([
            'sh', __DIR__ . '/../../../plugins/telegram/enqueue.sh',
            $queue->getDirectory(), $event, $torrent, $hash, $comment,
            $timestamp,
        ], $details === null ? [] : $details), [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        return is_resource($process) && proc_close($process) === 0;
    }
}
