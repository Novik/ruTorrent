<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');

/**
 * What the create path does to a torrent: plugins/create/createtorrent.php
 * lines 70-93, plugins/create/correct.php lines 53-76, and the re-read at
 * plugins/create/action.php line 233 that hands the result to the browser.
 *
 * The create path is the one that writes the three keys a torrent carries
 * which are not PHP identifiers -- 'created by', 'creation date' and
 * 'announce-list'. A torrent is built here from real files, saved, and read
 * back, so that both halves of that -- what is written and what comes back --
 * are asserted against bytes this test built by hand.
 *
 * All three scripts are top level scripts that read $argv or $_REQUEST, so
 * their sequences are transcribed rather than executed. That pins the
 * transcription, not the script; the subject is Torrent.
 */
class TorrentCreatePathSequenceTest extends TestCase
{
	use TorrentSequenceFixtures;

	private $root;
	private $source;
	private $saved;

	/** The contents of the files the torrent is built from. */
	private $files = array(
		'a.data'     => 'the first file',
		'sub/b.data' => 'the second file, in a subdirectory',
	);

	public function setUp()
	{
		$this->root = sys_get_temp_dir() . '/rutorrent-create-seq-' . getmypid();
		$this->source = $this->root . '/created-payload';
		$this->saved = $this->root . '/result.torrent';
		foreach ($this->files as $relative => $contents) {
			$path = $this->source . '/' . $relative;
			if (!is_dir(dirname($path))) {
				mkdir(dirname($path), 0777, true);
			}
			file_put_contents($path, $contents);
		}
	}

	public function tearDown()
	{
		foreach (array_keys($this->files) as $relative) {
			@unlink($this->source . '/' . $relative);
		}
		@unlink($this->saved);
		@rmdir($this->source . '/sub');
		@rmdir($this->source);
		@rmdir($this->root);
	}

	/**
	 * The info dictionary the class builds out of those files.
	 *
	 * Torrent::files() sorts deepest path first and then by name, hashes the
	 * files in that order as one stream, and records each one's length and its
	 * path relative to the folder. The pieces are computed here from the same
	 * contents, in that order, so nothing in the expected bytes comes from the
	 * class.
	 */
	private function createdInfo($extra = array())
	{
		$order = array('sub/b.data', 'a.data');
		$stream = '';
		$entries = array();
		foreach ($order as $relative) {
			$stream .= $this->files[$relative];
			$components = array();
			foreach (explode('/', $relative) as $component) {
				$components[] = $this->bstr($component);
			}
			$entries[] = $this->bdict(array(
				'length' => $this->bint(strlen($this->files[$relative])),
				'path'   => $this->blist($components),
			));
		}
		$pairs = array(
			'files'        => $this->blist($entries),
			'name'         => $this->bstr('created-payload'),
			'piece length' => $this->bint(16 * 1024),
			'pieces'       => $this->bstr(sha1($stream, true)),
		);
		foreach ($extra as $key => $value) {
			$pairs[$key] = $value;
		}
		ksort($pairs, SORT_STRING);
		return $this->bdict($pairs);
	}

	// ---- plugins/create/createtorrent.php --------------------------------

