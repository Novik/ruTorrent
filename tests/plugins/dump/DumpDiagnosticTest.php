<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/DumpActionFixture.php');

/**
 * plugins/dump/action.php hands a .torrent file to an external dumper. It
 * looks for that file in two places -- the copy rtorrent keeps in its session
 * directory, and the file the download is tied to -- and when it can use
 * neither it has to say so instead of starting a task.
 *
 * Two things are measured here.
 *
 * Whether a task is started at all. The decision is `$fname !== ''`, and
 * `$fname` was only ever assigned inside the branch that runs when the XMLRPC
 * request succeeded; a request that fails left it unset, `null !== ''` is
 * true, and the script went on to dump escapeshellarg(null).
 *
 * What the refusal says. Five conditions used to reach the same
 * "Can't retrieve information": the session copy is missing, the session copy
 * is there but this process cannot open it, rtorrent names no session
 * directory, the request carried no hash, and rtorrent did not answer. They
 * send whoever reads the message to five different places, and issue #3360
 * spent a round trip establishing which one had happened -- the reporter's
 * session files were mode 600, which is_readable() had already measured.
 */
class DumpDiagnosticTest extends TestCase
{
	use DumpActionFixture;

	/**
	 * The initialisation of `$fname`. With none, a failing request is
	 * indistinguishable from a found file and the dumper is run on nothing.
	 */
	public function testAFailedRequestStartsNoTask()
	{
		$this->newCase();
		$run = $this->runDump(array(FakeRtorrentDaemon::fault('no such command')));

		$this->assertTrue($this->isPluginError($run),
			'a daemon fault is answered by the plugin, not by a started task: '
				. $this->describe($run));
		$this->assertEquals('dumpErrorNoAnswer', $this->reason($run),
			'and it is reported as the daemon not answering: ' . $this->describe($run));
		$this->assertEquals('', $this->undefinedValueDiagnostics($run),
			'and no unset value is read on the way there: '
				. $this->undefinedValueDiagnostics($run));
	}

	/** The same for a daemon that is not listening: the ordinary outage. */
	public function testAnUnreachableDaemonStartsNoTask()
	{
		$this->newCase();
		$run = $this->runDumpAgainstNothing();

		$this->assertTrue($this->isPluginError($run),
			'an unreachable daemon is answered by the plugin, not by a started task: '
				. $this->describe($run));
		$this->assertEquals('dumpErrorNoAnswer', $this->reason($run),
			'and it is reported as the daemon not answering: ' . $this->describe($run));
		$this->assertEquals('', $this->undefinedValueDiagnostics($run),
			'and no unset value is read on the way there: '
				. $this->undefinedValueDiagnostics($run));
	}

	/**
	 * The control. A message that separates the failures is worth nothing if
	 * it is also given on the path that works.
	 */
	public function testAReadableSessionCopyStartsATask()
	{
		$this->newCase();
		$this->writeSessionCopy(0644);
		$run = $this->runDump(array(array($this->sessionDir(), '')));

		$this->assertTrue(!$this->isPluginError($run),
			'a readable session copy is dumped rather than refused: ' . $this->describe($run));
	}

	/**
	 * #3360. The file is there and the process cannot open it, which is a
	 * permissions job -- so the message names the file and the account that
	 * was refused, because those are the two facts needed to fix it.
	 */
	public function testAnUnreadableSessionCopyNamesTheFileAndTheUser()
	{
		$this->newCase();
		$this->writeSessionCopy(0000);
		$run = $this->runDump(array(array($this->sessionDir(), '')));

		$this->assertTrue($this->isPluginError($run),
			'an unreadable session copy is refused: ' . $this->describe($run));
		$this->assertEquals('dumpErrorUnreadable', $this->reason($run),
			'and reported as unreadable rather than as missing: ' . $this->describe($run));
		$this->assertEquals($this->sessionCopyPath(), $this->field($run, 'file'),
			'the file it could not open is named: ' . $this->describe($run));
		$this->assertEquals($this->processUser(), $this->field($run, 'user'),
			'and the account that was refused is the one the script runs as: '
				. $this->describe($run));
	}

	/**
	 * A tied file is the other candidate, so an unreadable one has to be
	 * named the same way -- naming only the session copy would send its
	 * reader to a file that has nothing wrong with it.
	 */
	public function testAnUnreadableTiedFileIsNamedInsteadOfTheSessionCopy()
	{
		$this->newCase();
		@mkdir($this->sessionDir(), 0700, true);
		$tied = $this->writeTiedFile(0000);
		$run = $this->runDump(array(array($this->sessionDir(), $tied)));

		$this->assertEquals('dumpErrorUnreadable', $this->reason($run),
			'an unreadable tied file is reported as unreadable: ' . $this->describe($run));
		$this->assertEquals($tied, $this->field($run, 'file'),
			'and it is the file that is named: ' . $this->describe($run));
	}

