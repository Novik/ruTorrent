<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TestHttpServer.php');
require_once(__DIR__ . '/UnpackPlaceFixture.php');

/**
 * plugins/unpack/place.php moves what one extractor run left in its private
 * directory into the output directory. It drops every symbolic link the
 * archive brought, never writes into or through a symbolic link already in
 * the output, and reports each refusal by name in the task's output and,
 * classified and without a name, in the application log.
 *
 * The private directory here is built by hand; UnpackScriptsTest.php runs
 * the unpack scripts that make it.
 */
class UnpackPlaceTest extends TestCase
{
	const HASH = UnpackPlaceFixture::HASH;

	private $base;
	private $shm;
	private $user;

	/** The runner calls setUp() once a class: each case starts with a tree of its own. */
	private function fresh()
	{
		$this->tearDown();
		$this->base = sys_get_temp_dir() . '/rutorrent-unpack-place-' . getmypid() . '-' . bin2hex(random_bytes(3));
		foreach (array('profile', 'stage', 'out', 'outside') as $directory)
			mkdir($this->base . '/' . $directory, 0700, true);
		$this->base = realpath($this->base);
		file_put_contents($this->base . '/outside/target', 'OLD');
		$this->shm = null;
		$this->user = '';
	}

	public function tearDown()
	{
		UnpackPlaceFixture::remove($this->base);
		if ($this->shm !== null)
			UnpackPlaceFixture::remove($this->shm);
	}

	/**
	 * place.php in $mode with $arguments after the context, the user being
	 * $this->user. $options: 'ini', PHP options; 'how' and 'hash', the
	 * context (automatic, HASH).
	 */
	private function placePhp($mode, array $arguments, array $options = array())
	{
		$run = PhpProcess::run(array_merge(UnpackPlaceFixture::phpOptions(), $options['ini'] ?? array(), array(
			dirname(__DIR__, 3) . '/plugins/unpack/place.php', $options['how'] ?? 'automatic',
			$options['hash'] ?? self::HASH, $this->user, $mode), $arguments),
			array('env' => array('RU_PROFILE_PATH' => $this->base . '/profile',
				'RU_LOG_FILE' => $this->base . '/errors.log')));
		$run['log'] = UnpackPlaceFixture::logLines($this->base . '/errors.log');
		return $run;
	}

	private function place($stage = null, array $ini = array())
	{
		return $this->placePhp('place', array($stage ?? $this->base . '/stage', $this->base . '/out/', '0'),
			array('ini' => $ini));
	}

	private function tree($directory)
	{
		return UnpackPlaceFixture::tree($directory);
	}

	private function assertRefused(array $run, $reason, $entries, array $names)
	{
		$this->assertEquals(1, $run['code'], 'a refusal fails the placement: ' . json_encode($run));
		foreach ($names as $name)
			$this->assertTrue(strpos($run['err'], 'unpack: refused ' . $reason . ': ' . $name . "\n") !== false,
				'the task output names ' . $name . ': ' . $run['err']);
		$this->assertTrue($run['log'] === array('unpack: automatic unpack refused hash=' . self::HASH
			. ' reason=' . $reason . ' entries=' . $entries),
			'the application log has one classified line and no name: ' . json_encode($run['log']));
	}

	private function assertOutsideUntouched(array $run)
	{
		$this->assertTrue($this->tree($this->base . '/outside') === array('target'),
			'nothing is created outside the output: ' . json_encode($run));
		$this->assertEquals('OLD', file_get_contents($this->base . '/outside/target'),
			'nothing outside is written: ' . json_encode($run));
	}

	public function testEveryLinkTheArchiveBroughtIsDropped()
	{
		$this->fresh();
		$stage = $this->base . '/stage';
		symlink($this->base . '/outside', $stage . '/absolute');
		symlink('../outside/target', $stage . '/up');
		symlink('kept.txt', $stage . '/inner');
		file_put_contents($stage . '/kept.txt', 'kept');
		// A directory the output lacks is moved whole, with what is in it.
		mkdir($stage . '/new');
		symlink($this->base . '/outside', $stage . '/new/nested');
		file_put_contents($stage . '/new/f', 'f');
		$run = $this->place();
		$this->assertTrue($this->tree($this->base . '/out') === array('kept.txt', 'new', 'new/f'),
			'only the files are placed, even a link that stays inside is not: ' . json_encode($run));
		$this->assertRefused($run, 'archive-symbolic-link', 4, array('absolute', 'up', 'inner', 'new/nested'));
	}

	public function testLinksTheExtractorSkippedCountAsRefused()
	{
		$this->fresh();
		file_put_contents($this->base . '/stage/f', 'f');
		$run = $this->placePhp('place', array($this->base . '/stage', $this->base . '/out/', '2'));
		$this->assertTrue($this->tree($this->base . '/out') === array('f'), 'the rest is placed: ' . json_encode($run));
		$this->assertEquals('', $run['err'], 'the script named them already');
		$this->assertRefused($run, 'archive-symbolic-link', 2, array());
	}

