<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TorrentSequenceFixtures.php');

/**
 * What plugins/edit/action.php does to a torrent.
 *
 * action.php is a top level script -- it reads $_REQUEST, asks rtorrent for
 * the session path and hands the result to rTorrent::sendTorrent() over SCGI --
 * so it cannot be included. Its call sequence (action.php lines 95-135) is
 * transcribed into replay() below and driven against the class; the bencoded
 * bytes that come out are asserted whole, because those bytes are what is
 * handed to rtorrent and what a regression in Torrent would corrupt.
 *
 * As with the retrackers tests, this pins the transcription rather than the
 * script: an edit to action.php will not fail it. The subject is Torrent.
 */
class EditActionSequenceTest extends TestCase
{
	use TorrentSequenceFixtures;

	/**
	 * plugins/edit/action.php lines 97-121, verbatim but for the RPC either
	 * side of it. $announce_list is what the script has already assembled out
	 * of the textarea, and $trackersCount is the number of non-empty lines in
	 * it.
	 */
	private function replay($torrent, $options)
	{
		$setPrivate    = isset($options['private']);
		$private       = $setPrivate ? $options['private'] : null;
		$setTrackers   = isset($options['announce_list']);
		$announce_list = $setTrackers ? $options['announce_list'] : array();
		$trackersCount = 0;
		foreach ($announce_list as $group) {
			$trackersCount += count($group);
		}
		$setComment    = array_key_exists('comment', $options);
		$comment       = $setComment ? $options['comment'] : null;

		if ($setPrivate) {
			$torrent->is_private($private);
		}
		if ($setTrackers) {
			$torrent->clear_announce();
			$torrent->clear_announce_list();
			if (count($announce_list) > 0) {
				$torrent->announce($announce_list[0][0]);
				if ($trackersCount > 1) {
					$torrent->announce_list($announce_list);
				}
			}
		}
		if ($setComment) {
			$torrent->clear_comment();
			$comment = trim($comment);
			if (strlen($comment)) {
				$torrent->comment($comment);
			}
		}
		if (isset($torrent->{'rtorrent'})) {
			unset($torrent->{'rtorrent'});
		}
	}

	// ---- trackers --------------------------------------------------------

