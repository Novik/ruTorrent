<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../../php/Torrent.php');

/**
 * plugins/create hashes what it is pointed at, and with "start seeding"
 * rtorrent then serves it, so what it reads has to stay inside
 * $topDirectory once symbolic links are followed, not only as written: the
 * path it is given, and every link inside a folder it is given.
 *
 * The tree is real: a top directory, a directory outside it, a link out, a
 * folder inside holding a link to a file outside, and a link to a file
 * elsewhere inside the top directory.
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
		@mkdir($this->base . '/outside', 0777, true);
		$this->base = realpath($this->base);
		$this->top = $this->base . '/top';
		file_put_contents($this->base . '/outside/secret.bin', 'outside');
		file_put_contents($this->top . '/pack/mine.txt', 'mine');
		file_put_contents($this->top . '/other/shared.txt', 'shared');
		symlink($this->base . '/outside', $this->top . '/out');
		symlink($this->base . '/outside/secret.bin', $this->top . '/pack/leak.bin');
		symlink($this->top . '/other/shared.txt', $this->top . '/pack/shared.txt');
	}

	public function tearDown()
	{
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

	/** Posts "create" to action.php for $path and returns its answer. */
	private function create($path)
	{
		$root = realpath(__DIR__ . '/../../..');
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$driver = $this->base . '/drive-create.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$topDirectory = ' . var_export($this->top . '/', true) . ";\n"
			. '$tempDirectory = ' . var_export($this->base . '/', true) . ";\n"
			. '$_REQUEST = array("cmd" => "create", "path_edit" => ' . var_export($path, true)
				. ', "piece_size" => "16", "start_seeding" => "0", "private" => "0", "trackers" => "",'
				. ' "comment" => "", "source" => "");' . "\n"
			. 'chdir(' . var_export($root . '/plugins/create', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/create/action.php', true) . ");\n");
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver) . ' 2>&1', $output);
		$printed = implode("\n", $output);
		$answer = json_decode($printed, true);
		if (!is_array($answer) || !array_key_exists('errors', $answer)) {
			throw new Exception('action.php printed: ' . $printed);
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
