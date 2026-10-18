<?php

require_once(__DIR__ . '/TestCase.php');

class ConfigTest extends TestCase
{
	private $hadProfileMask;
	private $oldProfileMask;
	private $hadLocalhosts;
	private $oldLocalhosts;

	public function setUp()
	{
		$this->hadProfileMask = array_key_exists('RU_PROFILE_MASK', $_ENV);
		$this->oldProfileMask = $_ENV['RU_PROFILE_MASK'] ?? null;
		$this->hadLocalhosts = array_key_exists('RU_LOCALHOSTS', $_ENV);
		$this->oldLocalhosts = $_ENV['RU_LOCALHOSTS'] ?? null;
	}

	public function tearDown()
	{
		if ($this->hadProfileMask) {
			$_ENV['RU_PROFILE_MASK'] = $this->oldProfileMask;
		} else {
			unset($_ENV['RU_PROFILE_MASK']);
		}
		if ($this->hadLocalhosts) {
			$_ENV['RU_LOCALHOSTS'] = $this->oldLocalhosts;
		} else {
			unset($_ENV['RU_LOCALHOSTS']);
		}
	}

	private function configuredProfileMask($value, &$diagnostic = null)
	{
		$_ENV['RU_PROFILE_MASK'] = $value;
		$errorLog = tempnam(sys_get_temp_dir(), 'rut-mask-log-');
		$oldErrorLog = ini_get('error_log');
		ini_set('error_log', $errorLog);
		try {
			include(__DIR__ . '/../../conf/config.php');
		} finally {
			$diagnostic = is_file($errorLog) ? file_get_contents($errorLog) : '';
			ini_set('error_log', $oldErrorLog);
			unlink($errorLog);
		}
		return $profileMask;
	}

	public function testEnvironmentProfileMaskIsParsedAsOctalInteger()
	{
		$mask = $this->configuredProfileMask('0770');
		$this->assertTrue(is_int($mask), 'RU_PROFILE_MASK becomes an integer before permission operations');
		$this->assertEquals(0770, $mask, 'RU_PROFILE_MASK=0770 keeps its documented octal meaning');
	}

	public function testEmptyEnvironmentProfileMaskUsesTheDefault()
	{
		$this->assertEquals(
			0777,
			$this->configuredProfileMask(''),
			'An empty RU_PROFILE_MASK behaves like an unset value instead of crashing requests'
		);
	}

	public function testAMaskThatCouldNotBeReadSaysSo()
	{
		// The fallback is 0777, which is wider than any mask an admin sets one
		// for. Taking it silently turns a typo into world-writable profiles.
		$diagnostic = null;
		$mask = $this->configuredProfileMask('02770', $diagnostic);

		$this->assertEquals(0777, $mask, 'a mask that could not be read falls back to the default');
		$this->assertTrue($diagnostic !== '', 'and the fallback is reported');
		$this->assertTrue(strpos($diagnostic, 'RU_PROFILE_MASK') !== false,
			'and the report names the setting: ' . var_export($diagnostic, true));
	}

	/**
	 * Naming the setting only narrows the hunt to one variable; the operator
	 * still has to find where it was set to see what it says. The diagnostic
	 * carries the value it rejected so that reading the log is enough.
	 */
	public function testTheDiagnosticNamesTheValueItRejected()
	{
		$diagnostic = null;
		$this->configuredProfileMask('02770', $diagnostic);

		$this->assertTrue(strpos($diagnostic, '02770') !== false,
			'the rejected value is in the diagnostic: ' . var_export($diagnostic, true));
	}

	/**
	 * error_log() writes one entry per call, and the value is read from the
	 * environment, so it can carry anything. A raw newline in it would split
	 * the entry in two and let the tail of the value read as a log line of
	 * its own.
	 */
	public function testTheDiagnosticStaysOnOneLineWhateverTheValue()
	{
		$diagnostic = null;
		$this->configuredProfileMask("0770\n0777", $diagnostic);

		// Asserted first: without it the two below pass on a diagnostic that
		// never mentions the value, which is the thing being tested.
		$this->assertTrue(strpos($diagnostic, '0770') !== false,
			'the value reached the diagnostic: ' . var_export($diagnostic, true));
		$this->assertEquals(1, substr_count($diagnostic, "\n"),
			'and the entry is still one line: ' . var_export($diagnostic, true));
		$this->assertTrue(strpos($diagnostic, '\n') !== false,
			'because the newline is shown escaped rather than written raw');
	}

