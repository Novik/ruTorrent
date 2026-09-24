<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * plugins/bulk_magnet/action.php counts a torrent rtorrent already has as a
 * duplicate, not as an addition.
 *
 * rtorrent answers load.start for an info hash it has already loaded with 0
 * and no fault, and drops the new copy. So the answer to the load cannot tell
 * the two apart; the script has to know what was loaded before it sent it.
 *
 * action.php is a top level script that reads php://input, so it is driven
 * where it lives, in a process of its own, with FakeRtorrentDaemon on the
 * other end of its SCGI socket answering each load as rtorrent does.
 */
class BulkAddDuplicateTest extends TestCase
{
	const LOADED = '1111111111111111111111111111111111111111';
	const FRESH = '2222222222222222222222222222222222222222';

	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-bulk-duplicate-' . getmypid();
		@mkdir($this->base . '/profile/settings', 0700, true);
	}

	public function tearDown()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
		foreach (glob($this->base . '/*') as $entry) {
			is_dir($entry) ? @rmdir($entry) : @unlink($entry);
		}
		@rmdir($this->base . '/profile/settings');
		@rmdir($this->base . '/profile');
		@rmdir($this->base);
	}

	/**
	 * Runs action.php against a daemon that has LOADED and answers every
	 * load with 0, and returns what the script printed.
	 */
	private function bulkAdd($items)
	{
		$root = realpath(__DIR__ . '/../../..');
		$replies = array(array(self::LOADED));
		foreach ($items as $item) {
			$replies[] = array(0);
		}
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');

		$post = '';
		foreach ($items as $item) {
			$post .= '&torrent=' . rawurlencode($item);
		}
		$driver = $this->base . '/drive-bulk.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. '$HTTP_RAW_POST_DATA = ' . var_export($post, true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/bulk_magnet', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/bulk_magnet/action.php', true) . ");\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return json_decode(implode("\n", $output), true);
	}

	public function testATorrentAlreadyLoadedIsADuplicateNotAnAddition()
	{
		$result = $this->bulkAdd(array(
			'magnet:?xt=urn:btih:' . self::LOADED . '&dn=loaded',
			strtolower(self::FRESH),
			'magnet:?xt=urn:btih:' . self::FRESH,
			'not a link',
		));
		$this->assertEquals(array('error' => 1, 'success' => 1, 'duplicate' => 2), $result,
			'one new torrent is added, the loaded one and the second copy of the new one are '
			. 'duplicates, and the line that is not a link is an error; the script answered '
			. json_encode($result));
	}
}
