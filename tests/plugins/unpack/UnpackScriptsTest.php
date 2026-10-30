<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/UnpackPlaceFixture.php');

/**
 * The unpack scripts and stage.sh under this runtime's own sh, with
 * extractors written here that do what the real unzip and unrar do with
 * a link: unzip writes through one it finds below the directory it is
 * given, and unrar without -ol- creates the links an archive holds. Each
 * extractor run gets an empty private directory, and place.php moves what
 * it wrote into the output without its links.
 */
class UnpackScriptsTest extends TestCase
{
	const HASH = UnpackPlaceFixture::HASH;
	const USER = 'unpacktest';
	// unrar 7.23's own "lt -v" lines for a RAR 5.0 archive with a Unix and a
	// Windows symbolic link and a junction, plus a file named like a type.
	const LISTED_LINKS = "        Name: real\n        Type: File\n        Name: lib.so\n        Type: Unix symbolic link\n"
		. "      Target: real\n        Name: winlink\n        Type: Windows symbolic link\n      Target: real\n"
		. "        Name: junction\n        Type: NTFS junction point\n      Target: real\n"
		. "        Name: Type: Junction\n        Type: File\n";

	private $base;

	/** The runner calls setUp() once a class: each case starts with a tree of its own. */
	private function fresh()
	{
		$this->tearDown();
		$this->base = sys_get_temp_dir() . '/rutorrent-unpack-scripts-' . getmypid() . '-' . bin2hex(random_bytes(3));
		foreach (array('profile', 'in', 'out', 'outside') as $directory)
			mkdir($this->base . '/' . $directory, 0700, true);
		$this->base = realpath($this->base);
		// PHP as $pathToExternals['php'] names it, with this process's settings.
		file_put_contents($this->base . '/php', "#!/bin/sh\nexec " . implode(' ', array_map('Utility::escapeShellArg',
			array_merge(array(PHP_BINARY), UnpackPlaceFixture::phpOptions()))) . " \"\$@\"\n");
		chmod($this->base . '/php', 0700);
	}

	public function tearDown()
	{
		UnpackPlaceFixture::remove($this->base);
	}

	/**
	 * An extractor that records each call, its arguments and the directory
	 * it was given, and runs $body with $dest, its last argument, that
	 * directory, $1 the command ("x" or "lt" for unrar, "-o" for unzip) and
	 * $outside the directory beside the output that nothing may reach.
	 */
	private function extractor($name, $body)
	{
		$path = $this->base . '/' . $name;
		file_put_contents($path, "#!/bin/sh\nfor dest; do :; done\noutside=\"\$(dirname \"\$0\")/outside\"\n"
			. "echo \"\$1\" >> \"\$0.calls\"\necho \"\$*\" >> \"\$0.args\"\necho \"\$dest\" >> \"\$0.dest\"\n"
			. $body . "\n");
		chmod($path, 0700);
		return $path;
	}

	/** What extractor $name recorded of its calls: "calls", "args" or "dest". */
	private function calls($name, $what = 'calls')
	{
		$calls = $this->base . '/' . $name . '.' . $what;
		return is_file($calls) ? file($calls, FILE_IGNORE_NEW_LINES) : array();
	}

	/** Runs plugins/unpack/$script with the arguments unpack.php gives it. */
	private function script($script, array $arguments)
	{
		$arguments = array_pad($arguments, 6, '');
		array_push($arguments, $this->base . '/php', 'automatic', self::HASH, self::USER);
		$process = proc_open(array_merge(array(dirname(__DIR__, 3) . '/plugins/unpack/' . $script), $arguments),
			array(0 => array('file', '/dev/null', 'r'), 1 => array('file', $this->base . '/stdout', 'w'),
			2 => array('file', $this->base . '/stderr', 'w')), $pipes, $this->base,
			array_merge(getenv(), array('RU_PROFILE_PATH' => $this->base . '/profile',
				'RU_LOG_FILE' => $this->base . '/errors.log')));
		if (!is_resource($process))
			throw new RuntimeException('cannot start ' . $script);
		$code = proc_close($process);
		return array('code' => $code, 'out' => file_get_contents($this->base . '/stdout'),
			'err' => file_get_contents($this->base . '/stderr'),
			'log' => UnpackPlaceFixture::logLines($this->base . '/errors.log'));
	}

