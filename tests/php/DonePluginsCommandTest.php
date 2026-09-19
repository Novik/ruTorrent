<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * php/doneplugins.php dispatches on the cmd parameter. These tests run the
 * production file in a throwaway PHP server and record what each command
 * actually does: what javascript comes back, whether the plugin's done.php was
 * run, and whether the plugin was unregistered.
 *
 * Permissions in the fixture:
 *   alpha  FLAG_CAN_CHANGE_LAUNCH             - may be launched and shut down
 *   beta   FLAG_CAN_CHANGE_LAUNCH|CANT_SHUTDOWN - may be launched, not shut down
 */
class DonePluginsCommandTest extends TestCase
{
	private $sourceRoot;

	public function setUp()
	{
		$this->sourceRoot = realpath(__DIR__ . '/../..');
		if($this->sourceRoot === false)
			throw new Exception('could not locate the production source root');
	}

	public function testUnlaunchOnlyUnlaunches()
	{
		$run = $this->post('cmd=unlaunch&plg=alpha');
		$this->assertEquals("thePlugins.get('alpha').unlaunch();", $run['body'],
			'unlaunch answers with the unlaunch call alone');
		$this->assertEquals(array(), $run['journal']['done'],
			'unlaunch does not run the plugin\'s done.php');
		$this->assertEquals(array(), $run['journal']['unregistered'],
			'unlaunch does not unregister the plugin');
		$this->assertEquals(0, $run['journal']['stored'],
			'unlaunch does not write the plugin settings');
	}

	public function testUnlaunchOfSeveralPluginsOnlyUnlaunches()
	{
		$run = $this->post('cmd=unlaunch&plg=alpha&plg=beta');
		$this->assertEquals("thePlugins.get('alpha').unlaunch();thePlugins.get('beta').unlaunch();",
			$run['body'], 'each named plugin is unlaunched and nothing more');
		$this->assertEquals(array(), $run['journal']['done'],
			'no done.php runs for any of them');
		$this->assertEquals(array(), $run['journal']['unregistered'],
			'none of them is unregistered');
	}

	/**
	 * beta cannot be shut down, so the done branch would refuse it anyway.
	 * It is here to show the difference is the command, not the permission.
	 */
	public function testUnlaunchOfAPluginThatCannotBeShutDown()
	{
		$run = $this->post('cmd=unlaunch&plg=beta');
		$this->assertEquals("thePlugins.get('beta').unlaunch();", $run['body'],
			'a plugin that cannot be shut down unlaunches normally');
		$this->assertEquals(array(), $run['journal']['done'], 'and runs no done.php');
	}

	public function testDoneStillRemovesThePlugin()
	{
		$run = $this->post('cmd=done&plg=alpha');
		$this->assertEquals("thePlugins.get('alpha').remove();", $run['body'],
			'done answers with the remove call');
		$this->assertEquals(array('alpha'), $run['journal']['done'],
			'done runs the plugin\'s done.php');
		$this->assertEquals(array('alpha'), $run['journal']['unregistered'],
			'done unregisters the plugin');
		$this->assertEquals(1, $run['journal']['stored'],
			'done writes the plugin settings');
	}

	public function testDoneRefusesAPluginThatCannotBeShutDown()
	{
		$run = $this->post('cmd=done&plg=beta');
		$this->assertEquals('', $run['body'], 'done answers with nothing for a protected plugin');
		$this->assertEquals(array(), $run['journal']['done'], 'and runs no done.php');
		$this->assertEquals(array(), $run['journal']['unregistered'], 'and unregisters nothing');
	}

	public function testLaunchOnlyLaunches()
	{
		$run = $this->post('cmd=launch&plg=alpha');
		$this->assertEquals("thePlugins.get('alpha').launch();", $run['body'],
			'launch answers with the launch call alone');
		$this->assertEquals(array(), $run['journal']['done'], 'and runs no done.php');
		$this->assertEquals(array(), $run['journal']['unregistered'], 'and unregisters nothing');
	}

	public function testAnUnregisteredPluginIsNotRemoved()
	{
		$run = $this->post('cmd=done&plg=nosuchplugin');
		$this->assertEquals('', $run['body'], 'an unregistered name produces no javascript');
		$this->assertEquals(array(), $run['journal']['done'], 'and runs no done.php');
	}

	// ---- plumbing --------------------------------------------------------

	/**
	 * doneplugins.php resolves a plugin's done.php as "../plugins/<name>/
	 * done.php", relative to the working directory, so the fixture puts the
	 * served scripts in <base>/php and the plugins in <base>/plugins, which is
	 * the shape of an install.
	 */
	private function post($body)
	{
		$base = sys_get_temp_dir() . '/rutorrent-doneplugins-' . uniqid('', true);
		$process = null;
		try
		{
			if(!mkdir($base . '/php/utility', 0700, true) && !is_dir($base . '/php/utility'))
				throw new Exception('could not create doneplugins fixture tree');
			foreach(array('php/doneplugins.php', 'php/utility/cachedecho.php') as $relative)
			{
				$source = $this->sourceRoot . '/' . $relative;
				$target = $base . '/' . $relative;
				if(!copy($source, $target) || (hash_file('sha256', $source) !== hash_file('sha256', $target)))
					throw new Exception('could not byte-copy production source ' . $relative);
			}
			$journal = $base . '/journal.json';
			file_put_contents($journal, json_encode(array(
				'done' => array(), 'unregistered' => array(), 'stored' => 0, 'cached' => 0,
			)));
			$this->writeStubs($base, $journal);

			$port = $this->reservePort();
			$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d zlib.output_compression=0'
				. ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($base . '/php');
			$process = proc_open($command, array(
				0 => array('pipe', 'r'),
				1 => array('file', $base . '/server.out', 'a'),
				2 => array('file', $base . '/server.err', 'a'),
			), $pipes, $base . '/php');
			if(!is_resource($process))
				throw new Exception('could not start copied PHP server');
			fclose($pipes[0]);
			$this->waitForServer($port, $process, $base . '/server.err');
			$response = $this->rawPost($port, '/doneplugins.php', $body);
			$decoded = json_decode(file_get_contents($journal), true);
			if(!is_array($decoded))
				throw new Exception('the fixture journal was not readable');
			$response['journal'] = $decoded;
			return $response;
		}
		finally
		{
			if(is_resource($process))
			{
				@proc_terminate($process);
				@proc_close($process);
			}
			$this->deleteTree($base);
		}
	}

