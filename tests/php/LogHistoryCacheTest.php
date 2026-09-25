<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * plugins/log_history keeps the Log tab in a cache file, log_history.dat,
 * which it writes and reads back through rCache. The endpoint is a script
 * that answers the request it is run for, so each case here runs it the way
 * a request does: once to save a message, once to read the list back, each
 * in its own process against one profile directory.
 */
class LogHistoryCacheTest extends TestCase
{
	private $base;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-log-history-' . getmypid();
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

	private function refusals()
	{
		$log = @file_get_contents($this->base . '/errors.log');
		return $log === false ? 0 : substr_count($log, 'log_history.dat');
	}

	public function testASavedMessageIsReadBackOnTheNextRequest()
	{
		$saved = $this->request('POST', array('message' => 'first entry', 'status' => 'info'));
		$this->assertEquals('success', $saved['status'] ?? null);
		$this->assertTrue(is_file($this->base . '/profile/settings/log_history.dat'),
			'the message is stored in log_history.dat');

		$read = $this->request('GET');
		$messages = array_column($read['logs'] ?? array(), 'message');
		$this->assertEquals(array('first entry'), $messages);
		$this->assertEquals(0, $this->refusals());
	}

	public function testMessagesAccumulateAcrossRequests()
	{
		@unlink($this->base . '/profile/settings/log_history.dat');
		$this->request('POST', array('message' => 'first entry', 'status' => 'info'));
		$this->request('POST', array('message' => 'second entry', 'status' => 'info'));
		$this->request('POST', array('message' => 'third entry', 'status' => 'error'));

		$read = $this->request('GET');
		$messages = array_column($read['logs'] ?? array(), 'message');
		$this->assertEquals(array('first entry', 'second entry', 'third entry'), $messages);
		$this->assertEquals(0, $this->refusals());
	}
}
