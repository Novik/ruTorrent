<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * plugins/httprpc/action.php, mode setsettings, sets rtorrent's default
 * directory from "sdirectory". A download added without a directory of its
 * own is written there, through any symbolic link along it, so the
 * directory has to stay inside $topDirectory once its links are followed,
 * not only as written.
 *
 * The tree is real: a top directory, a directory outside it, a link out, a
 * link that stays inside and a dangling link. What is read is whether
 * rtorrent was asked to set the directory.
 */
class HttprpcSetDirectoryTest extends TestCase
{
	private $base;
	private $top;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-httprpc-setdir-' . uniqid('', true);
		@mkdir($this->base . '/profile/settings', 0700, true);
		@mkdir($this->base . '/top/plain', 0777, true);
		@mkdir($this->base . '/outside', 0777, true);
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';
		symlink($this->base . '/outside', $this->top . '/out');
		symlink($this->top . '/plain', $this->top . '/in');
		symlink($this->base . '/nowhere', $this->top . '/dangle');
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

	/** Sets the default directory through action.php; true when rtorrent was asked to. */
	private function setDirectory($directory)
	{
		$root = realpath(__DIR__ . '/../../..');
		if ($this->daemon !== null) {
			$this->daemon->stop();
		}
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->daemon = new FakeRtorrentDaemon(function ($body, $methods) {
			return array(0);
		}, $this->base . '/calls.log');

		$driver = $this->base . '/drive-setdir.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. '$topDirectory = ' . var_export($this->top . '/', true) . ";\n"
			. '$HTTP_RAW_POST_DATA = ' . var_export('mode=setsettings&s=sdirectory&v='
				. rawurlencode($this->top . $directory), true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/httprpc', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/httprpc/action.php', true) . ");\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return $this->daemon->received('directory.default.set');
	}

	public function testALinkOutOfTheTopDirectoryIsNotSet()
	{
		$this->assertTrue(!$this->setDirectory('/out'),
			'a directory that resolves outside the top directory is not set: ' . json_encode($this->daemon->calls()));
	}

	public function testANewDirectoryUnderALinkOutIsNotSet()
	{
		$this->assertTrue(!$this->setDirectory('/out/new'),
			'nor one that does not exist yet under a link out: ' . json_encode($this->daemon->calls()));
	}

	public function testADanglingLinkIsNotSet()
	{
		$this->assertTrue(!$this->setDirectory('/dangle'),
			'nor a link that does not resolve: ' . json_encode($this->daemon->calls()));
	}

	public function testALinkThatStaysInsideIsSet()
	{
		$this->assertTrue($this->setDirectory('/in'),
			'a link that resolves inside the top directory is set: ' . json_encode($this->daemon->calls()));
	}

	public function testADirectoryInsideIsSet()
	{
		$this->assertTrue($this->setDirectory('/plain/new'),
			'a directory inside the top directory is set: ' . json_encode($this->daemon->calls()));
	}
}