	private function writeStubs($base, $journal)
	{
		$journalLiteral = var_export($journal, true);
		$cachedecho = var_export($base . '/php/utility/cachedecho.php', true);
		file_put_contents($base . '/php/utility/fileutil.php', "<?php\nclass FileUtil {}\n");
		file_put_contents($base . '/php/utility/utility.php', "<?php\nclass Utility {}\n");
		file_put_contents($base . '/php/xmlrpc.php', <<<PHP
<?php

require_once({$cachedecho});
\$phpUseGzip = false;

function doneplugins_journal(\$key, \$value = null)
{
	\$state = json_decode(file_get_contents({$journalLiteral}), true);
	if(\$value !== null)
	{
		if(is_array(\$state[\$key]))
			\$state[\$key][] = \$value;
		else
			\$state[\$key] = \$state[\$key] + 1;
		file_put_contents({$journalLiteral}, json_encode(\$state));
	}
	return(\$state[\$key]);
}

class rCache
{
	public function get(&\$data) { return(true); }
	public function set(&\$data) { doneplugins_journal('cached', 1); return(true); }
}

class rXMLRPCRequest
{
	public function __construct(\$command = null) {}
	public function run() { return(true); }
}
PHP
		);
		file_put_contents($base . '/php/settings.php', <<<'PHP'
<?php

class rTorrentSettings
{
	private static $instance = null;
	// FLAG_CAN_CHANGE_LAUNCH is 0x0100 and FLAG_CANT_SHUTDOWN is 0x0080;
	// doneplugins.php defines both after this file is included.
	private $plugins = array( 'alpha' => 0x0100, 'beta' => 0x0180 );

	public static function get()
	{
		if(self::$instance === null)
			self::$instance = new rTorrentSettings();
		return(self::$instance);
	}
	public function getPluginData($plugin)
	{
		return(array_key_exists($plugin, $this->plugins) ? $this->plugins[$plugin] : null);
	}
	public function unregisterPlugin($plugin)
	{
		unset($this->plugins[$plugin]);
		doneplugins_journal('unregistered', $plugin);
	}
	public function store()
	{
		doneplugins_journal('stored', 1);
	}
}
PHP
		);
		foreach(array('alpha', 'beta') as $plugin)
		{
			if(!mkdir($base . '/plugins/' . $plugin, 0700, true))
				throw new Exception('could not create fixture plugin ' . $plugin);
			file_put_contents($base . '/plugins/' . $plugin . '/done.php',
				"<?php\ndoneplugins_journal('done', " . var_export($plugin, true) . ");\n");
		}
	}

	private function reservePort()
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		if($socket === false)
			throw new Exception('could not reserve local test port: ' . $error);
		$name = stream_socket_get_name($socket, false);
		fclose($socket);
		$parts = explode(':', $name);
		return intval(array_pop($parts));
	}

	private function waitForServer($port, $process, $errorFile)
	{
		for($i = 0; $i < 100; $i++)
		{
			$socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.05);
			if($socket !== false)
			{
				fclose($socket);
				return;
			}
			$status = proc_get_status($process);
			if(!$status['running'])
				throw new Exception('copied PHP server exited: ' . @file_get_contents($errorFile));
			usleep(25000);
		}
		throw new Exception('copied PHP server did not start');
	}

	private function rawPost($port, $path, $body)
	{
		$socket = @fsockopen('127.0.0.1', $port, $errno, $error, 2);
		if($socket === false)
			throw new Exception('could not connect raw HTTP client: ' . $error);
		$request = "POST " . $path . " HTTP/1.1\r\nHost: 127.0.0.1\r\n"
			. "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body)
			. "\r\nConnection: close\r\n\r\n" . $body;
		if(fwrite($socket, $request) === false)
			throw new Exception('could not write raw HTTP request');
		$raw = stream_get_contents($socket);
		fclose($socket);
		if($raw === false || strpos($raw, "\r\n\r\n") === false)
			throw new Exception('copied doneplugins.php returned no complete HTTP response');
		list($headerBlock, $responseBody) = explode("\r\n\r\n", $raw, 2);
		$headers = explode("\r\n", $headerBlock);
		$status = array_shift($headers);
		$values = array();
		foreach($headers as $header)
		{
			$position = strpos($header, ':');
			if($position !== false)
				$values[strtolower(trim(substr($header, 0, $position)))] = trim(substr($header, $position + 1));
		}
		return array('status' => $status, 'headers' => $values, 'body' => $responseBody);
	}

	private function deleteTree($path)
	{
		if(!is_dir($path))
			return;
		foreach(scandir($path) as $entry)
			if(($entry !== '.') && ($entry !== '..'))
			{
				$child = $path . '/' . $entry;
				if(is_dir($child))
					$this->deleteTree($child);
				else
					@unlink($child);
			}
		@rmdir($path);
	}
}
