<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/BencodeReaderFixture.php');
require_once(__DIR__ . '/../../php/Torrent.php');

/**
 * Every torrent ruTorrent loads is written back out by this class before
 * rtorrent sees it: rTorrent::sendTorrent() sends
 * base64_encode($torrent->__toString()), and Torrent::hash_info() takes the
 * info hash over Torrent::encode($this->info). The torrent that gets loaded
 * is therefore the re-encoded one, and whether that is still the torrent in
 * the file is a property to pin rather than assume.
 *
 * One invariant covers it: for any input, either this class writes back the
 * bytes it read and reports the file's own info hash, or errors() says it
 * cannot. Every caller here is written as if(!$torrent->errors()), so the
 * second branch means refused. The outcome that must not exist is a third:
 * a different torrent, accepted.
 *
 * A bencoded dictionary is where the two come apart. PHP coerces a
 * numeric-string array key to an integer, so the dictionary {"0": x} and the
 * list [x] decode into the same array, as do "de" and "le"; the encoder asks
 * is_list(), which can only answer from the keys. Both shapes are legal
 * bencode -- BEP 3 says a dictionary key is a string and says nothing about
 * its characters, and a BEP 52 file tree is keyed by directory and file
 * names, which may be digits.
 *
 * The empty dictionary is the one documented exception to the invariant, and
 * testAnEmptyDictionaryBecomesAnEmptyListWithoutAReport says why it has to
 * be: there is nothing in an empty array to decide it with, and both ways of
 * deciding cost more than they fix.
 */
class TorrentDictionaryRoundTripTest extends TestCase
{
	use BencodeReaderFixture;

