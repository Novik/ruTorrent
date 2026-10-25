<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/TestTree.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * rTorrent::fastResume() asks the settings singleton whether the download
 * directory is inside the configured tree, which on a live install is answered
 * out of a cache written by a running daemon. Here it is answered yes, so the
 * fixture directory this test made can be used.
 */
class AddPathSequenceSettings extends rTorrentSettings
{
	public function correctDirectory(&$dir, $resolve_links = false)
	{
		return true;
	}
}

/**
 * What the add path does to a torrent: php/addtorrent.php line 120 onwards,
 * and the two places in php/rtorrent.php it hands the torrent to.
 *
 * php/rtorrent.php is declarations only -- three require_once and then the
 * classes -- so rTorrent::fastResume() is the real function here, driven
 * directly. It is the caller that matters most: it builds
 * libtorrent_resume['files'][$i]['mtime'] one array offset at a time, into a
 * key the torrent may not have at all, and if that write does not reach the
 * torrent every fast-resume load silently loses its resume data and rehashes.
 *
 * addtorrent.php and rTorrent::sendTorrent() cannot be included -- one reads
 * $_REQUEST at the top level, the other talks SCGI -- so their sequences are
 * transcribed. That pins the transcription, not the script; the subject is
 * Torrent, and the bencoded bytes are asserted whole.
 */
class TorrentAddPathSequenceTest extends TestCase
{
	use TorrentSequenceFixtures;

	private $base;
	private $previousSettings;

	/** The mtimes the fixture files are given, so the expected bytes are fixed. */
	private $mtimes = array(
		'test.data'            => 1500000001,
		'payload/one.data'     => 1500000002,
		'payload/sub/two.data' => 1500000003,
	);

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-addpath-seq-' . getmypid();
		$this->makeFile('test.data', 1024);
		$this->makeFile('payload/one.data', 600);
		$this->makeFile('payload/sub/two.data', 400);

