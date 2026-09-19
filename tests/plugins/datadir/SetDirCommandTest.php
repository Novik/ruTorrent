<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/datadir/setdircommand.php');

/**
 * action.php starts setdir.php through rtorrent's execute with a shell:
 * "sh", "-c", <one string>. Seven values go into that string. Six of them were
 * escaped and the hash was interpolated raw, so the only thing standing between
 * a hash and the shell was the ctype_xdigit() check action.php makes on the
 * request parameter. That check does hold there, so this was not reachable from
 * the panel -- it was one caller, or one refactor of that caller, away from
 * being reachable, with nothing at the shell to fall back on.
 */
class SetDirCommandTest extends TestCase
{
	private function build($hash)
	{
		return rtSetDirCommand('/usr/bin/php', '/srv/plugins/datadir/setdir.php',
			$hash, '/srv/downloads', '1', '0', '1', 'someone');
	}

	// What the interpolation produced, kept as the control: the hash reached
	// the shell as shell, and a hash is a request parameter.
	private function buildTheOldWay($hash)
	{
		return escapeshellarg('/usr/bin/php') . ' ' .
			escapeshellarg('/srv/plugins/datadir/setdir.php') .
			' ' . $hash . ' ' . escapeshellarg('/srv/downloads') .
			' ' . '1' . ' ' . '0' . ' ' . '1' .
			' ' . escapeshellarg('someone') . ' & exit 0';
	}

	public function testTheInterpolatedHashReachedTheShellAsShell()
	{
		$command = $this->buildTheOldWay('a;touch /tmp/x;b');
		$this->assertTrue(strpos($command, ' a;touch /tmp/x;b ') !== false,
			'the interpolated hash stood in the command as its own shell words');
	}

	public function testAHashCarryingShellMetacharactersIsOneWord()
	{
		$command = $this->build('a;touch /tmp/x;b');
		$this->assertTrue(strpos($command, ' a;touch /tmp/x;b ') === false,
			'the hash no longer stands in the command unquoted');
		$this->assertTrue(strpos($command, escapeshellarg('a;touch /tmp/x;b')) !== false,
			'it stands there as a single quoted word');
	}

	public function testEveryValueIsQuoted()
	{
		// Quoting six of seven is what made this possible to miss, so the
		// claim is about all of them rather than about the hash.
		$command = rtSetDirCommand('p h p', 's c r i p t', 'h a s h', 'd i r',
			'a d d', 'm o v e', 'r e s u m e', 'u s e r');
		foreach(array('p h p', 's c r i p t', 'h a s h', 'd i r',
			'a d d', 'm o v e', 'r e s u m e', 'u s e r') as $value)
		{
			$this->assertTrue(strpos($command, escapeshellarg($value)) !== false,
				'"'.$value.'" is quoted');
			$this->assertTrue(strpos($command, ' '.$value.' ') === false,
				'"'.$value.'" does not also stand there unquoted');
		}
	}

	public function testTheArgumentsKeepTheOrderSetdirReadsThemIn()
	{
		// setdir.php reads argv 1..6 as hash, datadir, add-path, move-files,
		// fast-resume, user. A reordering here would be a silent change of
		// meaning, not an error.
		$command = $this->build('abc123');
		$expected = escapeshellarg('/usr/bin/php') . ' ' .
			escapeshellarg('/srv/plugins/datadir/setdir.php') . ' ' .
			escapeshellarg('abc123') . ' ' .
			escapeshellarg('/srv/downloads') . ' ' .
			escapeshellarg('1') . ' ' .
			escapeshellarg('0') . ' ' .
			escapeshellarg('1') . ' ' .
			escapeshellarg('someone') . ' & exit 0';
		$this->assertTrue($command === $expected,
			'the command is the eight words setdir.php expects, in order');
	}

	public function testItStillDetachesAndSucceeds()
	{
		// rtorrent waits for the shell it started, and setdir.php talks back to
		// rtorrent, so the shell has to return before setdir.php finishes.
		$this->assertTrue(substr($this->build('abc123'), -9) === ' & exit 0',
			'the script is still backgrounded and the shell still exits 0');
	}
}
