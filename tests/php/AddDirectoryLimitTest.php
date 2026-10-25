<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * rTorrent::sendMagnet() and rTorrent::sendTorrent() refuse to add a
 * download into a directory no POSIX filesystem can hold -- a name longer
 * than NAME_MAX, a path longer than PATH_MAX allows, or a URL pasted where a
 * directory belongs -- and send rtorrent nothing. The rules themselves, byte
 * for byte, are UserDirectoryTest's; this is what the senders do with them.
 *
 * A reload of a download rtorrent already holds is not judged by them: the
 * callers that reload erase first, and refusing after the erase would lose
 * the download.
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

	// ---- the senders load nothing ------------------------------------------

	public function testSendMagnetLoadsNothingForARefusedDirectory()
	{
		foreach (array($this->magnet(40), 'http://x/y', str_repeat('a', 300), substr($this->pathOfLength(4097), 1)) as $directory) {
			$this->daemon();
			$GLOBALS['topDirectory'] = '/';
			$result = rTorrent::sendMagnet($this->magnet(40), true, false, $directory, '');
			$this->assertTrue($result === false, 'sendMagnet() fails for a ' . strlen($directory) . '-byte bad directory');
			$this->assertTrue(rTorrent::refusedDirectory() === true, 'and says it was the directory');
			$this->assertEquals(array(), $this->daemon->calls(),
				'and rtorrent was sent nothing; it saw ' . json_encode($this->daemon->calls()));
		}
	}

	public function testSendMagnetStillLoadsIntoAValidDirectory()
	{
		$this->daemon();
		$GLOBALS['topDirectory'] = self::DEFAULT_DIR . '/';
		$result = rTorrent::sendMagnet($this->magnet(2), true, false, 'Movies', '');
		$this->assertTrue($result === self::HASH, 'sendMagnet() answers the hash for an ordinary directory');
		$bodies = $this->daemon->bodies();
		$this->assertTrue(count($bodies) === 2 &&
				strpos($bodies[1], 'd.set_directory_base="' . self::DEFAULT_DIR . '/Movies"') !== false,
			'and the load, sent after the mkdir, carries the resolved directory');
	}

	public function testSendTorrentLoadsNothingForARefusedDirectory()
	{
		foreach (array($this->magnet(40), 'http://x/y', str_repeat('a', 300)) as $directory) {
			$this->daemon();
			$GLOBALS['topDirectory'] = '/';
			$torrent = new Torrent($this->announceOnlyTorrent());
			$result = rTorrent::sendTorrent($torrent, true, false, $directory, '', false, false);
			$this->assertTrue($result === false, 'sendTorrent() fails for a ' . strlen($directory) . '-byte bad directory');
			$this->assertTrue(rTorrent::refusedDirectory() === true, 'and says it was the directory');
			$this->assertEquals(array(), $this->daemon->calls(),
				'and rtorrent was sent nothing; it saw ' . json_encode($this->daemon->calls()));
		}
	}

	public function testSendTorrentStillLoadsIntoAValidDirectory()
	{
		$this->daemon();
		$GLOBALS['topDirectory'] = self::DEFAULT_DIR . '/';
		$torrent = new Torrent($this->announceOnlyTorrent());
		$result = rTorrent::sendTorrent($torrent, true, false, 'Movies', '', false, false);
		$this->assertTrue($result === $torrent->hash_info(), 'sendTorrent() answers the hash for an ordinary directory');
		$bodies = $this->daemon->bodies();
		$this->assertTrue(count($bodies) === 2 &&
				strpos($bodies[1], 'd.set_directory_base="' . self::DEFAULT_DIR . '/Movies"') !== false,
			'and the load, sent after the mkdir, carries the resolved directory');
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
