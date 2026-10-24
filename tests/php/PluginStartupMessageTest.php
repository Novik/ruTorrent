<?php

require_once(__DIR__.'/TestCase.php');

// The messages a plugin's init.php sends to the browser when the plugin cannot work
class PluginStartupMessageTest extends TestCase
{
	private static function initFiles()
	{
		return glob(__DIR__.'/../../plugins/*/init.php');
	}

	public function testShowErrorIsGivenAnExpressionToEvaluateLater()
	{
		// rPlugin.showError() runs eval() on its argument once the plugin's language file has loaded: it must get
		// the name of the string ('theUILang.x'), not the string itself, which is still undefined at that point.
		foreach (self::initFiles() as $file) {
			$plugin = basename(dirname($file));
			preg_match_all('/showError\(\s*(.)/', file_get_contents($file), $m);
			foreach ($m[1] as $first) {
				$this->assertTrue($first === "'" || $first === '"',
					$plugin.': showError() gets a quoted expression');
			}
		}
	}

	public function testCantStartMessageNamesItsOwnPlugin()
	{
		foreach (self::initFiles() as $file) {
			$plugin = basename(dirname($file));
			preg_match_all("/noty\\('([A-Za-z0-9_]+): '\\+theUILang\\.pluginCantStart/", file_get_contents($file), $m);
			foreach ($m[1] as $named) {
				$this->assertEquals($plugin, $named, $plugin.': the "cannot start" message names this plugin');
			}
		}
	}
}
