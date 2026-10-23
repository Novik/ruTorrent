<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/Torrent.php');

/**
 * A torrent's info dictionary has to carry one SHA-1 per piece:
 * ceil(total length / piece length) of them, the last one over a short tail. The
 * length(s) and the pieces key are written from the same files in the same
 * call, so the two disagreeing is not a cosmetic defect -- a client that is
 * given more data than the hashes account for has no hash to check the last
 * piece against, and rejects the metainfo.
 *
 * The tail is where the two can come apart: it is the only read of the file
 * that returns fewer bytes than a piece, and a one-byte tail has exactly one
 * value that PHP does not distinguish from end of input.
 *
 * Torrent::file() is the torrent builder of plugins/create with the shipped
 * configuration ($useExternal = false selects inner.sh, which runs
 * createtorrent.php, which constructs this class from a path).
 *
 * The torrents built here are parsed back by a bencode reader written in this
 * file, so the class under test is not asked to confirm its own output, and
 * each hash is compared against one taken over the file's bytes read whole.
 */
class TorrentPieceTailTest extends TestCase
{
	/**
	 * In kilobytes, as the constructor takes it: 1 is the smallest piece
	 * length it can express, which keeps the fixtures a few kilobytes
	 * instead of the megabytes a real piece length would need.
	 */
	const PIECE_KB = 1;