	private function refusedLine($reason, $entries)
	{
		return 'unpack: automatic unpack refused hash=' . self::HASH . ' reason=' . $reason . ' entries=' . $entries;
	}

	/**
	 * The first zip makes d -> outside; the second has d/pwn.txt, and unzip
	 * writes it through that link.
	 */
	private function zipsWithALinkThenAFileBelowIt()
	{
		file_put_contents($this->base . '/in/a.zip', 'zip');
		file_put_contents($this->base . '/in/b.zip', 'zip');
		return $this->extractor('unzip', 'case "$2" in'
			. ' *a.zip) ln -s "$outside" "$dest/d" ;;'
			. ' *b.zip) mkdir -p "$dest/d" && echo PWN > "$dest/d/pwn.txt" ;; esac');
	}

	/** $root holds the private directories, $output gets what is placed. */
	private function assertZipLinkRefused(array $run, $root, $output)
	{
		$private = $this->calls('unzip', 'dest');
		$this->assertTrue(count($private) === 2 && count(array_unique($private)) === 2 && count(array_filter($private,
			function ($dest) use ($root) { return strpos($dest, $root . '/.rutorrent-unpack.') === 0; })) === 2,
			'each zip is unpacked into a private directory of its own in ' . $root . ': ' . json_encode($private));
		$this->assertTrue(UnpackPlaceFixture::tree($this->base . '/outside') === array(),
			'nothing is written outside: ' . json_encode($run));
		$this->assertTrue(is_dir($output) && UnpackPlaceFixture::tree($output) === array('d', 'd/pwn.txt'),
			'the second zip is unpacked into the output, the link of the first is not: '
			. json_encode(is_dir($output) ? UnpackPlaceFixture::tree($output) : null));
		$this->assertEquals(1, $run['code'], 'the unpack fails');
		$this->assertTrue(strpos($run['err'], "unpack: refused archive-symbolic-link: d\n") !== false,
			'the task output names the link: ' . $run['err']);
		$this->assertTrue($run['log'] === array($this->refusedLine('archive-symbolic-link', 1)),
			'the log has the refusal: ' . json_encode($run['log']));
		$this->assertTrue(is_file($this->base . '/in/a.zip') && is_file($this->base . '/in/b.zip'),
			'and the archives are kept');
	}

	public function testALinkOneZipMadeIsNotWrittenThroughByTheNext()
	{
		$this->fresh();
		$unzip = $this->zipsWithALinkThenAFileBelowIt();
		$run = $this->script('unzip_dir.sh', array($unzip, $this->base . '/in/', $this->base . '/out/', '',
			$this->base . '/in/a.zip;' . $this->base . '/in/b.zip;'));
		$this->assertZipLinkRefused($run, $this->base . '/out', $this->base . '/out');
	}

	public function testALinkOneZipMadeIsNotWrittenThroughByTheNextInTheTempDirectory()
	{
		$this->fresh();
		$unzip = $this->zipsWithALinkThenAFileBelowIt();
		// An output that is not there yet is made.
		$run = $this->script('unzip_dir.sh', array($unzip, $this->base . '/in/', $this->base . '/out/new/', '',
			$this->base . '/in/a.zip;' . $this->base . '/in/b.zip;', $this->base . '/temp/'));
		$this->assertZipLinkRefused($run, $this->base . '/temp', $this->base . '/out/new');
		$this->assertTrue(!file_exists($this->base . '/temp'), 'the temp directory is removed');
	}

