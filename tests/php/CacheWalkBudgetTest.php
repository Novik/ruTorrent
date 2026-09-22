<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-cache-budget-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/cache.php');

/**
 * rCache::get() walks what unserialize() built, looking for a class the caller
 * was not allowed to hold. The bytes it walks are a cache file's, and a cache
 * file is writable by a local account: conf/config.php leaves $profileMask at
 * 0777, and plugins/rss/action.php takes the cache key from a request
 * parameter. So the shape of the walk is chosen by whoever wrote the file,
 * and the walk has to end whatever shape that is.
 *
 * Three shapes end it only if it is budgeted. An array may contain itself --
 * "a:1:{i:0;R:1;}" is fifteen bytes and PHP builds it without complaint -- and
 * a walk with no array identity tracking follows it until the process dies.
 * A structure may nest more deeply than the stack can follow. And a structure
 * may hold the same sub-value at several places through R: back-references, so
 * that a few hundred bytes describe a graph with more nodes in it than there
 * are seconds in a century.
 *
 * Ending is not enough on its own, because a refused file is not always
 * rewritten: rCookies::load() and rRetrackers::load() discard what get()
 * returns and hand back their defaults, so a planted file stays on disk and is
 * walked again on every read. What the walk may spend is therefore tied to the
 * length of the file it came from, and a file that asks for more than it is
 * written in is refused rather than followed.
 */

/** A cached class that legitimately stores objects of another class. */
class BudgetItemPayload
{
	public $name = '';
	public $peer = null;
}

class BudgetListPayload
{
	public $hash = 'budget-list.dat';
	public $modified = false;
	public $lst = array();

	static public function cacheClasses()
	{
		return array('BudgetItemPayload');
	}
}

/** Reaches the protected walk, so the budgets are tested where they live. */
class CacheWalkProbe extends rCache
{
	/** Why the last walk refused, or null when it did not. */
	public static $reason = null;

	public static function walk($value, $bytes = 0)
	{
		self::$reason = null;
		return self::holdsRefusedClass($value, $bytes, self::$reason);
	}
}

class CacheWalkBudgetTest extends TestCase
{
	private $profilePath;
	private $settings;
	private $logFile;

	public function setUp()
	{
		$this->profilePath = $_ENV['RU_PROFILE_PATH'];
		$GLOBALS['profileMask'] = 0777;
		$this->logFile = $this->profilePath . '/cache-budget.log';
		$GLOBALS['log_file'] = '';
		FileUtil::makeDirectory(FileUtil::getSettingsPath());
		$this->settings = FileUtil::getSettingsPath();
	}

	public function tearDown()
	{
		$GLOBALS['log_file'] = '';
		if (is_dir($this->profilePath))
			$this->removeDir($this->profilePath);
	}