	private $base;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-piece-tail-' . getmypid();
		@mkdir($this->base, 0700, true);
	}

	public function tearDown()
	{
		foreach (glob($this->base . '/*') as $file) {
			@unlink($file);
		}
		@rmdir($this->base);
	}

	private function fixture($name, $content)
	{
		$path = $this->base . '/' . $name;
		$written = file_put_contents($path, $content);
		$this->assertTrue($written === strlen($content), $name . ': the fixture is written whole');
		return $path;
	}

	/**
	 * Read one bencoded value starting at $at, moving $at past it. Strict on
	 * purpose: anything it accepts has to be metainfo a client would accept,
	 * so a malformed number, an unterminated container or a string shorter
	 * than its own length is an exception rather than a best effort.
	 */
	private function bdecode($data, &$at)
	{
		if (!isset($data[$at])) {
			throw new Exception('a value was expected at offset ' . $at);
		}
		$type = $data[$at];
		if ($type === 'i') {
			$end = strpos($data, 'e', $at);
			if ($end === false) {
				throw new Exception('an integer without its terminator at offset ' . $at);
			}
			$digits = substr($data, $at + 1, $end - $at - 1);
			if (preg_match('/^(0|-?[1-9][0-9]*)$/D', $digits) !== 1) {
				throw new Exception('not a bencoded integer: ' . json_encode($digits));
			}
			$at = $end + 1;
			return (int)$digits;
		}
		if (($type === 'l') || ($type === 'd')) {
			$at++;
			$value = array();
			while (isset($data[$at]) && ($data[$at] !== 'e')) {
				if ($type === 'l') {
					$value[] = $this->bdecode($data, $at);
					continue;
				}
				$key = $this->bdecode($data, $at);
				if (!is_string($key)) {
					throw new Exception('a dictionary key that is not a string at offset ' . $at);
				}
				$value[$key] = $this->bdecode($data, $at);
			}
			if (!isset($data[$at])) {
				throw new Exception('a container without its terminator');
			}
			$at++;
			return $value;
		}
		$colon = strpos($data, ':', $at);
		if ($colon === false) {
			throw new Exception('a string without its length at offset ' . $at);
		}
		$length = substr($data, $at, $colon - $at);
		if (preg_match('/^(0|[1-9][0-9]*)$/D', $length) !== 1) {
			throw new Exception('not a bencoded string length: ' . json_encode($length));
		}
		if (($colon + 1 + (int)$length) > strlen($data)) {
			throw new Exception('a string shorter than the ' . $length . ' bytes it declares');
		}
		$at = $colon + 1 + (int)$length;
		return substr($data, $colon + 1, (int)$length);
	}

	/** The metainfo in $bytes, which has to be all of $bytes. */
	private function metainfo($bytes, $what)
	{
		$at = 0;
		$parsed = $this->bdecode($bytes, $at);
		$this->assertTrue($at === strlen($bytes),
			$what . ': the metainfo parses with nothing left over (' . $at . ' of ' . strlen($bytes) . ')');
		$this->assertTrue(is_array($parsed) && isset($parsed['info']) && is_array($parsed['info']),
			$what . ': it is a dictionary with an info dictionary in it');
		return $parsed['info'];
	}

	/**
	 * Build a torrent from $content and check the hashes against the file.
	 * The name and the length come out of the parsed metainfo, so the file
	 * the hashes are compared with is the one the torrent describes.
	 */
	private function assertFileIsHashedWhole($what, $content)
	{
		$path = $this->fixture(str_replace(' ', '-', $what) . '.data', $content);
		$torrent = new Torrent($path, array(), self::PIECE_KB);
		$this->assertTrue($torrent->errors() === false,
			$what . ': the torrent is built without errors');
		$info = $this->metainfo((string)$torrent, $what);

		$this->assertTrue(isset($info['length'], $info['name'], $info['piece length'], $info['pieces']),
			$what . ': the info dictionary carries length, name, piece length and pieces');
		$this->assertTrue($info['name'] === basename($path),
			$what . ': it names the file: ' . json_encode($info['name']));
		$this->assertTrue($info['length'] === strlen($content),
			$what . ': its length is the file\'s: ' . json_encode($info['length']));
		// file_get_contents, not a read loop: the bytes each hash is checked
		// against must not be assembled the way the code under test does it.
		$bytes = file_get_contents($path);
		$this->assertTrue($bytes === $content, $what . ': the fixture reads back as it was written');
		$this->assertPiecesMatch($what, $info, $bytes);
	}

	private function assertPiecesMatch($what, $info, $bytes)
	{
		$this->assertTrue($info['piece length'] === (self::PIECE_KB * 1024),
			$what . ': its piece length is the one asked for: ' . json_encode($info['piece length']));
		$pieceLength = $info['piece length'];
		$expected = (int)ceil(strlen($bytes) / $pieceLength);
		$this->assertTrue((strlen($info['pieces']) % 20) === 0,
			$what . ': pieces is a whole number of SHA-1 hashes: ' . strlen($info['pieces']) . ' bytes');
		$this->assertTrue(strlen($info['pieces']) === ($expected * 20),
			$what . ': pieces holds ' . (strlen($info['pieces']) / 20) . ' hashes, and '
			. strlen($bytes) . ' bytes in pieces of ' . $pieceLength . ' need ' . $expected);
		for ($piece = 0; $piece < $expected; $piece++) {
			$this->assertTrue(substr($info['pieces'], $piece * 20, 20)
					=== sha1(substr($bytes, $piece * $pieceLength, $pieceLength), true),
				$what . ': hash ' . $piece . ' is the SHA-1 of piece ' . $piece);
		}
	}

	/** File-list and folder inputs share the same concatenated piece stream. */
	private function assertFilesAreHashedWhole($what, $contents)
	{
		$directory = $this->base . '/files';
		mkdir($directory, 0700);
		$paths = array();
		try {
			foreach ($contents as $index => $content) {
				$path = $directory . '/part-' . sprintf('%03d', $index) . '.data';
				$this->assertTrue(file_put_contents($path, $content) === strlen($content),
					$what . ': file ' . $index . ' is written whole');
				$paths[] = $path;
			}
			foreach (array('file list' => array_reverse($paths), 'folder' => $directory) as $mode => $input) {
				$label = $what . ' (' . $mode . ')';
				$torrent = new Torrent($input, array(), self::PIECE_KB);
				$this->assertTrue($torrent->errors() === false, $label . ': the torrent is built without errors');
				$info = $this->metainfo((string)$torrent, $label);
				$this->assertTrue(isset($info['files'], $info['name'], $info['piece length'], $info['pieces'])
					&& !isset($info['length']), $label . ': it is multi-file metainfo');
				$this->assertTrue($info['name'] === basename($directory), $label . ': it names the shared directory');
				$this->assertTrue(count($info['files']) === count($contents), $label . ': every file is listed');
				$bytes = '';
				foreach ($info['files'] as $index => $file) {
					$this->assertTrue($file['path'] === array(basename($paths[$index])),
						$label . ': file ' . $index . ' has the expected relative path and order');
					$this->assertTrue($file['length'] === strlen($contents[$index]),
						$label . ': file ' . $index . ' has its complete length');
					$content = file_get_contents($directory . '/' . $file['path'][0]);
					$this->assertTrue($content === $contents[$index], $label . ': file ' . $index . ' reads back whole');
					$bytes .= $content;
				}
				$this->assertPiecesMatch($label, $info, $bytes);
			}
		} finally {
			foreach ($paths as $path) unlink($path);
			rmdir($directory);
		}
	}

	/**
	 * The tail is one byte and that byte is '0'. fread() returns it as the
	 * string "0", which is falsy, and a loop that reads while the read is
	 * truthy ends on it as if the file had ended.
	 */
	public function testATailOfOneZeroByteIsHashed()
	{
		$this->assertFileIsHashedWhole('a zero byte after two whole pieces',
			str_repeat('x', 2 * self::PIECE_KB * 1024) . '0');
		$this->assertFileIsHashedWhole('a zero byte after one whole piece',
			str_repeat('x', self::PIECE_KB * 1024) . '0');
	}

	/** The whole file is that byte, so the alternative is no hashes at all. */
	public function testAFileOfOneZeroByteIsHashed()
	{
		$this->assertFileIsHashedWhole('a file of one zero byte', '0');
	}

	/** Tails that are not that byte, and a file with no tail at all. */
	public function testOrdinaryTailsAreHashed()
	{
		$piece = self::PIECE_KB * 1024;
		$this->assertFileIsHashedWhole('a one byte tail that is not zero', str_repeat('x', $piece) . '1');
		$this->assertFileIsHashedWhole('a tail of several bytes', str_repeat('x', $piece) . '0123');
		$this->assertFileIsHashedWhole('a file of one piece exactly', str_repeat('x', $piece));
		$this->assertFileIsHashedWhole('a file of three pieces exactly', str_repeat('x', 3 * $piece));
		$this->assertFileIsHashedWhole('a file of one byte that is not zero', '1');
	}

	public function testMultipleFileInputsKeepAZeroTail()
	{
		$piece = self::PIECE_KB * 1024;
		foreach (array(
			'one file of one zero byte' => array('0'),
			'a zero after a complete file' => array(str_repeat('x', $piece), '0'),
			'a piece crosses files before a zero tail' => array(str_repeat('x', $piece - 1), 'x0'),
			'two pieces cross three files before a zero tail' => array(str_repeat('x', 2 * $piece - 1), 'x', '0'),
			'a zero between empty files' => array('', '0', ''),
		) as $what => $contents) {
			$this->assertFilesAreHashedWhole($what, $contents);
		}
	}

	public function testMultipleFileInputsKeepOrdinaryAndEmptyTails()
	{
		$piece = self::PIECE_KB * 1024;
		foreach (array(
			'one file of one nonzero byte' => array('1'),
			'a nonzero after a complete file' => array(str_repeat('x', $piece), '1'),
			'an exact piece crosses files' => array(str_repeat('x', $piece - 1), 'x'),
			'a longer tail follows a file boundary' => array(str_repeat('x', $piece - 2), 'x0123'),
			'an empty file follows an exact piece' => array(str_repeat('x', $piece), ''),
			'only empty files' => array('', ''),
			'a complete piece of zero characters' => array(str_repeat('0', $piece)),
		) as $what => $contents) {
			$this->assertFilesAreHashedWhole($what, $contents);
		}
	}

	/** The bencode reader above refuses what it should, so its acceptance means something. */
	public function testTheBencodeReaderIsStrict()
	{
		foreach (array(
			'd3:fooe' => 'a dictionary with a key and no value',
			'i-0e' => 'a negative zero',
			'i01e' => 'a leading zero',
			'ie' => 'an empty integer',
			'3:ab' => 'a string shorter than its length',
			'l1:ae1:b' => 'bytes after the end of the value',
			'd1:a1:b' => 'a dictionary without its terminator',
			'' => 'no value at all',
		) as $bytes => $what) {
			$refused = false;
			try {
				$at = 0;
				$this->bdecode($bytes, $at);
				// Not an exception, but not the whole input either.
				$refused = ($at !== strlen($bytes));
			} catch (Exception $error) {
				$refused = true;
			}
			$this->assertTrue($refused, 'the bencode reader refuses ' . $what);
		}
	}
}