	/**
	 * More than one tracker: announce holds the first and announce-list holds
	 * the groups. The list the torrent came with is cleared first, so what is
	 * written is the new list and nothing of the old one.
	 */
	public function testSettingSeveralTrackersReplacesBothKeys()
	{
		$before = time();
		$torrent = new Torrent($this->announceListTorrent());
		$this->replay($torrent, array('announce_list' => array(
			array('http://new.test/announce'),
			array('udp://new.test/announce'),
		)));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://new.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://new.test/announce'),
				array('udp://new.test/announce'),
			)),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected, 'both tracker keys are replaced wholesale');

		$reread = new Torrent($written);
		$this->assertTrue($reread->announce() === 'http://new.test/announce', 'announce reads back');
		$this->assertTrue($reread->announce_list() === array(
				array('http://new.test/announce'), array('udp://new.test/announce')),
			'announce-list reads back');
	}

	/**
	 * A single tracker: the script sets announce and deliberately does not set
	 * announce-list, so a torrent that had one comes back without it.
	 */
	public function testASingleTrackerLeavesTheTorrentWithNoAnnounceList()
	{
		$before = time();
		$torrent = new Torrent($this->announceListTorrent());
		$this->replay($torrent, array('announce_list' => array(array('http://only.test/announce'))));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://only.test/announce'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected, 'the announce-list key is gone from the written torrent');

		$reread = new Torrent($written);
		$this->assertTrue($reread->announce_list() === null, 'and it reads back as unset');
	}

	/**
	 * An empty tracker box clears both keys and puts neither back. A torrent
	 * with no announce at all is what rtorrent is then handed.
	 */
	public function testAnEmptyTrackerListClearsBothKeys()
	{
		$before = time();
		$torrent = new Torrent($this->announceListTorrent());
		$this->replay($torrent, array('announce_list' => array()));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected, 'neither tracker key is written');
	}

	// ---- the private flag ------------------------------------------------

	/** Marking a public torrent private adds info/private, which changes its hash. */
	public function testMarkingATorrentPrivateWritesTheFlagIntoInfo()
	{
		$before = time();
		$torrent = new Torrent($this->announceOnlyTorrent());
		$was = $torrent->hash_info();
		$this->assertTrue($torrent->is_private() === false, 'the fixture is public');

		$this->replay($torrent, array('private' => true));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(true),
		));
		$this->assertTrue($written === $expected, 'info/private is written as 1');
		$this->assertTrue($torrent->is_private() === true, 'and the getter agrees');
		$this->assertTrue($torrent->hash_info() !== $was, 'the info hash moved, as it must');
		$this->assertTrue($torrent->hash_info() === strtoupper(sha1($this->singleFileInfo(true))),
			'and it is the hash of the info dictionary that was written');
	}

	/**
	 * Clearing the flag writes info/private as 0 rather than dropping the key:
	 * the setter is `$private ? 1 : 0`, and the difference is a different info
	 * hash, so it has to stay exactly as it is.
	 */
	public function testUnmarkingATorrentWritesPrivateAsZeroRatherThanDroppingIt()
	{
		$before = time();
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$this->assertTrue($torrent->is_private() === true, 'the fixture is private');

		$this->replay($torrent, array('private' => false));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'comment'       => $this->bstr('from the tracker'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'encoding'      => $this->bstr('UTF-8'),
			'info'          => $this->multiFileInfo(false),
		));
		$this->assertTrue($written === $expected, 'info/private is written as 0 and the files are untouched');
		$this->assertTrue($torrent->is_private() === false, 'the getter reads it as public');
	}

	// ---- the comment -----------------------------------------------------

	public function testSettingACommentReplacesTheOne()
	{
		$before = time();
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$this->replay($torrent, array('comment' => "  edited by the owner \n"));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'comment'       => $this->bstr('edited by the owner'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'encoding'      => $this->bstr('UTF-8'),
			'info'          => $this->multiFileInfo(true),
		));
		$this->assertTrue($written === $expected, 'the comment is trimmed and written');
	}

	/** A comment box left blank drops the key. */
	public function testAnEmptyCommentDropsTheKey()
	{
		$before = time();
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$this->replay($torrent, array('comment' => "   "));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'encoding'      => $this->bstr('UTF-8'),
			'info'          => $this->multiFileInfo(true),
		));
		$this->assertTrue($written === $expected, 'the comment key is not written');

		$reread = new Torrent($written);
		$this->assertTrue($reread->comment() === null, 'and it reads back as unset');
	}

	// ---- what rtorrent added ---------------------------------------------

	/**
	 * Every edit ends by dropping the 'rtorrent' key, so that rtorrent takes
	 * the reloaded torrent as a new one. The resume data is left alone: the
	 * script passes $isNew = false to sendTorrent(), which is what stops it
	 * being dropped as well.
	 */
	public function testAnEditOfASessionTorrentDropsRtorrentAndKeepsResumeData()
	{
		$before = time();
		$torrent = new Torrent($this->sessionTorrent());
		$this->assertTrue(isset($torrent->rtorrent), 'the fixture carries the key rtorrent added');

		$this->replay($torrent, array('comment' => 'edited'));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'          => $this->bstr('http://one.test/announce'),
			'announce-list'     => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce'),
			)),
			'comment'           => $this->bstr('edited'),
			'created by'        => $this->bstr($this->ourCreator()),
			'creation date'     => $this->bint($this->stampedDate($written, $before)),
			'info'              => $this->singleFileInfo(),
			'libtorrent_resume' => $this->resumeDictionary(),
		));
		$this->assertTrue($written === $expected, 'rtorrent is dropped, libtorrent_resume is kept');
	}

	/**
	 * All three edits at once, on the torrent rtorrent saved -- the sequence
	 * as the plugin actually runs it when every box on the form is ticked.
	 */
	public function testAllThreeEditsAtOnce()
	{
		$before = time();
		$torrent = new Torrent($this->sessionTorrent());
		$this->replay($torrent, array(
			'private'       => true,
			'announce_list' => array(array('http://new.test/announce', 'http://new.test/announce2')),
			'comment'       => 'all three',
		));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'          => $this->bstr('http://new.test/announce'),
			'announce-list'     => $this->bannounceList(array(
				array('http://new.test/announce', 'http://new.test/announce2'),
			)),
			'comment'           => $this->bstr('all three'),
			'created by'        => $this->bstr($this->ourCreator()),
			'creation date'     => $this->bint($this->stampedDate($written, $before)),
			'info'              => $this->singleFileInfo(true),
			'libtorrent_resume' => $this->resumeDictionary(),
		));
		$this->assertTrue($written === $expected, 'the three edits land together and nothing else moves');

		$reread = new Torrent($written);
		$this->assertTrue($reread->is_private() === true, 'private reads back');
		$this->assertTrue($reread->comment() === 'all three', 'the comment reads back');
		$this->assertTrue($reread->announce() === 'http://new.test/announce', 'announce reads back');
		$this->assertTrue(!isset($reread->rtorrent), 'and rtorrent is gone');
	}
}
