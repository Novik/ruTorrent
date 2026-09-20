<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * A "Set data directory" that cannot move the files leaves the download
 * running, because it was running when the move was asked for.
 *
 * rtSetDataDir() stops and closes the download before it touches the
 * filesystem -- rtorrent holds the files open, so the move cannot happen while
 * it is running -- and starts it again at the end. Everything after the move is
 * behind one $is_ok, so a move that does not happen skips the restart as well:
 * the download stays stopped and closed, with its data untouched at the source
 * and nothing anywhere saying why it stopped.
 *
 * That branch used to be hard to reach. rtOpFiles() unlinked whatever stood at
 * a destination name and reported success, so the move failed only on a
 * permission or a full disk; now it refuses an occupied destination, which is
 * an ordinary thing for a user to ask for by accident, and the stopped
 * download is what they get.
 *
 * The assertion is the end condition and not a fixed call list: whatever
 * rtSetDataDir() did to the download's run state on the way in, the run it
 * gives back is the run state it found. FakeRtorrentDaemon is the rtorrent on
 * the other end, and every call asserted here is one it was actually asked for.
 */
class SetDirRefusedMoveTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-setdir-refused-' . getmypid();
	}

	public function tearDown()
	{
		if ($this->daemon !== null)
		{
			$this->daemon->stop();
			$this->daemon = null;
		}
		$this->removeTree($this->base);
	}

	private function removeTree($path)
	{
		if (is_link($path)) { @unlink($path); return; }
		if (!is_dir($path)) { @unlink($path); return; }
		foreach (glob($path . '/*') as $entry)
			$this->removeTree($entry);
		@rmdir($path);
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	/**
	 * Runs plugins/datadir/setdir.php against the daemon for a download that
	 * is open and active, and returns what the daemon was asked for plus what
	 * is on disk afterwards.
	 *
	 * $occupy is the list of names already standing in the destination, and
	 * $isOpen / $isActive are the run state rtorrent reports for the download
	 * before any of this starts -- which is the state it has to be given back.
	 */
	private function setTheDataDirectory($occupy, $isOpen = 1, $isActive = 1)
	{
		$this->removeTree($this->base);
		if ($this->daemon !== null)
			$this->daemon->stop();

		$names = array('1.bin', '2.bin');
		@mkdir($this->base . '/profile/settings', 0700, true);
		@mkdir($this->base . '/src/mytorrent', 0777, true);
		@mkdir($this->base . '/dst', 0777, true);
		foreach ($names as $n)
			file_put_contents($this->base . '/src/mytorrent/' . $n, 'download ' . $n);
		foreach ($occupy as $n)
		{
			@mkdir($this->base . '/dst/mytorrent', 0777, true);
			file_put_contents($this->base . '/dst/mytorrent/' . $n, 'already there');
		}

		// One reply per request rtSetDataDir() makes, in order:
		//   d.is_open + d.is_active
		//   d.open, but only for a download that was closed, so that the base
		//     path and the file list can be read at all
		//   d.get_name, d.get_base_path, d.get_base_filename,
		//     d.is_multi_file, d.get_complete
		//   f.multicall                      -- the download's files
		//   d.stop and/or d.close
		// and then whatever it does after the move, which is what is under
		// test; the spares are so a run that does more is not cut off.
		$replies = array( array($isOpen, $isActive) );
		if (!$isOpen)
			$replies[] = array(0);
		$replies[] = array('mytorrent', $this->base . '/src/mytorrent', 'mytorrent', 1, 1);
		$replies[] = $names;
		$replies[] = array(0, 0);
		for ($i = 0; $i < 6; $i++)
			$replies[] = array(0);
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');

		$driver = $this->base . '/drive-setdir.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10; $rpcLogCalls = false;' . "\n"
			. 'require(' . var_export($this->repoRoot() . '/plugins/datadir/setdir.php', true) . ");\n");

		$cmd = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver)
			. ' ' . escapeshellarg(self::HASH)
			. ' ' . escapeshellarg($this->base . '/dst')
			. ' 1 1 0 tester';
		$out = array();
		exec($cmd . ' 2>&1', $out);

		return array(
			// Only what happened after the files were let go of: the run
			// opens a closed download at the start to read its paths, and that
			// is not the restart being asked about.
			'calls' => $this->daemon->calls(),
			'after_close' => $this->afterTheFilesWereLetGo($this->daemon->calls()),
			'printed' => implode("\n", $out),
			'at_source' => array_map(function($n) {
				return is_file($this->base . '/src/mytorrent/' . $n);
			}, $names),
			'at_dest' => array_map(function($n) {
				return is_file($this->base . '/dst/mytorrent/' . $n)
					&& file_get_contents($this->base . '/dst/mytorrent/' . $n) === 'download ' . $n;
			}, $names),
			'occupants' => array_map(function($n) {
				return @file_get_contents($this->base . '/dst/mytorrent/' . $n);
			}, $occupy),
		);
	}

	/** The calls made after the last d.stop or d.close of the run. */
	private function afterTheFilesWereLetGo($calls)
	{
		$at = -1;
		foreach ($calls as $index => $call)
			if ($call === 'd.stop' || $call === 'd.close')
				$at = $index;
		return $at < 0 ? array() : array_slice($calls, $at + 1);
	}

	private function called($calls, $method)
	{
		return in_array($method, $calls, true);
	}

	/** Any command that repoints the download, however the version spells it. */
	private function repointed($calls)
	{
		foreach ($calls as $call)
			if (strpos($call, 'd.set_directory') === 0 || strpos($call, 'd.directory.set') === 0)
				return true;
		return false;
	}

	/**
	 * The control. With nothing in the way the move happens, the download is
	 * repointed and started again, and the assertions below are therefore
	 * about the collision and not about the fixture.
	 */
	public function testAnUnobstructedMoveRepointsAndRestartsTheDownload()
	{
		$r = $this->setTheDataDirectory(array());
		$calls = $r['calls'];

		$this->assertEquals(array(true, true), $r['at_dest'],
			'both files arrive at the new data directory');
		$this->assertTrue($this->repointed($calls),
			'and rtorrent is pointed at it; calls: ' . implode(', ', $calls));
		$this->assertTrue($this->called($calls, 'd.start'),
			'and the download it stopped is started again; calls: ' . implode(', ', $calls));
	}

	/**
	 * The whole point: the download was running when the move was asked for,
	 * the move did not happen, and it has to be running when the run ends.
	 */
	public function testARefusedMoveGivesTheDownloadBackTheWayItWasFound()
	{
		$r = $this->setTheDataDirectory(array('2.bin'));
		$calls = $r['calls'];

		$this->assertTrue($this->called($calls, 'd.stop'),
			'the run stops the download before it touches the files; calls: '
			. implode(', ', $calls));
		$this->assertTrue($this->called($r['after_close'], 'd.start'),
			'and starts it again when the move is refused, because it was running '
			. 'when it was asked; calls: ' . implode(', ', $calls));
		$this->assertTrue($this->called($r['after_close'], 'd.open'),
			'and a download it closed is opened again; calls: ' . implode(', ', $calls));
	}

	/**
	 * And the other way round. A download that was already stopped when the
	 * move was asked for is given back stopped: a refusal is not an occasion to
	 * start something the user had paused.
	 */
	public function testARefusedMoveDoesNotStartADownloadThatWasNotRunning()
	{
		$r = $this->setTheDataDirectory(array('2.bin'), 0, 0);

		$this->assertTrue(!$this->called($r['after_close'], 'd.start'),
			'a download that was not running is not started by a refused move; '
			. 'calls: ' . implode(', ', $r['calls']));
		$this->assertTrue(!$this->called($r['after_close'], 'd.open'),
			'and a download that was closed is left closed; calls: '
			. implode(', ', $r['calls']));
		$this->assertEquals(array(true, true), $r['at_source'],
			'and its data is untouched at the source');
	}

	/**
	 * And nothing was applied on rtorrent's side: a download whose directory
	 * was changed without its files being moved cannot find its data.
	 */
	public function testARefusedMoveDoesNotRepointTheDownload()
	{
		$r = $this->setTheDataDirectory(array('2.bin'));

		$this->assertTrue(!$this->repointed($r['calls']),
			'a refused move leaves the download pointing at its data; calls: '
			. implode(', ', $r['calls']));
		$this->assertEquals(array(true, true), $r['at_source'],
			'which is still whole at the source');
		$this->assertEquals(array('already there'), $r['occupants'],
			'and the file that was already at the destination survives');
	}
}