	private $base;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-dict-roundtrip-' . getmypid();
		@mkdir($this->base, 0700, true);
	}

	public function tearDown()
	{
		foreach (glob($this->base . '/*') as $path) {
			@unlink($path);
		}
		@rmdir($this->base);
	}

	/** An info dictionary of one short file, with $extra keys added to it. */
	private function info($extra = array())
	{
		return $this->dict(array_merge(array(
			'length' => 1,
			'name' => 'x',
			'piece length' => 16384,
			'pieces' => sha1('x', true),
		), $extra));
	}

	/** Ordinary metainfo around $info, with $extra top level keys added. */
	private function metainfoAround($info, $extra = array())
	{
		return $this->dict(array_merge(array(
			'announce' => 'http://tracker.invalid/announce',
			'info' => $info,
		), $extra));
	}

	/** The raw bencode of the top level info value, located independently. */
	private function rawInfo($bytes, $what)
	{
		$this->assertTrue(isset($bytes[0]) && ($bytes[0] === 'd'), $what . ': the metainfo is a dictionary');
		$at = 1;
		while (isset($bytes[$at]) && ($bytes[$at] !== 'e')) {
			$key = $this->bdecode($bytes, $at);
			$from = $at;
			$this->bdecode($bytes, $at);
			if ($key === 'info') {
				return substr($bytes, $from, $at - $from);
			}
		}
		$this->assertTrue(false, $what . ': the metainfo has an info key');
		return '';
	}

	/**
	 * The invariant, on $bytes. $expected names the branch this input is
	 * supposed to take, so a change that starts refusing everything fails
	 * here as loudly as one that starts accepting everything.
	 */
	private function assertWrittenBackOrRefused($what, $bytes, $expected)
	{
		$torrent = new Torrent($bytes);
		$written = (string)$torrent;
		$errors = $torrent->errors();
		$identical = ($written === $bytes);

		$this->assertTrue($identical || ($errors !== false),
			$what . ': it is written back unchanged or reported, never silently changed'
			. ($identical ? '' : ' (read ' . $this->printable($bytes) . ', wrote ' . $this->printable($written) . ')'));

		if ($expected === 'written back') {
			$this->assertTrue($errors === false, $what . ': nothing is reported'
				. ($errors === false ? '' : ': ' . $this->messages($errors)));
			$this->assertTrue($identical, $what . ': the bytes written back are the bytes read');
			$raw = $this->rawInfo($bytes, $what);
			$this->assertTrue($torrent->hash_info() === strtoupper(sha1($raw)),
				$what . ': its info hash is the SHA-1 of the info dictionary as it was read');
			// The reader is strict, so this says the output is metainfo too.
			$this->metainfo($written, $what);
			return;
		}

		$this->assertTrue($errors !== false, $what . ': it is reported');
		$this->assertTrue(is_array($errors) && count($errors), $what . ': the report is a list of errors');
		$this->assertTrue(strpos($this->messages($errors), 'dictionary') !== false,
			$what . ': the report names a dictionary: ' . $this->messages($errors));
		// What every caller in this repository does with that report.
		$this->assertTrue(!(!$torrent->errors()), $what . ': if(!$torrent->errors()) refuses it');
	}

	/** Metainfo carries a SHA-1 per piece, so json_encode() would refuse it. */
	private function printable($bytes)
	{
		return addcslashes($bytes, "\0..\37\177..\377\\");
	}

	private function messages($errors)
	{
		$out = array();
		foreach ((array)$errors as $error) {
			$out[] = ($error instanceof Exception) ? $error->getMessage() : json_encode($error);
		}
		return implode('; ', $out);
	}

	/**
	 * Keys that are decimal numbers and nothing else. By the time the torrent
	 * is written back the array holds integers, and nothing in it says the
	 * keys were ever there.
	 */
	public function testNumericDictionaryKeysAreReported()
	{
		foreach (array(
			'a dictionary keyed 0 and 1' => array('0' => 7, '1' => 8),
			'a dictionary keyed 0' => array('0' => 7),
			'a dictionary keyed by a negative number' => array('-1' => 1),
			'a dictionary keyed by a longer number' => array('1234' => 1),
		) as $what => $pairs) {
			$this->assertWrittenBackOrRefused($what,
				$this->bencode($this->metainfoAround($this->info(), array('z' => $this->dict($pairs)))),
				'refused');
		}
	}

	/** Inside info, where losing the keys moves the info hash. */
	public function testNumericKeysInsideInfoAreReported()
	{
		$tree = $this->dict(array('2024' => $this->dict(array('' => $this->dict(array('length' => 1))))));
		$this->assertWrittenBackOrRefused('a BEP 52 file tree with a directory named 2024',
			$this->bencode($this->metainfoAround($this->info(array('file tree' => $tree)))),
			'refused');
	}

	/**
	 * The one shape left outside the invariant above, on purpose.
	 *
	 * "de" and "le" decode into the same empty array, and unlike a dictionary
	 * that has keys there is nothing in it to prefer one reading over the
	 * other. Neither answer is available here. Writing "de" for an empty
	 * array would turn every genuinely empty list into a dictionary, which is
	 * the same corruption pointing the other way. Reporting it is worse:
	 * errors() is what every caller tests before loading a torrent, so a
	 * report would refuse a torrent that loads today over a container with
	 * nothing in it.
	 *
	 * So an empty dictionary is written back as an empty list and nothing is
	 * said about it. Pinned here rather than left to be rediscovered, with
	 * the consequence named: inside info it moves the info hash. The day the
	 * decoded value can carry which of the two it was, this is the test that
	 * should change.
	 */
	public function testAnEmptyDictionaryBecomesAnEmptyListWithoutAReport()
	{
		$beside = $this->bencode($this->metainfoAround($this->info(), array('z' => $this->dict(array()))));
		$this->assertEmptyDictionaryBecomesAList('an empty dictionary beside info', $beside, '1:zde', '1:zle');

		$inside = $this->bencode($this->metainfoAround($this->info(array('file tree' => $this->dict(array())))));
		$torrent = $this->assertEmptyDictionaryBecomesAList('an empty dictionary inside info',
			$inside, '9:file treede', '9:file treele');
		$raw = $this->rawInfo($inside, 'an empty dictionary inside info');
		$this->assertTrue($torrent->hash_info() !== strtoupper(sha1($raw)),
			'an empty dictionary inside info: the info hash is no longer the file\'s, and this is the cost'
			. ' of the ambiguity rather than something the class can decide');
	}

	/** $bytes is read with $from in it and written back with $to in its place. */
	private function assertEmptyDictionaryBecomesAList($what, $bytes, $from, $to)
	{
		$this->assertTrue(strpos($bytes, $from) !== false,
			$what . ': the fixture carries ' . $from);
		$torrent = new Torrent($bytes);
		$written = (string)$torrent;
		$this->assertTrue($torrent->errors() === false, $what . ': nothing is reported'
			. ($torrent->errors() === false ? '' : ': ' . $this->messages($torrent->errors())));
		$this->assertTrue($written === str_replace($from, $to, $bytes),
			$what . ': it is written back as ' . $to . ', and nothing else about it changes'
			. ' (wrote ' . $this->printable($written) . ')');
		return $torrent;
	}

	/**
	 * One key PHP leaves as a string is enough to keep the array a
	 * dictionary, so none of these may be refused: this is what holds the
	 * report to the shapes that actually lose something.
	 */
	public function testDictionariesThatSurviveAreWrittenBack()
	{
		foreach (array(
			'a numeric key beside an ordinary one' => $this->dict(array('0' => 7, 'announce' => 'abc')),
			'keys that look numeric but are not' =>
				$this->dict(array('00' => 1, '0.0' => 2, '-0' => 3, '08' => 4, '1e2' => 5, ' 1' => 6, '+1' => 7)),
			'a number too long to be an integer key' => $this->dict(array('99999999999999999999' => 1)),
			'an empty key' => $this->dict(array('' => 1)),
			'ordinary keys' => $this->dict(array('a' => 1, 'b' => 2)),
			'an empty list' => array(),
			'a list of numbers' => array(7, 8),
			'a list of dictionaries' => array($this->dict(array('a' => 1)), $this->dict(array('b' => 2))),
		) as $what => $value) {
			$this->assertWrittenBackOrRefused($what,
				$this->bencode($this->metainfoAround($this->info(), array('z' => $value))),
				'written back');
		}
	}

	/** Metainfo with nothing unusual in it, including the keys rtorrent adds. */
	public function testOrdinaryMetainfoIsWrittenBack()
	{
		$this->assertWrittenBackOrRefused('single file metainfo',
			$this->bencode($this->metainfoAround($this->info())), 'written back');

		$this->assertWrittenBackOrRefused('metainfo with a comment, an author and resume data',
			$this->bencode($this->metainfoAround($this->info(), array(
				'comment' => 'hello',
				'created by' => 'mktorrent 1.1',
				'creation date' => 1700000000,
				'libtorrent_resume' => $this->dict(array(
					'files' => array($this->dict(array('completed' => 1, 'mtime' => 1700000000))),
					'bitfield' => 1,
				)),
				'rtorrent' => $this->dict(array('directory' => '/tmp', 'tied_to_file' => '')),
			))), 'written back');

		$files = array(
			$this->dict(array('length' => 1, 'path' => array('a'))),
			$this->dict(array('length' => 2, 'path' => array('sub', 'b'))),
		);
		$this->assertWrittenBackOrRefused('multi file metainfo',
			$this->bencode($this->metainfoAround($this->dict(array(
				'files' => $files,
				'name' => 'd',
				'piece length' => 16384,
				'pieces' => sha1('x', true),
			)))), 'written back');
	}

	/**
	 * A torrent this class built, read back by a second instance. It is the
	 * shape plugins/create produces, and the one fixture here not written as
	 * a literal, so the invariant is checked against output the class chose.
	 */
	public function testATorrentThisClassBuiltIsWrittenBack()
	{
		$path = $this->base . '/built.data';
		$this->assertTrue(file_put_contents($path, str_repeat('b', 3000)) === 3000, 'the fixture is written');
		$built = new Torrent($path, array('announce' => 'http://tracker.invalid/announce'), 1);
		$this->assertTrue($built->errors() === false, 'a torrent is built from it without errors');
		$this->assertWrittenBackOrRefused('a torrent this class built', (string)$built, 'written back');
	}

	public function testTheBencodeReaderIsStrict()
	{
		$this->assertBencodeReaderIsStrict();
	}
}