	private function removeDir($dir)
	{
		foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
			$path = $dir . '/' . $entry;
			if (is_dir($path) && !is_link($path))
				$this->removeDir($path);
			else
				unlink($path);
		}
		rmdir($dir);
	}

	private function plant($relative, $bytes)
	{
		$path = $this->settings . '/' . $relative;
		FileUtil::makeDirectory(dirname($path));
		file_put_contents($path, $bytes);
		return $path;
	}

	/** Captures what get() logs while it loads $target. */
	private function logOf($target)
	{
		@unlink($this->logFile);
		$GLOBALS['log_file'] = $this->logFile;
		$result = (new rCache())->get($target);
		$GLOBALS['log_file'] = '';
		$logged = is_file($this->logFile) ? file_get_contents($this->logFile) : '';
		return array($result, $logged);
	}

	/**
	 * An acyclic array nested $depth deep. Written as serialized text because
	 * building it in PHP and serializing it is the same recursion the walk is
	 * being tested for.
	 */
	private function nested($depth)
	{
		$bytes = 's:1:"x";';
		for ($i = 0; $i < $depth; $i++)
			$bytes = 'a:1:{i:0;' . $bytes . '}';
		return $bytes;
	}

	/**
	 * $depth levels, each holding the level below it twice: once written out,
	 * once as an R: back-reference to what was just written. The text grows by
	 * a line per level and the graph doubles, so this is the cheapest way to
	 * ask for an unbounded walk from a file small enough to go unnoticed.
	 *
	 * The value ids unserialize() hands out run in order of appearance, so the
	 * array at level k is id k and the level below it is id k+1.
	 */
	private function amplified($depth)
	{
		$bytes = 's:1:"x";';
		for ($k = $depth; $k >= 1; $k--)
			$bytes = 'a:2:{i:0;' . $bytes . 'i:1;R:' . ($k + 1) . ';}';
		return $bytes;
	}

	// -- the walk ends -------------------------------------------------------

	public function testAnArrayHoldingItselfIsRefused()
	{
		$value = unserialize('a:1:{i:0;R:1;}');
		$this->assertTrue(is_array($value) && ($value[0] === $value),
			'PHP really does build an array that contains itself');
		$this->assertTrue(CacheWalkProbe::walk($value) === true,
			'and walking it ends, refusing the file');
	}

	public function testAnArrayHoldingItselfInsideAnAllowedObjectIsRefused()
	{
		// The object's own class is one the caller allows, so unserialize()
		// builds it and the walk goes inside -- where the loop is.
		$bytes = 'O:17:"BudgetListPayload":1:{s:3:"lst";a:1:{i:0;R:2;}}';
		$value = unserialize($bytes, array('allowed_classes' => array('BudgetListPayload')));
		$this->assertTrue(($value instanceof BudgetListPayload) && ($value->lst[0] === $value->lst),
			'the object holds an array that contains itself');
		$this->assertTrue(CacheWalkProbe::walk($value) === true,
			'and walking it ends, refusing the file');
	}

	public function testAGraphTooDeepIsRefused()
	{
		$value = unserialize($this->nested(4000));
		$this->assertTrue(is_array($value), 'the deep graph was built');
		$this->assertTrue(CacheWalkProbe::walk($value) === true,
			'a graph deeper than the budget is refused rather than followed');
		$this->assertTrue(CacheWalkProbe::$reason === 'nests deeper than a cache file may nest',
			'and it is the depth that refused it, not a class or the node budget');
	}

	public function testAGraphTooWideIsRefused()
	{
		// 40 levels: under any nesting limit, and 2^40 nodes to walk.
		$bytes = $this->amplified(40);
		$this->assertTrue(strlen($bytes) < 1024, 'the file is under a kilobyte');
		$value = unserialize($bytes);
		$this->assertTrue(is_array($value), 'the graph was built');

		$this->assertTrue(CacheWalkProbe::walk($value, strlen($bytes)) === true,
			'a graph wider than what its file is written in is refused');
		$this->assertTrue(CacheWalkProbe::$reason === 'describes more than a cache file of its size may describe',
			'and it is the node budget that refused it, not a class or the depth');
	}

	// -- where exactly the budgets fall --------------------------------------

	public function testTheDepthBudgetFallsWhereItSays()
	{
		$atLimit = unserialize($this->nested(rCache::WALK_MAX_DEPTH));
		$this->assertTrue(CacheWalkProbe::walk($atLimit) === false,
			'a value at exactly WALK_MAX_DEPTH is walked');

		$pastLimit = unserialize($this->nested(rCache::WALK_MAX_DEPTH + 1));
		$this->assertTrue(CacheWalkProbe::walk($pastLimit) === true,
			'and one level below that is refused');
	}

	public function testTheNodeBudgetFallsWhereItSays()
	{
		// A flat array costs one node for itself and one for each element.
		$atLimit = array_fill(0, rCache::WALK_MIN_NODES - 1, 1);
		$this->assertTrue(CacheWalkProbe::walk($atLimit) === false,
			'exactly WALK_MIN_NODES nodes are walked');

		$pastLimit = array_fill(0, rCache::WALK_MIN_NODES, 1);
		$this->assertTrue(CacheWalkProbe::walk($pastLimit) === true,
			'and one node past that is refused');
	}

	// -- what the budgets must not refuse ------------------------------------

	public function testTheBudgetGrowsWithTheFileTheValueCameFrom()
	{
		// Twice the floor in nodes, so the floor alone would refuse it -- but
		// it is honestly written out, and its own length pays for it.
		$rows = array_fill(0, 2 * rCache::WALK_MIN_NODES, 1);
		$this->assertTrue(CacheWalkProbe::walk($rows, 0) === true,
			'more nodes than the floor allows is refused when nothing pays for them');
		$this->assertTrue(CacheWalkProbe::walk($rows, strlen(serialize($rows))) === false,
			'and walked when the file it came from is long enough to describe them');
	}

	public function testAnObjectMetTwiceIsNotWalkedTwice()
	{
		// Two objects pointing at each other. Identity tracking has to end
		// this by recognising the second visit, not by running out of depth.
		$first = new BudgetItemPayload();
		$second = new BudgetItemPayload();
		$first->peer = $second;
		$second->peer = $first;
		$this->assertTrue(CacheWalkProbe::walk($first) === false,
			'a cycle between objects is walked to the end, not refused');
	}

	public function testAnOrdinaryCacheStillRoundTrips()
	{
		$item = new BudgetItemPayload();
		$item->name = 'nested';
		$stored = new BudgetListPayload();
		$stored->lst = array($item, array('a', array('b', array('c'))));
		$this->assertTrue((new rCache())->set($stored) === true, 'the store succeeds');

		$loaded = new BudgetListPayload();
		$this->assertTrue((new rCache())->get($loaded) === true, 'the load succeeds');
		$this->assertTrue($loaded->lst[0] instanceof BudgetItemPayload,
			'the nested object comes back as its own class');
		$this->assertTrue($loaded->lst[1] === array('a', array('b', array('c'))),
			'and the nested arrays come back whole');
	}

	public function testAWideButHonestCacheIsNotRefused()
	{
		// A flat list far larger than anything the shipped code caches. Its
		// nodes are written out, not back-referenced, so it costs what it
		// looks like it costs.
		$rows = array();
		for ($i = 0; $i < 20000; $i++)
			$rows[] = array('h' => str_repeat('a', 40), 'n' => 'row ' . $i, 'd' => $i);
		$this->assertTrue(CacheWalkProbe::walk($rows, strlen(serialize($rows))) === false,
			'twenty thousand records are walked, not refused');
	}

	// -- and the same answer through the caller ------------------------------

	public function testAPlantedLoopIsAMissRatherThanAFatal()
	{
		$this->plant('budget-list.dat',
			'O:17:"BudgetListPayload":1:{s:3:"lst";a:1:{i:0;R:2;}}');

		$loaded = new BudgetListPayload();
		$this->assertTrue((new rCache())->get($loaded) === false,
			'a planted loop is reported as a miss');
		$this->assertTrue($loaded instanceof BudgetListPayload,
			'and the caller still holds its own object');
	}

	public function testTheLogSaysWhichBudgetRefusedTheFile()
	{
		$this->plant('budget-list.dat', $this->amplified(40));
		list($result, $logged) = $this->logOf(new BudgetListPayload());
		$this->assertTrue($result === false, 'a planted amplified graph is a miss');
		$this->assertTrue(strpos($logged, 'describes more than a cache file of its size may describe') !== false,
			'and the log says so rather than blaming a class: ' . trim($logged));

		// The same path with a class actually refused, so the two are told
		// apart rather than one message standing for every refusal.
		$this->plant('budget-list.dat',
			'O:17:"BudgetListPayload":1:{s:3:"lst";a:1:{i:0;O:8:"Intruder":0:{}}}');
		list($result, $logged) = $this->logOf(new BudgetListPayload());
		$this->assertTrue($result === false, 'a planted refused class is a miss too');
		$this->assertTrue(strpos($logged, 'names a class it may not hold') !== false,
			'and that one is logged as a class: ' . trim($logged));
	}
}