	public function testLinksInARarAreRefusedAndUnrarCreatesNone()
	{
		$this->fresh();
		// The real file of the archive is left out: with -ol- unrar writes
		// nothing at all, and the links are refused all the same.
		$unrar = $this->extractor('unrar', '[ "$1" = lt ] && printf %s ' . Utility::escapeShellArg(self::LISTED_LINKS)
			. "\n" . '[ "$1" = x ] || exit 0; case " $* " in *" -ol- "*) ;;'
			. ' *) ln -s real "$dest/lib.so"; ln -s real "$dest/winlink"; ln -s real "$dest/junction" ;; esac');
		file_put_contents($this->base . '/in/a.rar', 'rar');
		$run = $this->script('unrar_file.sh', array($unrar, $this->base . '/in/a.rar', $this->base . '/out/', '',
			$this->base . '/in/a.rar'));
		$this->assertEquals(1, $run['code'], 'the unpack fails: ' . json_encode($run));
		$args = file($this->base . '/unrar.args', FILE_IGNORE_NEW_LINES);
		$this->assertTrue(strpos($args[0], 'lt -v -p- -- ') === 0,
			'every volume is listed, without a password prompt: ' . $args[0]);
		$this->assertTrue(strpos(' ' . ($args[1] ?? '') . ' ', ' -ol- ') !== false,
			'unrar extracts with -ol-: ' . json_encode($args));
		$this->assertTrue(strpos($run['err'], 'Type: Junction') === false,
			'a file named like a type is not refused: ' . $run['err']);
		foreach (array('lib.so', 'winlink', 'junction') as $name)
			$this->assertTrue(strpos($run['err'], "unpack: refused archive-symbolic-link: $name\n") !== false,
				'the task output names ' . $name . ': ' . $run['err']);
		$this->assertTrue($run['log'] === array($this->refusedLine('archive-symbolic-link', 3)),
			'the log counts them: ' . json_encode($run['log']));
		$this->assertTrue(UnpackPlaceFixture::tree($this->base . '/out') === array(),
			'no link reaches the output: ' . json_encode(UnpackPlaceFixture::tree($this->base . '/out')));
		$this->assertTrue(is_file($this->base . '/in/a.rar'), 'and the archive is kept');
	}

	public function testUnallHandsBothScriptsTheirContext()
	{
		$this->fresh();
		$link = 'ln -s "$outside" "$dest/l"';
		// unrar lists the link, once for each of two volumes, and creates it
		// only without -ol-.
		$unrar = $this->extractor('unrar', '[ "$1" = lt ] && printf "        Name: l\n        Type: Unix symbolic link\n%.0s" 1 2'
			. "\n" . '[ "$1" = x ] || exit 0; case " $* " in *" -ol- "*) ;; *) ' . $link . ' ;; esac');
		$unzip = $this->extractor('unzip', $link);
		file_put_contents($this->base . '/in/a.rar', 'rar');
		file_put_contents($this->base . '/in/a.zip', 'zip');
		$run = $this->script('unall_dir.sh', array($unrar, $this->base . '/in/', $this->base . '/out/', $unzip));
		$this->assertTrue($this->calls('unrar') === array('lt', 'x'), 'unrar lists, then extracts');
		$this->assertTrue($this->calls('unzip') === array('-o'), 'unzip extracts');
		$line = $this->refusedLine('archive-symbolic-link', 1);
		$this->assertTrue($run['log'] === array($line, $line),
			'each script logs its refusal, the link listed twice counted once: ' . json_encode($run['log']));
		$this->assertEquals(2, substr_count($run['err'], "unpack: refused archive-symbolic-link: l\n"),
			'and each names it once: ' . $run['err']);
		// ruTorrent makes the profile directory of the user it runs as.
		$this->assertTrue(is_dir($this->base . '/profile/users/' . self::USER), 'as the user the unpack was for');
	}

	public function testArchivesTwoDirectoriesDeepAreUnpacked()
	{
		$this->fresh();
		// unzip -d makes only the last directory of the path it is given.
		$unzip = $this->extractor('unzip', '{ mkdir "$dest" 2>/dev/null || [ -d "$dest" ]; } || exit 2; echo z > "$dest/z"');
		// unrar is given each directory as "<directory>/.", rar or not, and
		// makes the whole path it extracts to.
		$unrar = $this->extractor('unrar', '[ "$1" = x ] || exit 0; for a; do case "$a" in *.) d=${a%.} ;; esac; done;'
			. ' for a in "$d"*.rar; do [ -f "$a" ] && mkdir -p "$dest" && echo r > "$dest/r"; done; exit 0');
		mkdir($this->base . '/in/Subs/eng', 0700, true);
		file_put_contents($this->base . '/in/Subs/eng/x.zip', 'zip');
		file_put_contents($this->base . '/in/Subs/eng/x.rar', 'rar');
		$run = $this->script('unall_dir.sh', array($unrar, $this->base . '/in/', $this->base . '/out/', $unzip));
		$this->assertTrue($run['code'] === 0 && $run['err'] === '', 'the unpack succeeds: ' . json_encode($run));
		$this->assertTrue(UnpackPlaceFixture::tree($this->base . '/out') === array('Subs', 'Subs/eng', 'Subs/eng/r',
			'Subs/eng/z'), 'the nested subdirectory is made and filled: '
			. json_encode(UnpackPlaceFixture::tree($this->base . '/out')));
	}