	/**
	 * config.php is loaded by every request, so the diagnostic is written on
	 * every request for as long as the setting stays wrong. What it writes
	 * cannot be whatever length the environment happens to carry.
	 */
	public function testTheDiagnosticBoundsAnOverlongValue()
	{
		$diagnostic = null;
		$this->configuredProfileMask(str_repeat('7', 4096), $diagnostic);

		// Asserted first, for the same reason as above: a diagnostic that drops
		// the value entirely is short too.
		$this->assertTrue(strpos($diagnostic, '7777') !== false,
			'the value reached the diagnostic: ' . var_export($diagnostic, true));
		$this->assertTrue(strlen($diagnostic) < 256,
			'but not whole: ' . strlen($diagnostic) . ' bytes for a 4096-character value');
	}

	public function testInvalidMaskDiagnosticLeavesCliOutputClean()
	{
		$errorLog = tempnam(sys_get_temp_dir(), 'rut-mask-log-');
		$code = '$_ENV["RU_PROFILE_MASK"] = "02770"; require '
			. var_export(__DIR__ . '/../../conf/config.php', true) . ';';
		$process = proc_open(array(PHP_BINARY, '-d', 'variables_order=GPCS',
			'-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0',
			'-d', 'error_log=' . $errorLog, '-r', $code), array(
			0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')
		), $pipes);
		try {
			if (!is_resource($process))
				throw new RuntimeException('Could not start the configuration CLI fixture');
			fclose($pipes[0]);
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$status = proc_close($process);
			$diagnostic = file_get_contents($errorLog);
		} finally {
			unlink($errorLog);
		}

		$this->assertEquals(0, $status, 'an invalid mask does not terminate configuration loading');
		$this->assertEquals('', $stdout, 'the configuration diagnostic leaves CLI stdout clean');
		$this->assertEquals('', $stderr, 'the configuration diagnostic leaves CLI stderr clean');
		$this->assertTrue(strpos($diagnostic, 'RU_PROFILE_MASK') !== false,
			'the invalid mask remains visible in the server error log: ' . var_export($diagnostic, true));
	}

	public function testInvalidMaskDiagnosticLeavesHttpJsonResponseClean()
	{
		$directory = tempnam(sys_get_temp_dir(), 'rut-mask-http-');
		unlink($directory);
		mkdir($directory, 0700);
		$router = $directory . '/router.php';
		$errorLog = $directory . '/errors.log';
		$serverLog = $directory . '/server.log';
		file_put_contents($router, '<?php $_ENV["RU_PROFILE_MASK"] = "02770"; require '
			. var_export(__DIR__ . '/../../conf/config.php', true)
			. '; header("Content-Type: application/json"); echo json_encode(array("profileMask" => $profileMask));');
		$process = null;
		try {
			$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
			if ($socket === false)
				throw new RuntimeException('Could not reserve a configuration HTTP port: ' . $error);
			$address = stream_socket_get_name($socket, false);
			fclose($socket);
			// exec keeps proc_terminate directed at PHP rather than a shell parent.
			$command = 'exec ' . escapeshellarg(PHP_BINARY)
				. ' -d variables_order=GPCS -d error_reporting=-1 -d display_errors=1 -d log_errors=0'
				. ' -d error_log=' . escapeshellarg($errorLog)
				. ' -S ' . escapeshellarg($address) . ' ' . escapeshellarg($router);
			$process = proc_open($command, array(0 => array('pipe', 'r'),
				1 => array('file', $serverLog, 'a'), 2 => array('file', $serverLog, 'a')), $pipes);
			if (!is_resource($process))
				throw new RuntimeException('Could not start the configuration HTTP fixture');
			fclose($pipes[0]);
			$ready = false;
			$deadline = microtime(true) + 5;
			do {
				$connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
				if ($connection !== false) {
					fclose($connection);
					$ready = true;
					break;
				}
				usleep(10000);
			} while (microtime(true) < $deadline && proc_get_status($process)['running']);
			if (!$ready)
				throw new RuntimeException('Configuration HTTP fixture did not become ready: ' . file_get_contents($serverLog));
			$context = stream_context_create(array('http' => array('timeout' => 5, 'ignore_errors' => true)));
			$response = fopen('http://' . $address . '/', 'r', false, $context);
			if ($response === false)
				throw new RuntimeException('Could not read the configuration HTTP response');
			$body = stream_get_contents($response);
			$headers = stream_get_meta_data($response)['wrapper_data'];
			fclose($response);
			$diagnostic = is_file($errorLog) ? file_get_contents($errorLog) : '';
			$this->assertTrue(strpos($headers[0], '200 OK') !== false, 'configuration loading keeps HTTP status 200');
			$this->assertTrue(in_array('Content-Type: application/json', $headers, true),
				'the configuration diagnostic does not prevent the JSON content type');
			$this->assertEquals('{"profileMask":511}', $body,
				'the actual HTTP body contains only JSON with the documented fallback mask');
			$this->assertTrue(strpos($diagnostic, 'RU_PROFILE_MASK') !== false,
				'the HTTP request records the invalid mask in the server error log');
		} finally {
			if (is_resource($process)) {
				proc_terminate($process);
				proc_close($process);
			}
			foreach (array($router, $errorLog, $serverLog) as $file)
				if (is_file($file)) unlink($file);
			rmdir($directory);
		}
	}

