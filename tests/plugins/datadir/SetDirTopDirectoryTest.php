<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * "Set data directory" keeps a download inside $topDirectory once the
 * symbolic links along the new directory are followed, not only as text.
 *
 * correctDirectory() compares the path as written. setdir.php makes the
 * directory with mkdir and moves the data with rename and copy, and all of
 * them follow links, so a link inside the top directory that points out of
 * it had the download's files moved wherever it pointed.
 *
 * action.php refuses such a directory before it starts anything, and
 * setdir.php checks again before it makes the directory: it runs detached,
 * possibly after other moves, and a link made in the meantime must not be
 * followed either.
 *
 * The tree is real: a top directory, a directory outside it, a link out, a
 * link that stays inside and a dangling link.
 */
class SetDirTopDirectoryTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

	private $base;
	private $top;
	private $daemon;

	public function setUp()
	{
	}

	public function tearDown()
	{
		$this->reset();
	}

	private function reset()
	{
		if ($this->daemon !== null)
		{
			$this->daemon->stop();
			$this->daemon = null;
		}
		if ($this->base !== null)
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

	private function makeTree()
	{
		$this->reset();
		$this->base = sys_get_temp_dir() . '/rutorrent-setdir-top-' . uniqid('', true);
		@mkdir($this->base . '/profile/settings', 0700, true);
		@mkdir($this->base . '/top/src/mytorrent', 0777, true);
		@mkdir($this->base . '/top/plain', 0777, true);
		@mkdir($this->base . '/outside', 0777, true);
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';
		foreach (array('1.bin', '2.bin') as $n)
			file_put_contents($this->top . '/src/mytorrent/' . $n, 'download ' . $n);
		symlink($this->base . '/outside', $this->top . '/out');
		symlink($this->top . '/plain', $this->top . '/in');
		symlink($this->base . '/nowhere', $this->top . '/dangle');
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/tester');
		$this->daemon = new FakeRtorrentDaemon(array(
			array(1, 'mytorrent', $this->top . '/src/mytorrent', 'mytorrent', 1),
			array('1.bin', '2.bin'),
			array(0), array(0), array(0),
		), $this->base . '/calls.log');
	}

	/**
	 * The lines a driver needs before it loads a datadir script: the
	 * profile, this daemon and $topDirectory, set after config.php so the
	 * script's own require_once of it does not undo them.
	 */
	private function driverHead()
	{
		return "<?php\n"
			. '$_SERVER[\'REMOTE_USER\'] = "tester"; $_ENV[\'RU_PROFILE_PATH\'] = '
				. var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . ';'
				. ' $rpcTimeOut = 10; $rpcLogCalls = false;' . "\n"
			. '$topDirectory = ' . var_export($this->top . '/', true) . ";\n";
	}

	private function runDriver($code)
	{
		$driver = $this->base . '/drive.php';
		file_put_contents($driver, $code);
		$out = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver)
			. ' 2>&1', $out);
		return implode("\n", $out);
	}

	/**
	 * Posts a "set data directory" with the files moved to action.php and
	 * reports what came back and whether the move was started.
	 */
	private function postAction($datadir)
	{
		$this->makeTree();
		$printed = $this->runDriver($this->driverHead()
			. '$HTTP_RAW_POST_DATA = "hash=' . self::HASH . '&datadir='
				. rawurlencode($this->top . $datadir)
				. '&move_addpath=1&move_datafiles=1&move_fastresume=0";' . "\n"
			. 'chdir(' . var_export($this->repoRoot() . '/plugins/datadir', true) . ");\n"
			. 'require(' . var_export($this->repoRoot() . '/plugins/datadir/action.php', true) . ");\n");
		$answer = json_decode($printed, true);
		if (!is_array($answer) || !array_key_exists('errors', $answer))
			throw new Exception('action.php printed: ' . $printed);
		return array(
			'printed' => $printed,
			'errors' => $answer['errors'],
			'started' => in_array('execute', $this->daemon->calls(), true),
		);
	}

	public function testALinkOutOfTheTopDirectoryIsRefused()
	{
		$r = $this->postAction('/out');
		$this->assertTrue(count($r['errors']) > 0,
			'a directory that resolves outside the top directory is refused: ' . $r['printed']);
		$this->assertTrue(!$r['started'], 'and the move is not started');
	}

	public function testANewDirectoryUnderALinkOutIsRefused()
	{
		$r = $this->postAction('/out/new');
		$this->assertTrue(count($r['errors']) > 0,
			'a directory that does not exist yet, under a link out, is refused: ' . $r['printed']);
		$this->assertTrue(!$r['started'], 'and the move is not started');
	}

	public function testADanglingLinkIsRefused()
	{
		$r = $this->postAction('/dangle/new');
		$this->assertTrue(count($r['errors']) > 0,
			'a link that does not resolve is refused: ' . $r['printed']);
		$this->assertTrue(!$r['started'], 'and the move is not started');
	}

	public function testALinkThatStaysInsideIsAccepted()
	{
		$r = $this->postAction('/in/new');
		$this->assertEquals(array(), $r['errors'],
			'a link that resolves inside the top directory is followed: ' . $r['printed']);
		$this->assertTrue($r['started'], 'and the move is started');
	}

	public function testANewDirectoryInsideIsAccepted()
	{
		$r = $this->postAction('/plain/new');
		$this->assertEquals(array(), $r['errors'],
			'a directory inside the top directory is accepted: ' . $r['printed']);
		$this->assertTrue($r['started'], 'and the move is started');
	}

	/**
	 * setdir.php runs detached, after action.php has answered and possibly
	 * after other moves, so the link can be made after action.php checked.
	 * Started with a directory under a link out, it makes nothing there and
	 * asks rTorrent for nothing.
	 */
	public function testSetDirMakesNothingThroughALinkOut()
	{
		$this->makeTree();
		$printed = $this->runDriver($this->driverHead()
			. '$argv = array("setdir.php", "' . self::HASH . '", '
				. var_export($this->top . '/out/new', true) . ', "1", "1", "0", "tester");' . "\n"
			. '$argc = count($argv);' . "\n"
			. 'require(' . var_export($this->repoRoot() . '/plugins/datadir/setdir.php', true) . ");\n");
		$this->assertTrue(!file_exists($this->base . '/outside/new'),
			'no directory is made outside the top directory: ' . $printed);
		$this->assertEquals(array('1.bin', '2.bin'), array_values(array_diff(scandir($this->top . '/src/mytorrent'), array('.', '..'))),
			'the data stays where it was');
		$this->assertEquals(array(), $this->daemon->calls(),
			'and rTorrent is asked for nothing');
	}
}
