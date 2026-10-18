<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-cache-injection-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/cache.php');
require_once(__DIR__ . '/../../php/lock.php');

/**
 * A cache file is a serialized object, and rCache::get() used to build it with
 * a bare unserialize(). unserialize() constructs whatever class the bytes name
 * and runs that class's magic methods, so the bytes of a cache file chose the
 * class -- and php/lock.php ships one worth choosing: rLock::__destruct()
 * calls release(), which unlinks a path held in a protected property, and a
 * protected property is no obstacle to a serialized payload.
 *
 * Two things put those bytes there. The settings directory is created with
 * $profileMask, which conf/config.php leaves at 0777, so a local account
 * writes a cache file directly; plugins/loginmgr/init.php then unserializes
 * loginmgr.dat on every page load. And rCache::getName() built the path by
 * concatenating the cache key, which plugins/rss/action.php takes from the
 * rss= parameter without looking at it, so a key holding '../' read any file
 * the web user could read and unserialized that instead.
 *
 * The version comparison further down get() is not a check on any of this. It
 * runs after unserialize() has already built the object, so a destructor
 * gadget has fired by the time it is consulted -- and it admits any stored
 * object that simply has no version property, which is every class that was
 * never meant to be in a cache file at all.
 *
 * So the boundary these tests fix has two halves. A cache key names one file
 * inside the cache directory and cannot leave it. And a cache file comes back
 * as the class the caller asked for, or as nothing: a payload naming any other
 * class is a miss, and the class it named is never constructed.
 */

/** Stands in for a plugin's cached settings: one class, no nested objects. */
class InjectionTargetPayload
{
	public $hash = 'injection-target.dat';
	public $modified = false;
	public $rows = array();
}

/** A cached class that legitimately stores objects of another class. */
class InjectionItemPayload
{
	public $name = '';
	public static $wakeups = 0;

	public function __wakeup()
	{
		self::$wakeups++;
	}
}

class InjectionListPayload
{
	public $hash = 'injection-list.dat';
	public $modified = false;
	public $lst = array();

	static public function cacheClasses()
	{
		return array('InjectionItemPayload');
	}
}

class CacheObjectInjectionTest extends TestCase
{
	private $profilePath;
	private $settings;
	private $victim;

	public function setUp()
	{
		$this->profilePath = $_ENV['RU_PROFILE_PATH'];
		$GLOBALS['profileMask'] = 0777;
		$GLOBALS['log_file'] = '';
		FileUtil::makeDirectory(FileUtil::getSettingsPath());
		$this->settings = FileUtil::getSettingsPath();
		$this->victim = $this->profilePath . '/victim.txt';
	}

	public function tearDown()
	{
		if (is_dir($this->profilePath)) {
			$this->removeDir($this->profilePath);
		}
	}

