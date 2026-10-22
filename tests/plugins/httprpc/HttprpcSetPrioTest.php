<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * plugins/httprpc/action.php, mode setprio, turns each "v" of the request
 * into the index of a file of the torrent. "v" is the door's general
 * parameter -- other modes read a label or a peer address out of it -- so a
 * value that is not a file index is a value this mode has nothing to do
 * with, and intval() is not what decides: it answers 0 for every string that
 * is not a number, and file 0 is a file of the torrent.
 *
 * The subject is therefore which f.set_priority calls reach rtorrent, read
 * off the XMLRPC body the daemon was sent rather than off the script's
 * output, which says nothing about them.
 */
class HttprpcSetPrioTest extends TestCase
{
	private $base;
	private $daemon;
	const HASH = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-httprpc-setprio-' . getmypid();
		@mkdir($this->base . '/profile/settings', 0700, true);
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
		if (!is_dir($path)) {
			return;
		}
		foreach (glob($path . '/*') as $entry) {
			is_dir($entry) ? $this->removeTree($entry) : @unlink($entry);
		}
		@rmdir($path);
	}

	/** Posts a form body to action.php and returns its output. */
	private function post($body)
	{
		$root = realpath(__DIR__ . '/../../..');
		if ($this->daemon !== null) {
			$this->daemon->stop();
		}
		// An install whose interface has been opened has a settings cache;
		// leave one here rather than have the script probe and spend the
		// reply the request under test needs.
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->daemon = new FakeRtorrentDaemon(array(array(0, 0, 0)), $this->base . '/calls.log');

		$driver = $this->base . '/drive-setprio.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. '$topDirectory = "/";' . "\n"
			. '$HTTP_RAW_POST_DATA = ' . var_export($body, true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/httprpc', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/httprpc/action.php', true) . ");\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	/**
	 * The file every priority call named, in order. rtorrent is asked by
	 * "<hash>:f<index>", so the index is read back as the bytes the script
	 * put there rather than as a number -- a value intval() turned into
	 * something else has to be visible here.
	 */
	private function filesSet()
	{
		$files = array();
		foreach ($this->daemon->bodies() as $body) {
			if (preg_match_all('`<string>' . self::HASH . ':f([^<]*)</string>`', $body, $matches)) {
				foreach ($matches[1] as $file) {
					$files[] = $file;
				}
			}
		}
		return $files;
	}

	private function setprio($values, $priority = '0')
	{
		$body = 'mode=setprio&hash=' . self::HASH;
		foreach ($values as $value) {
			$body .= '&v=' . $value;
		}
		return $this->post($body . '&s=' . $priority);
	}

	public function testEveryFileNamedIsTheFileSet()
	{
		$this->setprio(array('2', '3', '11'));
		$this->assertEquals(array('2', '3', '11'), $this->filesSet(),
			'each index named is set, and no other file is: ' . json_encode($this->filesSet()));
	}

	/**
	 * The shape plugins/mobile sent for every folder priority change: an
	 * empty segment ahead of the real indexes.
	 */
	public function testAnEmptySegmentSetsNoFile()
	{
		$this->setprio(array('', '2', '3'));
		$this->assertEquals(array('2', '3'), $this->filesSet(),
			'an empty v names no file, so file 0 is left alone: ' . json_encode($this->filesSet()));
	}

	public function testAValueThatIsNotANumberSetsNoFile()
	{
		foreach (array('x', '1x', ' 1', '+1', '-1', '0x2', '1.0') as $value) {
			$this->setprio(array($value));
			$this->assertEquals(array(), $this->filesSet(),
				'"' . $value . '" is not a file index, so it sets no file, and it set '
					. json_encode($this->filesSet()));
		}
	}

	public function testTheTorrentIsStillToldToApplyThem()
	{
		$this->setprio(array('', '2'));
		$this->assertTrue($this->daemon->received('d.update_priorities'),
			'rtorrent is asked to apply the change: ' . json_encode($this->daemon->calls()));
	}
}
