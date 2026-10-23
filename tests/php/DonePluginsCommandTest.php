<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TestHttpServer.php');
require_once(__DIR__ . '/TestTree.php');

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

			$process = TestHttpServer::start($base.'/php', array(),
				array_merge(PhpProcess::sameIni(), array('-d', 'zlib.output_compression=0')));
			$response = TestHttpServer::rawPost($process->port(), '/doneplugins.php', $body,
				'application/x-www-form-urlencoded');
			$decoded = json_decode(file_get_contents($journal), true);
			if(!is_array($decoded))
				throw new Exception('the fixture journal was not readable');
			$response['journal'] = $decoded;
			return $response;
		}
		finally
		{
			if($process !== null) $process->close();
			TestTree::remove($base);
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
}
