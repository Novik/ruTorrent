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
 * A directory a new download is added into has to stay inside $topDirectory
 * once the symbolic links along it are followed, not only as text.
 *
 * correctDirectory() compares the path as written. rtorrent's "mkdir -p" and
 * its file writes follow links, so a link inside the top directory that
 * points out of it -- one the account can make with any tool that writes
 * links into its own tree -- passes the text check and has rtorrent create
 * directories and write the download's files wherever the link points.
 *
 * The tree here is real: a top directory, a directory outside it, a link out,
 * a link that stays inside, and a dangling link. The senders are driven
 * against FakeRtorrentDaemon, and addtorrent.php through PHP's built-in
 * server, so "sends nothing" is what the daemon saw.
 */
class AddDirectorySymlinkTest extends TestCase
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

		$this->base = sys_get_temp_dir() . '/rutorrent-add-directory-symlink-' . uniqid('', true);
		@mkdir($this->base . '/top/plain', 0700, true);
		@mkdir($this->base . '/outside', 0700, true);
		@mkdir($this->base . '/profile/torrents', 0700, true);
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';
		symlink($this->base . '/outside', $this->top . '/out');
		symlink($this->top . '/plain', $this->top . '/in');
		symlink($this->base . '/nowhere', $this->top . '/dangle');

		$settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
		$settings->directory = $this->top . '/plain';
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

	private function daemon($replies = array(array(0, 0), array(0, 0)))
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

	// ---- the check itself --------------------------------------------------

	public function testALinkOutOfTheTopDirectoryIsRefused()
	{
		foreach (array('/out', '/out/', '/out/new', '/out/a/b', '/plain/../out/new') as $tail) {
			$dir = $this->top . $tail;
			$this->assertTrue(rTorrentSettings::get()->correctDirectory($dir),
				"{$tail} passes the text check");
			$this->assertTrue(rTorrent::isInsideTopDirectory($dir) === false,
				"but {$tail} resolves outside the top directory and is refused");
		}
	}

	public function testALinkThatStaysInsideIsAccepted()
	{
		$this->assertTrue(rTorrent::isInsideTopDirectory($this->top . '/in/new') === true,
			'a link to a directory inside the top directory is accepted');
	}

	public function testDirectoriesThatDoNotExistYetAreAccepted()
	{
		foreach (array('', '/plain', '/plain/new', '/plain/new/deeper', '/new/deeper') as $tail) {
			$this->assertTrue(rTorrent::isInsideTopDirectory($this->top . $tail) === true,
				"'{$tail}' under the top directory is accepted");
		}
	}

	public function testADanglingLinkIsRefused()
	{
		$this->assertTrue(rTorrent::isInsideTopDirectory($this->top . '/dangle/x') === false,
			'a link whose target cannot be resolved is refused, not read as a name');
		$this->assertTrue(rTorrent::isInsideTopDirectory($this->top . '/dangle') === false,
			'and so is the link itself');
	}

	public function testATopDirectoryOfRootAcceptsEverything()
	{
		// setUp() runs once for the whole class, so the top directory goes back.
		$GLOBALS['topDirectory'] = '/';
		$inside = rTorrent::isInsideTopDirectory($this->top . '/out/new');
		$GLOBALS['topDirectory'] = $this->top . '/';
		$this->assertTrue($inside === true, 'with "/" as the top directory nothing is outside it');
	}

	// ---- the senders load nothing through a link out ------------------------

	public function testSendTorrentLoadsNothingThroughALinkOut()
	{
		$this->daemon();
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, $this->top . '/out/new', '', false, false);
		$this->assertTrue($result === false, 'sendTorrent() fails for a directory behind a link out');
		$this->assertEquals(array(), $this->daemon->calls(),
			'and rtorrent was sent nothing; it saw ' . json_encode($this->daemon->calls()));
	}

	public function testSendMagnetLoadsNothingThroughALinkOut()
	{
		$this->daemon();
		$result = rTorrent::sendMagnet('magnet:?xt=urn:btih:' . self::HASH, true, false, $this->top . '/out/new', '');
		$this->assertTrue($result === false, 'sendMagnet() fails for a directory behind a link out');
		$this->assertEquals(array(), $this->daemon->calls(),
			'and rtorrent was sent nothing; it saw ' . json_encode($this->daemon->calls()));
	}

	public function testTheSendersStillLoadThroughALinkThatStaysInside()
	{
		$this->daemon(array(array(0), array(0)));
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, $this->top . '/in/new', '', false, false);
		$this->assertTrue($result === $torrent->hash_info(), 'sendTorrent() loads into a link that stays inside');

		$this->daemon(array(array(0), array(0)));
		$result = rTorrent::sendMagnet('magnet:?xt=urn:btih:' . self::HASH, true, false, 'Movies/2026', '');
		$this->assertTrue($result === self::HASH, 'sendMagnet() loads into a relative directory not made yet');
		$bodies = $this->daemon->bodies();
		$this->assertTrue(count($bodies) === 2 &&
				strpos($bodies[1], 'd.set_directory_base="' . $this->top . '/plain/Movies/2026"') !== false,
			'and the load carries the directory as written, not as resolved: ' . json_encode($this->daemon->calls()));
	}

	public function testAReloadThroughALinkIsNotRefused()
	{
		$this->daemon();
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, $this->top . '/out/new', '', false, false, false);
		$this->assertTrue($result === $torrent->hash_info(),
			'a reload ($isNew false) goes back where rtorrent had it');
	}

	// ---- addtorrent.php ----------------------------------------------------

	/**
	 * One request to addtorrent.php; answers the result[] values it redirects
	 * with. $requests is how many requests after the first the daemon answers
	 * as accepted: a new download's mkdir and its load are one each.
	 */
	private function add($query, $requests)
	{
		$this->stop();
		$root = realpath(__DIR__ . '/../..');
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->daemon = new FakeRtorrentDaemon(
			array_merge(array(array()), array_fill(0, $requests, array(0))), $this->base . '/calls.log');
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

	public function testAddTorrentAnswersFailedDirectoryForALinkOut()
	{
		// The daemon would accept the mkdir and the load, so only the directory check can refuse it.
		$results = $this->add('dir_edit=' . rawurlencode($this->top . '/out/new'), 2);
		$this->assertEquals(array('FailedDirectory'), $results,
			'a directory behind a link out is the directory failure: ' . json_encode($results));
		$this->assertEquals(array(), $this->loads(),
			'and nothing was loaded or made: ' . json_encode($this->daemon->calls()));
	}

	public function testAddTorrentStillAddsThroughALinkThatStaysInside()
	{
		$results = $this->add('dir_edit=' . rawurlencode($this->top . '/in/new'), 2);
		$this->assertEquals(array('Success'), $results,
			'a directory behind a link that stays inside is added: ' . json_encode($results));
		$loads = $this->loads();
		$this->assertTrue((count($loads) === 2) && ($loads[0] === 'execute') && (strpos($loads[1], 'load') === 0),
			'with its directory made and the magnet loaded: ' . json_encode($this->daemon->calls()));
	}
}
