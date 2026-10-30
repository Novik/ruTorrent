<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');

/**
 * autotools moves finished downloads into, and loads torrents from,
 * directories in its settings, and the moves and loads follow any symbolic
 * link along them. The settings are checked as written when they are saved,
 * and a link can be made at any time after, so the directories have to stay
 * inside $topDirectory once their links are followed when they are used:
 * by check.php, which names the directory rtorrent points the download at,
 * by move.php, which carries the data there, and by watch.php.
 *
 * The tree is real: a top directory holding the download directory, a
 * directory outside it, and links out. The scripts run as rtorrent runs
 * them, through check.php and move.php, or watch.php, with the plugin's
 * settings stored where they read them.
 */
class AutoToolsTopDirectoryTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
	const USER = 'tester';

	private $base;
	private $top;

	public function setUp()
	{
	}

	public function tearDown()
	{
		if ($this->base !== null)
			$this->removeTree($this->base);
	}

	private function removeTree($path)
	{
		if (is_link($path) || !is_dir($path)) { @unlink($path); return; }
		foreach (glob($path . '/{,.}[!.]*', GLOB_BRACE) as $entry)
			$this->removeTree($entry);
		@rmdir($path);
	}

	private function makeDir($dir)
	{
		$mask = umask(0);
		try {
			if (!is_dir($dir) && !mkdir($dir, 0700, true))
				throw new RuntimeException('Could not create fixture directory ' . $dir);
		} finally {
			umask($mask);
		}
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	private function bstr($s)
	{
		return strlen($s) . ':' . $s;
	}

	/** A torrent of the files $names, called $root. */
	private function torrentBytes($names, $root)
	{
		$files = '';
		foreach ($names as $n)
			$files .= 'd' . $this->bstr('length') . 'i12e' . $this->bstr('path') . 'l' . $this->bstr($n) . 'ee';
		$info = 'd' . $this->bstr('files') . 'l' . $files . 'e' . $this->bstr('name') . $this->bstr($root)
			. $this->bstr('piece length') . 'i32768e' . $this->bstr('pieces') . $this->bstr(str_repeat("\x01", 20)) . 'e';
		return 'd' . $this->bstr('announce') . $this->bstr('http://tracker.invalid/announce')
			. $this->bstr('info') . $info . 'e';
	}

	private function prelude()
	{
		return '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
			. ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = 1; $rpcTimeOut = 1; $rpcLogCalls = false;' . "\n"
			. '$topDirectory = ' . var_export($this->top . '/', true) . ";\n";
	}

	private function runPhp($file, $args = array())
	{
		$mask = umask(022);
		try {
			$run = PhpProcess::run(array_merge(PhpProcess::sameIni(),
				array('-d', 'display_errors=stderr', $file), $args));
		} finally {
			umask($mask);
		}
		return array('exit' => $run['timedOut'] ? 'timed out' : $run['code'],
			'printed' => $run['out'], 'errors' => $run['err']);
	}

	private function runScript($which, $args)
	{
		$driver = $this->base . '/drive-' . $which . '.php';
		file_put_contents($driver, "<?php\n" . $this->prelude()
			. 'require(' . var_export($this->repoRoot() . '/plugins/autotools/' . $which . '.php', true) . ");\n");
		return $this->runPhp($driver, $args);
	}

	/** A top directory with a download directory, and a directory outside it. */
	private function makeTree()
	{
		if ($this->base !== null)
			$this->removeTree($this->base);
		$this->base = sys_get_temp_dir() . '/rutorrent-autotools-top-' . uniqid('', true);
		$this->makeDir($this->base . '/profile/settings');
		$this->makeDir($this->base . '/session');
		$this->makeDir($this->base . '/top/dl');
		$this->makeDir($this->base . '/outside');
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';
	}

	/** Stores the plugin settings the scripts read. */
	private function seed($settings)
	{
		$set = '';
		foreach ($settings as $k => $v)
			$set .= '$at->' . $k . ' = ' . var_export($v, true) . '; ';
		$seed = $this->base . '/seed.php';
		file_put_contents($seed, "<?php\n" . $this->prelude()
			. 'require_once(' . var_export($this->repoRoot() . '/php/settings.php', true) . ");\n"
			. 'require_once(' . var_export($this->repoRoot() . '/plugins/autotools/autotools.php', true) . ");\n"
			. '$s = rTorrentSettings::get();' . "\n"
			. '$s->directory = ' . var_export($this->top . '/dl', true) . ";\n"
			. '$s->session = ' . var_export($this->base . '/session', true) . ";\n"
			. '$s->linkExist = true; $s->iVersion = 0x1015; $s->store();' . "\n"
			. '$at = rAutoTools::load(); ' . $set . '$at->store();' . "\n"
			. 'echo "seeded";' . "\n");
		$seeded = $this->runPhp($seed);
		if ($seeded['printed'] !== 'seeded')
			throw new Exception('the plugin settings did not take: ' . $seeded['printed'] . $seeded['errors']);
	}

	/**
	 * A finished download "mytorrent" of two files in the download
	 * directory, with check.php and move.php run on it as rtorrent runs them
	 * for $operation. Reports the directory check.php named and where the
	 * files are.
	 */
	private function finish($finished, $operation = 'Move')
	{
		$names = array('1.bin', '2.bin');
		$this->makeDir($this->top . '/dl/mytorrent');
		foreach ($names as $n)
			file_put_contents($this->top . '/dl/mytorrent/' . $n, 'download ' . $n);
		file_put_contents($this->base . '/session/' . self::HASH . '.torrent', $this->torrentBytes($names, 'mytorrent'));
		$this->seed(array('enable_move' => 1, 'fileop_type' => $operation, 'path_to_finished' => $finished,
			'automove_filter' => '/.*/', 'addLabel' => 0, 'addName' => 0, 'skip_move_for_files' => ''));

		$basePath = $this->top . '/dl/mytorrent';
		$check = ($operation === 'Move')
			? $this->runScript('check', array($basePath, 'mytorrent', '1', '', 'mytorrent', self::USER))
			: array('printed' => '');
		$move = $this->runScript('move', array(self::HASH, $basePath, 'mytorrent', '1', '', 'mytorrent', self::USER));
		$outside = array();
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base . '/outside',
			FilesystemIterator::SKIP_DOTS)) as $f)
			$outside[] = substr($f->getPathname(), strlen($this->base . '/outside/'));
		sort($outside);
		return array(
			'named' => rtrim($check['printed'], '/'),
			'atSource' => is_file($basePath . '/1.bin') && is_file($basePath . '/2.bin'),
			'outside' => $outside,
			'move' => $move,
		);
	}

	public function testAFinishedDirectoryThatIsALinkOutIsNotMovedTo()
	{
		$this->makeTree();
		symlink($this->base . '/outside', $this->top . '/fin');
		$r = $this->finish($this->top . '/fin');
		$this->assertEquals($this->top . '/dl/mytorrent', $r['named'],
			'check.php leaves the download where its data is');
		$this->assertTrue($r['atSource'], 'the data stays where it is');
		$this->assertEquals(array(), $r['outside'], 'and nothing is written outside the top directory');
	}

	public function testADownloadsNameThatIsALinkOutIsNotMovedTo()
	{
		$this->makeTree();
		$this->makeDir($this->top . '/fin');
		symlink($this->base . '/outside', $this->top . '/fin/mytorrent');
		$r = $this->finish($this->top . '/fin');
		$this->assertEquals($this->top . '/dl/mytorrent', $r['named'],
			'check.php leaves the download where its data is when its own name in the finished directory is a link out');
		$this->assertEquals(array(), $r['outside'], 'and nothing is written outside the top directory');
	}

	public function testACopyThroughALinkOutIsNotMade()
	{
		$this->makeTree();
		symlink($this->base . '/outside', $this->top . '/fin');
		$r = $this->finish($this->top . '/fin', 'Copy');
		$this->assertEquals(array(), $r['outside'], 'a copy is not made outside the top directory');
		$this->assertTrue($r['atSource'], 'and the data stays where it is');
	}

	public function testAFinishedDirectoryInsideIsMovedTo()
	{
		$this->makeTree();
		$this->makeDir($this->top . '/fin');
		$r = $this->finish($this->top . '/fin');
		$this->assertEquals($this->top . '/fin/mytorrent', $r['named'], 'check.php names the finished directory');
		$this->assertTrue(is_file($this->top . '/fin/mytorrent/1.bin') && is_file($this->top . '/fin/mytorrent/2.bin'),
			'and the data is moved there: ' . $r['move']['errors']);
	}

	/**
	 * watch.php with rtorrent unreachable: each torrent it takes up is
	 * touched, its load fails and it is renamed to .fail, so a file left as
	 * it was is one watch.php did not take up.
	 */
	private function watch($watched)
	{
		$this->seed(array('enable_watch' => 1, 'path_to_watch' => $watched, 'watch_start' => 0));
		return $this->runScript('watch', array(self::USER));
	}

	public function testAWatchDirectoryThatIsALinkOutIsNotScanned()
	{
		$this->makeTree();
		file_put_contents($this->base . '/outside/x.torrent', 'not ours');
		touch($this->base . '/outside/x.torrent', 1000000000);
		symlink($this->base . '/outside', $this->top . '/watch');
		$this->watch($this->top . '/watch');
		clearstatcache();
		$this->assertTrue(is_file($this->base . '/outside/x.torrent') && filemtime($this->base . '/outside/x.torrent') === 1000000000,
			'a torrent outside the top directory is neither touched nor renamed');
	}

	/**
	 * A torrent in a subdirectory of the watch directory is loaded into the
	 * same subdirectory of the download directory, which watch.php makes
	 * first; a link out there must not have it make directories outside.
	 */
	public function testADataDirectoryThroughALinkOutIsNotMade()
	{
		$this->makeTree();
		$this->makeDir($this->top . '/watch/sub/deeper');
		file_put_contents($this->top . '/watch/sub/deeper/y.torrent', 'ours');
		symlink($this->base . '/outside', $this->top . '/dl/sub');
		$this->watch($this->top . '/watch');
		clearstatcache();
		$this->assertTrue(!file_exists($this->base . '/outside/deeper'),
			'no directory is made outside the top directory for its data');
	}

	public function testALinkOutInsideTheWatchDirectoryIsNotFollowed()
	{
		$this->makeTree();
		$this->makeDir($this->top . '/watch');
		file_put_contents($this->base . '/outside/x.torrent', 'not ours');
		touch($this->base . '/outside/x.torrent', 1000000000);
		symlink($this->base . '/outside', $this->top . '/watch/sub');
		file_put_contents($this->top . '/watch/mine.torrent', 'ours');
		$this->watch($this->top . '/watch');
		clearstatcache();
		$this->assertTrue(is_file($this->base . '/outside/x.torrent') && filemtime($this->base . '/outside/x.torrent') === 1000000000,
			'a torrent reached through a link out of the watch directory is neither touched nor renamed');
		$this->assertTrue(is_file($this->top . '/watch/mine.torrent.fail'),
			'while one inside it is taken up (and, with rtorrent unreachable, set aside)');
	}
}
