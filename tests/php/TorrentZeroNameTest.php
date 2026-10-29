<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/BencodeReaderFixture.php');
require_once(__DIR__ . '/../../php/Torrent.php');

/**
 * "0" is an ordinary name and an ordinary byte, and the one string PHP cannot
 * tell from the absence of a string. Three things a torrent is built out of
 * can be exactly that string: the base directory's path, a file's name, and a
 * file's content. Each reaches a different test in php/Torrent.php, and each
 * is pinned here, because a truthiness test that mistakes one of them for
 * "nothing was given" does not fail -- it writes a different torrent.
 *
 * What a client is shown is what is asserted: the name key, every file in the
 * file list with its relative path and its length, and one SHA-1 per piece
 * taken over the bytes those lengths account for. Metainfo built from a
 * relative base directory and from an absolute one has to come out byte for
 * byte identical, since the base directory is not part of what a torrent
 * describes -- only its last component is, as the name.
 *
 * Torrent::file(), Torrent::files() and Torrent::folder() are the three
 * builders behind plugins/create with the shipped configuration
 * ($useExternal = false selects inner.sh, which runs createtorrent.php).
 *
 * One case is about the locale instead: a single file's non-ASCII name in
 * C, where PHP 7.4's basename() drops it.
 */
class TorrentZeroNameTest extends TestCase
{
	use BencodeReaderFixture;

	/** Kilobytes, as the constructor takes it: the smallest it can express. */
	const PIECE_KB = 1;

	private $base;

	/**
	 * The runner calls setUp() once per file and then every test method, so
	 * each case gets a directory of its own to be the base directory's
	 * parent: two cases both wanting a directory named 0 must not meet.
	 */
	private $cases = 0;

