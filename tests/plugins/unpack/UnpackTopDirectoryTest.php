<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * unpack extracts into a directory the archive tool writes through any
 * symbolic link along it, so the directory it is given has to stay inside
 * $topDirectory once its links are followed, not only as written. That is
 * the directory in the plugin's settings with the label and name added, or
 * the download's own directory when the setting is empty.
 *
 * The tree is real: a top directory with a zip in it, a directory outside
 * it, a link out and a link that stays inside. unpack runs in dry-run mode,
 * so what is read is whether it went as far as starting the extraction,
 * from its own log.
 */
class UnpackTopDirectoryTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
	const STARTED = 'Start unpack operation';

	private $base;
	private $top;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-unpack-top-' . uniqid('', true);
		@mkdir($this->base . '/profile/settings', 0700, true);
		@mkdir($this->base . '/top/plain', 0777, true);
		@mkdir($this->base . '/outside/held', 0777, true);
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';
		file_put_contents($this->top . '/plain/a.zip', 'zip');
		file_put_contents($this->base . '/outside/held/b.zip', 'zip');
		symlink($this->base . '/outside', $this->top . '/out');
		symlink($this->top . '/plain', $this->top . '/in');
	}

	public function tearDown()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
		$this->removeTree($this->base);
	}

	private function removeTree($path)
	{
		if (is_link($path) || !is_dir($path)) {
			@unlink($path);
			return;
		}
		foreach (glob($path . '/*') as $entry) {
			$this->removeTree($entry);
		}
		@rmdir($path);
	}

	/**
	 * Drives $call against an rUnpack whose output directory setting is $path,
	 * with rtorrent answering f.get_frozen_path with $frozen. Returns what the
	 * call returned and what unpack logged.
	 */
	private function drive($path, $call, $frozen = '', $replies = null)
	{
		$root = realpath(__DIR__ . '/../../..');
		if ($this->daemon !== null) {
			$this->daemon->stop();
		}
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->daemon = new FakeRtorrentDaemon(($replies === null) ? array(array($frozen)) : $replies,
			$this->base . '/calls.log');
		$log = $this->base . '/unpack.log';
		@unlink($log);

		$driver = $this->base . '/drive-unpack.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . ';'
				. ' $rpcTimeOut = 10; $rpcLogCalls = false;' . "\n"
			. '$topDirectory = ' . var_export($this->top . '/', true) . ";\n"
			. '$log_file = ' . var_export($log, true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/unpack', true) . ");\n"
			. 'require_once(' . var_export($root . '/plugins/unpack/unpack.php', true) . ");\n"
			. '$unpack_debug_enabled = "dry-run";' . "\n"
			. '$up = new rUnpack(); $up->path = ' . var_export($path, true) . ";\n"
			. '$ret = ' . $call . ";\n"
			. 'echo json_encode($ret);' . "\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return array(
			'ret' => json_decode(implode("\n", $output), true),
			'printed' => implode("\n", $output),
			'log' => is_file($log) ? file_get_contents($log) : '',
		);
	}

	private function silent($path, $basename)
	{
		return $this->drive($path, '$up->startSilentTask(' . var_export($basename, true)
			. ', ' . var_export(dirname($basename), true) . ', "", "a", "' . self::HASH . '")');
	}

	private function manual($dir, $frozen)
	{
		return $this->drive('', '$up->startTask("' . self::HASH . '", ' . var_export($dir, true)
			. ', "zip", 0)', $frozen);
	}

	/**
	 * A manual unpack of the whole download: rtorrent reports its base path,
	 * label and name, then its files.
	 */
	private function whole($dir, $basePath)
	{
		return $this->drive('', '$up->startTask("' . self::HASH . '", ' . var_export($dir, true)
			. ', "", 0)', '', array(array($basePath, '', 'a'), array(basename($basePath))));
	}

	private function started($r)
	{
		return strpos($r['log'], self::STARTED) !== false;
	}

	public function testAutoUnpackThroughALinkOutIsNotStarted()
	{
		$r = $this->silent($this->top . '/out', $this->top . '/plain/a.zip');
		$this->assertTrue(!$this->started($r),
			'an output directory that resolves outside the top directory is not unpacked into: ' . $r['log']);
	}

	public function testAutoUnpackIntoTheDownloadsOwnDirectoryThroughALinkOutIsNotStarted()
	{
		$r = $this->silent('', $this->top . '/out/held/b.zip');
		$this->assertTrue(!$this->started($r),
			'nor is a download\'s own directory reached through a link out: ' . $r['log']);
	}

	public function testAutoUnpackThroughALinkThatStaysInsideIsStarted()
	{
		$r = $this->silent($this->top . '/in', $this->top . '/plain/a.zip');
		$this->assertTrue($this->started($r),
			'a link that resolves inside the top directory is unpacked into: ' . $r['log']);
	}

	public function testAutoUnpackIntoTheDownloadsOwnDirectoryIsStarted()
	{
		$r = $this->silent('', $this->top . '/plain/a.zip');
		$this->assertTrue($this->started($r),
			'with no output directory set, the download\'s own directory is used: ' . $r['log']);
	}

	public function testManualUnpackThroughALinkOutIsRefused()
	{
		$r = $this->manual($this->top . '/out', $this->top . '/plain/a.zip');
		$this->assertTrue(!$this->started($r),
			'a manual unpack into a link out is not started: ' . $r['log']);
		$this->assertTrue(is_array($r['ret']) && count($r['ret']['errors']) > 0
			&& ($r['ret']['errors'] !== array('Dry-run operation, no real task start')),
			'and is answered with an error of its own: ' . $r['printed']);
	}

	public function testManualUnpackIntoTheDownloadsOwnDirectoryThroughALinkOutIsRefused()
	{
		$r = $this->manual('', $this->top . '/out/held/b.zip');
		$this->assertTrue(!$this->started($r),
			'nor one into a download\'s own directory reached through a link out: ' . $r['log']);
	}

	public function testManualUnpackOfAWholeDownloadThroughALinkOutIsRefused()
	{
		$r = $this->whole($this->top . '/out', $this->top . '/plain/a.zip');
		$this->assertTrue(!$this->started($r),
			'a whole download unpacked into a link out is not started: ' . $r['log']);
	}

	public function testManualUnpackOfAWholeDownloadInsideIsStarted()
	{
		$r = $this->whole($this->top . '/plain/new', $this->top . '/plain/a.zip');
		$this->assertTrue($this->started($r),
			'a whole download unpacked inside the top directory is started: ' . $r['log']);
	}

	public function testManualUnpackInsideIsStarted()
	{
		$r = $this->manual($this->top . '/plain/new', $this->top . '/plain/a.zip');
		$this->assertTrue($this->started($r),
			'a manual unpack inside the top directory is started: ' . $r['log']);
	}
}