	public function testANameIsShownEscaped()
	{
		$this->fresh();
		symlink($this->base . '/outside', $this->base . "/stage/a\nb");
		symlink($this->base . '/outside', $this->base . "/stage/c\177d");
		$run = $this->place();
		$this->assertRefused($run, 'archive-symbolic-link', 2, array('a\\nb', 'c\\177d'));
	}

	public function testALinkThatCannotBeDroppedStopsThePlacement()
	{
		$this->fresh();
		mkdir($this->base . '/stage/locked');
		symlink($this->base . '/outside', $this->base . '/stage/locked/link');
		file_put_contents($this->base . '/stage/f', 'NEW');
		chmod($this->base . '/stage/locked', 0500);
		$this->assertTrue(!is_writable($this->base . '/stage/locked'), 'precondition: not run as root');
		$run = $this->place();
		$this->assertOutsideUntouched($run);
		$this->assertTrue($this->tree($this->base . '/out') === array(), 'nothing is placed: ' . json_encode($run));
		$this->assertRefused($run, 'archive-symbolic-link', 1, array('locked/link'));
	}

	public function testADirectoryThatCannotBeEnteredIsNotMoved()
	{
		$this->fresh();
		// It can be listed but not entered, so its entries cannot be told
		// links or not: read as "not a link" it would be moved whole.
		mkdir($this->base . '/stage/d');
		symlink('/', $this->base . '/stage/d/l');
		file_put_contents($this->base . '/stage/d/f', 'f');
		chmod($this->base . '/stage/d', 0644);
		$this->assertTrue(@lstat($this->base . '/stage/d/f') === false, 'precondition: not run as root');
		$run = $this->place();
		$this->assertTrue($this->tree($this->base . '/out') === array(), 'nothing is placed: ' . json_encode($run));
		$this->assertRefused($run, 'move-failed', 2, array('d/f', 'd/l'));
	}

	public function testADirectoryIsNotMergedIntoALinkInTheOutput()
	{
		$this->fresh();
		symlink($this->base . '/outside', $this->base . '/out/d');
		mkdir($this->base . '/stage/d');
		file_put_contents($this->base . '/stage/d/f', 'NEW');
		$run = $this->place();
		$this->assertOutsideUntouched($run);
		$this->assertTrue($this->tree($this->base . '/out') === array('d -> ' . $this->base . '/outside'),
			'the link in the output is left as it was');
		$this->assertRefused($run, 'output-symbolic-link', 1, array('d'));
	}

	public function testAFileReplacesALinkInTheOutputInsteadOfWritingThroughIt()
	{
		$this->fresh();
		symlink($this->base . '/outside/target', $this->base . '/out/f');
		symlink($this->base . '/outside', $this->base . '/out/g');
		file_put_contents($this->base . '/stage/f', 'NEW');
		file_put_contents($this->base . '/stage/g', 'NEW');
		$run = $this->place();
		$this->assertEquals(0, $run['code'], 'a file may take a link\'s place: ' . json_encode($run));
		$this->assertOutsideUntouched($run);
		foreach (array('f', 'g') as $name)
			$this->assertTrue(!is_link($this->base . '/out/' . $name)
				&& file_get_contents($this->base . '/out/' . $name) === 'NEW',
				'the file replaces the link to a ' . (($name === 'f') ? 'file' : 'directory'));
	}

	public function testFromAnotherFilesystemNothingIsWrittenThroughTheOutputAndTimesAreKept()
	{
		$this->fresh();
		$this->shm = '/dev/shm/rutorrent-unpack-place-' . getmypid() . '-' . bin2hex(random_bytes(3));
		$this->assertTrue(@mkdir($this->shm . '/new', 0700, true)
			&& stat($this->shm)['dev'] !== stat($this->base)['dev'],
			'precondition: /dev/shm is writable and is another filesystem than ' . $this->base);
		$old = 978307200;
		foreach (array('f', 'g', 'new/h') as $name)
		{
			file_put_contents($this->shm . '/' . $name, 'NEW');
			touch($this->shm . '/' . $name, $old);
		}
		touch($this->shm . '/new', $old);
		// A link to nothing yet, which the copy would create, and a second
		// name of a file outside, which the copy would write into.
		symlink($this->base . '/outside/created', $this->base . '/out/f');
		link($this->base . '/outside/target', $this->base . '/out/g');
		$run = $this->place($this->shm);
		$this->assertEquals(0, $run['code'], 'a copy across filesystems is a placement: ' . json_encode($run));
		$this->assertOutsideUntouched($run);
		clearstatcache();
		foreach (array('f', 'g', 'new/h') as $name)
		{
			$this->assertTrue(!is_link($this->base . '/out/' . $name)
				&& @file_get_contents($this->base . '/out/' . $name) === 'NEW', $name . ' is placed as a new file');
			$this->assertTrue(@filemtime($this->base . '/out/' . $name) === $old, $name . ' keeps its time');
		}
		$this->assertTrue(@filemtime($this->base . '/out/new') === $old,
			'a directory rename() cannot carry across is rebuilt and keeps its time');
	}

