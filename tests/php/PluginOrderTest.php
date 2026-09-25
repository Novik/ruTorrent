<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * The order php/getplugins.php registers plugins in.
 *
 * The Plugins tab lists plugins in the order they were registered, so that
 * order is what the user sees. It has to be the sorted order (runlevel, then
 * name) whatever state a plugin is in: turning one off from the tab must not
 * move its row, and neither may a plugin that could not be loaded.
 *
 * getplugins.php is run for real, in a process of its own, against a profile
 * in a temporary directory and an rTorrent socket that does not exist, so the
 * plugins that need rTorrent are among the disabled ones as well.
 */
class PluginOrderTest extends TestCase
{
	private $tree;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-pluginorder-' . getmypid();
		$this->wipe();
	}

	public function tearDown()
	{
		$this->wipe();
	}

	private function wipe()
	{
		if (!is_dir($this->tree))
			return;
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->tree, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($items as $item)
			$item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		@rmdir($this->tree);
	}

	private function repoRoot()
	{
		return dirname(dirname(__DIR__));
	}

	/**
	 * Runs getplugins.php with the named plugins turned off by the user, and
	 * returns the plugins it registered, in order, as
	 * name => array(perms, disabled, unlaunched).
	 */
	private function registered($off)
	{
		$this->wipe();
		@mkdir($this->tree . '/profile/settings', 0777, true);
		@mkdir($this->tree . '/profile/torrents', 0777, true);
		@mkdir($this->tree . '/tmp', 0777, true);

		$env = '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->tree . '/profile', true) . ";\n"
			. '$_ENV["RU_TEMP_DIRECTORY"] = ' . var_export($this->tree . '/tmp/', true) . ";\n"
			. '$_ENV["RU_LOG_FILE"] = ' . var_export($this->tree . '/errors.log', true) . ";\n"
			. '$_ENV["RU_SCGI_PORT"] = 0;' . "\n"
			. '$_ENV["RU_SCGI_HOST"] = ' . var_export('unix://' . $this->tree . '/none.sock', true) . ";\n";
		$probe = $this->tree . '/probe.php';
		file_put_contents($probe, "<?php\n" . $env
			. 'chdir(' . var_export($this->repoRoot() . '/php', true) . ");\n"
			. 'require_once("settings.php");' . "\n"
			. '$off = ' . var_export($off, true) . ";\n"
			. 'if (count($off)) {' . "\n"
			. '	$perms = array("__hash__" => "plugins.dat");' . "\n"
			. '	foreach ($off as $name) $perms[$name] = false;' . "\n"
			. '	$cache = new rCache();' . "\n"
			. '	if (!$cache->set($perms)) { echo "could not store plugins.dat"; exit(1); }' . "\n"
			. "}\n"
			. 'require("getplugins.php");' . "\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -d display_errors=0 '
			. escapeshellarg($probe) . ' 2>&1', $output);
		$js = implode("\n", $output);

		$found = array();
		// The bundle may have been minified, so whitespace is not relied on.
		$blocks = preg_split("/\\(function\\s*\\(\\)\\s*\\{\\s*var\\s+plugin\\s*=\\s*new\\s+rPlugin\\(\\s*'/", $js);
		array_shift($blocks);
		foreach ($blocks as $block)
		{
			if (!preg_match("/^([^']+)',\\s*[^,]*,\\s*'.*?',\\s*'.*?',\\s*(\\d+)\\s*,/", $block, $m))
				throw new Exception('unreadable registration: ' . substr($block, 0, 200));
			// A plugin that is not loaded is registered and switched off,
			// and nothing else: its init.js is not sent.
			$off = preg_match("/^[^']+',\\s*[^,]*,\\s*'.*?',\\s*'.*?',\\s*\\d+\\s*,\\s*'[^']*'\\s*\\)\\s*;"
				. "\\s*plugin\\.disable\\(\\)\\s*;\\s*(plugin\\.unlaunch\\(\\)\\s*;\\s*)?\\}\\)\\(\\);/", $block, $d);
			$found[$m[1]] = array((int)$m[2], $off === 1, $off === 1 && !empty($d[1]));
		}
		if (!count($found))
			throw new Exception('getplugins.php registered nothing; it printed: ' . substr($js, 0, 500));
		return $found;
	}

	/** The runlevel getplugins.php sorts a plugin by. */
	private function runlevel($name)
	{
		$level = 10.0;
		$info = $this->repoRoot() . '/plugins/' . $name . '/plugin.info';
		if (is_readable($info))
			foreach (file($info) as $line)
			{
				$fields = explode(':', $line, 2);
				if (count($fields) == 2 && trim($fields[0]) === 'plugin.runlevel')
					$level = floatval(trim($fields[1]));
			}
		return $level;
	}

	private function sorted($names)
	{
		$keyed = array();
		foreach ($names as $name)
			$keyed[] = array($this->runlevel($name), $name);
		usort($keyed, function ($a, $b) {
			if ($a[0] != $b[0])
				return ($a[0] > $b[0]) ? 1 : -1;
			return strcmp($a[1], $b[1]);
		});
		return array_map(function ($k) { return $k[1]; }, $keyed);
	}

	public function testPluginsAreRegisteredInSortedOrderWhateverTheirState()
	{
		$all = $this->registered(array());
		$names = array_keys($all);
		$disabled = array_keys(array_filter($all, function ($p) { return $p[1]; }));
		$this->assertTrue(count($names) > 10, 'getplugins.php registered the shipped plugins: ' . count($names));
		$this->assertTrue(count($disabled) > 0 && count($disabled) < count($names),
			'with no rTorrent some plugins are disabled and some are not: ' . count($disabled) . ' of ' . count($names));
		$this->assertEquals($this->sorted($names), $names,
			'every plugin, enabled or not, is registered in its sorted place');
	}

	public function testTurningAPluginOffDoesNotMoveIt()
	{
		$all = $this->registered(array());
		$canBeLaunched = 0x0100;
		$pick = null;
		foreach ($all as $name => $p)
			if (!$p[1] && ($p[0] & $canBeLaunched))
			{
				$pick = $name;
				break;
			}
		$this->assertTrue($pick !== null, 'an enabled plugin the user may turn off is available');
		if ($pick === null)
			return;
		$this->assertTrue(array_search($pick, array_keys($all)) < count($all) - 1,
			$pick . ' is not already the last one registered');

		$off = $this->registered(array($pick));
		$this->assertEquals(array_keys($all), array_keys($off),
			'turning ' . $pick . ' off leaves every plugin where it was');
		$this->assertTrue($off[$pick][1] && $off[$pick][2],
			$pick . ' is registered disabled and not launched');
		$unlaunched = array_keys(array_filter($off, function ($p) { return $p[2]; }));
		$this->assertEquals(array($pick), $unlaunched,
			'only the plugin the user turned off reads as not launched');
		foreach ($all as $name => $p)
			if ($p[1])
				$this->assertTrue($off[$name][1] && !$off[$name][2],
					$name . ' is still disabled, and still as one that could not load');
	}
}