	private function caseDirectory()
	{
		$parent = $this->base . '/case-' . (++$this->cases);
		$this->assertTrue(@mkdir($parent, 0700, true), 'case ' . $this->cases . ': its directory is created');
		return $parent;
	}

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-zero-name-' . getmypid();
		$this->remove($this->base);
		$this->assertTrue(@mkdir($this->base, 0700, true), 'the fixture root is created');
	}

	public function tearDown()
	{
		$this->remove($this->base);
	}

	private function remove($path)
	{
		if (is_dir($path) && !is_link($path)) {
			foreach (array_diff(scandir($path), array('.', '..')) as $item) {
				$this->remove($path . '/' . $item);
			}
			@rmdir($path);
			return;
		}
		if (file_exists($path)) {
			@unlink($path);
		}
	}

	/** $layout maps a path relative to $directory to that file's content. */
	private function write($directory, $layout)
	{
		foreach ($layout as $relative => $content) {
			$path = $directory . '/' . $relative;
			$parent = dirname($path);
			if (!is_dir($parent)) {
				$this->assertTrue(@mkdir($parent, 0700, true), $relative . ': its directory is created');
			}
			$this->assertTrue(file_put_contents($path, $content) === strlen($content),
				$relative . ': the fixture is written whole (' . strlen($content) . ' bytes)');
		}
	}

	/** Build from $input with the cwd at $cwd, and restore the cwd after. */
	private function buildAt($cwd, $input, $what)
	{
		$was = getcwd();
		$this->assertTrue(chdir($cwd), $what . ': the working directory is the fixture root');
		try {
			$torrent = new Torrent($input, array(), self::PIECE_KB);
			$bytes = (string)$torrent;
		} finally {
			chdir($was);
		}
		$this->assertTrue($torrent->errors() === false, $what . ': the torrent is built without errors'
			. ($torrent->errors() === false ? '' : ': ' . $this->firstError($torrent)));
		return $bytes;
	}

	private function firstError($torrent)
	{
		$errors = $torrent->errors();
		return is_array($errors) && $errors ? $errors[0]->getMessage() : json_encode($errors);
	}

	/**
	 * The metainfo of the directory $name under the fixture root, built once
	 * from the relative path and once from the absolute one, asserted to be
	 * the same bytes, and checked against $layout.
	 */
	private function assertFolderIsDescribed($name, $layout)
	{
		$what = 'a directory named ' . json_encode($name);
		$parent = $this->caseDirectory();
		$directory = $parent . '/' . $name;
		$this->assertTrue(@mkdir($directory, 0700, true), $what . ': it is created');
		$this->write($directory, $layout);

		$relative = $this->buildAt($parent, $name, $what . ' (relative)');
		$absolute = $this->buildAt($parent, $directory, $what . ' (absolute)');
		$this->assertTrue($relative === $absolute,
			$what . ': the relative and absolute base directories describe the same torrent');

		$info = $this->metainfo($relative, $what);
		$this->assertTrue($info->get('name') === $name,
			$what . ': the torrent is named after it, not after something inside it: '
			. json_encode($info->get('name')));
		$this->assertTrue(!$info->has('length') && $info->has('files'),
			$what . ': it is multi-file metainfo');
		$this->assertFilesAreDescribed($what, $info, $directory, $layout);
	}

	/**
	 * Every file of $layout appears once, with its path relative to the base
	 * directory and its complete length, and the pieces are the SHA-1s of
	 * those files' bytes concatenated in the order the file list gives.
	 */
	private function assertFilesAreDescribed($what, $info, $directory, $layout)
	{
		$files = $info->get('files');
		$this->assertTrue(is_array($files) && (count($files) === count($layout)),
			$what . ': every file is listed, and only once: ' . (is_array($files) ? count($files) : 'not a list')
			. ' of ' . count($layout));
		$described = array();
		$bytes = '';
		foreach ((array)$files as $index => $file) {
			$this->assertTrue($file instanceof BencodeDictionary, $what . ': entry ' . $index . ' is a dictionary');
			$path = $file->get('path');
			$this->assertTrue(is_array($path) && $path && (array_keys($path) === range(0, count($path) - 1)),
				$what . ': entry ' . $index . ' has a path that is a non-empty list');
			$joined = implode('/', (array)$path);
			$this->assertTrue(!isset($described[$joined]), $what . ': ' . $joined . ' is listed once');
			$described[$joined] = $file->get('length');
			// Read through the path the torrent gives, from the base directory:
			// that is what a client does with it.
			$content = @file_get_contents($directory . '/' . $joined);
			$this->assertTrue($content !== false, $what . ': ' . $joined . ' exists where the torrent says it does');
			$bytes .= (string)$content;
		}
		foreach ($layout as $relative => $content) {
			$this->assertTrue(isset($described[$relative]) || array_key_exists($relative, $described),
				$what . ': ' . $relative . ' is in the file list (' . implode(', ', array_keys($described)) . ')');
			$this->assertTrue(isset($described[$relative]) && ($described[$relative] === strlen($content)),
				$what . ': ' . $relative . ' is listed with its complete length: '
				. json_encode(isset($described[$relative]) ? $described[$relative] : null)
				. ' of ' . strlen($content));
		}
		$this->assertPiecesMatch($what, $info, $bytes);
	}

	private function assertPiecesMatch($what, $info, $bytes)
	{
		$pieceLength = $info->get('piece length');
		$this->assertTrue($pieceLength === (self::PIECE_KB * 1024),
			$what . ': the piece length is the one asked for: ' . json_encode($pieceLength));
		$pieces = $info->get('pieces');
		$expected = (int)ceil(strlen($bytes) / $pieceLength);
		$this->assertTrue(is_string($pieces) && ((strlen($pieces) % 20) === 0),
			$what . ': pieces is a whole number of SHA-1 hashes');
		$this->assertTrue(strlen($pieces) === ($expected * 20),
			$what . ': pieces holds ' . (strlen((string)$pieces) / 20) . ' hashes, and ' . strlen($bytes)
			. ' bytes in pieces of ' . $pieceLength . ' need ' . $expected);
		for ($piece = 0; $piece < $expected; $piece++) {
			$this->assertTrue(substr($pieces, $piece * 20, 20)
					=== sha1(substr($bytes, $piece * $pieceLength, $pieceLength), true),
				$what . ': hash ' . $piece . ' is the SHA-1 of piece ' . $piece);
		}
	}

	/**
	 * The base directory's path is "0". Everything the torrent says about the
	 * content is derived from that path, so a builder that reads it as "no
	 * base directory was given" falls back to the deepest file's directory:
	 * the name becomes a subdirectory's name, the files outside that
	 * subdirectory fail the same-folder check and are dropped, and the paths
	 * that survive are relative to the wrong place.
	 */
	public function testADirectoryNamedZeroIsTheTorrentName()
	{
		$this->assertFolderIsDescribed('0', array(
			'a.txt' => str_repeat('a', 100),
			'sub/b.txt' => str_repeat('b', 100),
		));
	}

	/** The same shape under an ordinary name, as the control. */
	public function testADirectoryWithAnOrdinaryNameIsTheTorrentName()
	{
		$this->assertFolderIsDescribed('z', array(
			'a.txt' => str_repeat('a', 100),
			'sub/b.txt' => str_repeat('b', 100),
		));
	}

	/**
	 * Names next to "0" that PHP does not read as false, so a fix that keys
	 * on the string rather than on whether a path was given stays honest.
	 */
	public function testNamesAroundZeroAreTheTorrentName()
	{
		foreach (array('00', '0.0', '-0', 'false', '0x0') as $name) {
			$this->assertFolderIsDescribed($name, array(
				'a.txt' => str_repeat('a', 100),
				'sub/b.txt' => str_repeat('b', 100),
			));
		}
	}

	/**
	 * A directory named 0, holding a file named 0 whose only byte is 0, built
	 * across a piece boundary: the three places the string can appear, in one
	 * torrent, with nothing but "0" to lose.
	 */
	public function testZeroAsDirectoryFileNameAndContentTogether()
	{
		$piece = self::PIECE_KB * 1024;
		$this->assertFolderIsDescribed('0', array(
			'0' => '0',
			'sub/0' => str_repeat('0', $piece - 1),
			'sub/deeper/0.bin' => '0',
		));
	}

	/**
	 * A file list carries no base directory at all, so the name is the
	 * shared directory and a file named 0 must still be described. This is
	 * the branch the directory case is mistaken for, so it is asserted to
	 * work rather than assumed.
	 */
	public function testAFileListWithAFileNamedZero()
	{
		$parent = $this->caseDirectory();
		$directory = $parent . '/list';
		$this->assertTrue(@mkdir($directory, 0700, true), 'the file list directory is created');
		$layout = array('0' => '0', 'a.txt' => str_repeat('a', 2048), 'b.txt' => '0');
		$this->write($directory, $layout);
		$paths = array();
		foreach (array_keys($layout) as $relative) {
			$paths[] = $directory . '/' . $relative;
		}
		$what = 'a file list including a file named "0"';
		$bytes = $this->buildAt($parent, array_reverse($paths), $what);
		$info = $this->metainfo($bytes, $what);
		$this->assertTrue($info->get('name') === basename($directory),
			$what . ': it names the shared directory: ' . json_encode($info->get('name')));
		$this->assertFilesAreDescribed($what, $info, $directory, $layout);
	}

	/**
	 * A single file named 0 whose content is 0 goes through Torrent::file(),
	 * the third builder, where the name is a basename and the content is one
	 * short piece.
	 */
	public function testASingleFileNamedZeroWithZeroContent()
	{
		$parent = $this->caseDirectory();
		$this->assertTrue(file_put_contents($parent . '/0', '0') === 1, 'the one byte file is written');
		$what = 'a single file named "0" of one zero byte';
		$bytes = $this->buildAt($parent, '0', $what);
		$info = $this->metainfo($bytes, $what);
		$this->assertTrue($info->get('name') === '0', $what . ': it is named "0": ' . json_encode($info->get('name')));
		$this->assertTrue($info->get('length') === 1, $what . ': its length is one byte: ' . json_encode($info->get('length')));
		$this->assertTrue(!$info->has('files'), $what . ': it is single-file metainfo');
		$this->assertPiecesMatch($what, $info, '0');
	}

	/**
	 * A single file's name must not depend on the locale: in a non-UTF-8
	 * one, such as C, PHP 7.4's basename() drops the non-ASCII bytes of a
	 * name, and Сериал.mkv became .mkv.
	 */
	public function testASingleFileWithANonAsciiNameIsTheTorrentName()
	{
		$parent = $this->caseDirectory();
		$name = 'Сериал.mkv';
		$this->assertTrue(file_put_contents($parent . '/' . $name, 'video') === 5, 'the file is written');
		$what = 'a single file named ' . $name;
		$locale = setlocale(LC_CTYPE, '0');
		setlocale(LC_CTYPE, 'C');
		try {
			$bytes = $this->buildAt($parent, $parent . '/' . $name, $what);
		} finally {
			setlocale(LC_CTYPE, $locale);
		}
		$info = $this->metainfo($bytes, $what);
		$this->assertTrue($info->get('name') === $name, $what . ': it is named after the file: ' . json_encode($info->get('name')));
		$this->assertTrue($info->get('length') === 5, $what . ': its length is the file\'s: ' . json_encode($info->get('length')));
	}

	public function testTheBencodeReaderIsStrict()
	{
		$this->assertBencodeReaderIsStrict();
	}
}
