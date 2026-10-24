<?php

require_once(__DIR__.'/TestCase.php');

// The messages a plugin's init.php sends to the browser when the plugin cannot work
class PluginStartupMessageTest extends TestCase
{
	private static function initFiles()
	{
		return glob(__DIR__.'/../../plugins/*/init.php');
	}

	/**
	 * The premise of both cases below. Each reports only on what its pattern
	 * matched, so a glob that found no init.php would leave them iterating
	 * nothing, asserting nothing and failing on nothing.
	 */
	public function testThePluginInitFilesWereFound()
	{
		$this->assertTrue(count(self::initFiles()) > 0,
			'plugin init.php files were found');
	}

	public function testShowErrorIsGivenAnExpressionToEvaluateLater()
	{
		// rPlugin.showError() runs eval() on its argument once the plugin's language file has loaded: it must get
		// the name of the string ('theUILang.x'), not the string itself, which is still undefined at that point.
		$checked = 0;
		foreach (self::initFiles() as $file) {
			$plugin = basename(dirname($file));
			preg_match_all('/showError\(\s*(.)/', file_get_contents($file), $m);
			foreach ($m[1] as $first) {
				$checked++;
				$this->assertTrue($first === "'" || $first === '"',
					$plugin.': showError() gets a quoted expression');
			}
		}
		$this->assertTrue($checked > 0, 'found showError() calls to check');
	}

	public function testCantStartMessageNamesItsOwnPlugin()
	{
		$checked = 0;
		foreach (self::initFiles() as $file) {
			$plugin = basename(dirname($file));
			preg_match_all("/noty\\('([A-Za-z0-9_]+): '\\+theUILang\\.pluginCantStart/", file_get_contents($file), $m);
			foreach ($m[1] as $named) {
				$checked++;
				$this->assertEquals($plugin, $named, $plugin.': the "cannot start" message names this plugin');
			}
		}
		$this->assertTrue($checked > 0, 'found "cannot start" messages to check');
	}
}
