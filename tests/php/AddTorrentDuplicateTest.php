<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/RtorrentProbeFixture.php');
require_once(__DIR__ . '/TestHttpServer.php');
require_once(__DIR__ . '/TestTree.php');

/**
 * php/addtorrent.php reports a torrent rtorrent already has as a duplicate,
 * not as an addition.
 *
 * rtorrent answers a load for an info hash it has already loaded with 0 and
 * no fault, and drops the new copy. So the answer to the load cannot tell the
 * two apart; the script has to know what was loaded before it sent it.
 *
 * A load rtorrent refuses is the third outcome: no hash comes back at all,
 * so it is neither an addition nor a duplicate.
 *
 * addtorrent.php answers with a redirect whose result[] values are the
 * outcome, so it is served by PHP's built-in server, through a router that
 * points it at FakeRtorrentDaemon, and the Location header is what is read.
 */
class AddTorrentDuplicateTest extends TestCase
{
	const LOADED = '1111111111111111111111111111111111111111';
	const FRESH = '2222222222222222222222222222222222222222';

	private $base;
	private $daemon;
	private $server;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-addtorrent-duplicate-' . uniqid('', true);
		@mkdir($this->base . '/profile/torrents', 0700, true);
		@mkdir($this->base . '/profile/settings', 0700, true);
	}

	public function tearDown()
	{
		$this->stop();
		TestTree::remove($this->base);
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

	/** A single file torrent whose info dictionary hashes to the return value. */
	private function torrent(&$hash)
	{
		$info = 'd6:lengthi1e4:name5:a.bin12:piece lengthi16384e6:pieces20:'
			. str_repeat("\x01", 20) . 'e';
		$hash = strtoupper(sha1($info));
		$announce = 'http://127.0.0.1:1/announce';
		return 'd8:announce' . strlen($announce) . ':' . $announce . '4:info' . $info . 'e';
	}

	/**
	 * Sends one request to addtorrent.php, with a daemon that has the $loaded
	 * hashes and answers $loads loads, and returns the result[] values it
	 * redirects with. $loads is a count of loads answered the way rtorrent
	 * answers an accepted one, or the list of reply entries to answer them
	 * with when the answer itself is the subject.
	 */
	private function add($loaded, $loads, $contentType, $body)
	{
		$this->stop();
		$root = realpath(__DIR__ . '/../..');
		$replies = array_merge(array($loaded),
			is_array($loads) ? $loads : array_fill(0, $loads, array(0)));
		// An install whose interface has been opened has a settings cache.
		// This script is driven against a profile of its own, so leave one
		// there rather than have it probe and spend the replies below.
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');

		$router = $this->base . '/router.php';
		file_put_contents($router, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. '$saveUploadedTorrents = true;' . "\n"
			. 'chdir(' . var_export($root . '/php', true) . ");\n"
			. 'require(' . var_export($root . '/php/addtorrent.php', true) . ");\n"
			. "return true;\n");

		$this->server = TestHttpServer::start($this->base, array(), PhpProcess::sameIni(), $router);
		$response = TestHttpServer::rawPost($this->server->port(), '/php/addtorrent.php', $body, $contentType);
		if (!isset($response['headers']['location'])
			|| !preg_match('`^[^?]*\?(.*)$`', $response['headers']['location'], $location)) {
			return array('no redirect: ' . $response['body']);
		}
		parse_str($location[1], $query);
		return isset($query['result']) ? $query['result'] : array();
	}

	private function loadCalls()
	{
		$calls = $this->daemon->calls();
		$this->assertEquals('download_list', isset($calls[0]) ? $calls[0] : null,
			'the loaded hashes are read before anything is loaded: ' . implode(', ', $calls));
		return count(array_filter($calls, function($call) {
			return (strpos($call, 'load') === 0);
		}));
	}

	public function testAMagnetAlreadyLoadedIsADuplicateNotAnAddition()
	{
		$urls = 'magnet:?xt=urn:btih:' . self::LOADED . '&dn=loaded' . "\n"
			. 'magnet:?xt=urn:btih:' . self::FRESH . "\n"
			. 'magnet:?xt=urn:btih:' . strtolower(self::FRESH);
		$results = $this->add(array(self::LOADED), 3, 'application/x-www-form-urlencoded',
			'url=' . rawurlencode($urls));
		$this->assertEquals(array('Duplicate', 'Success', 'Duplicate'), $results,
			'the loaded magnet and the second copy of the new one are duplicates: ' . json_encode($results));
		$this->assertEquals(3, $this->loadCalls(), 'each magnet was still sent');
	}

	private function upload($loaded, $names, $loads = null)
	{
		foreach ($this->kept() as $file) {
			unlink($this->base . '/profile/torrents/' . $file);
		}
		$torrent = $this->torrent($hash);
		$boundary = 'dup' . md5($torrent);
		$body = '';
		foreach ($names as $name) {
			$body .= '--' . $boundary . "\r\n"
				. 'Content-Disposition: form-data; name="torrent_file[]"; filename="' . $name . '"' . "\r\n"
				. "Content-Type: application/x-bittorrent\r\n\r\n" . $torrent . "\r\n";
		}
		$body .= '--' . $boundary . "--\r\n";
		return $this->add($loaded ? array(self::LOADED, $hash) : array(self::LOADED),
			$loads === null ? count($names) : $loads,
			'multipart/form-data; boundary=' . $boundary, $body);
	}

	private function kept()
	{
		return array_values(array_diff(scandir($this->base . '/profile/torrents'), array('.', '..')));
	}

	public function testAnUploadedTorrentAlreadyLoadedIsADuplicateAndNotKept()
	{
		$results = $this->upload(true, array('one.torrent'));
		$this->assertEquals(array('Duplicate'), $results,
			'an upload of a loaded torrent is a duplicate: ' . json_encode($results));
		$this->assertEquals(1, $this->loadCalls(), 'the upload was still sent');
		$this->assertEquals(array(), $this->kept(), 'and the uploaded copy is not kept');
	}

	public function testTheSecondCopyInOneUploadIsADuplicate()
	{
		$results = $this->upload(false, array('one.torrent', 'two.torrent'));
		$this->assertEquals(array('Success', 'Duplicate'), $results,
			'the second copy of the same torrent is a duplicate: ' . json_encode($results));
		$this->assertEquals(2, $this->loadCalls(), 'both uploads were sent');
		$this->assertEquals(array('one.torrent'), $this->kept(),
			'only the upload that was added is kept: ' . json_encode($this->kept()));
	}

	public function testAnUploadRtorrentRefusesIsFailedAndNotKept()
	{
		$results = $this->upload(false, array('one.torrent'),
			array(FakeRtorrentDaemon::fault('Could not create download')));
		$this->assertEquals(array('Failed'), $results,
			'a load rtorrent refused is a failure: ' . json_encode($results));
		$this->assertEquals(1, $this->loadCalls(), 'the upload was still sent');
		$this->assertEquals(array(), $this->kept(),
			'and the refused copy is not kept: ' . json_encode($this->kept()));
	}

	public function testAMagnetRtorrentRefusesIsFailed()
	{
		$results = $this->add(array(self::LOADED),
			array(FakeRtorrentDaemon::fault('Could not create download')),
			'application/x-www-form-urlencoded',
			'url=' . rawurlencode('magnet:?xt=urn:btih:' . self::FRESH));
		$this->assertEquals(array('Failed'), $results,
			'a magnet rtorrent refused is a failure: ' . json_encode($results));
		$this->assertEquals(1, $this->loadCalls(), 'the magnet was still sent');
	}

	public function testTheResultPageShowsADuplicateAsAnAlert()
	{
		$root = realpath(__DIR__ . '/../..');
		$driver = $this->base . '/drive-result.php';
		file_put_contents($driver, "<?php\n"
			. '$_REQUEST = array("result" => array("Duplicate"), "name" => array("x.torrent"));' . "\n"
			. 'chdir(' . var_export($root . '/php', true) . ");\n"
			. 'require(' . var_export($root . '/php/addtorrent.php', true) . ");\n");
		$output = shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver));
		$this->assertEquals('noty("x.torrent - "+theUILang["addTorrent"+"Duplicate"],"alert");', $output,
			'a duplicate is named with its own wording in the alert style');
	}
}
