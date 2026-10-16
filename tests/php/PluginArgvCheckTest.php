<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * The register_argc_argv warning php/getplugins.php sends.
 *
 * The warning is for the plugins that run PHP from the command line and read
 * $argv. Up to PHP 8.4 the directive decided whether such a run got $argv.
 * From PHP 8.5 a command-line run always gets it and the directive defaults
 * to 0, so there the warning is sent to every install and means nothing.
 *
 * getplugins.php is run for real, in a process of its own, with the
 * directive set to 0, the same way PluginOrderTest runs it.
 */
class PluginArgvCheckTest extends TestCase
{
	private $tree;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-argvcheck-' . getmypid();
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

	/** What getplugins.php prints with register_argc_argv set to 0. */
	private function output()
	{
		@mkdir($this->tree . '/profile/settings', 0777, true);
		@mkdir($this->tree . '/profile/torrents', 0777, true);
		@mkdir($this->tree . '/tmp', 0777, true);

		$probe = $this->tree . '/probe.php';
		file_put_contents($probe, "<?php\n"
			. '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->tree . '/profile', true) . ";\n"
			. '$_ENV["RU_TEMP_DIRECTORY"] = ' . var_export($this->tree . '/tmp/', true) . ";\n"
			. '$_ENV["RU_LOG_FILE"] = ' . var_export($this->tree . '/errors.log', true) . ";\n"
			. '$_ENV["RU_SCGI_PORT"] = 0;' . "\n"
			. '$_ENV["RU_SCGI_HOST"] = ' . var_export('unix://' . $this->tree . '/none.sock', true) . ";\n"
			. 'chdir(' . var_export(dirname(dirname(__DIR__)) . '/php', true) . ");\n"
			. 'require("getplugins.php");' . "\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -d display_errors=0 -d register_argc_argv=0 '
			. escapeshellarg($probe) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	public function testWarningIsSentOnlyBeforePhp85()
	{
		$js = $this->output();
		// The check runs only when a plugin needs php and php was found. The
		// bundle may have been minified, so whitespace is not relied on.
		$this->assertTrue(preg_match("/new\\s+rPlugin\\(\\s*'_task'/", $js) === 1
			&& preg_match("/webExternalNotFoundError\\s*\\+\\s*' \\('\\s*\\+\\s*'php'/", $js) === 0,
			'getplugins.php found php for the _task plugin');
		$warns = strpos($js, 'theUILang.phpParameterUnavailable') !== false;
		if (PHP_VERSION_ID < 80500)
			$this->assertTrue($warns, 'PHP ' . PHP_VERSION . ' with register_argc_argv=0 gets the warning');
		else
			$this->assertTrue(!$warns, 'PHP ' . PHP_VERSION . ' gets no register_argc_argv warning');
	}
}