	public function testDirectoriesAnArchiveLockedArePlaced()
	{
		$this->fresh();
		// A directory that cannot be written to or entered by its owner.
		$unzip = $this->extractor('unzip', 'mkdir -p "$dest/ro/sub" "$dest/shut" && echo f > "$dest/ro/sub/f"'
			. ' && echo g > "$dest/shut/g" && chmod 555 "$dest/ro/sub" "$dest/ro" && chmod 644 "$dest/shut"');
		file_put_contents($this->base . '/in/a.zip', 'zip');
		mkdir($this->base . '/out/ro');
		$run = $this->script('unzip_file.sh', array($unzip, $this->base . '/in/a.zip', $this->base . '/out/'));
		$this->assertEquals(0, $run['code'], 'the unpack succeeds: ' . json_encode($run));
		$this->assertTrue(UnpackPlaceFixture::tree($this->base . '/out') === array('ro', 'ro/sub', 'ro/sub/f', 'shut',
			'shut/g'), 'everything is placed: ' . json_encode(UnpackPlaceFixture::tree($this->base . '/out')));
		$this->assertTrue(!is_executable($this->base . '/out/ro/sub/f') && !is_executable($this->base . '/out/shut/g'),
			'and a file is not made executable');
	}

	public function testNamesWithSpacesNewlinesAndALeadingDashArePlaced()
	{
		$this->fresh();
		$input = $this->base . "/in/-in put\nx/";
		$output = $this->base . "/out/-out put\nx/";
		mkdir($input . "-sub dir\ny", 0700, true);
		file_put_contents($input . "-a b\nc.zip", 'zip');
		file_put_contents($input . "-sub dir\ny/-d e\nf.zip", 'zip');
		// Each file is named after its archive.
		$unzip = $this->extractor('unzip', 'name=${2##*/}; echo z > "$dest/${name%.zip}"');
		$run = $this->script('unzip_dir.sh', array($unzip, $input, $output));
		$this->assertEquals(0, $run['code'], 'the unpack succeeds: ' . json_encode($run));
		$this->assertTrue(UnpackPlaceFixture::tree($this->base . '/out') === array("-out put\nx", "-out put\nx/-a b\nc",
			"-out put\nx/-sub dir\ny", "-out put\nx/-sub dir\ny/-d e\nf"),
			'every name lands as it is: ' . json_encode(UnpackPlaceFixture::tree($this->base . '/out')));
		$this->assertTrue($run['err'] === '' && $run['log'] === array(), 'and nothing is reported: ' . json_encode($run));
	}

	public function testAPrivateDirectoryThatCannotBeMadeIsRefusedAndLogged()
	{
		$this->fresh();
		$unzip = $this->extractor('unzip', 'echo f > "$dest/f"');
		file_put_contents($this->base . '/in/a.zip', 'zip');
		chmod($this->base . '/out', 0500);
		$this->assertTrue(!is_writable($this->base . '/out'), 'precondition: not run as root');
		$run = $this->script('unzip_file.sh', array($unzip, $this->base . '/in/a.zip', $this->base . '/out/'));
		$this->assertEquals(1, $run['code'], 'the unpack fails: ' . json_encode($run));
		$this->assertTrue($this->calls('unzip') === array(), 'without starting the extractor');
		$this->assertTrue(strpos($run['err'], 'unpack: refused private-directory-unavailable: '
			. $this->base . "/out/\n") !== false, 'the task output says why: ' . $run['err']);
		$this->assertTrue($run['log'] === array($this->refusedLine('private-directory-unavailable', 1)),
			'and so does the log: ' . json_encode($run['log']));
	}
}