	/**
	 * The create form with several trackers, a comment, a source tag and the
	 * private box ticked: everything the plugin can put on a new torrent.
	 */
	public function testCreatingATorrentWritesEveryKeyTheFormCanSet()
	{
		$before = time();
		$announce_list = array(
			array('http://one.test/announce', 'http://one.test/announce2'),
			array('udp://two.test/announce'),
		);
		$trackersCount = 3;

		// createtorrent.php lines 70-93.
		$torrent = new Torrent($this->source, $announce_list[0][0], 16, null, null);
		if ($trackersCount > 1) {
			$torrent->announce_list($announce_list);
		}
		$torrent->source('XIRVIK');
		$torrent->comment('made here');
		$torrent->is_private(true);

		$this->assertTrue($torrent->errors() === false, 'the torrent was built without errors');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList($announce_list),
			'comment'       => $this->bstr('made here'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->createdInfo(array(
				'private' => $this->bint(1),
				'source'  => $this->bstr('XIRVIK'),
			)),
		));
		$this->assertTrue($written === $expected, 'the built torrent is byte for byte what it should be');
		$this->assertTrue($torrent->hash_info() === strtoupper(sha1($this->createdInfo(array(
				'private' => $this->bint(1),
				'source'  => $this->bstr('XIRVIK'),
			)))),
			'and its info hash is the hash of the info dictionary that was written');
	}

	/** One tracker: announce is set by the constructor and no announce-list is added. */
	public function testCreatingATorrentWithOneTrackerWritesNoAnnounceList()
	{
		$before = time();
		$torrent = new Torrent($this->source, 'http://only.test/announce', 16, null, null);

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://only.test/announce'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->createdInfo(),
		));
		$this->assertTrue($written === $expected, 'the torrent carries announce and nothing else');
	}

	/** No tracker at all is a valid create, and the two stamped keys are still written. */
	public function testCreatingATorrentWithNoTrackerStillStampsTheCreator()
	{
		$before = time();
		$torrent = new Torrent($this->source, array(), 16, null, null);

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->createdInfo(),
		));
		$this->assertTrue($written === $expected, 'a trackerless torrent is built and stamped');
	}

	// ---- save, then plugins/create/action.php line 233 --------------------

	/**
	 * The plugin saves the result and a later request reads it back to hand it
	 * to the browser. The three keys that are not identifiers have to survive
	 * that round trip, because they are the ones a new storage could quietly
	 * drop.
	 */
	public function testASavedTorrentReadsBackByteForByte()
	{
		$before = time();
		$torrent = new Torrent($this->source, 'http://one.test/announce', 16, null, null);
		$torrent->announce_list(array(
			array('http://one.test/announce'),
			array('udp://two.test/announce'),
		));
		$torrent->comment('made here');

		$written = (string)$torrent;
		$this->assertTrue($torrent->save($this->saved) !== false, 'the torrent was saved');
		$this->assertTrue(file_get_contents($this->saved) === $written,
			'what is on disk is what the object encodes to');
		$this->assertTrue($torrent->getFileName() === $this->saved, 'and the object knows where it went');

		// plugins/create/action.php line 233.
		$reread = new Torrent($this->saved);
		$this->assertTrue($reread->errors() === false, 'the saved torrent parses');
		$this->assertTrue((string)$reread === $written, 'and re-encodes to the same bytes');

		$stamped = $this->stampedDate($written, $before);
		$this->assertTrue(strpos($written, $this->bstr('created by') . $this->bstr($this->ourCreator())) !== false,
			"'created by' is written with our creator");
		$this->assertTrue(strpos($written, $this->bstr('creation date') . $this->bint($stamped)) !== false,
			"'creation date' is written with the time of the build");
		$this->assertTrue($reread->announce_list() === array(
				array('http://one.test/announce'), array('udp://two.test/announce')),
			"'announce-list' reads back through a save and a load");
		$this->assertTrue($reread->comment() === 'made here', 'and so does the comment');
	}

	// ---- plugins/create/correct.php --------------------------------------

	/**
	 * correct.php opens the torrent an external creator wrote and puts the
	 * form's values on it. Its first move is clear_announce() with no
	 * clear_announce_list(), so a torrent that arrives with an announce-list
	 * and is corrected to a single tracker keeps the list it came with -- and
	 * that has to stay exactly as it is, list and all.
	 */
	public function testCorrectingAnExternallyBuiltTorrentSetsTheFormsValues()
	{
		$before = time();
		$torrent = new Torrent($this->announceListTorrent());

		// correct.php lines 53-76, with one tracker on the form.
		$announce_list = array(array('http://corrected.test/announce'));
		$trackersCount = 1;
		$torrent->clear_announce();
		if (count($announce_list) > 0) {
			$torrent->announce($announce_list[0][0]);
			if ($trackersCount > 1) {
				$torrent->announce_list($announce_list);
			}
		}
		$torrent->comment('corrected');
		$torrent->is_private(true);
		$torrent->source('XIRVIK');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://corrected.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce', 'udp://two.test/announce2'),
			)),
			'comment'       => $this->bstr('corrected'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->bdict(array(
				'length'       => $this->bint(1024),
				'name'         => $this->bstr('test.data'),
				'piece length' => $this->bint(16384),
				'pieces'       => $this->bstr($this->pieces()),
				'private'      => $this->bint(1),
				'source'       => $this->bstr('XIRVIK'),
			)),
		));
		$this->assertTrue($written === $expected,
			'the form values are written and the announce-list it came with is left alone');
	}
}