	/** Nothing in the session directory is a different job: no file to open. */
	public function testAMissingSessionCopyIsNotReportedAsAPermissionProblem()
	{
		$this->newCase();
		@mkdir($this->sessionDir(), 0700, true);
		$run = $this->runDump(array(array($this->sessionDir(), '')));

		$this->assertEquals('dumpErrorNoFile', $this->reason($run),
			'a missing session copy is reported as missing: ' . $this->describe($run));
		$this->assertEquals($this->sessionCopyPath(), $this->field($run, 'file'),
			'and the path that was looked at is named: ' . $this->describe($run));
		$this->assertEquals('', $this->field($run, 'user'),
			'with no user, which would only be noise here: ' . $this->describe($run));
	}

	/** rtorrent answering the session question with nothing is a third job. */
	public function testAnEmptySessionPathIsNamedAsSuch()
	{
		$this->newCase();
		$run = $this->runDump(array(array('', '')));

		$this->assertEquals('dumpErrorNoSessionDirectory', $this->reason($run),
			'an empty session path is reported as such: ' . $this->describe($run));
	}

	public function testAMissingNonUtf8TiedFileKeepsItsBytesVisible()
	{
		$this->newCase();
		$run = $this->runDump(array(array('', $this->caseDir . "/missing-\xFF.torrent")));
		$this->assertEquals('dumpErrorNoFile', $this->reason($run),
			'the missing raw-byte file is refused before starting a task');
		$this->assertEquals($this->caseDir . '/missing-\\xFF.torrent', $this->field($run, 'file'),
			'the JSON diagnostic identifies the original byte instead of replacing it');
	}

	/** And a request with no hash never reaches the daemon at all. */
	public function testAMissingHashIsNamedAsSuch()
	{
		$this->newCase();
		$run = $this->runDumpWithoutHash();

		$this->assertEquals('dumpErrorNoHash', $this->reason($run),
			'a request with no hash is reported as such: ' . $this->describe($run));
	}

	/**
	 * The property the single message did not have, stated once: no two of
	 * the five ways this fails arrive as the same answer. Each case above
	 * pins one of them; this pins that none of them collapses into another.
	 */
	public function testEveryFailureHasItsOwnReason()
	{
		$reasons = array();

		$this->newCase();
		$reasons['daemon fault'] = $this->reason(
			$this->runDump(array(FakeRtorrentDaemon::fault('no such command'))));

		$this->newCase();
		$reasons['no session directory'] = $this->reason($this->runDump(array(array('', ''))));

		$this->newCase();
		@mkdir($this->sessionDir(), 0700, true);
		$reasons['no session copy'] = $this->reason(
			$this->runDump(array(array($this->sessionDir(), ''))));

		$this->newCase();
		$this->writeSessionCopy(0000);
		$reasons['unreadable session copy'] = $this->reason(
			$this->runDump(array(array($this->sessionDir(), ''))));

		$this->newCase();
		$reasons['no hash'] = $this->reason($this->runDumpWithoutHash());

		foreach ($reasons as $case => $reason) {
			$this->assertTrue($reason !== '',
				'the ' . $case . ' case names a reason: ' . json_encode($reasons));
			// The reason is a key into theUILang, so one the language file
			// does not define reaches the user as the key itself.
			$this->assertTrue($this->englishDefines($reason),
				'plugins/dump/lang/en.js defines ' . $reason);
		}
		$this->assertEquals(count($reasons), count(array_unique($reasons)),
			'and no two failures share a reason: ' . json_encode($reasons));
	}

	/**
	 * The fallback the plugin has always had: an unreadable session copy is
	 * not the end of it if the download is tied to a file that can be read.
	 * The reasons above are worked out on the way past this, so one written
	 * over the filename would break the path that succeeds.
	 */
	public function testAReadableTiedFileIsStillUsedWhenTheSessionCopyCannotBeRead()
	{
		$this->newCase();
		$this->writeSessionCopy(0000);
		$tied = $this->writeTiedFile(0644);
		$run = $this->runDump(array(array($this->sessionDir(), $tied)));

		$this->assertTrue(!$this->isPluginError($run),
			'the tied file is dumped when the session copy cannot be read: '
				. $this->describe($run));
		$this->assertTrue(strpos($this->dumpCommand($run), $tied) !== false,
			'and it is the tied file that is handed to the dumper: ' . $this->dumpCommand($run));
	}

	/**
	 * Which of the two the dumper gets when both can be read. rtorrent's own
	 * copy is the one that matches the download it is asked about; the tied
	 * file is whatever path the torrent was loaded from, and on an install
	 * where that directory is shared it need not still be the same torrent.
	 */
	public function testTheSessionCopyIsPreferredOverAReadableTiedFile()
	{
		$this->newCase();
		$this->writeSessionCopy(0644);
		$tied = $this->writeTiedFile(0644);
		$run = $this->runDump(array(array($this->sessionDir(), $tied)));
		$command = $this->dumpCommand($run);

		$this->assertTrue(!$this->isPluginError($run),
			'a torrent that is in both places is dumped: ' . $this->describe($run));
		$this->assertTrue(strpos($command, $this->sessionCopyPath()) !== false,
			'from the session copy: ' . $command);
		$this->assertTrue(strpos($command, $tied) === false,
			'and not from the tied file: ' . $command);
	}
}