	public function testExistingDirectoriesAreMergedAndNamesKeptVerbatim()
	{
		$this->fresh();
		$names = array('a b', "line\nbreak", '-dash');
		mkdir($this->base . '/out/kept');
		file_put_contents($this->base . '/out/kept/old.txt', 'old');
		mkdir($this->base . '/stage/kept/sub', 0700, true);
		file_put_contents($this->base . '/stage/kept/' . $names[0], '1');
		file_put_contents($this->base . '/stage/kept/sub/deep', '2');
		file_put_contents($this->base . '/stage/' . $names[1], '3');
		file_put_contents($this->base . '/stage/' . $names[2], '4');
		$run = $this->place();
		$this->assertEquals(0, $run['code'], 'nothing is refused: ' . json_encode($run));
		$this->assertTrue($this->tree($this->base . '/out') === array($names[2], 'kept', 'kept/' . $names[0],
			'kept/old.txt', 'kept/sub', 'kept/sub/deep', $names[1]), 'every entry lands under its own name: '
			. json_encode($this->tree($this->base . '/out')));
		$this->assertTrue($run['err'] === '' && $run['log'] === array(), 'and nothing is reported: ' . json_encode($run));
	}

	public function testTypeConflictsAreRefusedAndLeftAlone()
	{
		$this->fresh();
		file_put_contents($this->base . '/out/x', 'X');
		mkdir($this->base . '/out/y');
		mkdir($this->base . '/stage/x');
		file_put_contents($this->base . '/stage/x/inner', 'I');
		file_put_contents($this->base . '/stage/y', 'Y');
		$run = $this->place();
		$this->assertTrue($this->tree($this->base . '/out') === array('x', 'y'), 'both stay as they were');
		$this->assertEquals('X', file_get_contents($this->base . '/out/x'), 'the file keeps its content');
		$this->assertRefused($run, 'output-type-conflict', 2, array('x', 'y'));
	}

	public function testADirectoryThatCannotBeReadIsReported()
	{
		$this->fresh();
		$run = $this->place($this->base . '/missing');
		$this->assertRefused($run, 'move-failed', 1, array('.'));
	}

	public function testWhatCannotBeMovedIsRefused()
	{
		$this->fresh();
		mkdir($this->base . '/stage/d');
		file_put_contents($this->base . '/stage/f', 'f');
		chmod($this->base . '/out', 0500);
		$this->assertTrue(!is_writable($this->base . '/out'), 'precondition: not run as root');
		$run = $this->place();
		chmod($this->base . '/out', 0700);
		$this->assertTrue($this->tree($this->base . '/out') === array(), 'nothing is placed: ' . json_encode($run));
		$this->assertRefused($run, 'move-failed', 2, array('d', 'f'));
	}

	public function testAMalformedHashIsNotCopiedIntoTheLog()
	{
		$this->fresh();
		// A manual unpack's hash comes from the request.
		$hashes = array("x\nforged", self::HASH . "\n", substr(self::HASH, 1), strtolower(self::HASH));
		foreach ($hashes as $hash)
			$this->placePhp('refuse', array('reason', 'name'), array('how' => 'manual', 'hash' => $hash));
		$line = 'unpack: manual unpack refused hash=%s reason=reason entries=1';
		$this->assertTrue(UnpackPlaceFixture::logLines($this->base . '/errors.log') === array(sprintf($line, 'invalid'),
			sprintf($line, 'invalid'), sprintf($line, 'invalid'), sprintf($line, self::HASH)),
			'only 40 hex digits and nothing after them are logged, in upper case: '
			. json_encode(UnpackPlaceFixture::logLines($this->base . '/errors.log')));
	}

