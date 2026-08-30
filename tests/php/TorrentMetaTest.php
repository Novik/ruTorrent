<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/Torrent.php');

/**
 * A torrent's top-level dictionary carries keys that are not PHP identifiers
 * -- 'created by', 'creation date', 'announce-list', 'url-list' -- which no
 * class can hold in a declared property; the rest are declared properties, and
 * callers outside the class reach for them as such: rTorrent::fastResume()
 * builds $torrent->libtorrent_resume from a key that is not there yet, and
 * sendTorrent() unsets it again.
 *
 * These tests pin all of that against the bytes of a fixture torrent built
 * here, key by key, so a change of storage cannot quietly change which keys
 * are written, in which order, or with which values.
 */
class TorrentMetaTest extends TestCase
{
	private $pieces;

	public function setUp()
	{
		$this->pieces = str_repeat("\x01", 20);
	}

	/** Bencode a string. Deliberately trivial: the fixture must not be built
	 * by the class under test. */
	private function bstr($string)
	{
		return strlen($string) . ':' . $string;
	}

	/** The info dictionary of the fixture, keys in ksort(SORT_STRING) order. */
	private function infoDictionary()
	{
		return 'd'
			. $this->bstr('length') . 'i1024e'
			. $this->bstr('name') . $this->bstr('test.data')
			. $this->bstr('piece length') . 'i16384e'
			. $this->bstr('pieces') . $this->bstr($this->pieces)
			. $this->bstr('private') . 'i1e'
			. 'e';
	}

	/** The libtorrent_resume dictionary of the fixture. */
	private function resumeDictionary()
	{
		return 'd'
			. $this->bstr('bitfield') . 'i1e'
			. $this->bstr('files')
				. 'l'
					. 'd' . $this->bstr('mtime') . 'i1234567891e'
						. $this->bstr('priority') . 'i2e' . 'e'
				. 'e'
			. 'e';
	}

	/**
	 * A torrent carrying every key the class has an accessor for, the two
	 * rtorrent adds to a torrent it has loaded, and one it only ever passes
	 * through. Keys are in ksort(SORT_STRING) order, which is the order
	 * Torrent::encode_dictionary() emits, so the file is its own expected
	 * output.
	 */
	private function fixture()
	{
		return 'd'
			. $this->bstr('announce') . $this->bstr('http://example.test/announce')
			. $this->bstr('announce-list')
				. 'l'
					. 'l' . $this->bstr('http://example.test/announce') . 'e'
					. 'l' . $this->bstr('udp://backup.test/announce') . 'e'
				. 'e'
			. $this->bstr('comment') . $this->bstr('a comment')
			. $this->bstr('created by') . $this->bstr('uTorrent/3.5.5')
			. $this->bstr('creation date') . 'i1234567890e'
			. $this->bstr('encoding') . $this->bstr('UTF-8')
			. $this->bstr('httpseeds') . 'l' . $this->bstr('http://seed.example.test/f.dat') . 'e'
			. $this->bstr('info') . $this->infoDictionary()
			. $this->bstr('libtorrent_resume') . $this->resumeDictionary()
			. $this->bstr('rtorrent') . 'd' . $this->bstr('directory') . $this->bstr('/downloads') . 'e'
			. $this->bstr('url-list') . 'l' . $this->bstr('http://web.example.test/f.dat') . 'e'
			. 'e';
	}

	/** Names of properties that were not declared by the class -- i.e. the
	 * dynamic properties PHP 8.2 deprecated. */
	private function dynamicProperties($object)
	{
		$dynamic = array();
		$reflection = new ReflectionObject($object);
		foreach ($reflection->getProperties() as $property) {
			if (!$property->isDefault()) {
				$dynamic[] = $property->getName();
			}
		}
		sort($dynamic);
		return $dynamic;
	}

