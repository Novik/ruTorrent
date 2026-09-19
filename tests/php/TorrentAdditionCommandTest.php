<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-addition-test-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * Answers the two settings questions the add path asks, without a daemon.
 * correctDirectory() says yes so that the directory a test passes is the one
 * that reaches the payload, which is what the override tests compare against.
 */
class AdditionCommandSettings extends rTorrentSettings
{
	public function correctDirectory(&$dir, $resolve_links = false)
	{
		return true;
	}

	public function maxContentSize()
	{
		return 1048576;
	}
}

/**
 * An addition is an rtorrent command, and php/addtorrent.php used to take the
 * list of them from the request.
 *
 * rtorrent's load commands take the torrent as their first parameter and read
 * every parameter after it as a command to run against the download being
 * created. php/rtorrent.php relies on that: the directory, the label and the
 * comment are all sent as trailing parameters of the same load call, and the
 * directory is the one the settings' correctDirectory() has just approved.
 *
 * rTorrent::sendTorrent() and rTorrent::sendMagnet() append $addition to that
 * same list, after the directory. Whatever is in it is therefore a command
 * rtorrent runs, so an 'execute=' addition runs a program as the daemon's user
 * and a second 'd.set_directory=' addition replaces the approved directory
 * with one that was never checked.
 *
 * The names in $addition are not the names rXMLRPCCommand checks. That check
 * governs the <methodName> element; an addition travels as a <param> of the
 * load call, HTML-escaped by rXMLRPCParam, and is read as a command by
 * rtorrent rather than by the XMLRPC layer. So it needs a boundary of its own,
 * which is what these tests fix: the set of commands that may be given as an
 * addition is the set the shipped plugins build, and nothing else; a refused
 * addition stops the add instead of sending part of it.
 *
 * The payloads are read out of the request log. php/xmlrpc.php writes every
 * payload there before it opens a socket, so with $rpcLogCalls on and the SCGI
 * port pointed at a closed one the log holds exactly what would have gone to a
 * daemon, and holds nothing when the add was refused before it built anything.
 */
class TorrentAdditionCommandTest extends TestCase
{
	use TorrentSequenceFixtures;

	/** A 40 character info hash, as a magnet carries one. */
	const MAGNET = 'magnet:?xt=urn:btih:0123456789ABCDEF0123456789ABCDEF01234567';

	private $base;
	private $logFile;
	private $torrentFile;
	private $previousSettings;

	public function setUp()
	{
		$this->base = $_ENV['RU_PROFILE_PATH'];
		@mkdir($this->base, 0700, true);
		$this->logFile = $this->base . '/rpc.log';
		$this->torrentFile = $this->base . '/fixture.torrent';
		file_put_contents($this->torrentFile, $this->announceOnlyTorrent());

		$reflection = new ReflectionClass('AdditionCommandSettings');
		$settings = $reflection->newInstanceWithoutConstructor();
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();
		$settings->directory = $this->base;

		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		$this->previousSettings = $property->getValue();
		$property->setValue(null, $settings);

		// Named so that a payload this test manages to send goes to a closed
		// port on the loopback rather than anywhere real.
		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['scgi_port'] = 1;
		$GLOBALS['rpcTimeOut'] = 1;
		$GLOBALS['rpcLogCalls'] = true;
		$GLOBALS['rpcLogFaults'] = false;
		$GLOBALS['log_file'] = $this->logFile;
		$GLOBALS['profileMask'] = 0600;
		$GLOBALS['saveUploadedTorrents'] = true;
		$GLOBALS['topDirectory'] = '/';
	}

	public function tearDown()
	{
		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $this->previousSettings);

