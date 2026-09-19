<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * plugins/edit/action.php erases the torrent before it reloads it.
 *
 * Saving an edit is d.erase followed by a load of the edited bytes, with
 * nothing in between that could put the old one back: if the load does not
 * happen the download is gone, and action.php's only response is to record
 * theUILang.errorAddTorrent and move to the next hash. So the reload failing
 * is data loss, and the two calls have to be watched together to see it.
 *
 * rTorrent::ADDITION_COMMANDS is what made the reload fail. action.php passes
 * d.set_custom3 in its addition and sendTorrent() drops the whole add when one
 * addition is not on that list, so it returned false before building a
 * payload -- after the erase had already gone through.
 *
 * action.php is a top level script that reads php://input, so it is driven
 * where it lives, in a process of its own, with FakeRtorrentDaemon on the
 * other end of its SCGI socket. Everything asserted here is what that daemon
 * was actually asked for. tests/plugins/edit/EditActionSequenceTest.php covers
 * the bencoding the script produces, against a transcription of it; this
 * covers the call sequence, against the script itself.
 */
class EditActionReloadTest extends TestCase
{
	use TorrentSequenceFixtures;

	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-edit-reload-' . getmypid();
		@mkdir($this->base . '/session', 0700, true);
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

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	/**
	 * Runs plugins/edit/action.php against the daemon and returns what it
	 * printed. The nine values are the multicall action.php opens with:
	 * get_session, d.is_open, d.is_active, d.get_state, d.get_tied_to_file,
	 * d.get_custom1, d.get_directory_base, d.get_connection_seed and
	 * d.get_complete. d.get_complete is 0 so the reload does not take the
	 * fast resume branch, and the directory is empty so the add does not need
	 * a directory to be approved.
	 */
	private function saveAnEdit($post)
	{
		$root = $this->repoRoot();
		if ($this->daemon !== null) {
			$this->daemon->stop();
		}
		// sendTorrent() is called with saveTorrent false, so it unlinks the
		// session copy it loaded from; each run gets a fresh one.
		file_put_contents($this->base . '/session/' . self::HASH . '.torrent',
			$this->announceOnlyTorrent());
		$this->daemon = new FakeRtorrentDaemon(array(
			array($this->base . '/session/', 1, 1, 1, '', '', '', 'seed', 0),
			array(0),
			array(0),
			array(0),
		), $this->base . '/calls.log');

		$driver = $this->base . '/drive-edit.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. '$saveUploadedTorrents = false;' . "\n"
			. '$HTTP_RAW_POST_DATA = ' . var_export($post, true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/edit', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/edit/action.php', true) . ");\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	/** The index of the first call to $method, or -1. */
	private function indexOf($method)
	{
		$calls = $this->daemon->calls();
		$at = array_search($method, $calls, true);
		return $at === false ? -1 : $at;
	}

	/** The load command, whatever the daemon in front of it spells it. */
	private function loadCallIndex()
	{
		foreach ($this->daemon->calls() as $index => $call) {
			if (strpos($call, 'load') === 0 || strpos($call, 'load.') === 0) {
				return $index;
			}
		}
		return -1;
	}

	/**
	 * The whole point: the torrent the script erased is loaded again in the
	 * same run. Before the addition allowlist knew about d.set_custom3 the
	 * erase went through and the load never did.
	 */
	public function testAnEditedTorrentIsReloadedAfterItIsErased()
	{
		$printed = $this->saveAnEdit('hash=' . self::HASH . '&set_comment=1&comment='
			. rawurlencode('an edited comment'));

		$erase = $this->indexOf('d.erase');
		$load = $this->loadCallIndex();

		$this->assertTrue($erase >= 0,
			'the script erases the torrent before reloading it; calls: '
			. implode(', ', $this->daemon->calls()));
		$this->assertTrue($load >= 0,
			'and loads it again -- a run that erases and does not load has lost the '
			. 'download; calls: ' . implode(', ', $this->daemon->calls()));
		$this->assertTrue($erase < $load,
			'the erase comes first, which is why a refused reload cannot be retried');
		$this->assertTrue(strpos($printed, 'errorAddTorrent') === false,
			'and the script reports no add error: ' . $printed);
		$this->assertTrue(strpos($printed, self::HASH) !== false,
			'the saved hash comes back in the response: ' . $printed);
	}

	/**
	 * The same run seen from the other side: the reload carries the commands
	 * action.php builds as its addition, so they reached the daemon rather
	 * than stopping the add.
	 */
	public function testTheReloadCarriesTheEditPluginsAddition()
	{
		$this->saveAnEdit('hash=' . self::HASH . '&set_private=1&private=1');

		$this->assertTrue($this->loadCallIndex() >= 0,
			'a private-flag edit reloads too; calls: ' . implode(', ', $this->daemon->calls()));
	}

	/**
	 * The throttle plugin is not registered in this fixture, so action.php
	 * asks for nine values and never reads a throttle name. It used to put the
	 * unset throttle into the addition list anyway, and a non-string entry
	 * refuses the whole add.
	 */
	public function testTheReloadHappensWithNoThrottlePluginRegistered()
	{
		$this->saveAnEdit('hash=' . self::HASH . '&set_comment=1&comment=x');

		$calls = $this->daemon->calls();
		$this->assertTrue(!in_array('d.get_throttle_name', $calls, true),
			'the fixture has no throttle plugin, so no throttle name is read');
		$this->assertTrue($this->loadCallIndex() >= 0,
			'and the reload still happens; calls: ' . implode(', ', $calls));
	}
}
