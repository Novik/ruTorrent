<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * log_history.dat holds a LogHandler, and a LogHandler holds the rCache it was
 * loaded with, so every file written before #3333 names both classes. The fix
 * declares rCache, which is what lets those files keep loading -- an install
 * that updates does not have to lose its Log tab or delete the file.
 *
 * The file here is written as the pre-fix code wrote it: serialize() of the
 * same object graph, which is what rCache::set() puts on disk. Reading it back
 * goes through the endpoint, in its own process, the way a request does.
 */
class LogHistoryUpgradeTest extends TestCase
{
	private $base;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-log-upgrade-' . getmypid();
		@mkdir($this->base . '/profile/settings', 0777, true);
		file_put_contents($this->base . '/request.php', '<?php
$_ENV["RU_PROFILE_PATH"] = getenv("RU_PROFILE_PATH");
$_ENV["RU_LOG_FILE"] = getenv("RU_LOG_FILE");
$_SERVER["REQUEST_METHOD"] = getenv("LOG_HISTORY_METHOD");
$_GET = array();
$_POST = json_decode(getenv("LOG_HISTORY_POST"), true);
chdir(getenv("LOG_HISTORY_PLUGIN"));
require "log_history.php";
');
	}

	public function tearDown()
	{
		foreach (array('profile/settings/log_history.dat', 'profile/settings/log_history.dat.lock',
			'errors.log', 'request.php') as $file) {
			@unlink($this->base . '/' . $file);
		}
		@rmdir($this->base . '/profile/settings');
		@rmdir($this->base . '/profile');
		@rmdir($this->base);
	}

	private function request($method, $post = array())
	{
		$env = array(
			'RU_PROFILE_PATH' => $this->base . '/profile',
			'RU_LOG_FILE' => $this->base . '/errors.log',
			'LOG_HISTORY_METHOD' => $method,
			'LOG_HISTORY_POST' => json_encode($post),
			'LOG_HISTORY_PLUGIN' => realpath(__DIR__ . '/../../plugins/log_history'),
		);
		$process = proc_open(array(PHP_BINARY, '-d', 'display_errors=stderr', $this->base . '/request.php'),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $env);
		fclose($pipes[0]);
		$out = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);
		return json_decode($out, true);
	}

	/** The bytes a pre-fix install has on disk: the handler and its cache. */
	private function plantPreFixFile($message)
	{
		$writer = $this->base . '/write.php';
		file_put_contents($writer, '<?php
$_ENV["RU_PROFILE_PATH"] = getenv("RU_PROFILE_PATH");
chdir(getenv("LOG_HISTORY_PLUGIN"));
require_once "../../php/cache.php";
require_once "log_history.php";
$handler = new LogHandler(new rCache());
$handler->logs = array(array("message" => getenv("LOG_MESSAGE"), "status" => "info", "timestamp" => 1790000000));
file_put_contents(getenv("RU_PROFILE_PATH")."/settings/log_history.dat", serialize($handler));
');
		$env = array(
			'RU_PROFILE_PATH' => $this->base . '/profile',
			'LOG_HISTORY_PLUGIN' => realpath(__DIR__ . '/../../plugins/log_history'),
			'LOG_MESSAGE' => $message,
		);
		$process = proc_open(array(PHP_BINARY, $writer),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $env);
		fclose($pipes[0]);
		$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);
		@unlink($writer);
		return $out;
	}

	private function classesInFile()
	{
		$blob = (string)@file_get_contents($this->base . '/profile/settings/log_history.dat');
		preg_match_all('/O:\d+:"([^"]+)"/', $blob, $m);
		return array_values(array_unique($m[1]));
	}

	private function messagesFrom($answer)
	{
		$messages = array();
		foreach ((array)(isset($answer['logs']) ? $answer['logs'] : array()) as $entry) {
			if (is_array($entry) && isset($entry['message'])) {
				$messages[] = $entry['message'];
			}
		}
		return $messages;
	}

	public function testAFileFromBeforeTheFixStillLoads()
	{
		// Requiring the plugin runs its endpoint, so the writer answers a
		// request on the way past; the file it leaves is what matters.
		$this->plantPreFixFile('from the old build');

		$classes = $this->classesInFile();
		$this->assertTrue(in_array('LogHandler', $classes, true),
			'the file names the handler: ' . implode(',', $classes));
		$this->assertTrue(in_array('rCache', $classes, true),
			'and the cache it holds: ' . implode(',', $classes));

		$answer = $this->request('GET');
		$this->assertTrue(in_array('from the old build', $this->messagesFrom($answer), true),
			'the message written before the fix comes back: ' . json_encode($answer));
		$this->assertEquals('', trim((string)@file_get_contents($this->base . '/errors.log')),
			'and nothing was refused');
	}

	public function testSavingAfterwardsKeepsTheOlderMessage()
	{
		$this->plantPreFixFile('from the old build');
		$this->request('POST', array('message' => 'from the new build', 'status' => 'info'));

		$messages = $this->messagesFrom($this->request('GET'));
		$this->assertTrue(in_array('from the old build', $messages, true),
			'the old message survives the next save: ' . implode(',', $messages));
		$this->assertTrue(in_array('from the new build', $messages, true),
			'alongside the new one: ' . implode(',', $messages));
	}
}
