<?php

require_once(__DIR__ . '/../../php/TestCase.php');

/**
 * The queue erase.php writes into, and the single drainer that empties it.
 *
 * The load behaviour this exists for -- hundreds of firings not becoming
 * hundreds of blocking RPC clients -- is not something a unit test can show.
 * What it can hold is the contract underneath: one marker per hash however
 * many times a caller fires, a hash that is not a hash never reaching the
 * filesystem, an attempt counter that gives up rather than retrying forever,
 * and a second drainer standing down instead of working the same queue.
 *
 * pending.php is copied rather than included so its two require_once calls can
 * be answered by stubs: the real ones pull in settings.php and a live rtorrent.
 * The copy is byte-compared, so what runs is the shipped file.
 */
class PendingQueueTest extends TestCase
{
	private $tree;
	private $listPath;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-pending-' . getmypid();
		$this->wipe();
		mkdir($this->tree . '/plugins/erasedata', 0777, true);
		mkdir($this->tree . '/php', 0777, true);
		mkdir($this->tree . '/list', 0777, true);
		$this->listPath = $this->tree . '/list';

		$source = dirname(dirname(dirname(__DIR__))) . '/plugins/erasedata/pending.php';
		$target = $this->tree . '/plugins/erasedata/pending.php';
		copy($source, $target);
		if (hash_file('sha256', $source) !== hash_file('sha256', $target))
			throw new Exception('could not copy pending.php');