		foreach (glob($this->base . '/*') as $path) {
			@unlink($path);
		}
		@rmdir($this->base);
	}

	/** Everything php/xmlrpc.php logged since the last call to this. */
	private function takeLog()
	{
		$log = file_exists($this->logFile) ? file_get_contents($this->logFile) : '';
		@unlink($this->logFile);
		return $log;
	}

	/** Run one add and return what it would have sent. */
	private function addTorrentWith($addition, $directory = null)
	{
		$this->takeLog();
		rTorrent::sendTorrent(new Torrent($this->torrentFile), true, true,
			$directory, null, true, false, true, $addition);
		return $this->takeLog();
	}

	private function addMagnetWith($addition, $directory = null)
	{
		$this->takeLog();
		rTorrent::sendMagnet(self::MAGNET, true, true, $directory, null, $addition);
		return $this->takeLog();
	}

	// ---- the mechanism ---------------------------------------------------

	/**
	 * The premise the rest of this file rests on: a trailing parameter of a
	 * load call is a command, and php/rtorrent.php sends the approved
	 * directory as one. Without this the other tests would be asserting
	 * against a payload shape nobody uses.
	 */
	public function testTheApprovedDirectoryTravelsAsATrailingLoadParameter()
	{
		$payload = $this->addTorrentWith(null, '/downloads/approved');

		$this->assertTrue(strpos($payload, 'load_raw_start') !== false,
			'the add builds a load call');
		$this->assertTrue(strpos($payload, 'd.set_directory="/downloads/approved"') !== false,
			'the directory rides along as a parameter of that call, i.e. as a command');
	}

	// ---- what an addition could do ---------------------------------------

	public function testAnExecuteAdditionNeverReachesThePayload()
	{
		$payload = $this->addTorrentWith(array('execute=/bin/sh,-c,id > /tmp/pwned'));

		$this->assertTrue(strpos($payload, 'execute=') === false,
			'an execute addition is not sent');
		$this->assertTrue($payload === '',
			'and the add is refused whole, rather than sent without it');
	}

	public function testAnExecuteThrowAdditionNeverReachesThePayload()
	{
		$payload = $this->addTorrentWith(array('execute.throw=/bin/sh,-c,id'));

		$this->assertTrue($payload === '', 'execute.throw is refused too');
	}

	/**
	 * The addition list is appended after the directory, so a second
	 * d.set_directory is the last one rtorrent reads -- and it is the one
	 * correctDirectory() never saw.
	 */
	public function testAnAdditionCannotReplaceTheApprovedDirectory()
	{
		$payload = $this->addTorrentWith(array('d.set_directory=/etc'),
			'/downloads/approved');

		$this->assertTrue(strpos($payload, 'd.set_directory=/etc') === false,
			'an unchecked directory is not sent');
		$this->assertTrue($payload === '', 'the add is refused');
	}

	public function testAnImportAdditionNeverReachesThePayload()
	{
		$this->assertTrue($this->addTorrentWith(array('import=/tmp/rc')) === '',
			'import is refused');
	}

	public function testAMagnetAdditionIsHeldToTheSameSet()
	{
		$payload = $this->addMagnetWith(array('execute=/bin/sh,-c,id'));

		$this->assertTrue($payload === '',
			'sendMagnet refuses the same addition sendTorrent refuses');
	}

	/** One bad entry refuses the batch, not just itself. */
	public function testOneRefusedAdditionStopsTheWholeAdd()
	{
		$payload = $this->addTorrentWith(array(
			getCmd('d.set_throttle_name=') . 'slow',
			'execute=/bin/sh,-c,id',
		));

		$this->assertTrue($payload === '',
			'a list holding one refused addition sends none of itself');
	}

	public function testAnAdditionThatNamesNoCommandIsRefused()
	{
		$this->assertTrue($this->addTorrentWith(array('d.set_throttle_name')) === '',
			'an addition with no = names no command and is refused');
	}

	public function testANonStringAdditionIsRefused()
	{
		$this->assertTrue($this->addTorrentWith(array(array('execute=id'))) === '',
			'an addition that is not a string is refused');
	}

	// ---- what the shipped plugins build ----------------------------------

	/** plugins/rss/rss.php and plugins/rutracker_check/check.php. */
	public function testTheThrottleAdditionIsSent()
	{
		$payload = $this->addTorrentWith(array(getCmd('d.set_throttle_name=') . 'slow'));

		$this->assertTrue(strpos($payload, 'throttle_name') !== false,
			'the throttle addition the rss plugin builds still reaches the payload');
	}

	/** plugins/rss/rss.php and plugins/rutracker_check/check.php. */
	public function testTheRatioViewAdditionIsSent()
	{
		$payload = $this->addTorrentWith(array(getCmd('view.set_visible=') . 'rat_1'));

		$this->assertTrue(strpos($payload, 'view.set_visible=rat_1') !== false,
			'the ratio view addition still reaches the payload');
	}

	/** plugins/rutracker_check/check.php. */
	public function testThePushBackUniqueAdditionIsSent()
	{
		$payload = $this->addTorrentWith(array(getCmd('d.views.push_back_unique=') . 'rat_1'));

		$this->assertTrue(strpos($payload, 'd.views.push_back_unique=rat_1') !== false,
			'the view membership addition still reaches the payload');
	}

	/** plugins/datadir/util_setdir.php. */
	public function testTheConnectionSeedAdditionIsSent()
	{
		$payload = $this->addTorrentWith(array(getCmd('d.set_connection_seed=') . 'seed'));

		$this->assertTrue(strpos($payload, 'connection_seed') !== false ||
			strpos($payload, 'connection.seed') !== false,
			'the connection addition the datadir plugin builds still reaches the payload');
	}

	/** plugins/rss/rss.php builds both at once. */
	public function testTheRssPluginsPairIsSent()
	{
		$payload = $this->addMagnetWith(array(
			getCmd('d.set_throttle_name=') . 'slow',
			getCmd('view.set_visible=') . 'rat_1',
		));

		$this->assertTrue(strpos($payload, 'throttle_name') !== false &&
			strpos($payload, 'view.set_visible=rat_1') !== false,
			'both additions of an rss filter reach the magnet payload');
	}

	/** An add with no addition at all is the common case and must still work. */
	public function testAnAddWithNoAdditionIsSent()
	{
		$this->assertTrue($this->addTorrentWith(null) !== '',
			'an add that passes no addition still builds a payload');
	}

	// ---- the request ------------------------------------------------------

	/**
	 * php/addtorrent.php is a script, not a function: it reads $_REQUEST at the
	 * top level and ends by calling header(). So it is driven where it lives, in
	 * a process of its own, over the magnet branch -- the one branch that needs
	 * neither an upload nor an outbound fetch.
	 */
	private function runAddTorrentScript($query)
	{
		$root = realpath(__DIR__ . '/../..');
		$driver = $this->base . '/drive-addtorrent.php';
		// conf/config.php assigns the globals this test needs to control, so it
		// is loaded first and the settings are written over it. addtorrent.php
		// reaches it through the same require_once and finds it already loaded.
		$code = "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base, true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. 'parse_str(' . var_export($query, true) . ", \$_REQUEST);\n"
			. "\$scgi_host = '127.0.0.1';\n"
			. "\$scgi_port = 1;\n"
			. "\$rpcTimeOut = 1;\n"
			. "\$rpcLogCalls = true;\n"
			. "\$log_file = " . var_export($this->logFile, true) . ";\n"
			. 'set_include_path(' . var_export($root . '/php', true) . ");\n"
			. 'require(' . var_export($root . '/php/addtorrent.php', true) . ");\n";
		file_put_contents($driver, $code);

		$this->takeLog();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $ignored);
		@unlink($driver);
		return $this->takeLog();
	}

	/**
	 * The reported vector end to end: addition[] in the query string of a
	 * request to php/addtorrent.php.
	 */
	public function testTheScriptDoesNotTakeAnAdditionFromTheRequest()
	{
		$payload = $this->runAddTorrentScript('url=' . rawurlencode(self::MAGNET) .
			'&addition[]=' . rawurlencode('execute=/bin/sh,-c,id > /tmp/pwned'));

		$this->assertTrue(strpos($payload, 'execute=') === false,
			'an addition given in the request does not reach the payload');
		$this->assertTrue(strpos($payload, 'load') !== false,
			'and the magnet itself is still added');
	}

	/** The positive control for the test above: the script does send a payload. */
	public function testTheScriptStillAddsAMagnet()
	{
		$payload = $this->runAddTorrentScript('url=' . rawurlencode(self::MAGNET));

		$this->assertTrue(strpos($payload, self::MAGNET) !== false,
			'the magnet reaches the payload when no addition is given');
	}
}