	private function removeDir($dir)
	{
		foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
			$path = $dir . '/' . $entry;
			if (is_dir($path) && !is_link($path)) {
				$this->removeDir($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}

	/**
	 * An rLock whose destructor unlinks $path. Written by hand rather than with
	 * serialize(), because a payload is bytes an attacker writes and not an
	 * object this process was ever holding -- and because serialize() would
	 * have to be handed a live rLock, whose own destructor would then run.
	 */
	private function lockGadget($path)
	{
		$nul = "\0";
		return 'O:5:"rLock":3:'
			. '{s:7:"' . $nul . '*' . $nul . 'file";s:' . strlen($path) . ':"' . $path . '";'
			. 's:11:"' . $nul . '*' . $nul . 'lockTime";i:0;'
			. 's:9:"' . $nul . '*' . $nul . 'locked";b:1;}';
	}

	/** Puts the victim file back and reports whether the gadget deleted it. */
	private function gadgetFired()
	{
		clearstatcache(true, $this->victim);
		$fired = !file_exists($this->victim);
		file_put_contents($this->victim, "still here\n");
		return $fired;
	}

	private function plant($relative, $bytes)
	{
		$path = $this->settings . '/' . $relative;
		FileUtil::makeDirectory(dirname($path));
		file_put_contents($path, $bytes);
		return $path;
	}

	// ---- the class a cache file may name ---------------------------------

	/**
	 * The local variant: a cache file in the 0777 settings directory, holding
	 * a class the caller never asked for.
	 */
	public function testAPlantedGadgetClassIsNeverConstructed()
	{
		file_put_contents($this->victim, "delete me\n");
		$this->plant('injection-target.dat', $this->lockGadget($this->victim));

		$target = new InjectionTargetPayload();
		$loaded = (new rCache())->get($target);
		unset($target);
		gc_collect_cycles();

		$this->assertTrue($this->gadgetFired() === false,
			'the destructor of the planted class did not run');
		$this->assertTrue($loaded === false,
			'and the load is reported as a miss');
	}

	/**
	 * A miss must leave the caller's own object alone: code downstream calls
	 * methods on it, so handing back something else -- an incomplete class
	 * above all -- would only move the failure.
	 */
	public function testARefusedPayloadLeavesTheCallersObjectIntact()
	{
		file_put_contents($this->victim, "delete me\n");
		$this->plant('injection-target.dat', $this->lockGadget($this->victim));

		$target = new InjectionTargetPayload();
		(new rCache())->get($target);

		$this->assertTrue($target instanceof InjectionTargetPayload,
			'the caller still holds its own class');
		$this->assertTrue(!($target instanceof __PHP_Incomplete_Class),
			'and not an incomplete one');
	}

	/** The class the caller did ask for still round-trips. */
	public function testTheCallersOwnClassStillLoads()
	{
		$stored = new InjectionTargetPayload();
		$stored->rows = array('a', 'b');
		$this->assertTrue((new rCache())->set($stored) === true, 'the store succeeds');

		$loaded = new InjectionTargetPayload();
		$this->assertTrue((new rCache())->get($loaded) === true, 'the load succeeds');
		$this->assertTrue($loaded->rows === array('a', 'b'), 'and carries the data back');
	}

	/** A list class declares what it may contain, and that still round-trips. */
	public function testADeclaredNestedClassStillLoads()
	{
		$item = new InjectionItemPayload();
		$item->name = 'nested';
		$stored = new InjectionListPayload();
		$stored->lst = array($item);
		$this->assertTrue((new rCache())->set($stored) === true, 'the store succeeds');

		$loaded = new InjectionListPayload();
		$this->assertTrue((new rCache())->get($loaded) === true, 'the load succeeds');
		$this->assertTrue(isset($loaded->lst[0]) && ($loaded->lst[0] instanceof InjectionItemPayload),
			'the nested object comes back as its own class');
		$this->assertTrue(isset($loaded->lst[0]) && ($loaded->lst[0]->name === 'nested'),
			'with its data');
	}

	/** An undeclared nested class is a miss, not a partly built object. */
	public function testAnUndeclaredNestedClassIsAMiss()
	{
		file_put_contents($this->victim, "delete me\n");
		$this->plant('injection-list.dat',
			'O:20:"InjectionListPayload":1:{s:3:"lst";a:1:{i:0;'
			. $this->lockGadget($this->victim) . '}}');

		$loaded = new InjectionListPayload();
		$ok = (new rCache())->get($loaded);
		unset($loaded);
		gc_collect_cycles();

		$this->assertTrue($this->gadgetFired() === false,
			'a gadget nested inside an allowed class is not constructed either');
		$this->assertTrue($ok === false, 'and the load is a miss');
	}

	/** An array cache carries no objects at all. */
	public function testAnArrayCacheRefusesObjects()
	{
		file_put_contents($this->victim, "delete me\n");
		$this->plant('array-target.dat',
			'a:2:{s:8:"__hash__";s:16:"array-target.dat";s:1:"x";'
			. $this->lockGadget($this->victim) . '}');

		$target = array('__hash__' => 'array-target.dat');
		$ok = (new rCache())->get($target);
		unset($target);
		gc_collect_cycles();

		$this->assertTrue($this->gadgetFired() === false,
			'no object is constructed for an array cache');
		$this->assertTrue($ok === false, 'and the load is a miss');
	}

	/**
	 * A class a list declares may sit inside the list, not in its place.
	 * Handed back as the root it is another class to a caller that goes on
	 * to use it as the list -- rRatioRulesList::load() then returns an
	 * rRatioRule, and extratio's init.php fails on its first method call.
	 * The file's root is checked before unserialize(), so the class in the
	 * root's place is not constructed either, and the refusal is logged.
	 */
	public function testADeclaredNestedClassIsNotAcceptedAsTheRoot()
	{
		$item = new InjectionItemPayload();
		$item->name = 'nested';
		$this->plant('injection-list.dat', serialize($item));
		InjectionItemPayload::$wakeups = 0;
		$log = $this->profilePath . '/cache-root.log';
		$GLOBALS['log_file'] = $log;
		try {
			$loaded = new InjectionListPayload();
			$ok = (new rCache())->get($loaded);
		} finally {
			$GLOBALS['log_file'] = '';
		}
		$lines = is_file($log) ? file_get_contents($log) : '';

		$this->assertTrue($ok === false, 'a file whose root is a declared nested class is a miss');
		$this->assertTrue($loaded instanceof InjectionListPayload, 'the caller still holds its own class');
		$this->assertTrue(InjectionItemPayload::$wakeups === 0,
			'the nested class is not constructed in the root\'s place');
		$this->assertTrue(substr_count($lines, 'rCache: injection-list.dat does not hold InjectionListPayload'
			. ' at its root (found class InjectionItemPayload); not loaded.') === 1,
			'the refusal is logged once, naming the file, the class asked for and the class found: '
			. json_encode($lines));
	}

	/**
	 * The same for a root of another type: an array or a scalar in an
	 * object's file, a scalar in an array's file. Each used to come back in
	 * place of the caller's value.
	 */
	public function testARootOfAnotherTypeIsAMiss()
	{
		$cases = array(
			array('injection-target.dat', serialize(array('rows' => array('a'))), new InjectionTargetPayload()),
			array('injection-target.dat', serialize(42), new InjectionTargetPayload()),
			array('array-target.dat', serialize('text'), array('__hash__' => 'array-target.dat')),
		);
		foreach ($cases as list($key, $bytes, $target)) {
			$this->plant($key, $bytes);
			$expected = is_object($target) ? get_class($target) : 'array';
			$thrown = '';
			try {
				$ok = (new rCache())->get($target);
			} catch (Throwable $e) {
				$ok = null;
				$thrown = ' (threw ' . get_class($e) . ')';
			}
			$this->assertTrue($ok === false, "{$key} holding {$bytes} is a miss" . $thrown);
			$this->assertTrue(($expected === 'array') ? is_array($target) : ($target instanceof $expected),
				"and the caller still holds its {$expected}");
		}
	}

	/**
	 * The refusal says what the file holds at its root, as the refusal of a
	 * nested class names that class: a class name when the header spells
	 * one plainly, otherwise a fixed word. Nothing else from the file -- a
	 * name of the wrong length, a long one, one carrying a line break --
	 * reaches the log.
	 */
	public function testTheRootRefusalSaysWhatTheFileHoldsAndNothingMore()
	{
		$long = str_repeat('A', 129);
		$cases = array(
			array('', 'InjectionTargetPayload', 'an empty file'),
			array('a:1:{i:0;s:1:"a";}', 'InjectionTargetPayload', 'an array'),
			array('i:42;', 'InjectionTargetPayload', 'a scalar'),
			array('N;', 'InjectionTargetPayload', 'null'),
			array('garbage', 'InjectionTargetPayload', 'no serialized value'),
			array('O:8:"Intruder":0:{}', 'InjectionTargetPayload', 'class Intruder'),
			array('O:9:"Intruder":0:{}', 'InjectionTargetPayload', 'an object'),
			array('O:129:"' . $long . '":0:{}', 'InjectionTargetPayload', 'an object'),
			array("O:15:\"Intruder\nFORGED\":0:{}", 'InjectionTargetPayload', 'an object'),
			array('O:8:"Intruder":0:{}', 'array', 'class Intruder'),
		);
		$log = $this->profilePath . '/cache-root-found.log';
		foreach ($cases as list($bytes, $asked, $found)) {
			@unlink($log);
			if ($asked === 'array') {
				$this->plant('array-target.dat', $bytes);
				$target = array('__hash__' => 'array-target.dat');
				$line = 'rCache: array-target.dat does not hold an array at its root (found '
					. $found . '); not loaded.';
			} else {
				$this->plant('injection-target.dat', $bytes);
				$target = new InjectionTargetPayload();
				$line = 'rCache: injection-target.dat does not hold InjectionTargetPayload at its root (found '
					. $found . '); not loaded.';
			}
			$GLOBALS['log_file'] = $log;
			$thrown = '';
			try {
				(new rCache())->get($target);
			} catch (Throwable $e) {
				$thrown = ' (threw ' . get_class($e) . ')';
			} finally {
				$GLOBALS['log_file'] = '';
			}
			$lines = is_file($log) ? file_get_contents($log) : '';
			$this->assertTrue((substr_count($lines, $line) === 1) && (substr_count($lines, "\n") === 1)
				&& (strpos($lines, 'FORGED') === false) && (strpos($lines, $long) === false),
				'a root of ' . json_encode(substr($bytes, 0, 24)) . ' is logged as "' . $found . '": '
				. json_encode($lines) . $thrown);
		}
	}

	// ---- the file a cache key may name -----------------------------------

	/**
	 * The remote variant: plugins/rss/action.php assigns the rss= parameter to
	 * the key, so the key decides which file is read and unserialized.
	 */
	public function testATraversingKeyReadsNothing()
	{
		file_put_contents($this->victim, "delete me\n");
		$outside = $this->profilePath . '/outside.dat';
		file_put_contents($outside, $this->lockGadget($this->victim));

		$target = new InjectionTargetPayload();
		$target->hash = '../../../../../..' . $outside;
		$ok = (new rCache())->get($target);
		unset($target);
		gc_collect_cycles();

		$this->assertTrue($this->gadgetFired() === false,
			'a key that climbs out of the cache directory reads nothing');
		$this->assertTrue($ok === false, 'and the load is a miss');
	}

	public function testAKeyWithASeparatorIsRefused()
	{
		$nested = $this->settings . '/sub';
		FileUtil::makeDirectory($nested);
		file_put_contents($nested . '/thing.dat', serialize(new InjectionTargetPayload()));

		$target = new InjectionTargetPayload();
		$target->hash = 'sub/thing.dat';

		$this->assertTrue((new rCache())->get($target) === false,
			'a key naming a path rather than a file is refused');
	}

	public function testABackslashKeyIsRefused()
	{
		$target = new InjectionTargetPayload();
		$target->hash = '..\\..\\thing.dat';
		$this->assertTrue((new rCache())->get($target) === false,
			'a backslash separator is refused too');
	}

	public function testADotKeyIsRefused()
	{
		$target = new InjectionTargetPayload();
		$target->hash = '..';
		$this->assertTrue((new rCache())->get($target) === false,
			'the parent directory is not a cache file');
	}

	public function testAnEmptyKeyIsRefused()
	{
		$target = new InjectionTargetPayload();
		$target->hash = '';
		$this->assertTrue((new rCache())->get($target) === false,
			'an empty key names no cache file');
	}

	public function testATraversingKeyWritesNothing()
	{
		$outside = $this->profilePath . '/written.dat';
		$target = new InjectionTargetPayload();
		$target->hash = '../../../../../..' . $outside;

		$this->assertTrue((new rCache())->set($target) === false,
			'a store through a traversing key is refused');
		clearstatcache(true, $outside);
		$this->assertTrue(file_exists($outside) === false,
			'and writes nothing outside the cache directory');
	}

	public function testATraversingKeyRemovesNothing()
	{
		$outside = $this->profilePath . '/removable.dat';
		file_put_contents($outside, "keep me\n");
		$target = new InjectionTargetPayload();
		$target->hash = '../../../../../..' . $outside;

		(new rCache())->remove($target);
		clearstatcache(true, $outside);
		$this->assertTrue(file_exists($outside) === true,
			'a remove through a traversing key deletes nothing outside the cache directory');
	}

	/** Every key the shipped code actually uses still names a file. */
	public function testTheShippedKeyShapesAreAccepted()
	{
		$keys = array('rtorrent.dat', 'WebUISettings.dat', 'which.dat', 'loginmgr.dat',
			'history', 'filters', 'groups', 'info', 'data',
			md5('http://example.test/feed.xml'), 'grp_' . uniqid(time()));
		$cache = new rCache();
		foreach ($keys as $key) {
			$stored = new InjectionTargetPayload();
			$stored->hash = $key;
			$stored->rows = array($key);
			$this->assertTrue($cache->set($stored) === true, "key '{$key}' can be stored");

			$loaded = new InjectionTargetPayload();
			$loaded->hash = $key;
			$this->assertTrue($cache->get($loaded) === true, "key '{$key}' can be read back");
		}
	}
}
