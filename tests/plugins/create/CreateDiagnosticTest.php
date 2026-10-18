<?php

require_once(__DIR__ . '/../../php/TestCase.php');

// init.php runs inside the plugin loader. These stand in for the parts of it
// the diagnostic touches, so the resolution it performs can be observed.

function findEXE($exe)
{
	$GLOBALS['findEXE_asked'][] = $exe;
	return (in_array($exe, $GLOBALS['findEXE_present']) ? '/usr/bin/' . $exe : false);
}

class FileUtil
{
	public static $conf = '';
	public static function getPluginConf($name)
	{
		return self::$conf;
	}
}

class LoaderSettings
{
	public $registered = array();
	public function registerPlugin($name, $perms)
	{
		$this->registered[] = $name;
	}
}

/**
 * The create plugin disables itself when it cannot find its external program.
 * The name it looks up, and the name it puts in front of the operator, have to
 * be the program's -- a message naming the backend key sends them looking for
 * a command that does not exist.
 */
class CreateDiagnosticTest extends TestCase
{
	private function loadInit($useExternal, $present, $pathToCreatetorrent = '')
	{
		$GLOBALS['findEXE_asked'] = array();
		$GLOBALS['findEXE_present'] = $present;
		FileUtil::$conf = '$useExternal = ' . var_export($useExternal, true) . ';'
			. ' $pathToCreatetorrent = ' . var_export($pathToCreatetorrent, true) . ';'
			. ' $useInternalHybrid = true;';

		$plugin = array('name' => 'create');
		$pInfo = array('perms' => 0);
		$do_diagnostic = true;
		$jResult = '';
		$theSettings = new LoaderSettings();

		include(dirname(dirname(dirname(__DIR__))) . '/plugins/create/init.php');

		return array(
			'js' => $jResult,
			'asked' => $GLOBALS['findEXE_asked'],
			'registered' => $theSettings->registered,
		);
	}

	public function testItLooksUpTheProgramNameNotTheBackendKey()
	{
		$r = $this->loadInit('transmissioncreate', array());
		$this->assertEquals(array('transmission-create'), $r['asked'],
			'findEXE is asked for the program Transmission installs');
	}

	public function testTheErrorNamesTheProgramTheOperatorMustInstall()
	{
		$r = $this->loadInit('transmissioncreate', array());
		$this->assertTrue(strpos($r['js'], 'plugin.disable();') !== false,
			'the plugin disables itself when the program is absent');
		$this->assertTrue(strpos($r['js'], '(transmission-create)') !== false,
			'the message names transmission-create: ' . $r['js']);
		$this->assertEquals(array(), $r['registered'], 'and the plugin is not registered');
	}

	public function testThePluginLoadsWhenTheProgramIsInstalled()
	{
		$r = $this->loadInit('transmissioncreate', array('transmission-create'));
		$this->assertEquals(array('create'), $r['registered'],
			'a box with transmission-create on PATH gets the plugin');
		$this->assertTrue(strpos($r['js'], 'plugin.disable();') === false,
			'and no disable was emitted');
	}

	public function testAnExplicitPathStillSkipsTheLookupEntirely()
	{
		$r = $this->loadInit('transmissioncreate', array(), '/opt/bin/transmission-create');
		$this->assertEquals(array(), $r['asked'], 'a configured path is not second-guessed');
		$this->assertEquals(array('create'), $r['registered'], 'and the plugin loads');
	}

	public function testABackendSpelledLikeItsProgramIsUnaffected()
	{
		$r = $this->loadInit('mktorrent', array('mktorrent'));
		$this->assertEquals(array('mktorrent'), $r['asked'], 'mktorrent is looked up as itself');
		$this->assertEquals(array('create'), $r['registered'], 'and the plugin loads');
	}
}
