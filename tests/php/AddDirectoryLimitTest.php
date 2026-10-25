<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * rTorrent::sendMagnet() and rTorrent::sendTorrent() refuse to add a
 * download into a directory no POSIX filesystem can hold -- a component
 * longer than NAME_MAX, a path longer than PATH_MAX, or a URL pasted where a
 * directory belongs -- and send rtorrent nothing.
 *
 * Every directory accepted before is still accepted and resolves to the same
 * bytes. correctDirectory() itself is unchanged, and so is a reload of a
 * download rtorrent already holds: the callers that reload erase first, and
 * refusing after the erase would lose the download.
 *
 * The senders are driven against FakeRtorrentDaemon, so "sends nothing" is
 * what the daemon saw rather than what the function returned.
 */
class AddDirectoryLimitTest extends TestCase
{
	use TorrentSequenceFixtures;

	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
	const DEFAULT_DIR = '/srv/downloads';
	const HOME = '/home/someone';

	private $previousSettings;
	private $previousGlobals;
	private $hadTopDirectory;
	private $previousTopDirectory;
	private $base;
	private $daemon;

	public function setUp()
	{
		$this->previousGlobals = GlobalSettingsFixture::scgi();
		$this->hadTopDirectory = array_key_exists('topDirectory', $GLOBALS);
		$this->previousTopDirectory = $GLOBALS['topDirectory'] ?? null;

		$settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
		$settings->directory = self::DEFAULT_DIR;
		$settings->home = self::HOME;
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();
		$this->previousSettings = self::swapSettings($settings);

		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['rpcTimeOut'] = 5;
		$GLOBALS['rpcTransferTimeOut'] = 5;
		unset($GLOBALS['rpcMaxResponseBytes']);
		$GLOBALS['rpcLogCalls'] = false;
		$GLOBALS['rpcLogFaults'] = false;

		$this->base = sys_get_temp_dir() . '/rutorrent-add-directory-limit-' . getmypid();
		@mkdir($this->base, 0700, true);
		$GLOBALS['log_file'] = $this->base . '/errors.log';
		$GLOBALS['tempDirectory'] = $this->base;
		$GLOBALS['tempDirectory_init_done'] = false;
	}