	/**
	 * Parse and re-emit: every key, in order, byte for byte. 'created by' and
	 * 'creation date' are only touched by touch(), so an untouched torrent
	 * must come back with the creator the file had.
	 */
	public function testRoundTripsTheWholeDictionaryByteForByte()
	{
		$torrent = new Torrent($this->fixture());
		$this->assertTrue($torrent->errors() === false, 'The fixture parses without errors');
		$this->assertTrue((string)$torrent === $this->fixture(),
			'A parsed torrent re-encodes to the bytes it was read from');
	}

	public function testGettersReadTheKeysThatAreNotIdentifiers()
	{
		$torrent = new Torrent($this->fixture());
		$this->assertTrue($torrent->announce() === 'http://example.test/announce',
			'announce() reads the announce key');
		$this->assertTrue($torrent->announce_list() === array(
				array('http://example.test/announce'),
				array('udp://backup.test/announce')),
			'announce_list() reads the announce-list key');
		$this->assertTrue($torrent->comment() === 'a comment', 'comment() reads the comment key');
		$this->assertTrue($torrent->url_list() === array('http://web.example.test/f.dat'),
			'url_list() reads the url-list key');
		$this->assertTrue($torrent->httpseeds() === array('http://seed.example.test/f.dat'),
			'httpseeds() reads the httpseeds key');
		$this->assertTrue($torrent->name() === 'test.data', 'name() reads info/name');
		$this->assertTrue($torrent->piece_length() == 16384, 'piece_length() reads info/piece length');
		$this->assertTrue($torrent->is_private() === true, 'is_private() reads info/private');
		$this->assertTrue($torrent->hash_info() === strtoupper(sha1($this->infoDictionary())),
			'hash_info() is the SHA1 of the info dictionary as it was read');
	}

	public function testClearRemovesTheKeyFromWhatIsWritten()
	{
		$torrent = new Torrent($this->fixture());
		$torrent->clear_announce();
		$torrent->clear_announce_list();
		$torrent->clear_comment();
		$written = (string)$torrent;
		$this->assertTrue(strpos($written, $this->bstr('announce-list')) === false,
			'clear_announce_list() drops the announce-list key');
		$this->assertTrue(strpos($written, $this->bstr('comment')) === false,
			'clear_comment() drops the comment key');
		$this->assertTrue(strpos($written, $this->bstr('announce')) === false,
			'clear_announce() drops the announce key');
		$this->assertTrue(strpos($written, $this->bstr('info')) !== false,
			'The rest of the dictionary is still written');
		$this->assertTrue(is_null($torrent->announce()) && is_null($torrent->announce_list())
				&& is_null($torrent->comment()),
			'The cleared getters read as unset');
	}

	/**
	 * rTorrent::sendTorrent() unsets 'libtorrent_resume' and
	 * rTorrent::fastResume() then builds it again one array offset at a time,
	 * i.e. it writes into a key that is not there. If that write does not
	 * reach the torrent, every fast-resume load silently loses its resume
	 * data.
	 */
	public function testACallerCanBuildAKeyThatIsNotThereYet()
	{
		$torrent = new Torrent($this->fixture());
		unset($torrent->libtorrent_resume);
		$this->assertTrue(!isset($torrent->libtorrent_resume),
			'The torrent no longer has libtorrent_resume');
		$this->assertTrue(strpos((string)$torrent, $this->bstr('libtorrent_resume')) === false,
			'and it is not written');

		$torrent->libtorrent_resume['bitfield'] = 7;
		$this->assertTrue(!isset($torrent->libtorrent_resume['files']),
			'isset() on an offset of a key answers for the offset');
		$torrent->libtorrent_resume['files'] = array();
		$torrent->libtorrent_resume['files'][0] = array('priority' => 2, 'mtime' => 1234567891);

		$reread = new Torrent((string)$torrent);
		$resume = $reread->libtorrent_resume;
		$this->assertTrue($resume['bitfield'] == 7, 'The bitfield reached the written torrent');
		$this->assertTrue($resume['files'][0]['priority'] == 2, 'The file entry reached the written torrent');
		$this->assertTrue(count($resume['files']) === 1, 'Only the entry that was written is there');
	}

