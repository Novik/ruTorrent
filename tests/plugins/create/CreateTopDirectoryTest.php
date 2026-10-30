<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../../php/Torrent.php');

/**
 * plugins/create hashes what it is pointed at, and with "start seeding"
 * rtorrent then serves it, so what it reads has to stay inside
 * $topDirectory once symbolic links are followed, not only as written: the
 * path it is given, and every link inside a folder it is given. mktorrent
 * and the other external creators follow links in a folder, so such a
 * folder is refused before any creator starts, naming the entry.
 *
 * The tree is real: a top directory, a directory outside it, a link out, a
 * folder inside holding a link to a file outside, and a link to a file
 * elsewhere inside the top directory; a folder whose links all stay inside,
 * two of them back up the folder itself; a folder whose link to a folder
 * inside leads to a link out; a folder holding a link that does not
 * resolve; a link out behind 45 folder links, more than the kernel follows
 * in one path; and a link out whose name is not UTF-8.
 */
class CreateTopDirectoryTest extends TestCase
{
	private $base;
	private $top;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-create-top-' . uniqid('', true);
		@mkdir($this->base . '/profile/settings', 0700, true);
		@mkdir($this->base . '/top/pack', 0777, true);
		@mkdir($this->base . '/top/other', 0777, true);
		@mkdir($this->base . '/top/clean', 0777, true);
		@mkdir($this->base . '/top/hub', 0777, true);
		@mkdir($this->base . '/top/beyond', 0777, true);
		@mkdir($this->base . '/top/dangling', 0777, true);
		@mkdir($this->base . '/top/sub/locked', 0777, true);
		@mkdir($this->base . '/top/chain', 0777, true);
		@mkdir($this->base . '/top/odd', 0777, true);
		for ($k = 1; $k <= 45; $k++) {
			@mkdir($this->base . '/top/cr/d' . $k, 0777, true);
		}
		@mkdir($this->base . '/outside', 0777, true);
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';
		file_put_contents($this->base . '/outside/secret.bin', 'outside');
		file_put_contents($this->top . '/pack/mine.txt', 'mine');
		file_put_contents($this->top . '/other/shared.txt', 'shared');
		symlink($this->base . '/outside', $this->top . '/out');
		symlink($this->base . '/outside/secret.bin', $this->top . '/pack/leak.bin');
		symlink($this->top . '/other/shared.txt', $this->top . '/pack/shared.txt');
		file_put_contents($this->top . '/clean/mine.txt', 'mine');
		symlink($this->top . '/other/shared.txt', $this->top . '/clean/shared.txt');
		symlink($this->top . '/clean', $this->top . '/clean/again');
		symlink($this->top . '/clean', $this->top . '/clean/twice');
		symlink($this->top . '/beyond', $this->top . '/hub/via');
		symlink($this->base . '/outside/secret.bin', $this->top . '/beyond/leak.bin');
		symlink($this->base . '/nowhere', $this->top . '/dangling/gone');
		symlink($this->top . '/cr/d1', $this->top . '/chain/n');
		for ($k = 1; $k < 45; $k++) {
			symlink($this->top . '/cr/d' . ($k + 1), $this->top . '/cr/d' . $k . '/n');
		}
		symlink($this->base . '/outside/secret.bin', $this->top . '/cr/d45/leak.bin');
		symlink($this->base . '/outside/secret.bin', $this->top . "/odd/\xff\n.bin");
	}

	public function tearDown()
	{
		@chmod($this->top . '/sub/locked', 0777);
		$this->removeTree($this->base);
		unset($GLOBALS['topDirectory']);
	}

	private function removeTree($path)
	{
		if (is_link($path) || !is_dir($path)) {
			@unlink($path);
			return;
		}
		foreach (glob($path . '/{,.}[!.]*', GLOB_BRACE) as $entry) {
			$this->removeTree($entry);
		}
		@rmdir($path);
	}

	private function copyTree($from, $to)
	{
		@mkdir($to, 0700, true);
		foreach (scandir($from) as $entry) {
			if ($entry === '.' || $entry === '..' || $entry === 'config.local.php' || $entry === 'conf.local.php') {
				continue;
			}
			if (is_dir($from . '/' . $entry)) {
				$this->copyTree($from . '/' . $entry, $to . '/' . $entry);
			} else {
				copy($from . '/' . $entry, $to . '/' . $entry);
			}
		}
	}

	/**
	 * Posts "create" to action.php for $path and returns its answer. Options:
	 * 'top', the top directory; 'creator', an external creator set in a copy
	 * of the plugin's conf.local.php, which leaves base/creator behind when
	 * it is read; 'disable', functions the child lacks; 'mayFail', to answer
	 * null errors instead of throwing when nothing is printed; 'daemon', to
	 * have a stand-in rtorrent accept the task, so its parameters stay while
	 * nothing runs.
	 */
	private function create($path, $options = array())
	{
		$root = realpath(__DIR__ . '/../../..');
		if (isset($options['creator'])) {
			$tree = $this->base . '/tree';
			foreach (array('php', 'conf', 'plugins/_task', 'plugins/create') as $dir) {
				$this->copyTree($root . '/' . $dir, $tree . '/' . $dir);
			}
			file_put_contents($tree . '/plugins/create/conf.local.php', "<?php\n"
				. '$useExternal = ' . var_export($options['creator'], true) . ";\n"
				. '$pathToCreatetorrent = ' . var_export($this->base . '/bin/' . $options['creator'], true) . ";\n"
				. 'file_put_contents(' . var_export($this->base . '/creator', true) . ', $useExternal);' . "\n");
			$root = $tree;
		}
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$daemon = empty($options['daemon']) ? null
			: new FakeRtorrentDaemon(array(array('0'), array('0'), array('0'), array('0')), $this->base . '/calls.log');
		$driver = $this->base . '/drive-create.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$topDirectory = ' . var_export(isset($options['top']) ? $options['top'] : $this->top . '/', true) . ";\n"
			. '$tempDirectory = ' . var_export($this->base . '/', true) . ";\n"
			. ($daemon === null ? '' : '$scgi_host = "127.0.0.1"; $scgi_port = ' . $daemon->port() . ";\n")
			. '$_REQUEST = array("cmd" => "create", "path_edit" => ' . var_export($path, true)
				. ', "piece_size" => "16", "start_seeding" => "0", "private" => "0", "trackers" => "",'
				. ' "comment" => "", "source" => "");' . "\n"
			. 'chdir(' . var_export($root . '/plugins/create', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/create/action.php', true) . ");\n");
		$output = array();
		$disable = isset($options['disable']) ? ' -d disable_functions=' . escapeshellarg($options['disable']) : '';
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -d max_execution_time=30' . $disable . ' '
			. escapeshellarg($driver) . ' 2>&1', $output);
		if ($daemon !== null) {
			$daemon->stop();
		}
		$printed = implode("\n", $output);
		$answer = json_decode($printed, true);
		if (!is_array($answer) || !array_key_exists('errors', $answer)) {
			if (empty($options['mayFail'])) {
				throw new Exception('action.php printed: ' . $printed);
			}
			$answer = array('errors' => null);
		}
		return $answer;
	}

	private function refused($answer)
	{
		return count($answer['errors']) > 0 && strpos($answer['errors'][0], 'Incorrect directory') === 0;
	}

	public function testAPathThroughALinkOutIsRefused()
	{
		$a = $this->create($this->top . '/out/secret.bin');
		$this->assertTrue($this->refused($a), 'a file reached through a link out is not hashed: ' . json_encode($a['errors']));
	}

	public function testAFolderThatIsALinkOutIsRefused()
	{
		$a = $this->create($this->top . '/out');
		$this->assertTrue($this->refused($a), 'nor a folder that is a link out: ' . json_encode($a['errors']));
	}

	public function testAPathInsideIsAccepted()
	{
		$a = $this->create($this->top . '/pack/mine.txt');
		$this->assertTrue(!$this->refused($a), 'a file inside the top directory is hashed: ' . json_encode($a['errors']));
	}

	/** Asserts $answer refuses with "Incorrect directory" naming $entry under the top directory. */
	private function assertRefusedNaming($entry, $answer, $what)
	{
		$this->assertEquals(array('Incorrect directory (' . $entry . ')'), $answer['errors'], $what);
	}

	public function testAFolderHoldingALinkOutIsRefused()
	{
		$this->assertRefusedNaming('/pack/leak.bin', $this->create($this->top . '/pack'),
			'a folder holding a link out is not hashed by any creator, and the link is named');
	}

	public function testALinkOutBehindAFolderLinkInsideIsRefused()
	{
		$this->assertRefusedNaming('/hub/via/leak.bin', $this->create($this->top . '/hub'),
			'nor a folder whose link to a folder inside leads to a link out');
	}

	public function testALinkThatDoesNotResolveIsRefused()
	{
		$this->assertRefusedNaming('/dangling/gone', $this->create($this->top . '/dangling'),
			'nor a folder holding a link that does not resolve');
	}

	public function testALinkOutBehindMoreFolderLinksThanThePathLimitIsRefused()
	{
		$this->assertRefusedNaming('/chain' . str_repeat('/n', 45) . '/leak.bin', $this->create($this->top . '/chain'),
			'nor a folder whose link out sits behind 45 folder links');
	}

	public function testAnOddNameIsNamedInValidJson()
	{
		$a = $this->create($this->top . '/odd');
		$this->assertTrue((count($a['errors']) === 1) && (preg_match('`^Incorrect directory \(/odd/.+\.bin\)$`su', $a['errors'][0]) === 1),
			'a link out whose name is not UTF-8 is refused and named in a valid answer: ' . json_encode($a['errors']));
	}

	public function testAFolderWhoseLinksStayInsideIsAccepted()
	{
		$a = $this->create($this->top . '/clean');
		$this->assertTrue(!$this->refused($a), 'links elsewhere inside, and back up the folder, are no reason to refuse: '
			. json_encode($a['errors']));
	}

	public function testMktorrentIsNotStartedOnAFolderHoldingALinkOut()
	{
		$a = $this->create($this->top . '/pack', array('creator' => 'mktorrent'));
		$this->assertEquals('mktorrent', @file_get_contents($this->base . '/creator'),
			'the plugin is configured with mktorrent');
		$this->assertRefusedNaming('/pack/leak.bin', $a,
			'mktorrent follows links, so it is not started on a folder holding a link out');
	}

	public function testAFolderIsRefusedWhenAFunctionTheWalkNeedsIsDisabled()
	{
		// realpath() is not checked by the walk: rTorrent::isInsideTopDirectory()
		// has already refused the folder, or stopped the request, without it.
		foreach (array('is_link', 'scandir', 'is_dir', 'realpath') as $function) {
			$a = $this->create($this->top . '/pack', array('disable' => $function, 'mayFail' => true));
			// PHP 8 stops at action.php's own is_dir() call, or at realpath()
			// in rTorrent::isInsideTopDirectory(), and prints nothing.
			$stopped = ($a['errors'] === null) && (PHP_MAJOR_VERSION >= 8)
				&& in_array($function, array('is_dir', 'realpath'));
			$this->assertTrue($stopped || (($a['errors'] !== null) && $this->refused($a)),
				'without ' . $function . '() a folder is not hashed: ' . json_encode($a['errors']));
		}
	}

	public function testAFolderThatCannotBeListedIsRefused()
	{
		chmod($this->top . '/sub/locked', 0100);
		if (@scandir($this->top . '/sub/locked') !== false) {
			echo 'NOT CHECKED (permissions do not stop this process): a folder create cannot list' . "\n";
			return;
		}
		$this->assertRefusedNaming('/sub/locked', $this->create($this->top . '/sub'),
			'a folder holding a folder that cannot be listed is not hashed, and that folder is named');
	}

	public function testATopDirectoryOfRootWalksNothing()
	{
		$a = $this->create($this->top . '/pack', array('top' => '/', 'disable' => 'is_link'));
		$this->assertTrue(!$this->refused($a), 'with "/" as the top directory nothing is outside it, '
			. 'and nothing is walked: ' . json_encode($a['errors']));
	}

	public function testTheBuiltInCreatorHashesOnlyThePathThatWasChecked()
	{
		// out/../outside reads as <top>/outside, which is inside, but the
		// kernel follows out first and the .. then leads beside its target.
		$a = $this->create($this->top . '/out/../outside', array('daemon' => true));
		$this->assertTrue(!$this->refused($a), 'the path is accepted as the folder it reads as: ' . json_encode($a['errors']));
		$params = glob($this->base . '/profile/settings/tasks/*/params') ?: array();
		$this->assertEquals(1, count($params), 'one creator task is started');
		if (count($params) !== 1) {
			return;
		}
		$request = unserialize(file_get_contents($params[0]), array('allowed_classes' => false));
		$this->assertEquals($this->top . '/outside', $request['path_edit'], 'the task holds the path that was checked');
		$output = array();
		exec('RU_PROFILE_PATH=' . escapeshellarg($this->base . '/profile') . ' ' . escapeshellarg(PHP_BINARY)
			. ' -d error_reporting=0 -d variables_order=EGPCS '
			. escapeshellarg(realpath(__DIR__ . '/../../..') . '/plugins/create/createtorrent.php') . ' '
			. escapeshellarg(basename(dirname($params[0]))) . ' 2>&1', $output);
		$result = dirname($params[0]) . '/result.torrent';
		$this->assertTrue(!is_file($result) || (strpos(file_get_contents($result), 'secret.bin') === false),
			'and the built-in creator does not hash the file outside: ' . implode("\n", $output));
	}

	/** The names of the files a torrent built from $dir holds. */
	private function folderNames($dir)
	{
		$GLOBALS['topDirectory'] = $this->top . '/';
		$torrent = new Torrent($dir, array(), 16);
		$names = array();
		foreach ($torrent->info['files'] as $file) {
			$names[] = implode('/', $file['path']);
		}
		sort($names);
		return $names;
	}

	public function testALinkOutInsideAFolderIsLeftOut()
	{
		$this->assertEquals(array('mine.txt', 'shared.txt'), $this->folderNames($this->top . '/pack'),
			'a folder\'s link to a file outside the top directory is not hashed, '
			. 'and its link to a file elsewhere inside it is');
	}

	public function testATopDirectoryOfRootLeavesNothingOut()
	{
		$GLOBALS['topDirectory'] = '/';
		$torrent = new Torrent($this->top . '/pack', array(), 16);
		$names = array();
		foreach ($torrent->info['files'] as $file) {
			$names[] = implode('/', $file['path']);
		}
		sort($names);
		$this->assertEquals(array('leak.bin', 'mine.txt', 'shared.txt'), $names,
			'with "/" as the top directory nothing is outside it');
	}
}