	public function tearDown()
	{
		try
		{
			self::swapSettings($this->previousSettings);
			$this->stopDaemon();
			foreach(glob($this->base . '/*') as $file)
				@unlink($file);
			@rmdir($this->base);
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

	private function stopDaemon()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
	}

	/** A daemon that answers every request it is sent with success. */
	private function daemon()
	{
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon(array(array(0, 0), array(0, 0)), $this->base . '/calls.log');
		$GLOBALS['scgi_port'] = $this->daemon->port();
	}

	/**
	 * What the senders ask of a directory: correctDirectory() resolves it and
	 * checks the top directory, isPossibleDirectory() checks what it resolved
	 * to. Returns [accepted, $dir after].
	 */
	private function correct($dir, $top = '/')
	{
		$GLOBALS['topDirectory'] = $top;
		$given = $dir;
		$result = rTorrentSettings::get()->correctDirectory($dir) &&
			rTorrent::isPossibleDirectory($given, $dir);
		return array($result, $dir);
	}

	/** An absolute path of exactly $length bytes made of 200-byte components. */
	private function pathOfLength($length)
	{
		$path = '';
		while (strlen($path) + 201 <= $length) {
			$path .= '/' . str_repeat('d', 200);
		}
		$rest = $length - strlen($path) - 1;
		return $path . '/' . str_repeat('e', max($rest, 1));
	}

	private function magnet($trackers = 0)
	{
		$magnet = 'magnet:?xt=urn:btih:' . self::HASH . '&dn=Some.Name';
		for ($i = 0; $i < $trackers; $i++) {
			$magnet .= '&tr=' . rawurlencode('udp://tracker' . $i . '.example:6969/announce');
		}
		return $magnet;
	}

	// ---- what is refused -------------------------------------------------

	public function testAComponentLongerThanNameMaxIsRefused()
	{
		list($ok) = $this->correct(self::DEFAULT_DIR . '/' . str_repeat('a', 300));
		$this->assertTrue($ok === false, 'a 300-byte path component is refused');

		list($ok) = $this->correct(str_repeat('a', 256));
		$this->assertTrue($ok === false, 'so is a 256-byte relative one');
	}

	public function testAPathLongerThanPathMaxIsRefused()
	{
		$path = $this->pathOfLength(4097);
		$this->assertTrue(strlen($path) === 4097, 'the fixture is 4097 bytes');
		list($ok) = $this->correct($path);
		$this->assertTrue($ok === false, 'a 4097-byte path is refused');
	}

	public function testARelativeDirectoryThatResolvesPastPathMaxIsRefused()
	{
		$relative = substr($this->pathOfLength(4090), 1);
		list($ok, $dir) = $this->correct($relative);
		$this->assertTrue(strlen($dir) > 4096, 'joined to the default directory it is over 4096 bytes');
		$this->assertTrue($ok === false, 'and it is refused on its resolved length');
	}

	public function testAMagnetLinkIsRefused()
	{
		list($ok) = $this->correct($this->magnet());
		$this->assertTrue($ok === false, 'a short magnet link is refused');

		list($ok) = $this->correct($this->magnet(40));
		$this->assertTrue($ok === false, 'so is a long one with trackers');

		list($ok) = $this->correct(self::DEFAULT_DIR . '/' . $this->magnet());
		$this->assertTrue($ok === false, 'and one pasted after a real directory');

		list($ok) = $this->correct('MAGNET:?xt=urn:btih:' . self::HASH);
		$this->assertTrue($ok === false, 'in any case');
	}

	public function testAUrlIsRefused()
	{
		list($ok) = $this->correct('http://x/y');
		$this->assertTrue($ok === false, 'http://x/y is refused');

		list($ok) = $this->correct('https://tracker.example/download.php?id=1');
		$this->assertTrue($ok === false, 'so is an https link');

		list($ok) = $this->correct(self::DEFAULT_DIR . '/ftp://x');
		$this->assertTrue($ok === false, 'and a scheme after a real directory');
	}

	// ---- what still passes, byte for byte ----------------------------------

	public function testAnAbsoluteDirectoryStillPassesUnchanged()
	{
		list($ok, $dir) = $this->correct('/srv/downloads/Movies/2026');
		$this->assertTrue($ok === true, 'an ordinary absolute directory passes');
		$this->assertEquals('/srv/downloads/Movies/2026', $dir, 'and is left as it was');

		list($ok, $dir) = $this->correct('/srv/downloads//Movies/./x/../2026/');
		$this->assertTrue($ok === true, 'one with redundant parts passes');
		$this->assertEquals('/srv/downloads/Movies/2026', $dir, 'and is normalised as before');
	}

	public function testARelativeDirectoryStillResolvesAgainstTheDefault()
	{
		list($ok, $dir) = $this->correct('Movies/2026');
		$this->assertTrue($ok === true, 'an ordinary relative directory passes');
		$this->assertEquals(self::DEFAULT_DIR . '/Movies/2026', $dir, 'resolved against the default directory');

		list($ok, $dir) = $this->correct('~/incoming');
		$this->assertTrue($ok === true, 'a home-relative directory passes');
		$this->assertEquals(self::HOME . '/incoming', $dir, 'resolved against the home directory');
	}

	public function testTheLimitsThemselvesStillPass()
	{
		list($ok, $dir) = $this->correct(self::DEFAULT_DIR . '/' . str_repeat('a', 255));
		$this->assertTrue($ok === true, 'a 255-byte component passes');

		$path = $this->pathOfLength(4096);
		list($ok, $dir) = $this->correct($path);
		$this->assertTrue(strlen($path) === 4096 && $ok === true, 'a 4096-byte path passes');
		$this->assertEquals($path, $dir, 'unchanged');
	}

	public function testNamesThatOnlyLookLikeLinksStillPass()
	{
		foreach (array('Magnet: The Movie', 'magnet', 'a:b', 'Series: Season 1', 'http:', 'x:/y') as $name) {
			list($ok, $dir) = $this->correct($name);
			$this->assertTrue($ok === true, "'{$name}' passes");
			$this->assertEquals(FileUtil::fullpath($name, self::DEFAULT_DIR), $dir, "'{$name}' resolves as before");
		}
	}

	public function testTheTopDirectoryIsStillEnforced()
	{
		list($ok) = $this->correct('/etc/passwd-dir', '/srv/');
		$this->assertTrue($ok === false, 'a directory outside the top directory is still refused');
		list($ok) = $this->correct('Movies', '/srv/');
		$this->assertTrue($ok === true, 'and one inside it still passes');
	}

	// ---- the senders load nothing ------------------------------------------

	public function testSendMagnetLoadsNothingForARefusedDirectory()
	{
		foreach (array($this->magnet(40), 'http://x/y', str_repeat('a', 300), substr($this->pathOfLength(4097), 1)) as $directory) {
			$this->daemon();
			$GLOBALS['topDirectory'] = '/';
			$result = rTorrent::sendMagnet($this->magnet(40), true, false, $directory, '');
			$this->assertTrue($result === false, 'sendMagnet() fails for a ' . strlen($directory) . '-byte bad directory');
			$this->assertEquals(array(), $this->daemon->calls(),
				'and rtorrent was sent nothing; it saw ' . json_encode($this->daemon->calls()));
		}
	}

	public function testSendMagnetStillLoadsIntoAValidDirectory()
	{
		$this->daemon();
		$GLOBALS['topDirectory'] = '/';
		$result = rTorrent::sendMagnet($this->magnet(2), true, false, 'Movies', '');
		$this->assertTrue($result === self::HASH, 'sendMagnet() answers the hash for an ordinary directory');
		$bodies = $this->daemon->bodies();
		$this->assertTrue(count($bodies) === 1 &&
				strpos($bodies[0], 'd.set_directory_base="' . self::DEFAULT_DIR . '/Movies"') !== false,
			'and the load carries the resolved directory');
	}

	public function testSendTorrentLoadsNothingForARefusedDirectory()
	{
		foreach (array($this->magnet(40), 'http://x/y', str_repeat('a', 300)) as $directory) {
			$this->daemon();
			$GLOBALS['topDirectory'] = '/';
			$torrent = new Torrent($this->announceOnlyTorrent());
			$result = rTorrent::sendTorrent($torrent, true, false, $directory, '', false, false);
			$this->assertTrue($result === false, 'sendTorrent() fails for a ' . strlen($directory) . '-byte bad directory');
			$this->assertEquals(array(), $this->daemon->calls(),
				'and rtorrent was sent nothing; it saw ' . json_encode($this->daemon->calls()));
		}
	}

	public function testSendTorrentStillLoadsIntoAValidDirectory()
	{
		$this->daemon();
		$GLOBALS['topDirectory'] = '/';
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, 'Movies', '', false, false);
		$this->assertTrue($result === $torrent->hash_info(), 'sendTorrent() answers the hash for an ordinary directory');
		$bodies = $this->daemon->bodies();
		$this->assertTrue(count($bodies) === 1 &&
				strpos($bodies[0], 'd.set_directory_base="' . self::DEFAULT_DIR . '/Movies"') !== false,
			'and the load carries the resolved directory');
	}

	public function testCorrectDirectoryItselfIsUnchanged()
	{
		$GLOBALS['topDirectory'] = '/';
		$dir = $this->magnet(40);
		$ok = rTorrentSettings::get()->correctDirectory($dir);
		$this->assertTrue($ok === true, 'correctDirectory() alone still accepts what it accepted before');
		$this->assertEquals(self::DEFAULT_DIR . '/' . $this->magnet(40), $dir, 'and resolves it the same way');
	}

	public function testAReloadIntoTheDirectoryRtorrentReportedIsNotRefused()
	{
		$this->daemon();
		$GLOBALS['topDirectory'] = '/';
		$directory = '/tmp/' . str_repeat('x', 300);
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, $directory, '', false, false, false);
		$this->assertTrue($result === $torrent->hash_info(),
			'a reload ($isNew false) carries the directory rtorrent gave, whatever its length');
		$this->assertEquals(array('execute', 'load_raw_start'), $this->daemon->calls(), 'and is sent');
	}
}