	/** addtorrent.php writes into info the same way. */
	public function testACallerCanWriteIntoAnExistingKey()
	{
		$torrent = new Torrent($this->fixture());
		$torrent->info['unique'] = 'rutorrent-1';
		$reread = new Torrent((string)$torrent);
		$this->assertTrue($reread->info['unique'] === 'rutorrent-1', 'info/unique reached the written torrent');
		$this->assertTrue($reread->info['name'] === 'test.data', 'The rest of info is untouched');
	}

	/** sendTorrent() drops the two keys rtorrent adds to a saved torrent. */
	public function testACallerCanIssetAndUnsetAKey()
	{
		$torrent = new Torrent($this->fixture());
		$this->assertTrue(isset($torrent->rtorrent), 'A key the file carries reads as set');
		unset($torrent->rtorrent);
		$this->assertTrue(!isset($torrent->rtorrent), 'An unset key reads as unset');
		$this->assertTrue(strpos((string)$torrent, $this->bstr('rtorrent')) === false,
			'An unset key is not written');
		$torrent->rtorrent = array('directory' => '/downloads');
		$this->assertTrue((string)$torrent === $this->fixture(),
			'Setting the key again leaves the torrent as it was');
	}

	/** A torrent that failed to parse is still an empty dictionary. */
	public function testAnEmptyTorrentEncodesAsAnEmptyDictionary()
	{
		$torrent = new Torrent('');
		$this->assertTrue($torrent->errors() !== false, 'Empty data is an error');
		$this->assertTrue((string)$torrent === 'de', 'An empty torrent encodes as an empty dictionary');
	}

	/** A torrent whose keys all look like list indices is still a dictionary. */
	public function testATorrentWhoseKeysAreAllNumericIsStillADictionary()
	{
		$raw = 'd' . $this->bstr('0') . $this->bstr('a') . $this->bstr('1') . $this->bstr('b') . 'e';
		$torrent = new Torrent($raw);
		$this->assertTrue((string)$torrent === $raw, 'Numeric keys are re-encoded as a dictionary');
	}

	/**
	 * A key read back the way it was written. A bencode dictionary key that
	 * looks like a number arrives from the decoder as an int, and below PHP 8
	 * an int matches the first case of a switch over strings -- so meta(0)
	 * answered with the announce URL while meta('0') answered correctly.
	 */
	public function testANumericKeyIsReadBackByEitherSpelling()
	{
		$raw = 'd' . $this->bstr('announce') . $this->bstr('http://a/announce')
			. $this->bstr('0') . $this->bstr('zero') . 'e';
		$torrent = new Torrent($raw);
		$this->assertTrue($torrent->errors() === false, 'The torrent parses');
		$this->assertTrue($torrent->meta('0') === 'zero',
			"meta('0') reads the key the torrent carries");
		$this->assertTrue($torrent->meta(0) === 'zero',
			'meta(0) reads the same key, not the first named one');
		$this->assertTrue($torrent->announce === 'http://a/announce',
			'and announce is left alone');
	}

	/**
	 * The keys that are identifiers are declared properties, and no magic
	 * accessor stands between a caller and one of them -- a __get() would
	 * make every property access on a torrent, including a misspelt one,
	 * invisible to a static analyser.
	 */
	public function testTheKeysThatAreIdentifiersAreDeclaredProperties()
	{
		foreach (array('announce', 'comment', 'encoding', 'httpseeds', 'info',
			'libtorrent_resume', 'rtorrent') as $key) {
			$this->assertTrue(property_exists('Torrent', $key),
				"The {$key} key is a declared property");
		}
		foreach (array('__get', '__set', '__isset', '__unset') as $magic) {
			$this->assertTrue(!method_exists('Torrent', $magic),
				"Torrent has no {$magic}()");
		}
	}