		// rTorrentSettings' constructor reaches for a live daemon, so the
		// singleton is replaced with an instance built without it.
		$stub = new ReflectionClass('AddPathSequenceSettings');
		$settings = $stub->newInstanceWithoutConstructor();
		$settings->directory = $this->base;
		$settings->iVersion = 0x904;
		$settings->aliases = array();

		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		$this->previousSettings = $property->getValue();
		$property->setValue(null, $settings);
	}

	public function tearDown()
	{
		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $this->previousSettings);

		foreach (array_keys($this->mtimes) as $relative) {
			@unlink($this->base . '/' . $relative);
		}
		@rmdir($this->base . '/payload/sub');
		@rmdir($this->base . '/payload');
		@rmdir($this->base);
	}

	private function makeFile($relative, $size)
	{
		$path = $this->base . '/' . $relative;
		$dir = dirname($path);
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		file_put_contents($path, str_repeat("\x00", $size));
		touch($path, $this->mtimes[$relative]);
		clearstatcache(true, $path);
	}

	/** A libtorrent_resume dictionary as fastResume() builds one. */
	private function expectedResume($chunks, $files)
	{
		$entries = array();
		foreach ($files as $relative) {
			$entries[] = $this->bdict(array(
				'mtime'    => $this->bint($this->mtimes[$relative]),
				'priority' => $this->bint(2),
			));
		}
		return $this->bdict(array(
			'bitfield' => $this->bint($chunks),
			'files'    => $this->blist($entries),
		));
	}

	// ---- rTorrent::fastResume() ------------------------------------------

	/**
	 * A single file torrent that has never been loaded: the key is not there
	 * at all and fastResume() has to create it, offset by offset.
	 */
	public function testFastResumeBuildsResumeDataOnATorrentThatHasNone()
	{
		$torrent = new Torrent($this->announceOnlyTorrent());
		$this->assertTrue(!isset($torrent->libtorrent_resume), 'the fixture has no resume data');

		$resumed = rTorrent::fastResume($torrent, $this->base, true);
		$this->assertTrue($resumed === $torrent, 'fastResume() hands back the torrent it was given');

		$expected = $this->bdict(array(
			'announce'          => $this->bstr('http://one.test/announce'),
			'created by'        => $this->bstr('uTorrent/3.5.5'),
			'creation date'     => $this->bint(1234567890),
			'info'              => $this->singleFileInfo(),
			'libtorrent_resume' => $this->expectedResume(1, array('test.data')),
		));
		$this->assertTrue((string)$torrent === $expected,
			'the resume data is written and nothing else moved -- fastResume() is not a setter, '
				. 'so the creation date the file came with is still there');

		$reread = new Torrent((string)$torrent);
		$resume = $reread->libtorrent_resume;
		$this->assertTrue($resume['bitfield'] == 1, 'the bitfield reached the written torrent');
		$this->assertTrue(count($resume['files']) === 1, 'one file entry was written');
		$this->assertTrue($resume['files'][0]['priority'] == 2 &&
				$resume['files'][0]['mtime'] == $this->mtimes['test.data'],
			'with the priority and the mtime of the file on disk');
	}

	/**
	 * A multi file torrent, added with its own directory: every path is
	 * prefixed with the torrent name, and one entry is written per file in
	 * the order info/files carries them.
	 */
	public function testFastResumeWritesOneEntryPerFileWithTheAddPathPrefix()
	{
		$torrent = new Torrent($this->privateMultiFileTorrent());
		rTorrent::fastResume($torrent, $this->base, true);

		$expected = $this->bdict(array(
			'announce'          => $this->bstr('http://one.test/announce'),
			'comment'           => $this->bstr('from the tracker'),
			'created by'        => $this->bstr('uTorrent/3.5.5'),
			'creation date'     => $this->bint(1234567890),
			'encoding'          => $this->bstr('UTF-8'),
			'info'              => $this->multiFileInfo(true),
			'libtorrent_resume' => $this->expectedResume(1,
				array('payload/one.data', 'payload/sub/two.data')),
		));
		$this->assertTrue((string)$torrent === $expected,
			'both files are stat-ed under the torrent name and written in order');
	}

	/**
	 * Without add-path the files are looked for directly under the base
	 * directory, so a torrent whose files are not there gets no resume data
	 * at all -- fastResume() returns false and the caller loads the torrent
	 * unchanged rather than telling rtorrent the data is complete.
	 */
	public function testFastResumeRefusesWhenAFileIsNotWhereItWouldBe()
	{
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$fixture = (string)$torrent;

		$resumed = rTorrent::fastResume($torrent, $this->base, false);
		$this->assertTrue($resumed === false, 'a missing file is refused');

		$reread = new Torrent((string)$torrent);
		$this->assertTrue($reread->info['name'] === 'payload', 'the torrent still parses');
		$this->assertTrue(strpos((string)$torrent, $this->bstr('info')) !== false,
			'and it still carries its info dictionary');
		$this->assertTrue((string)$torrent !== $fixture,
			'the half-built resume key is left behind, which is why the caller drops the result');
	}

	/**
	 * The torrent rtorrent saved already carries resume data. fastResume()
	 * overwrites the entry for every file it stats and leaves the dictionary
	 * otherwise as it found it.
	 */
	public function testFastResumeOverwritesTheResumeDataASessionTorrentCameWith()
	{
		$torrent = new Torrent($this->sessionTorrent());
		$this->assertTrue($torrent->libtorrent_resume['files'][0]['mtime'] == 1234567891,
			'the fixture carries an mtime of its own');

		rTorrent::fastResume($torrent, $this->base, true);

		$expected = $this->bdict(array(
			'announce'          => $this->bstr('http://one.test/announce'),
			'announce-list'     => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce'),
			)),
			'created by'        => $this->bstr('uTorrent/3.5.5'),
			'creation date'     => $this->bint(1234567890),
			'info'              => $this->singleFileInfo(),
			'libtorrent_resume' => $this->expectedResume(1, array('test.data')),
			'rtorrent'          => $this->rtorrentDictionary(),
		));
		$this->assertTrue((string)$torrent === $expected,
			'the entry is replaced with the mtime on disk and the rest of the torrent is untouched');
	}

	/** Writes an empty file the fixture does not own, with any missing directories. */
	private function writeExtra($relative)
	{
		$path = $this->base . '/' . $relative;
		$missing = array();
		for ($dir = dirname($path); !is_dir($dir); $dir = dirname($dir)) {
			$missing[] = $dir;
		}
		foreach (array_reverse($missing) as $dir) {
			mkdir($dir);
			chmod($dir, 0700);
		}
		file_put_contents($path, '');
		clearstatcache();
	}

	public function testFastResumeRefusesADifferingSingleFileAliasBeforeWritingProgress()
	{
		try {
			$this->writeExtra('x_y.data');
			foreach (array('test.data' => 'серия.data', 'x_y.data' => 'x/y.data') as $name => $alias) {
				$torrent = new Torrent($this->announceOnlyTorrent());
				$torrent->info['name'] = $name;
				$torrent->info['name.utf-8'] = $alias;
				$before = (string)$torrent;
				$this->assertTrue(rTorrent::fastResume($torrent, $this->base) === false,
					'a daemon that reads the alias may look for another file than ' . $name);
				$this->assertTrue((string)$torrent === $before, 'refusal leaves the torrent and its progress unchanged');
			}
		} finally {
			@unlink($this->base . '/x_y.data');
		}
	}

	public function testFastResumeRefusesDifferingFileAliasesWithAndWithoutRootPrefix()
	{
		foreach (array(true, false) as $addPath) {
			$torrent = new Torrent($this->privateMultiFileTorrent());
			$torrent->info['files'][0]['path.utf-8'] = array('selected.data');
			$before = (string)$torrent;
			$base = $addPath ? $this->base : $this->base . '/payload';
			$this->assertTrue(rTorrent::fastResume($torrent, $base, $addPath) === false,
				'a file alias can change the loaded payload under either directory mode');
			$this->assertTrue((string)$torrent === $before, 'refusal precedes any resume write');
		}
	}

	public function testFastResumeChecksTheAliasOfEveryFile()
	{
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$torrent->info['files'][1]['path.utf-8'] = array('sub/two.data');
		$this->assertTrue(rTorrent::fastResume($torrent, $this->base, true) === false,
			'an alias on the second file refuses as well');
	}

	public function testFastResumeRefusesADifferentRootAliasWhenAddingTheRoot()
	{
		foreach (array('selected-root', 'a/b') as $alias) {
			$torrent = new Torrent($this->privateMultiFileTorrent());
			$torrent->info['name.utf-8'] = $alias;
			$before = (string)$torrent;
			$this->assertTrue(rTorrent::fastResume($torrent, $this->base, true) === false,
				'a daemon that reads the root alias puts every file under another directory');
			$this->assertTrue((string)$torrent === $before, 'the root-alias refusal does not change the torrent');
		}
	}

	public function testFastResumeComparesAliasesAsStrings()
	{
		try {
			$this->writeExtra('100');
			$torrent = new Torrent($this->announceOnlyTorrent());
			$torrent->info['name'] = '100';
			$torrent->info['name.utf-8'] = '1e2';
			$this->assertTrue(rTorrent::fastResume($torrent, $this->base) === false,
				'numeric-looking names that differ are different files');
		} finally {
			@unlink($this->base . '/100');
		}
	}

	public function testFastResumeIgnoresAliasesNoDaemonLoads()
	{
		$single = new Torrent($this->announceOnlyTorrent());
		$single->info['name.utf-8'] = 100;
		$torrents = array($single);
		foreach (array('selected.data', array(array('one.data')), array('key' => 'one.data')) as $alias) {
			$multi = new Torrent($this->privateMultiFileTorrent());
			$multi->info['files'][0]['path.utf-8'] = $alias;
			$torrents[] = $multi;
		}
		foreach ($torrents as $torrent) {
			try {
				$result = rTorrent::fastResume($torrent, $this->base);
			} catch (Throwable $e) {
				$result = $e;
			}
			$this->assertTrue($result === $torrent,
				'only a string name.utf-8 and a list of strings path.utf-8 are aliases a daemon can load');
		}
	}

	public function testFastResumeKeepsIdenticalAliasesAvailable()
	{
		$single = new Torrent($this->announceOnlyTorrent());
		$single->info['name.utf-8'] = $single->info['name'];
		$this->assertTrue(rTorrent::fastResume($single, $this->base) === $single,
			'an identical single-file alias names the same physical file');
		$this->assertTrue(($single->libtorrent_resume['files'][0]['mtime'] ?? null) === $this->mtimes['test.data'],
			'the identical single alias keeps the file mtime');
		$multi = new Torrent($this->privateMultiFileTorrent());
		$multi->info['name.utf-8'] = $multi->info['name'];
		foreach ($multi->info['files'] as $index => $file) {
			$multi->info['files'][$index]['path.utf-8'] = $file['path'];
		}
		$this->assertTrue(rTorrent::fastResume($multi, $this->base) === $multi,
			'identical root and file aliases do not disable fast resume');
		$files = $multi->libtorrent_resume['files'] ?? null;
		$this->assertTrue(is_array($files) && count($files) === 2, 'both file entries are present');
	}

	public function testFastResumeIgnoresAnUnusedMultiFileRootAlias()
	{
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$torrent->info['name.utf-8'] = 'unused/root';
		$this->assertTrue(rTorrent::fastResume($torrent, $this->base . '/payload', false) === $torrent,
			'directory_base selects the supplied root directly, so its unused metadata alias is irrelevant');
		$files = $torrent->libtorrent_resume['files'] ?? null;
		$this->assertTrue(is_array($files) && count($files) === 2, 'both file entries remain available');
	}

	public function testFastResumeRefusesComponentsTheDaemonRenames()
	{
		foreach (array('payload/one.data', '/test.data') as $name) {
			$single = new Torrent($this->announceOnlyTorrent());
			$single->info['name'] = $name;
			$this->assertTrue(rTorrent::fastResume($single, $this->base) === false,
				'a / in a single-file name does not stay a separator');
		}
		$raw = new Torrent($this->privateMultiFileTorrent());
		$raw->info['files'][1]['path'] = array('sub/two.data');
		$this->assertTrue(rTorrent::fastResume($raw, $this->base, true) === false,
			'a / in a path component does not stay a separator');
		try {
			$this->writeExtra('pay/load/one.data');
			$this->writeExtra('pay/load/sub/two.data');
			$root = new Torrent($this->privateMultiFileTorrent());
			$root->info['name'] = 'pay/load';
			$this->assertTrue(rTorrent::fastResume($root, $this->base, true) === false,
				'a / in the root name added with the torrent does not stay a separator');
		} finally {
			TestTree::remove($this->base . '/pay');
		}
	}

	public function testFastResumePreservesTheExactDirectoryInsteadOfStattingItsTrimmedTwin()
	{
		$base = $this->base . ' ';
		try {
			mkdir($base, 0700);
			chmod($base, 0700);
			file_put_contents($base . '/test.data', '');
			touch($base . '/test.data', 1500000099);
			$torrent = new Torrent($this->announceOnlyTorrent());
			$this->assertTrue(rTorrent::fastResume($torrent, $base) === $torrent, 'the exact directory is usable');
			$this->assertTrue(($torrent->libtorrent_resume['files'][0]['mtime'] ?? null) === 1500000099,
				'the daemon receives this directory, so its file mtime is used instead of the trimmed sibling');
		} finally {
			@unlink($base . '/test.data');
			@rmdir($base);
		}
		$torrent = new Torrent($this->announceOnlyTorrent());
		$this->assertTrue(rTorrent::fastResume($torrent, ' ') === false,
			'a directory of spaces is not the default directory, whose file would be certified');
	}

	// ---- php/addtorrent.php ---------------------------------------------

	/**
	 * addtorrent.php line 129: the "randomize hash" box writes a key into the
	 * info dictionary, which is the point -- the info hash has to come out
	 * different, and everything else has to come out the same.
	 */
	public function testRandomizeHashWritesIntoInfoAndMovesTheInfoHash()
	{
		$torrent = new Torrent($this->announceOnlyTorrent());
		$was = $torrent->hash_info();

		$torrent->info['unique'] = 'rutorrent-fixed';

		$info = $this->bdict(array(
			'length'       => $this->bint(1024),
			'name'         => $this->bstr('test.data'),
			'piece length' => $this->bint(16384),
			'pieces'       => $this->bstr($this->pieces()),
			'unique'       => $this->bstr('rutorrent-fixed'),
		));
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'created by'    => $this->bstr('uTorrent/3.5.5'),
			'creation date' => $this->bint(1234567890),
			'info'          => $info,
		));
		$this->assertTrue((string)$torrent === $expected, 'info/unique is written into the info dictionary');
		$this->assertTrue($torrent->hash_info() === strtoupper(sha1($info)), 'the info hash follows it');
		$this->assertTrue($torrent->hash_info() !== $was, 'and it is not the hash it had');

		$reread = new Torrent((string)$torrent);
		$this->assertTrue($reread->info['unique'] === 'rutorrent-fixed', 'it survives a write and a read');
		$this->assertTrue($reread->info['name'] === 'test.data', 'the rest of info is as it was');
	}

	/**
	 * The whole add path, in order: a torrent picked up from disk, randomized,
	 * and fast-resumed before it goes to rtorrent.
	 */
	public function testTheAddPathRandomizesThenFastResumes()
	{
		$torrent = new Torrent($this->announceOnlyTorrent());
		$torrent->info['unique'] = 'rutorrent-fixed';
		$resumed = rTorrent::fastResume($torrent, $this->base, true);
		$this->assertTrue($resumed === $torrent, 'the torrent was fast-resumed');

		$info = $this->bdict(array(
			'length'       => $this->bint(1024),
			'name'         => $this->bstr('test.data'),
			'piece length' => $this->bint(16384),
			'pieces'       => $this->bstr($this->pieces()),
			'unique'       => $this->bstr('rutorrent-fixed'),
		));
		$expected = $this->bdict(array(
			'announce'          => $this->bstr('http://one.test/announce'),
			'created by'        => $this->bstr('uTorrent/3.5.5'),
			'creation date'     => $this->bint(1234567890),
			'info'              => $info,
			'libtorrent_resume' => $this->expectedResume(1, array('test.data')),
		));
		$this->assertTrue((string)$torrent === $expected, 'both writes are in the bytes handed to rtorrent');
	}

	// ---- rTorrent::sendTorrent() and rTorrent::getSource() ---------------

	/**
	 * sendTorrent() lines 26-35: a torrent being loaded as a new one has the
	 * two keys rtorrent left on it dropped, so that rtorrent does not take the
	 * old session state with it.
	 */
	public function testSendTorrentDropsBothOfRtorrentsKeysForANewTorrent()
	{
		$torrent = new Torrent($this->sessionTorrent());

		$isNew = true;
		$mustSave = false;
		if ($isNew && isset($torrent->{'libtorrent_resume'})) {
			unset($torrent->{'libtorrent_resume'});
			$mustSave = true;
		}
		if ($isNew && isset($torrent->{'rtorrent'})) {
			unset($torrent->{'rtorrent'});
			$mustSave = true;
		}

		$this->assertTrue($mustSave === true, 'the torrent was changed, so it has to be saved');
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce'),
			)),
			'created by'    => $this->bstr('uTorrent/3.5.5'),
			'creation date' => $this->bint(1234567890),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue((string)$torrent === $expected,
			'both keys are gone and the rest of the torrent is byte for byte as it was');
	}

	/**
	 * The same two keys, on a torrent that has neither: the isset() guards
	 * must not add them.
	 */
	public function testSendTorrentLeavesATorrentWithNeitherKeyAlone()
	{
		$fixture = $this->announceOnlyTorrent();
		$torrent = new Torrent($fixture);

		if (isset($torrent->{'libtorrent_resume'})) {
			unset($torrent->{'libtorrent_resume'});
		}
		if (isset($torrent->{'rtorrent'})) {
			unset($torrent->{'rtorrent'});
		}
		$this->assertTrue((string)$torrent === $fixture, 'the torrent is untouched');
	}

	/**
	 * rTorrent::getSource() lines 164-171, which is what plugins/source hands
	 * the user: the torrent as it was, with everything rtorrent added taken
	 * back off it.
	 */
	public function testGetSourceStripsWhatRtorrentAdded()
	{
		$torrent = new Torrent($this->sessionTorrent());
		$this->assertTrue($torrent->errors() === false, 'the session torrent parses');
		if (isset($torrent->{'libtorrent_resume'})) {
			unset($torrent->{'libtorrent_resume'});
		}
		if (isset($torrent->{'rtorrent'})) {
			unset($torrent->{'rtorrent'});
		}

		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce'),
			)),
			'created by'    => $this->bstr('uTorrent/3.5.5'),
			'creation date' => $this->bint(1234567890),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue((string)$torrent === $expected, 'what is offered for download is the original torrent');

		// plugins/source/action.php line 54 names the file in the zip after it.
		$this->assertTrue($torrent->info['name'] === 'test.data', 'and its name is readable for the zip entry');
	}
}
