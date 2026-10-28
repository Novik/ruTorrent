<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');

/**
 * The directory autotools hands rtorrent is the directory the data ends up in.
 *
 * On a download finishing, rtorrent runs the pair the autotools plugin
 * installed for it. On rtorrent 0.8.8 and above, with the file operation set
 * to "Move", that pair is
 *
 *     d.set_directory_base="$execute_capture={php,check.php,...}" ; execute={php,move.php,...}
 *
 * -- check.php names a directory, rtorrent points the download at it, and only
 * then does move.php carry the files there. check.php answers from the plugin
 * settings and the path alone; it never looks at the destination. So when
 * move.php refuses, because something is already at one of the names it would
 * write, the download has already been pointed at a directory its data never
 * reached, and move.php's refusal goes nowhere: it is run through execute
 * rather than execute_capture, it prints nothing and it exits 0.
 *
 * The older branch of the same setting is not built this way: it runs move.php
 * under execute_capture into a custom field and sets the directory from that
 * field only when it is not empty, so a refusal leaves the download where its
 * data is.
 *
 * What the first three cases assert is the end condition rather than either
 * script's output: whatever path check.php names, the download's files are at
 * that path once move.php has run. That holds for a destination that is free
 * and for one that is taken, and it would still be the right question if the
 * pair were wired differently. The single-file cases at the end run move.php
 * alone; see runTheSingleFileHook().
 */
class AutoMoveDestinationTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
	const USER = 'tester';
	const PAYLOAD = "\x00\xffpartial\r\n";

	private $base;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-automove-' . uniqid('', true);
	}

	public function tearDown()
	{
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

	/**
	 * mkdir() subtracts the process umask, and under `umask 0177` a fixture
	 * directory loses its search bit and stops the scripts before they test
	 * anything. Ask for the mode with no umask in the way, then read it back.
	 */
	private function makeDir($dir)
	{
		$mask = umask(0);
		try {
			if (!is_dir($dir) && !mkdir($dir, 0700, true))
				throw new RuntimeException('Could not create fixture directory ' . $dir);
			if (!chmod($dir, 0700))
				throw new RuntimeException('Could not set the mode of fixture directory ' . $dir);
		} finally {
			umask($mask);
		}
		clearstatcache(true, $dir);
		$actual = fileperms($dir) & 07777;
		if ($actual !== 0700)
			throw new RuntimeException('Fixture directory ' . $dir . ' is mode ' . decoct($actual) . ', not 700');
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	private function bstr($s)
	{
		return strlen($s) . ':' . $s;
	}

	/**
	 * A torrent built here so the fixture is its own expectation: a list of
	 * names makes a torrent of those files called mytorrent, a string makes a
	 * single-file torrent of that name.
	 */
	private function torrentBytes($names, $root = 'mytorrent')
	{
		if (is_array($names))
		{
			$files = '';
			foreach ($names as $n)
				$files .= 'd' . $this->bstr('length') . 'i12e'
					. $this->bstr('path') . 'l' . implode('', array_map(array($this, 'bstr'), explode('/', $n))) . 'e' . 'e';
			$layout = $this->bstr('files') . 'l' . $files . 'e'
				. $this->bstr('name') . $this->bstr($root);
		}
		else
			$layout = $this->bstr('length') . 'i12e' . $this->bstr('name') . $this->bstr($names);
		$info = 'd' . $layout
			. $this->bstr('piece length') . 'i32768e'
			. $this->bstr('pieces') . $this->bstr(str_repeat("\x01", 20))
			. 'e';
		return 'd' . $this->bstr('announce')
			. $this->bstr('http://tracker.invalid/announce')
			. $this->bstr('info') . $info . 'e';
	}

	/** The prelude every driver shares: one profile, one user. */
	private function prelude()
	{
		return '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
			. ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = 1; $rpcTimeOut = 1; $rpcLogCalls = false;' . "\n";
	}

	/**
	 * Runs a script with the suite's configuration and without a shell:
	 * escapeshellarg() can drop the non-ASCII bytes of an argument, as PHP 7.4
	 * does under the C locale. What PHP reports goes to stderr, apart from
	 * what the script prints, so the printed path can be compared and a
	 * failure still names its reason.
	 *
	 * The scripts create directories with the umask they inherit, as they do
	 * under rtorrent, so one is stated here rather than taken from the caller.
	 * So is the locale: C, which is what they get from an rtorrent started
	 * without LANG, and the one where PHP 7.4 mishandles non-ASCII names.
	 */
	private function runPhp($file, $args = array())
	{
		$mask = umask(022);
		try {
			$run = PhpProcess::run(array_merge(PhpProcess::sameIni(),
				array('-d', 'display_errors=stderr', $file), $args),
				array('env' => array('LC_ALL' => 'C')));
		} finally {
			umask($mask);
		}
		return array('exit' => $run['timedOut'] ? 'timed out' : $run['code'],
			'printed' => $run['out'], 'errors' => $run['err']);
	}

	/**
	 * Builds a finished download, runs check.php and then move.php exactly as
	 * rtorrent runs them, and reports what each said and what is on disk.
	 *
	 * $occupy is the list of names already standing in the destination
	 * directory when the download finishes. $root is the download's name.
	 * $files are its files, or, as a string, the name of a single-file
	 * download, as torrentBytes() takes them. $skip is the skip filter.
	 */
	private function runTheFinishedHook($occupy, $root = 'mytorrent', $files = array('1.bin', '2.bin'), $skip = '')
	{
		$this->removeTree($this->base);
		$multi = is_array($files);
		$names = $multi ? $files : array($files);
		if (!$multi)
			$root = $files;   // a single file is the download, in the download directory itself
		$source = $this->base . '/dl' . ($multi ? '/' . $root : '');
		$destination = $this->base . '/fin' . ($multi ? '/' . $root : '');
		$this->makeDir($this->base . '/profile/settings');
		$this->makeDir($this->base . '/session');
		$this->makeDir($source);
		$this->makeDir($this->base . '/fin');
		foreach ($names as $n)
		{
			$this->makeDir(dirname($source . '/' . $n));
			file_put_contents($source . '/' . $n, 'download ' . $n);
		}
		foreach ($occupy as $n)
		{
			$this->makeDir(dirname($destination . '/' . $n));
			file_put_contents($destination . '/' . $n, 'already there');
		}
		file_put_contents($this->base . '/session/' . self::HASH . '.torrent',
			$this->torrentBytes($files, $root));
		$this->seed('Move', $skip);

		$basePath = $this->base . '/dl/' . $root;   // d.get_base_path
		// d.get_base_path, d.get_base_filename, d.is_multi_file, d.get_custom1, d.get_name
		$check = $this->runScript('check',
			array($basePath, $root, $multi ? '1' : '0', '', $root, self::USER));
		// and then, with the hash in front, the same five
		$move = $this->runScript('move',
			array(self::HASH, $basePath, $root, $multi ? '1' : '0', '', $root, self::USER));

		$named = rtrim($check['printed'], '/');
		return array(
			'check' => $check,
			'move' => $move,
			'named' => $named,
			// the download's files, at the directory check.php named
			'at_named' => array_map(function($n) use ($named) {
				return is_file($named . '/' . $n) && file_get_contents($named . '/' . $n) === 'download ' . $n;
			}, $names),
			'at_source' => array_map(function($n) use ($source) {
				return is_file($source . '/' . $n);
			}, $names),
			'occupants' => array_map(function($n) use ($destination) {
				return @file_get_contents($destination . '/' . $n);
			}, $occupy),
		);
	}

	/** Stores the plugin settings check.php and move.php read. */
	private function seed($operation, $skip = '', $addLabel = false)
	{
		$seed = $this->base . '/seed.php';
		file_put_contents($seed, "<?php\n" . $this->prelude()
			. 'require_once(' . var_export($this->repoRoot() . '/php/settings.php', true) . ");\n"
			. 'require_once(' . var_export($this->repoRoot() . '/plugins/autotools/autotools.php', true) . ");\n"
			. '$s = rTorrentSettings::get();' . "\n"
			. '$s->directory = ' . var_export($this->base . '/dl', true) . ";\n"
			. '$s->session = ' . var_export($this->base . '/session', true) . ";\n"
			. '$s->linkExist = true; $s->iVersion = 0x1015; $s->store();' . "\n"
			. '$at = rAutoTools::load();' . "\n"
			. '$at->enable_move = 1; $at->fileop_type = ' . var_export($operation, true) . ";\n"
			. '$at->path_to_finished = ' . var_export($this->base . '/fin', true) . ";\n"
			. '$at->automove_filter = "/.*/"; $at->addLabel = ' . ($addLabel ? 1 : 0) . '; $at->addName = 0;' . "\n"
			. '$at->skip_move_for_files = ' . var_export($skip, true) . '; $at->store();' . "\n"
			. 'echo rAutoTools::load()->path_to_finished;' . "\n");
		$seeded = $this->runPhp($seed);
		if ($seeded['printed'] !== $this->base . '/fin')
			throw new Exception('the plugin settings did not take (exit ' . var_export($seeded['exit'], true)
				. '): ' . $seeded['printed'] . $seeded['errors']);
	}

	/**
	 * One driver per script, because each is a top level script that reads
	 * $argv and chdir()s to where it lives.
	 */
	private function runScript($which, $args)
	{
		$driver = $this->base . '/drive-' . $which . '.php';
		file_put_contents($driver, "<?php\n" . $this->prelude()
			. 'require(' . var_export(
				$this->repoRoot() . '/plugins/autotools/' . $which . '.php', true) . ");\n");
		return $this->runPhp($driver, $args);
	}

	/**
	 * The control. With nothing in the way the pair works, and the assertion
	 * below is therefore about the collision and not about the fixture.
	 */
	public function testAFinishedDownloadIsMovedToTheDirectoryItIsPointedAt()
	{
		$r = $this->runTheFinishedHook(array());

		$this->assertTrue($r['named'] !== '',
			'check.php names a directory for the finished download; it printed: '
			. var_export($r['check']['printed'], true));
		$this->assertEquals(array(true, true), $r['at_named'],
			'and both files of the download are in it afterwards: ' . $r['named']);
		$this->assertEquals(array(false, false), $r['at_source'],
			'and none is left where the download ran');
	}

	/**
	 * The whole point: a destination name that move.php will refuse must not
	 * be the one rtorrent is given, because rtorrent is given it first. The
	 * download must end up at the directory it was pointed at, whichever
	 * directory the pair settles on.
	 */
	public function testTheDownloadIsAtTheDirectoryItWasPointedAtWhenTheMoveIsRefused()
	{
		$r = $this->runTheFinishedHook(array('2.bin'));

		$this->assertTrue($r['named'] !== '',
			'check.php names a directory even when the destination is taken; it printed: '
			. var_export($r['check']['printed'], true));
		$this->assertEquals(array('already there'), $r['occupants'],
			'the file that was already at the destination survives');
		$this->assertEquals(array(true, true), $r['at_named'],
			'and the download is at the directory rtorrent was pointed at (' . $r['named']
			. '), rather than pointed at a directory its data never reached');
	}

	/**
	 * The same run seen from the source. A refused move leaves the download
	 * whole where it is, so it is still seedable; a move that half happened
	 * is not.
	 */
	public function testARefusedMoveLeavesTheDownloadWholeInOnePlace()
	{
		$r = $this->runTheFinishedHook(array('2.bin'));

		$whole = ($r['at_source'] === array(true, true)) || ($r['at_named'] === array(true, true));
		$this->assertTrue($whole,
			'after a refused automove the download is whole in one directory; at the '
			. 'source: ' . json_encode($r['at_source']) . ', at ' . $r['named'] . ': '
			. json_encode($r['at_named']));
	}

	/**
	 * A destination that does not exist yet is created by move.php. Its name
	 * must not depend on the locale: in a non-UTF-8 locale such as C, which
	 * is what the scripts have when rtorrent runs without LANG, PHP 7.4's
	 * basename() drops the non-ASCII bytes of a path element.
	 */
	public function testANonAsciiDownloadIsMovedToTheDirectoryItIsPointedAt()
	{
		$r = $this->runTheFinishedHook(array(), 'Сериал');

		$this->assertEquals($this->base . '/fin/Сериал', $r['named'],
			'check.php names the download\'s own directory');
		$this->assertEquals(array(true, true), $r['at_named'],
			'and both files of the download are in it afterwards');
		$this->assertEquals(array(false, false), $r['at_source'],
			'and none is left where the download ran');
	}

	/**
	 * move.php leaves a download where it is when skip_move_for_files matches
	 * the path of one of its files below the download's own directory, so
	 * check.php must name that directory too.
	 */
	public function testAFilteredMultiFileDownloadStaysWithItsData()
	{
		$this->assertSkipFilter(array('1.bin', 'extras/cover.rar'), '/\.rar$/', true);
		$this->assertSkipFilter(array('1.bin', 'extras/notes.txt'), '/^extras\//', true);
	}

	public function testAFilteredSingleFileDownloadStaysWithItsData()
	{
		$this->assertSkipFilter('Film.rar', '/\.rar$/', true);
		// The filter is matched as UTF-8, so "\xd0\xb6" is one character.
		$this->assertSkipFilter("\xd0\xb6.rar", '/^.\.rar$/', true);
	}

	public function testADownloadTheFilterDoesNotMatchIsMoved()
	{
		$this->assertSkipFilter(array('1.bin', 'extras/cover.rar'), '/\.zip$/', false);
		$this->assertSkipFilter('Film.mkv', '/\.rar$/', false);
		$this->assertSkipFilter('Film.rar', '', false);
	}

	/**
	 * Finishes a download of $files, as runTheFinishedHook() takes them, with
	 * $skip as the skip filter. Whichever directory check.php names, the files
	 * are there afterwards; they were left where the download ran when
	 * $skipped, and moved from there otherwise.
	 */
	private function assertSkipFilter($files, $skip, $skipped)
	{
		$r = $this->runTheFinishedHook(array(), 'mytorrent', $files, $skip);
		$what = 'filter ' . var_export($skip, true) . ', ' . json_encode($files) . ': ';
		$this->assertEquals(array_fill(0, count($r['at_named']), true), $r['at_named'],
			$what . 'the files are at the directory check.php named (' . $r['named'] . ')');
		$this->assertEquals(array_fill(0, count($r['at_source']), $skipped), $r['at_source'],
			$what . 'and they were ' . ($skipped ? 'left where the download ran' : 'moved from where the download ran'));
		$this->assertTrue(strpos($r['check']['errors'], 'preg_match') === false,
			$what . 'and check.php raises no preg_match diagnostic: ' . $r['check']['errors']);
	}

	/**
	 * A finished single-file download, with move.php run on it alone, as it is
	 * for every operation but a Move on rtorrent 0.8.8 and above; for that Move
	 * it is move.php's half of the pair described above. rtorrent passes the
	 * base filename of the file it wrote, which can differ from the metainfo
	 * name: libtorrent's file name sanitizing replaces '/' in it, for one.
	 * check.php already tests that name. A file is also left at the metainfo
	 * name, so operating on it is seen, not just a failure to find the file.
	 */
	private function runTheSingleFileHook($operation, $name, $basename, $occupied = false, $skip = '', $label = '')
	{
		$this->removeTree($this->base);
		foreach (array('profile/settings', 'session', 'dl/keep', 'fin') as $dir)
			$this->makeDir($this->base . '/' . $dir);
		$source = $this->base . '/dl/' . $basename;
		$target = $this->base . '/fin/' . ($label !== '' ? $label . '/' : '') . $basename;
		file_put_contents($source, self::PAYLOAD);
		file_put_contents($this->base . '/dl/keep/note.txt', 'unrelated directory');
		if ($name !== $basename)
		{
			$this->makeDir(dirname($this->base . '/dl/' . $name));
			file_put_contents($this->base . '/dl/' . $name, 'unrelated neighbor');
		}
		if ($occupied)
			file_put_contents($target, 'existing destination');
		$metafile = $this->base . '/session/' . self::HASH . '.torrent';
		$metadata = $this->torrentBytes($name);
		file_put_contents($metafile, $metadata);
		$this->seed($operation, $skip, $label !== '');

		// d.get_hash, d.get_base_path, d.get_base_filename, d.is_multi_file, d.get_custom1, d.get_name
		$move = $this->runScript('move', array(self::HASH, $source, $basename, '0', rawurlencode($label), $name, self::USER));
		$this->assertTrue($move['exit'] === 0, $operation . ' hook completed (exit '
			. var_export($move['exit'], true) . '): ' . $move['printed'] . $move['errors']);
		clearstatcache();
		$this->assertTrue(file_get_contents($metafile) === $metadata, 'metainfo bytes stay unchanged');
		$this->assertTrue(file_get_contents($this->base . '/dl/keep/note.txt') === 'unrelated directory',
			'an extra directory stays at the source');
		$this->assertTrue(!file_exists($this->base . '/fin/keep'), 'and is not carried to the destination');
		if ($name !== $basename)
		{
			$this->assertTrue(@file_get_contents($this->base . '/dl/' . $name) === 'unrelated neighbor',
				'the file at the metainfo name stays at the source');
			$this->assertTrue(!file_exists($this->base . '/fin/' . $name),
				'and nothing is made at the metainfo name in the destination');
		}
		return array($move['printed'], $source, $target);
	}

	public function testUnchangedBasenameStillWorksForEveryOperation()
	{
		foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			$this->assertTransferred($op, 'episode.bin', 'episode.bin');
	}

	/**
	 * A single file is carried into the label's directory itself, so that
	 * directory is the last element of the destination.
	 */
	public function testASingleFileUnderANonAsciiLabelIsMovedIntoTheLabelDirectory()
	{
		list($out, $source, $target) = $this->runTheSingleFileHook('Move', 'episode.bin', 'episode.bin',
			false, '', 'Метка');
		$this->assertEquals($this->base . '/fin/Метка/', $out, 'Move reports the label\'s directory');
		$this->assertTrue(@file_get_contents($target) === self::PAYLOAD, 'and the file is in it');
		$this->assertTrue(!file_exists($source), 'and not left at the source');
	}

	public function testDaemonBasenameSelectsOnlyTheActualPartialFile()
	{
		foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			$this->assertTransferred($op, 'Серия/01.bin', 'Серия_01.bin');
	}

	private function assertTransferred($op, $name, $basename)
	{
		list($out, $source, $target) = $this->runTheSingleFileHook($op, $name, $basename);
		$this->assertTrue($out === $this->base . '/fin/', $op . ' reports the destination');
		$this->assertTrue(@file_get_contents($target) === self::PAYLOAD, $op . ' preserves the partial file bytes');
		$this->assertTrue($op === 'Move' ? !file_exists($source) : file_get_contents($source) === self::PAYLOAD,
			$op . ' has the expected source lifetime');
		$this->assertTrue(is_link($target) === ($op === 'SoftLink'), $op . ' has the expected link type');
		if ($op === 'HardLink')
		{
			$inode = @fileinode($source);
			$this->assertTrue($inode !== false && @fileinode($target) === $inode, 'HardLink shares the source inode');
		}
	}

	public function testOccupiedActualBasenameRefusesEveryOperation()
	{
		foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
		{
			list($out, $source, $target) = $this->runTheSingleFileHook($op, 'season/episode.bin',
				'season_episode.bin', true);
			$this->assertTrue($out === '', $op . ' refuses an occupied destination');
			$this->assertTrue(file_get_contents($source) === self::PAYLOAD, $op . ' leaves the selected source unchanged');
			$this->assertTrue(file_get_contents($target) === 'existing destination',
				$op . ' preserves the occupied destination');
		}
	}

	public function testSkipFilterUsesTheActualBasename()
	{
		list($out, $source, $target) = $this->runTheSingleFileHook('Move', 'season/episode.bin',
			'season_episode.bin', false, '/^season_episode[.]bin$/');
		$this->assertTrue($out === '', 'a matching physical basename is skipped');
		$this->assertTrue(file_get_contents($source) === self::PAYLOAD, 'the skipped file stays at the source');
		$this->assertTrue(!file_exists($target), 'and nothing is made at the destination');
	}
}