		// Everything below the queue's own boundary.
		file_put_contents($this->tree . '/php/xmlrpc.php', '<?php
			class rXMLRPCCommand {
				public $command; public $params;
				public function __construct($c, $p = null) { $this->command = $c; $this->params = $p; }
			}
			class rXMLRPCRequest {
				public static $live = array();      // hashes rtorrent admits to
				public static $reachable = true;
				public $val = array(); public $important = true;
				public function __construct($cmds = null) {}
				public function success() {
					if (!self::$reachable) return false;
					$this->val = self::$live;
					return true;
				}
			}
			function getCmd($c) { return $c; }
		');
		// pending.php requires this for FileUtil::toLog(), which it calls when a
		// request is abandoned.
		file_put_contents($this->tree . '/php/util.php', '<?php
			class FileUtil {
				public static $log = array();
				public static function toLog($m) { self::$log[] = $m; }
				public static function makeDirectory($d) { return @mkdir($d, 0777, true); }
			}
		');
		file_put_contents($this->tree . '/plugins/erasedata/removewithdata.php', '<?php
			function erasedataRemoveWithData($hashes, $force) {
				// The real one writes <hash>.list on success; success is what the
				// queue reads, so the double decides it per hash.
				foreach ($hashes as $h) {
					if (in_array($h, PendingProbe::$erasable, true))
						file_put_contents(PendingProbe::$listPath . "/" . $h . ".list", "x");
					PendingProbe::$erased[] = $h;
				}
				return true;
			}
		');
		PendingProbe::$listPath = $this->listPath;
		PendingProbe::$erasable = array();
		PendingProbe::$erased = array();
		// erasedataDrainQueue() loads these itself once it wins the lock; the
		// cases below call erasedataDrainOnce() directly, so they are loaded here.
		require_once($this->tree . '/php/xmlrpc.php');
		require_once($this->tree . '/plugins/erasedata/removewithdata.php');
		require_once($target);
	}

	public function tearDown()
	{
		$this->wipe();
	}

	private function wipe($dir = null)
	{
		$dir = is_null($dir) ? $this->tree : $dir;
		if (!is_dir($dir)) return;
		foreach (array_diff(scandir($dir), array('.', '..')) as $e) {
			$p = $dir . '/' . $e;
			is_dir($p) ? $this->wipe($p) : unlink($p);
		}
		rmdir($dir);
	}

	/**
	 * setUp() runs once for the whole file, not once per test, so a case that
	 * counts what is in the queue has to start from an empty one.
	 */
	private function fresh()
	{
		foreach (glob($this->listPath . '/*') as $f) unlink($f);
		foreach (glob($this->tree . '/*.pending') as $f) unlink($f);
		// The doubles are static, so they outlive a test as well.
		rXMLRPCRequest::$live = array();
		rXMLRPCRequest::$reachable = true;
		PendingProbe::$erasable = array();
		PendingProbe::$erased = array();
	}

	private function hash($c) { return str_repeat($c, 40); }
	private function marker($h) { return $this->listPath . '/' . $h . '.pending'; }

	public function testAFiringRecordsOneMarker()
	{
		$this->fresh();
		$h = $this->hash('a');
		$this->assertTrue(erasedataQueueRequest($this->listPath, $h, "1"), 'the request is queued');
		$this->assertTrue(is_file($this->marker($h)), 'a marker names the hash');
		$this->assertEquals("1\n0\n", file_get_contents($this->marker($h)),
			'and records the force mode with no attempts yet');
	}

	public function testFiringAgainBeforeTheDrainIsNotASecondRequest()
	{
		$this->fresh();
		$h = $this->hash('b');
		erasedataQueueRequest($this->listPath, $h, "1");
		erasedataQueueRequest($this->listPath, $h, "1");
		$this->assertEquals(1, count(glob($this->listPath . '/*.pending')),
			'a ratio group command firing on every check queues once');
	}

	public function testSomethingThatIsNotAHashNeverReachesTheFilesystem()
	{
		$this->fresh();
		// A name that would land one directory up, where this test can write --
		// so the refusal has to come from the check, not from the write failing.
		$escape = '../escaped';
		$this->assertTrue(erasedataQueueRequest($this->listPath, $escape, "1") === false,
			'a name that is not a hash is refused');
		$this->assertTrue(!is_file($this->listPath . '/' . $escape . '.pending'),
			'and nothing was written outside the queue directory');
		$this->assertEquals(array(), glob($this->listPath . '/*'), 'nor inside it');

		$this->assertTrue(erasedataQueueRequest($this->listPath, '../../etc/passwd', "1") === false,
			'and neither is a path');
	}

	/**
	 * The marker is created exclusively, so a caller that fires again lands on
	 * the early return. Truncating instead would put the attempt count back to
	 * zero on every firing -- and a ratio group command fires on every check,
	 * so the give-up limit would never be reached.
	 */
	public function testFiringAgainDoesNotResetTheAttemptCount()
	{
		$this->fresh();
		$h = $this->hash('3');
		erasedataQueueRequest($this->listPath, $h, "1");
		rXMLRPCRequest::$live = array($h);
		PendingProbe::$erasable = array();
		erasedataDrainOnce($this->listPath, array($h => "1"), 10);
		$this->assertEquals("1\n1\n", file_get_contents($this->marker($h)), 'one attempt is recorded');

		erasedataQueueRequest($this->listPath, $h, "1");
		$this->assertEquals("1\n1\n", file_get_contents($this->marker($h)),
			'and firing again leaves it where it was');
	}

	public function testAForceModeNobodyDefinedBecomesTheSafeOne()
	{
		$this->fresh();
		$h = $this->hash('c');
		erasedataQueueRequest($this->listPath, $h, "9");
		$this->assertEquals("1\n0\n", file_get_contents($this->marker($h)),
			'an unknown force falls back to deleting the download\'s own files');
	}

	public function testAHashAlreadyCollectedLeavesTheQueue()
	{
		$this->fresh();
		$h = $this->hash('d');
		erasedataQueueRequest($this->listPath, $h, "1");
		file_put_contents($this->listPath . '/' . $h . '.list', 'x');
		$pending = erasedataPendingHashes($this->listPath, array());
		$this->assertTrue(!array_key_exists($h, $pending), 'it is not queued again');
		$this->assertTrue(!is_file($this->marker($h)), 'and its marker is gone');
	}

	public function testAMarkerThatIsNotAHashIsSweptUp()
	{
		$this->fresh();
		file_put_contents($this->listPath . '/notahash.pending', "1\n0\n");
		erasedataPendingHashes($this->listPath, array());
		$this->assertTrue(!is_file($this->listPath . '/notahash.pending'),
			'a marker nobody could have written is removed');
	}

	public function testAHashRtorrentNoLongerHoldsIsDroppedNotRetried()
	{
		$this->fresh();
		$h = $this->hash('e');
		erasedataQueueRequest($this->listPath, $h, "1");
		rXMLRPCRequest::$live = array();          // the web UI removed it meanwhile
		erasedataDrainOnce($this->listPath, array($h => "1"), 10);
		$this->assertTrue(!is_file($this->marker($h)),
			'the marker goes rather than counting against the give-up limit');
		$this->assertEquals(array(), PendingProbe::$erased, 'and rtorrent is not asked to erase it');
	}

	public function testAFailedEraseIsCountedAndRetriedLater()
	{
		$this->fresh();
		$h = $this->hash('f');
		erasedataQueueRequest($this->listPath, $h, "1");
		rXMLRPCRequest::$live = array($h);
		PendingProbe::$erasable = array();         // the erase does not take
		erasedataDrainOnce($this->listPath, array($h => "1"), 10);
		$this->assertTrue(is_file($this->marker($h)), 'the request stays queued');
		$this->assertEquals("1\n1\n", file_get_contents($this->marker($h)), 'with one attempt against it');
	}

	public function testASuccessfulEraseLeavesTheQueueToTheGarbageCollector()
	{
		$this->fresh();
		$h = $this->hash('0');
		erasedataQueueRequest($this->listPath, $h, "1");
		rXMLRPCRequest::$live = array($h);
		PendingProbe::$erasable = array($h);
		erasedataDrainOnce($this->listPath, array($h => "1"), 10);
		$this->assertTrue(!is_file($this->marker($h)), 'the marker is cleared');
		$this->assertTrue(is_file($this->listPath . '/' . $h . '.list'),
			'and the file list the collector consumes is there');
	}

	public function testItGivesUpRatherThanRetryingForever()
	{
		$this->fresh();
		$h = $this->hash('1');
		erasedataQueueRequest($this->listPath, $h, "1");
		rXMLRPCRequest::$live = array($h);
		PendingProbe::$erasable = array();
		for ($i = 0; $i < 3; $i++)
			erasedataDrainOnce($this->listPath, array($h => "1"), 3);
		$this->assertTrue(!is_file($this->marker($h)),
			'the third attempt reaches the limit and the request is abandoned');
		$this->assertTrue(!is_file($this->listPath . '/' . $h . '.list'),
			'the download and its data are left in place');
	}

	public function testALimitOfZeroNeverGivesUp()
	{
		$this->fresh();
		$h = $this->hash('2');
		erasedataQueueRequest($this->listPath, $h, "1");
		rXMLRPCRequest::$live = array($h);
		PendingProbe::$erasable = array();
		for ($i = 0; $i < 5; $i++)
			erasedataDrainOnce($this->listPath, array($h => "1"), 0);
		$this->assertTrue(is_file($this->marker($h)), 'the request is still queued');
		$this->assertEquals("1\n5\n", file_get_contents($this->marker($h)), 'and still counting');
	}

	/** The claim the whole design rests on. */
	public function testASecondDrainerStandsDownInsteadOfWorkingTheSameQueue()
	{
		$this->fresh();
		$lockPath = $this->listPath . '/drain.lock';
		$holder = proc_open('exec ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg(
			'$f = fopen(' . var_export($lockPath, true) . ', "c"); flock($f, LOCK_EX);'
			. ' echo "held\n"; flush(); sleep(20);'),
			array(1 => array('pipe', 'w')), $pipes);
		if (!is_resource($holder))
			throw new Exception('could not start the competing drainer');
		fgets($pipes[1]);

		$began = microtime(true);
		$ret = erasedataDrainQueue($this->listPath, 10);
		$took = microtime(true) - $began;

		proc_terminate($holder, 9);
		for ($i = 0; $i < 100; $i++) {
			$s = proc_get_status($holder);
			if (!$s['running']) break;
			usleep(50000);
		}
		proc_close($holder);

		$this->assertEquals(-1, $ret, 'the second drainer reports that someone else holds the queue');
		$this->assertTrue($took < 1.0, 'and returns at once rather than waiting: took ' . round($took, 2) . 's');
	}
}

class PendingProbe
{
	public static $listPath = null;
	public static $erasable = array();
	public static $erased = array();
}