	public function testAMaskThatIsSimplyUnsetSaysNothing()
	{
		$diagnostic = null;
		$mask = $this->configuredProfileMask('', $diagnostic);

		$this->assertEquals(0777, $mask, 'an empty mask uses the documented default');
		$this->assertEquals('', $diagnostic, 'and says nothing, because nothing was asked for');
	}

	public function testInvalidEnvironmentProfileMaskUsesTheDefault()
	{
		$mask = $this->configuredProfileMask('not-a-mask');

		$this->assertEquals(
			0777,
			$mask,
			'An invalid RU_PROFILE_MASK cannot reach chmod or mkdir as a string'
		);
	}

	/**
	 * The list config.php builds. setUp() runs once for the file, not once per
	 * test, so a mask an earlier case left behind would be read here too --
	 * these cases are about RU_LOCALHOSTS and nothing else.
	 */
	private function configuredLocalhosts()
	{
		unset($_ENV['RU_PROFILE_MASK']);
		include(__DIR__ . '/../../conf/config.php');
		return $localhosts;
	}

	public function testAnAddressFromTheEnvironmentJoinsTheLocalInterfaces()
	{
		$_ENV['RU_LOCALHOSTS'] = '10.1.2.3';
		$hosts = $this->configuredLocalhosts();
		$this->assertTrue(in_array('10.1.2.3', $hosts, true),
			'RU_LOCALHOSTS is added to the local interfaces');
		$this->assertTrue(in_array('127.0.0.1', $hosts, true),
			'and the built-in ones are kept');
	}

	/**
	 * The list must never carry a null. It is compared against the address a
	 * request came from, and an entry that is not an address is at best noise
	 * in a security decision.
	 */
	public function testAnEnvironmentWithoutTheSettingAddsNothing()
	{
		unset($_ENV['RU_LOCALHOSTS']);
		$hosts = $this->configuredLocalhosts();
		$this->assertEquals(array('::1', '127.0.0.1', 'localhost'), $hosts,
			'an unset RU_LOCALHOSTS leaves the list as it ships');
	}

	public function testAnEmptySettingAddsNothing()
	{
		$_ENV['RU_LOCALHOSTS'] = '';
		$hosts = $this->configuredLocalhosts();
		$this->assertEquals(array('::1', '127.0.0.1', 'localhost'), $hosts,
			'an empty RU_LOCALHOSTS adds no entry');
	}

	/**
	 * getenv() and $_ENV disagree whenever variables_order has no E, which is
	 * the CLI default: the process carries the variable and the superglobal
	 * does not. Testing one and reading the other appended a null.
	 *
	 * The disagreement is created here rather than inherited from whatever the
	 * suite happens to be run with -- otherwise this passes on a clean machine
	 * whether the bug is present or not.
	 */
	public function testTheListIsBuiltWithoutReadingAKeyThatIsNotThere()
	{
		putenv('RU_LOCALHOSTS=10.9.9.9');
		unset($_ENV['RU_LOCALHOSTS']);
		$this->assertTrue(getenv('RU_LOCALHOSTS') === '10.9.9.9',
			'the process carries the variable');
		$this->assertTrue(!isset($_ENV['RU_LOCALHOSTS']),
			'and the superglobal does not');

		$raised = null;
		set_error_handler(function ($errno, $errstr) use (&$raised) {
			$raised = $errstr;
			return true;
		});
		$hosts = $this->configuredLocalhosts();
		restore_error_handler();
		putenv('RU_LOCALHOSTS');

		$this->assertTrue($raised === null,
			'building the list raises no diagnostic: ' . var_export($raised, true));
		$this->assertTrue(!in_array(null, $hosts, true),
			'and the list carries no null entry');
		$this->assertEquals(array('::1', '127.0.0.1', 'localhost'), $hosts,
			'and nothing was added from a source config.php does not read');
	}
}
