<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-cache-refusal-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/cache.php');

/**
 * A refused cache file is logged, and the line is what the reader has to work
 * from: it names the file, so it has to name the class as well. Without the
 * name there is nothing to act on -- the class the file may not hold is
 * usually a property of the stored object, and which property that is decides
 * whether the fix is a cacheClasses() entry or not storing it at all.
 */
class RefusalTargetPayload
{
	public $hash = 'refusal-target.dat';
	public $modified = false;
	public $rows = array();
	public $held = null;
}

/** Stores another class, and says so. */
class RefusalDeclaringPayload
{
	public $hash = 'refusal-declaring.dat';
	public $modified = false;
	public $held = null;

	static public function cacheClasses()
	{
		return array('RefusalHeldPayload');
	}
}

class RefusalHeldPayload
{
	public $name = '';
}

class CacheRefusalMessageTest extends TestCase
{
	private $settings;
	private $log;

	public function setUp()
	{
		$GLOBALS['profileMask'] = 0777;
		FileUtil::makeDirectory(FileUtil::getSettingsPath());
		$this->settings = FileUtil::getSettingsPath();
		$this->log = $_ENV['RU_PROFILE_PATH'] . '/errors.log';
		$GLOBALS['log_file'] = $this->log;
		@unlink($this->log);
	}

	public function tearDown()
	{
		foreach (glob($this->settings . '/*.dat') as $file) {
			@unlink($file);
		}
		foreach (glob($this->settings . '/*.lock') as $file) {
			@unlink($file);
		}
		@unlink($this->log);
		@rmdir($this->settings);
		@rmdir($_ENV['RU_PROFILE_PATH'] . '/settings');
		@rmdir($_ENV['RU_PROFILE_PATH']);
	}

	private function plant($name, $payload)
	{
		file_put_contents($this->settings . '/' . $name, $payload);
	}

	private function logged()
	{
		return trim((string)@file_get_contents($this->log));
	}

	public function testTheRefusalNamesTheClassTheFileMayNotHold()
	{
		// setUp() runs once per file, so each case starts from an empty log.
		@unlink($this->log);
		// Written the way a plugin writes it: a stored object that holds an
		// object of a class it does not declare -- the shape log_history.dat
		// had before #3333.
		$stored = new RefusalTargetPayload();
		$stored->rows = array('a');
		$stored->held = new RefusalHeldPayload();
		$stored->held->name = 'nested';
		$this->assertTrue((new rCache())->set($stored) === true, 'the file was written');

		$loaded = new RefusalTargetPayload();
		$ok = (new rCache())->get($loaded);

		$this->assertTrue($ok === false, 'the file is a miss');
		$this->assertTrue(strpos($this->logged(), 'RefusalHeldPayload') !== false,
			'the line names the class that was refused: ' . $this->logged());
		$this->assertTrue(strpos($this->logged(), 'refusal-target.dat') !== false,
			'and the file it was refused for: ' . $this->logged());
	}

	public function testADeclaredClassIsNotRefusedAndNothingIsLogged()
	{
		@unlink($this->log);
		$stored = new RefusalDeclaringPayload();
		$stored->held = new RefusalHeldPayload();
		$stored->held->name = 'nested';
		$this->assertTrue((new rCache())->set($stored) === true, 'the file was written');

		$loaded = new RefusalDeclaringPayload();
		$ok = (new rCache())->get($loaded);

		$this->assertTrue($ok === true, 'a declared class loads: ' . $this->logged());
		$this->assertEquals('', $this->logged(), 'and nothing is logged');
	}
}
