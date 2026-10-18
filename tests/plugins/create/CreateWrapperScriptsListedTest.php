<?php

require_once(__DIR__ . '/../../php/TestCase.php');

/**
 * The loader chmods every script named in rtorrent.script.error to 0755 and
 * refuses to run the plugin when one of them is not executable afterwards. A
 * wrapper left out of that list gets neither: on a deployment that did not
 * carry the mode across it stays unreadable as a program, and the failure
 * surfaces as a task exiting 126 rather than as a disabled plugin.
 */
class CreateWrapperScriptsListedTest extends TestCase
{
	private function pluginDir()
	{
		return dirname(dirname(dirname(__DIR__))) . '/plugins/create';
	}

	private function declaredScripts()
	{
		$key = 'rtorrent.script.error:';
		foreach (explode("\n", file_get_contents($this->pluginDir() . '/plugin.info')) as $line)
			if (strpos($line, $key) === 0)
				return array_map('trim', explode(',', trim(substr($line, strlen($key)))));
		return array();
	}

	public function testEveryWrapperTheDispatchCanReachIsDeclared()
	{
		$declared = $this->declaredScripts();
		$wrappers = glob($this->pluginDir() . '/*.sh');
		$this->assertTrue(count($wrappers) > 0, 'the plugin ships wrapper scripts');
		foreach ($wrappers as $wrapper)
		{
			$name = basename($wrapper);
			$this->assertTrue(in_array($name, $declared),
				$name . ' is named in rtorrent.script.error');
		}
	}

	public function testTheListNamesOnlyScriptsThatShip()
	{
		foreach ($this->declaredScripts() as $name)
			$this->assertTrue(is_file($this->pluginDir() . '/' . $name),
				$name . ' is declared and present');
	}

	/** action.php builds the wrapper name from $useExternal, so inner is one too. */
	public function testTheInternalRealizationIsDeclared()
	{
		$this->assertTrue(in_array('inner.sh', $this->declaredScripts()),
			'inner.sh is dispatched like any other backend');
	}
}