	/** The object's own fields are not torrent keys. */
	public function testInternalFieldsAreNotWrittenToTheTorrent()
	{
		$torrent = new Torrent($this->fixture());
		foreach (array('errors', 'basedir', 'pointer', 'data', 'log_callback',
			'err_callback', 'filename', 'extra') as $field) {
			$this->assertTrue(strpos((string)$torrent, $this->bstr($field)) === false,
				"The object field {$field} is not written as a torrent key");
		}
	}

	// ---- the keys that cannot be properties, reached by name -------------

	/** The keys that cannot be properties are read and written by name. */
	public function testMetaReadsAndWritesTheKeysThatCannotBeProperties()
	{
		$torrent = new Torrent($this->fixture());
		$this->assertTrue($torrent->meta('created by') === 'uTorrent/3.5.5',
			'meta() reads the created by key');
		$this->assertTrue($torrent->meta('creation date') == 1234567890,
			'meta() reads the creation date key');
		$this->assertTrue($torrent->meta('url-list') === array('http://web.example.test/f.dat'),
			'meta() reads the url-list key');
		$this->assertTrue(is_null($torrent->meta('nodes')), 'meta() reads a key the torrent has not got as null');

		$torrent->setMeta('nodes', array(array('router.test', 6881)));
		$torrent->clearMeta('url-list');
		$reread = new Torrent((string)$torrent);
		$this->assertTrue($reread->meta('nodes') === array(array('router.test', 6881.0)),
			'setMeta() writes a key the torrent has not got');
		$this->assertTrue(is_null($reread->meta('url-list')), 'clearMeta() drops a key');
	}

	/** meta() answers for a key that is a declared property too, so that no
	 * caller has to know which of the two a key is. */
	public function testMetaAlsoAnswersForTheKeysThatAreProperties()
	{
		$torrent = new Torrent($this->fixture());
		$this->assertTrue($torrent->meta('announce') === $torrent->announce,
			'meta() reads a key that is a property');
		$this->assertTrue($torrent->meta('info') === $torrent->info, 'meta() reads info');
		$torrent->setMeta('comment', 'set by name');
		$this->assertTrue($torrent->comment === 'set by name', 'setMeta() writes a key that is a property');
		$torrent->clearMeta('comment');
		$this->assertTrue(is_null($torrent->comment) && !isset($torrent->comment),
			'clearMeta() drops a key that is a property');
		$this->assertTrue(strpos((string)$torrent, $this->bstr('comment')) === false,
			'and the dropped key is not written');
	}

	/**
	 * Every setter stamps 'created by' and 'creation date' through touch(),
	 * whose result the setters used to return. They return null, and the two
	 * stamped keys are the class' own, not the file's.
	 */
	public function testSettersWriteTheKeyAndStampTheCreator()
	{
		$torrent = new Torrent($this->fixture());
		$before = time();
		$this->assertTrue(is_null($torrent->announce('http://new.test/announce')),
			'The announce() setter returns null');
		$this->assertTrue(is_null($torrent->comment('new comment')), 'The comment() setter returns null');
		$this->assertTrue(is_null($torrent->name('renamed.data')), 'The name() setter returns null');
		$this->assertTrue(is_null($torrent->url_list(array('http://new.test/f.dat'))),
			'The url_list() setter returns null');
		$this->assertTrue(is_null($torrent->announce_list(array(array('http://new.test/announce')))),
			'The announce_list() setter returns null');
		$this->assertTrue(is_null($torrent->httpseeds(array('http://new.test/f.dat'))),
			'The httpseeds() setter returns null');
		$this->assertTrue(is_null($torrent->is_private(false)), 'The is_private() setter returns null');
		$this->assertTrue(is_null($torrent->source('SRC')), 'The source() setter returns null');

		$written = (string)$torrent;
		$this->assertTrue($torrent->announce() === 'http://new.test/announce', 'announce() was written');
		$this->assertTrue($torrent->comment() === 'new comment', 'comment() was written');
		$this->assertTrue($torrent->name() === 'renamed.data', 'name() was written');
		$this->assertTrue($torrent->is_private() === false, 'is_private() was written');
		$this->assertTrue(strpos($written, $this->bstr('created by')
				. $this->bstr('ruTorrent (PHP Class - Adrien Gibrat)')) !== false,
			'A setter stamps our own created by');
		$this->assertTrue(strpos($written, $this->bstr('creation date') . 'i1234567890e') === false,
			'A setter replaces the creation date the file had');
		$reread = new Torrent($written);
		$this->assertTrue($reread->meta('creation date') >= $before,
			'The stamped creation date is the time of the write');
		$this->assertTrue($reread->meta('url-list') === array('http://new.test/f.dat'),
			'url-list came back through a full write and read');
		$this->assertTrue($reread->meta('announce-list') === array(array('http://new.test/announce')),
			'announce-list came back through a full write and read');
		$this->assertTrue($reread->info['source'] === 'SRC', 'source() wrote info/source');
	}

