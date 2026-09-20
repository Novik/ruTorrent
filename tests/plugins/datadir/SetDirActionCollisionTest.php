<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * "Set data directory" answers the one refusal it can answer before it starts
 * anything.
 *
 * action.php does not do the move. It asks rTorrent to run setdir.php through
 * a shell line that ends "& exit 0", so the job is detached and the shell
 * reports success the moment it is started; what action.php sends back is that
 * the job started, never that it worked. rtOpFiles() refusing an occupied
 * destination therefore happens minutes later in a process nobody is listening
 * to, and the dialog closes as though the move had been made.
 *
 * A destination that is already taken is the one refusal that does not need
 * the move to have started: it reads the destination and the download's file
 * list, and nothing else. So action.php tests it first, and a taken name comes
 * back as an error the user can act on -- init.js leaves the dialog open and
 * shows the path that stopped it -- instead of as silence.
 *
 * The risk in a check that runs early is that it is stricter than the move it
 * stands in for, and refuses something that would have worked. Both call
 * rtTakenDestination(), so a name is taken here exactly when it is taken
 * there; the cases below pin the three ways that could still go wrong -- a
 * name that only looks taken, a download the check cannot read, and a
 * destination that is where the data already is.
 */
class SetDirActionCollisionTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-setdir-action-' . getmypid();
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
	 * Posts a "set data directory" to action.php and reports what came back
	 * and what rTorrent was asked for.
	 *
	 * $occupy are names already standing under the destination, $isOpen is the
	 * run state rTorrent reports, and $datadir defaults to a directory that is
	 * not where the data already is.
	 */
	private function setTheDataDirectory($occupy, $isOpen = 1, $datadir = null, $moveFiles = 1)
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
		if ($datadir === null)
			$datadir = $this->base . '/dst';

		// One reply per request. Before the check existed the first request
		// was the execute; the list serves both shapes, because the only
		// thing read out of a reply is that it came back at all.
		$this->daemon = new FakeRtorrentDaemon(array(
			array($isOpen, 'mytorrent', $this->base . '/src/mytorrent', 'mytorrent', 1),
			$names,
			array(0), array(0), array(0),
		), $this->base . '/calls.log');

		$driver = $this->base . '/drive-action.php';
		file_put_contents($driver, "<?php\n"
			. '$_SERVER[\'REMOTE_USER\'] = "tester"; $_ENV[\'RU_PROFILE_PATH\'] = '
				. var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . ';'
				. ' $rpcTimeOut = 10; $rpcLogCalls = false;' . "\n"
			. '$topDirectory = "/";' . "\n"
			. '$HTTP_RAW_POST_DATA = "hash=' . self::HASH . '&datadir='
				. rawurlencode($datadir) . '&move_addpath=1&move_datafiles=' . $moveFiles
				. '&move_fastresume=0";' . "\n"
			. 'chdir(' . var_export($this->repoRoot() . '/plugins/datadir', true) . ");\n"
			. 'require(' . var_export($this->repoRoot() . '/plugins/datadir/action.php', true) . ");\n");

		$out = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver)
			. ' 2>&1', $out);
		$printed = implode("\n", $out);
		$answer = json_decode($printed, true);
		if (!is_array($answer) || !array_key_exists('errors', $answer))
			throw new Exception('action.php printed: ' . $printed);

		return array(
			'printed' => $printed,
			'errors' => $answer['errors'],
			'calls' => $this->daemon->calls(),
			'started' => in_array('execute', $this->daemon->calls(), true),
		);
	}

	/**
	 * The whole point: the dialog is told the move did not happen, and which
	 * name stopped it, rather than being told nothing at all.
	 */
	public function testATakenDestinationComesBackAsAnError()
	{
		$r = $this->setTheDataDirectory(array('2.bin'));

		$this->assertTrue(count($r['errors']) > 0,
			'a destination that is already taken is refused before anything is '
			. 'started; action.php answered: ' . $r['printed']);
		$this->assertTrue(!$r['started'],
			'and the detached move is not started at all; calls: '
			. implode(', ', $r['calls']));
		$this->assertTrue(strpos($r['printed'], '2.bin') !== false,
			'and the name that stopped it is in the answer, because it is the '
			. 'one thing the user can act on: ' . $r['printed']);
	}

	/**
	 * A name that only looks taken. On a case sensitive filesystem 2.BIN does
	 * not occupy 2.bin, the move would have made it, and the check must not
	 * refuse it: it is the same test the move applies, not a stricter one.
	 */
	public function testANameThatDiffersOnlyInCaseIsNotTreatedAsTaken()
	{
		// Whether 2.BIN occupies 2.bin is the filesystem's answer, not an
		// assumption, so it is asked rather than assumed.
		@mkdir($this->base, 0777, true);
		file_put_contents($this->base . '/CASE', 'x');
		$foldsCase = file_exists($this->base . '/case');
		@unlink($this->base . '/CASE');
		if ($foldsCase)
		{
			$this->assertTrue(true,
				'the filesystem under the test tree folds case, so 2.BIN does '
				. 'occupy 2.bin and there is nothing to tell apart here');
			return;
		}

		$r = $this->setTheDataDirectory(array('2.BIN'));

		$this->assertEquals(array(), $r['errors'],
			'a name differing only in case does not occupy the destination, so '
			. 'the move goes ahead; action.php answered: ' . $r['printed']);
		$this->assertTrue($r['started'],
			'and the move is started; calls: ' . implode(', ', $r['calls']));
	}

	/**
	 * A download the check cannot read. rTorrent reports no base path or file
	 * list for a closed download, and opening one to answer a question would
	 * be a change made by a check, so the collision is left to the move --
	 * which is where it was found before there was a check at all.
	 */
	public function testAClosedDownloadIsNotOpenedToAnswerTheQuestion()
	{
		$r = $this->setTheDataDirectory(array('2.bin'), 0);

		$this->assertTrue(!in_array('d.open', $r['calls'], true),
			'the check does not open a closed download; calls: '
			. implode(', ', $r['calls']));
		$this->assertTrue($r['started'],
			'and hands it to the move as before; calls: ' . implode(', ', $r['calls']));
	}

	/**
	 * A destination that is where the data already is. Every name is taken --
	 * by the download's own files -- and no move would happen, so refusing it
	 * would be refusing a request that cannot collide with anything.
	 */
	public function testMovingADownloadToWhereItAlreadyIsIsNotRefused()
	{
		$r = $this->setTheDataDirectory(array(), 1, $this->base . '/src');

		$this->assertEquals(array(), $r['errors'],
			'a download is not refused its own directory; action.php answered: '
			. $r['printed']);
		$this->assertTrue($r['started'],
			'calls: ' . implode(', ', $r['calls']));
	}

	/**
	 * And a request that does not move the files cannot collide with
	 * anything, so nothing is read for it.
	 */
	public function testARequestThatDoesNotMoveTheFilesIsNotChecked()
	{
		$r = $this->setTheDataDirectory(array('2.bin'), 1, null, 0);

		$this->assertEquals(array(), $r['errors'],
			'leaving the data where it is cannot collide; action.php answered: '
			. $r['printed']);
		$this->assertTrue(!in_array('f.multicall', $r['calls'], true),
			'so the file list is not read; calls: ' . implode(', ', $r['calls']));
		$this->assertTrue($r['started'],
			'and the directory change is started; calls: ' . implode(', ', $r['calls']));
	}
}
