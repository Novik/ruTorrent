<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/RtorrentProbeFixture.php');
require_once(__DIR__ . '/TestHttpServer.php');
require_once(__DIR__ . '/TestTree.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * What a new download's directory costs when it is refused or cannot be made.
 *
 * - addtorrent.php judges the directory as it was typed, so a URL is refused
 *   there even though folding "//" would make it look like a path.
 * - A directory that cannot be made is made before the load is sent, in a
 *   request of its own, and the load is not sent when it fails: rtorrent
 *   never holds a download whose directory does not exist.
 * - Every refusal of the directory, and a directory that could not be made,
 *   is answered as FailedDirectory, so the user is told what to change.
 * - A reload of a download rtorrent already had still sends the directory and
 *   the load together, as it always did.
 */
class AddDirectoryOutcomeTest extends TestCase
{
	use TorrentSequenceFixtures;

	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

	private $previousSettings;
	private $previousGlobals;
	private $hadTopDirectory;
	private $previousTopDirectory;
	private $base;
	private $top;
	private $daemon;
	private $server;

	public function setUp()
	{
		$this->previousGlobals = GlobalSettingsFixture::scgi();
		$this->hadTopDirectory = array_key_exists('topDirectory', $GLOBALS);
		$this->previousTopDirectory = $GLOBALS['topDirectory'] ?? null;

		$this->base = sys_get_temp_dir() . '/rutorrent-add-directory-outcome-' . uniqid('', true);
		@mkdir($this->base . '/top', 0700, true);
		@mkdir($this->base . '/profile/torrents', 0700, true);
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';

		$settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
		$settings->directory = $this->top;
		$settings->home = $this->base;
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();
		$this->previousSettings = self::swapSettings($settings);

		$GLOBALS['topDirectory'] = $this->top . '/';
		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['rpcTimeOut'] = 5;
		$GLOBALS['rpcTransferTimeOut'] = 5;
		unset($GLOBALS['rpcMaxResponseBytes']);
		$GLOBALS['rpcLogCalls'] = false;
		$GLOBALS['rpcLogFaults'] = false;
		$GLOBALS['log_file'] = $this->base . '/errors.log';
		$GLOBALS['tempDirectory'] = $this->base;
		$GLOBALS['tempDirectory_init_done'] = false;
	}

	public function tearDown()
	{
		try
		{
			self::swapSettings($this->previousSettings);
			$this->stop();
			TestTree::remove($this->base);
		}
		finally
		{
			$this->previousGlobals->restore();
			if($this->hadTopDirectory)
				$GLOBALS['topDirectory'] = $this->previousTopDirectory;
			else
				unset($GLOBALS['topDirectory']);
		}
	}

	private static function swapSettings($settings)
	{
		return Closure::bind(function ($settings) {
			$previous = rTorrentSettings::$theSettings;
			rTorrentSettings::$theSettings = $settings;
			return $previous;
		}, null, 'rTorrentSettings')->__invoke($settings);
	}

	private function stop()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
		if ($this->server !== null) $this->server->close();
		$this->server = null;
	}

	private function daemon($replies)
	{
		$this->stop();
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
		$GLOBALS['scgi_port'] = $this->daemon->port();
	}

	private function loads()
	{
		return array_values(array_filter($this->daemon->calls(), function ($call) {
			return (strpos($call, 'load') === 0) || ($call === 'execute');
		}));
	}

	// ---- the senders -------------------------------------------------------

	public function testANewMagnetIsLoadedOnlyAfterItsDirectoryIsMade()
	{
		$this->daemon(array(array(0), array(0)));
		$result = rTorrent::sendMagnet('magnet:?xt=urn:btih:' . self::HASH, true, false, '/thisisthedownloaddir', '');
		$this->assertTrue($result === self::HASH, 'the magnet is added');
		$bodies = $this->daemon->bodies();
		$this->assertTrue((count($bodies) === 2) && (strpos($bodies[0], '<methodName>execute') !== false)
				&& (strpos($bodies[0], $this->top . '/thisisthedownloaddir') !== false)
				&& (strpos($bodies[1], 'system.multicall') === false),
			'mkdir is a request of its own, sent first, for the directory under the top one: ' . json_encode($this->daemon->calls()));
		$this->assertTrue(strpos($bodies[1], 'd.set_directory_base="' . $this->top . '/thisisthedownloaddir"') !== false,
			'and the load carries that directory');
	}

	public function testANewMagnetIsNotLoadedWhenItsDirectoryCannotBeMade()
	{
		$this->daemon(array(FakeRtorrentDaemon::fault('mkdir: Permission denied')));
		$result = rTorrent::sendMagnet('magnet:?xt=urn:btih:' . self::HASH, true, false, 'x', '');
		$this->assertTrue($result === false, 'the magnet is not added');
		$this->assertEquals(array('execute'), $this->daemon->calls(), 'and only the mkdir was sent');
		$this->assertTrue(rTorrent::refusedDirectory() === true, 'the failure is the directory');
	}

	public function testANewTorrentIsNotLoadedWhenItsDirectoryCannotBeMade()
	{
		$this->daemon(array(FakeRtorrentDaemon::fault('mkdir: Permission denied')));
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, 'x', '', false, false);
		$this->assertTrue($result === false, 'the torrent is not added');
		$this->assertEquals(array('execute'), $this->daemon->calls(), 'and only the mkdir was sent');
		$this->assertTrue(rTorrent::refusedDirectory() === true, 'the failure is the directory');
	}

	public function testARefusedDirectoryIsReportedAsTheDirectory()
	{
		$this->daemon(array());
		$result = rTorrent::sendMagnet('magnet:?xt=urn:btih:' . self::HASH, true, false, 'http://x/y', '');
		$this->assertTrue(($result === false) && (rTorrent::refusedDirectory() === true),
			'a refused directory is the directory failure');
		$this->assertEquals(array(), $this->daemon->calls(), 'and nothing was sent');
	}

	public function testAFailedLoadIsNotReportedAsTheDirectory()
	{
		$this->daemon(array(array(0), FakeRtorrentDaemon::fault('Could not create download')));
		$result = rTorrent::sendMagnet('magnet:?xt=urn:btih:' . self::HASH, true, false, 'x', '');
		$this->assertTrue(($result === false) && (rTorrent::refusedDirectory() === false),
			'a load rtorrent refused after the directory was made is not the directory failure');
	}

	public function testAReloadStillSendsTheDirectoryAndTheLoadTogether()
	{
		$this->daemon(array(array(0, 0)));
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, $this->top . '/x', '', false, false, false);
		$this->assertTrue($result === $torrent->hash_info(), 'the reload is sent');
		$this->assertEquals(array('execute', 'load_raw_start'), $this->daemon->calls(), 'in one request, as before');
	}

	// ---- addtorrent.php ----------------------------------------------------

	/** One request to addtorrent.php; answers the result[] values it redirects with. */
	private function add($query, $replies)
	{
		$this->stop();
		$root = realpath(__DIR__ . '/../..');
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->daemon = new FakeRtorrentDaemon(array_merge(array(array()), $replies), $this->base . '/calls.log');
		$router = $this->base . '/router.php';
		file_put_contents($router, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$topDirectory = ' . var_export($this->top . '/', true) . ";\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. 'chdir(' . var_export($root . '/php', true) . ");\n"
			. 'require(' . var_export($root . '/php/addtorrent.php', true) . ");\n"
			. "return true;\n");
		$this->server = TestHttpServer::start($this->base, array(), PhpProcess::sameIni(), $router);
		$response = TestHttpServer::rawPost($this->server->port(), '/php/addtorrent.php?' . $query,
			'url=' . rawurlencode('magnet:?xt=urn:btih:' . self::HASH), 'application/x-www-form-urlencoded');
		if (!isset($response['headers']['location'])
			|| !preg_match('`^[^?]*\?(.*)$`', $response['headers']['location'], $location)) {
			return array('no redirect: ' . $response['body']);
		}
		parse_str($location[1], $answer);
		return isset($answer['result']) ? $answer['result'] : array();
	}

	public function testAddTorrentRefusesAUrlAsItWasTyped()
	{
		$results = $this->add('dir_edit=' . rawurlencode('http://x/y'), array(array(0), array(0)));
		$this->assertEquals(array('FailedDirectory'), $results,
			'a URL in the directory is the directory failure: ' . json_encode($results));
		$this->assertEquals(array(), $this->loads(), 'and nothing was made or loaded: ' . json_encode($this->daemon->calls()));
	}

	public function testAddTorrentPutsALeadingSlashUnderTheTopDirectory()
	{
		$results = $this->add('dir_edit=' . rawurlencode('/thisisthedownloaddir'), array(array(0), array(0)));
		$this->assertEquals(array('Success'), $results, 'the magnet is added: ' . json_encode($results));
		$bodies = $this->daemon->bodies();
		$this->assertTrue(strpos(implode("\n", $bodies), $this->top . '/thisisthedownloaddir') !== false,
			'into the directory under the top one');
	}

	public function testAddTorrentReportsADirectoryThatCannotBeMade()
	{
		$results = $this->add('dir_edit=x', array(FakeRtorrentDaemon::fault('mkdir: Permission denied'), array(0)));
		$this->assertEquals(array('FailedDirectory'), $results,
			'a directory rtorrent could not make is the directory failure: ' . json_encode($results));
		$this->assertEquals(array('execute'), $this->loads(),
			'and the magnet was not loaded: ' . json_encode($this->daemon->calls()));
	}
}