	/**
	 * Reading a key that is not there must not add it: a missing key read on
	 * the way to a save (save() and send() both read info) would otherwise be
	 * written out as an empty value.
	 */
	public function testReadingAKeyThatIsNotThereDoesNotAddIt()
	{
		$torrent = new Torrent($this->fixture());
		$this->assertTrue(is_null($torrent->meta('nodes')), 'A key that is not there reads as null');
		$this->assertTrue(is_null($torrent->encoding) === false, 'The fixture does carry encoding');
		$empty = new Torrent('d4:infod4:name1:xee');
		$this->assertTrue(is_null($empty->comment), 'A property key that is not there reads as null');
		$this->assertTrue(is_null($empty->meta('created by')), 'And so does a key that is not a property');
		$this->assertTrue((string)$empty === 'd4:infod4:name1:xee',
			'Reading missing keys leaves the torrent as it was');
		$this->assertTrue((string)$torrent === $this->fixture(),
			'Reading a missing key leaves the torrent as it was');
	}

	/**
	 * The keys are metadata, not object state: PHP 8.2 deprecated dynamic
	 * properties, and 'created by' / 'announce-list' could never have been
	 * declared ones.
	 */
	public function testMetadataIsNotStoredAsDynamicObjectProperties()
	{
		$torrent = new Torrent($this->fixture());
		$this->assertEquals(array(), $this->dynamicProperties($torrent),
			'Parsing a torrent creates no dynamic properties');

		$torrent->announce('http://new.test/announce');
		$torrent->setMeta('x-custom', 'kept');
		$torrent->libtorrent_resume['bitfield'] = 7;
		$this->assertEquals(array(), $this->dynamicProperties($torrent),
			'Writing keys creates no dynamic properties');

		$reread = new Torrent((string)$torrent);
		$this->assertTrue($reread->meta('x-custom') === 'kept', 'A key a caller added is still written');
		$this->assertTrue($reread->announce() === 'http://new.test/announce', 'announce is still written');
	}

	/**
	 * A torrent key is data and cannot reach the object's own fields: a
	 * torrent carrying a key named like one of them keeps the key, and the
	 * object keeps its field.
	 */
	public function testAKeyNamedLikeAnInternalFieldIsStillATorrentKey()
	{
		$fixture = 'd'
			. $this->bstr('errors') . $this->bstr('not an error')
			. $this->bstr('info') . $this->infoDictionary()
			. 'e';
		$torrent = new Torrent($fixture);
		$this->assertTrue($torrent->errors() === false, 'The key did not become the error list');
		$this->assertTrue($torrent->meta('errors') === 'not an error', 'The key reads back as a key');
		$this->assertTrue((string)$torrent === $fixture, 'The key is written back out');
	}
}