	public function testWithoutAnyOfItsFunctionsNothingIsMoved()
	{
		$this->fresh();
		foreach (array('scandir', 'lstat', 'is_link', 'is_dir', 'file_exists', 'unlink', 'rename', 'mkdir',
			'filemtime', 'touch') as $function)
		{
			@unlink($this->base . '/errors.log');
			symlink($this->base . '/outside', $this->base . '/stage/d');
			file_put_contents($this->base . '/stage/f', 'NEW');
			$run = $this->place(null, array('-d', 'disable_functions=' . $function));
			$this->assertTrue($this->tree($this->base . '/out') === array(), $function . ': nothing is placed');
			$this->assertTrue($run['code'] !== 0, $function . ': the placement fails');
			// The child's own diagnostics are not echoed: they name the
			// disabled function as a PHP warning or fatal error.
			$this->assertTrue(strpos($run['err'], "unpack: refused check-unavailable: $function\n") !== false,
				$function . ': the task output names it');
			// FileUtil::toLog() needs these two itself, and PHP 8 stops it.
			if (!in_array($function, array('file_exists', 'touch'), true) || PHP_VERSION_ID < 80000)
				$this->assertTrue($run['log'] === array('unpack: automatic unpack refused hash=' . self::HASH
					. ' reason=check-unavailable entries=1'), $function . ': the application log has the refusal');
			unlink($this->base . '/stage/d');
			unlink($this->base . '/stage/f');
		}
	}

	public function testTheScriptsReportsAreNamedAndLogged()
	{
		$this->fresh();
		$run = $this->placePhp('refuse', array('private-directory-unavailable', $this->base . '/out/'));
		$this->assertRefused($run, 'private-directory-unavailable', 1, array($this->base . '/out/'));
	}

	public function testAnIncompleteCommandIsRefused()
	{
		$this->fresh();
		foreach (array(array('place', array($this->base . '/stage', $this->base . '/out/')),
			array('refuse', array('reason')), array('bogus', array('reason', 'name'))) as list($mode, $arguments))
		{
			$run = $this->placePhp($mode, $arguments);
			$this->assertEquals(2, $run['code'], 'a short or unknown command is a usage error: ' . json_encode($run));
			$this->assertTrue(strpos($run['err'], 'usage: place.php ') === 0, 'which says how to call it');
		}
	}

	/**
	 * place.php runs as the user the unpack was for, so that user's own
	 * configuration, a log of their own included, applies. ruTorrent makes
	 * the user's profile directory as it starts, which shows it.
	 */
	public function testItRunsAsTheUserTheUnpackWasFor()
	{
		$this->fresh();
		$this->user = 'unpacktest';
		symlink($this->base . '/outside', $this->base . '/stage/l');
		$run = $this->place();
		$this->assertRefused($run, 'archive-symbolic-link', 1, array('l'));
		$this->assertTrue(is_dir($this->base . '/profile/users/unpacktest'), 'as the user: ' . json_encode($run));
	}

	/**
	 * With $pathToExternals['php'] naming php-cgi, place.php refuses, and the
	 * refusal fails: the unpack script then keeps what was extracted and the
	 * archives.
	 */
	public function testARefusalOutsideTheCommandLineFails()
	{
		$this->fresh();
		$cgi = dirname(PHP_BINARY) . '/php-cgi';
		if (!is_executable($cgi)) {
			echo "Skipped: no php-cgi beside " . PHP_BINARY . "\n";
			return;
		}
		file_put_contents($this->base . '/stage/f', 'f');
		exec(implode(' ', array_map('Utility::escapeShellArg', array_merge(array($cgi, '-q'),
			UnpackPlaceFixture::phpOptions(), array('-f', dirname(__DIR__, 3) . '/plugins/unpack/place.php', 'automatic',
			self::HASH, '', 'place', $this->base . '/stage', $this->base . '/out/', '0')))) . ' 2>&1', $output, $code);
		$run = array('code' => $code, 'output' => $output);
		$this->assertEquals(1, $code, 'it fails: ' . json_encode($run));
		$this->assertTrue(in_array('place.php can only be run from the command line', $output, true),
			'and says why: ' . json_encode($run));
		$this->assertTrue($this->tree($this->base . '/stage') === array('f')
			&& $this->tree($this->base . '/out') === array(), 'nothing is moved');
	}

	/** It moves what it is told to, so a web request must not run it. */
	public function testAWebRequestIsRefused()
	{
		$this->fresh();
		mkdir($this->base . '/web');
		file_put_contents($this->base . '/stage/f', 'f');
		$server = TestHttpServer::start($this->base . '/web', array(), array(),
			dirname(__DIR__, 3) . '/plugins/unpack/place.php');
		try
		{
			$response = TestHttpServer::rawGet($server->port(), '/place.php?automatic+' . self::HASH . '++place+'
				. rawurlencode($this->base . '/stage') . '+' . rawurlencode($this->base . '/out/') . '+0');
		}
		finally
		{
			$server->close();
		}
		$this->assertTrue(strpos($response['status'], ' 403 ') !== false, 'it is forbidden: ' . json_encode($response));
		$this->assertTrue($this->tree($this->base . '/stage') === array('f')
			&& $this->tree($this->base . '/out') === array(), 'and nothing is moved');
	}
}
