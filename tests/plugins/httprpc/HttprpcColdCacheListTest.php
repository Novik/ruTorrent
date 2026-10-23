<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * mode=list against a profile that has no settings cache yet.
 *
 * A client that posts to plugins/httprpc/action.php is a door of its own: it
 * does not load the web interface first, so nothing has run php/getplugins.php
 * for it and share/settings/rtorrent.dat may never have been written. The list
 * mode then builds d.multicall with "main" where rtorrent 0.16.16 and later
 * expect the target, and relays the daemon's refusal to the client.
 *
 * The daemon here holds that signature, so the mode is judged by what came
 * back to the client rather than by reading the request it sent.
 */
class HttprpcColdCacheListTest extends TestCase
{
	const HASH = 'C0FFEE0123456789C0FFEE0123456789C0FFEE01';

	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-httprpc-cold-' . getmypid();
		$this->removeTree($this->base);
	}

	public function tearDown()
	{
		$this->stopDaemon();
		$this->removeTree($this->base);
	}

	private function stopDaemon()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
	}

	private function removeTree($path)
	{
		if (!is_dir($path)) {
			return;
		}
		foreach (glob($path . '/*') as $entry) {
			is_dir($entry) ? $this->removeTree($entry) : @unlink($entry);
		}
		@rmdir($path);
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	/** Runs plugins/httprpc/action.php against the daemon; returns its output. */
	private function post($body, $profile)
	{
		$root = $this->repoRoot();
		$this->stopDaemon();
		@mkdir($profile . '/settings', 0700, true);
		$this->daemon = new FakeRtorrentDaemon(RtorrentProbeFixture::responder(self::HASH),
			$this->base . '/calls.log', false, RtorrentProbeFixture::replyClassFiles());

		$driver = $this->base . '/drive-httprpc.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($profile, true) . ";\n"
			. '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
			. '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string)$this->daemon->port(), true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. '$HTTP_RAW_POST_DATA = ' . var_export($body, true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/httprpc', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/httprpc/action.php', true) . ");\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	public function testTheListModeAnswersOnAProfileThatHasNoSettingsCache()
	{
		$out = $this->post('mode=list&cid=0', $this->base . '/cold');

		$this->assertTrue(strpos($out, RtorrentProbeFixture::INVALID_TARGET) === false,
			'rtorrent did not refuse the multicall: ' . substr($out, 0, 200));
		$this->assertTrue(strpos($out, self::HASH) !== false,
			'the torrent list came back: ' . substr($out, 0, 200));
		$this->assertTrue(json_decode($out, true) !== null,
			'and it came back as json, not as an error page: ' . substr($out, 0, 200));
	}

	public function testTheMulticallCarriesTheTargetTheDaemonAsksFor()
	{
		// The same answer read from the other side: the request that reached
		// the daemon is d.multicall("", "main", ...), not d.multicall("main", ...).
		$this->post('mode=list&cid=0', $this->base . '/cold-body');

		$multicall = null;
		foreach ($this->daemon->bodies() as $body) {
			if (RtorrentProbeFixture::methodNames($body) === array('d.multicall')) {
				$multicall = $body;
			}
		}
		$this->assertTrue($multicall !== null,
			'the daemon was asked for a multicall: ' . json_encode($this->daemon->calls()));
		$this->assertEquals('', RtorrentProbeFixture::firstParameter($multicall),
			'its first parameter is the empty target: '
				. json_encode(RtorrentProbeFixture::firstParameter($multicall)));
	}

	public function testTheListModeStillAnswersOnAProfileItCannotWrite()
	{
		// Nothing can be cached here, so the probe happens on every request.
		// It still has to produce an answer -- a read-only profile is a
		// deployment mistake, not a reason to serve a refusal.
		$profile = $this->base . '/readonly';
		@mkdir($profile . '/settings', 0700, true);
		@chmod($profile . '/settings', 0500);
		$out = $this->post('mode=list&cid=0', $profile);
		@chmod($profile . '/settings', 0700);

		$this->assertTrue(strpos($out, self::HASH) !== false,
			'the torrent list came back without a cache to write: ' . substr($out, 0, 200));
	}
}
