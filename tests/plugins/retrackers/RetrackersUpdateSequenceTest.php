<?php

// plugins/retrackers/update.php is a top level script: it reads $argv and,
// with no torrent hash on the command line, does nothing but declare its two
// pure helpers and load the plugin's configuration. That makes it includable,
// so clearTracker() and deleteTrackers() below are the real ones and not a
// second implementation of them.
//
// The profile path is pointed at a directory of our own first -- conf/config.php
// reads RU_PROFILE_PATH -- so the settings cache it opens on the way in is an
// empty one, and not whatever this checkout happens to have written there.
$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-retrackers-seq-' . getmypid();

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../../plugins/retrackers/update.php');

/**
 * What plugins/retrackers/update.php does to a torrent.
 *
 * The script itself cannot be called from a test -- it takes a hash on the
 * command line, asks rtorrent for the session path, and hands the result to
 * rTorrent::sendTorrent() over SCGI. What it does in between is a fixed
 * sequence of calls on a Torrent, and that sequence is replayed here against
 * the class, with the script's own clearTracker() and deleteTrackers() driving
 * it.
 *
 * The limitation that carries: this pins the transcription, not the script. An
 * edit to update.php will not fail these tests. They are here for Torrent --
 * the bencoded bytes it hands back are what a regression in the class would
 * corrupt, and they are asserted whole.
 */
class RetrackersUpdateSequenceTest extends TestCase
{
	use TorrentSequenceFixtures;

	public function tearDown()
	{
		// Loading the plugin's configuration created the settings directory.
		$profile = $_ENV['RU_PROFILE_PATH'];
		foreach (array($profile . '/settings', $profile) as $dir) {
			if (is_dir($dir)) {
				@rmdir($dir);
			}
		}
	}

	/** A plugin configuration object, as rRetrackers::load() would return one. */
	private function retrackers($list, $todelete = array(), $addToBegin = 0)
	{
		$trks = new rRetrackers();
		$trks->list = $list;
		$trks->todelete = $todelete;
		$trks->addToBegin = $addToBegin;
		return $trks;
	}

	/**
	 * plugins/retrackers/update.php lines 80-127, verbatim but for the RPC
	 * either side of it. Returns the two flags the script decides whether to
	 * rewrite the torrent at all by.
	 */
	private function replay($torrent, $trks)
	{
		$wasAddition = true;
		$lst = $torrent->announce_list();
		if (!$lst) {
			if (count($trks->list)) {
				if ($torrent->announce()) {
					$torrent->announce_list($trks->addToBegin ?
						array_merge($trks->list, array(array($torrent->announce()))) :
						array_merge(array(array($torrent->announce())), $trks->list));
				} else {
					$torrent->announce($trks->list[0][0]);
					$torrent->announce_list($trks->list);
				}
			} else {
				$wasAddition = false;
			}
		} else {
			$addition = $trks->list;
			foreach ($lst as $group) {
				foreach ($group as $tracker) {
					$addition = clearTracker($addition, $tracker);
				}
			}
			if (count($addition)) {
				$torrent->announce_list($trks->addToBegin ?
					array_merge($addition, $lst) : array_merge($lst, $addition));
			} else {
				$wasAddition = false;
			}
		}

		$wasDeletion = false;
		$lst = $torrent->announce_list();
		if ($lst && count($trks->todelete) && deleteTrackers($lst, $trks->todelete)) {
			$wasDeletion = true;
			$torrent->announce_list($lst);
		}

		if ($wasAddition || $wasDeletion) {
			if (isset($torrent->{'rtorrent'})) {
				unset($torrent->{'rtorrent'});
			}
		}
		return array($wasAddition, $wasDeletion);
	}

	// ---- the helpers the sequence is built out of ------------------------

	public function testTheScriptsHelpersAreTheRealOnes()
	{
		$this->assertTrue(function_exists('clearTracker') && function_exists('deleteTrackers'),
			'update.php was included and its two pure helpers are callable');
		$this->assertTrue(class_exists('rRetrackers'), 'the plugin configuration class is loadable too');
	}

	// ---- a torrent with announce and no announce-list --------------------

	/**
	 * The common case: rtorrent's copy of a single tracker torrent, and one
	 * tracker group to add. The torrent's own announce goes first.
	 */
	public function testAnnounceOnlyTorrentGainsAnAnnounceListEndingWithTheAddition()
	{
		$before = time();
		$torrent = new Torrent($this->announceOnlyTorrent());
		$this->assertTrue($torrent->errors() === false, 'the fixture parses');

		list($wasAddition, $wasDeletion) = $this->replay($torrent,
			$this->retrackers(array(array('http://added.test/announce'))));

		$this->assertTrue($wasAddition === true, 'a tracker was added');
		$this->assertTrue($wasDeletion === false, 'nothing was deleted');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('http://added.test/announce'),
			)),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected,
			'the whole torrent is written back with the addition appended');

		$reread = new Torrent($written);
		$this->assertTrue($reread->announce() === 'http://one.test/announce',
			'the original announce is untouched');
		$this->assertTrue($reread->announce_list() === array(
				array('http://one.test/announce'),
				array('http://added.test/announce')),
			'and it heads the new announce-list');
	}

	/** addToBegin puts the configured groups in front of the torrent's own. */
	public function testAddToBeginPutsTheAdditionFirst()
	{
		$before = time();
		$torrent = new Torrent($this->announceOnlyTorrent());
		list($wasAddition, $wasDeletion) = $this->replay($torrent,
			$this->retrackers(array(array('http://added.test/announce')), array(), 1));

		$this->assertTrue($wasAddition === true && $wasDeletion === false, 'an addition, no deletion');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://added.test/announce'),
				array('http://one.test/announce'),
			)),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected, 'the addition is written in front');
	}

	/**
	 * With no announce at all the script promotes the first configured tracker
	 * into announce, which is the only branch that writes both keys.
	 */
	public function testATrackerlessTorrentGetsAnnounceAndAnnounceList()
	{
		$before = time();
		$torrent = new Torrent($this->trackerlessTorrent());
		$this->assertTrue($torrent->announce() === null, 'the fixture has no announce');

		list($wasAddition,) = $this->replay($torrent, $this->retrackers(array(
			array('http://added.test/announce', 'http://added.test/announce2'),
			array('udp://added.test/announce'),
		)));
		$this->assertTrue($wasAddition === true, 'a tracker was added');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://added.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://added.test/announce', 'http://added.test/announce2'),
				array('udp://added.test/announce'),
			)),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected,
			'the first configured tracker becomes announce and the groups become announce-list');
	}

	// ---- a torrent that already has an announce-list ---------------------

	/** Groups the torrent already carries are dropped from the addition. */
	public function testATrackerAlreadyPresentIsNotAddedTwice()
	{
		$before = time();
		$torrent = new Torrent($this->announceListTorrent());
		list($wasAddition,) = $this->replay($torrent, $this->retrackers(array(
			array('http://one.test/announce'),
			array('http://added.test/announce'),
		)));
		$this->assertTrue($wasAddition === true, 'the group that was not present is still an addition');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce', 'udp://two.test/announce2'),
				array('http://added.test/announce'),
			)),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected,
			'only the group that was missing is appended');
	}

	/**
	 * Nothing to add and nothing to delete: the script never reaches
	 * sendTorrent(), and the torrent must come back byte for byte as it was --
	 * no setter ran, so not even the creation date moved.
	 */
	public function testATorrentThatNeedsNothingIsNotRewritten()
	{
		$fixture = $this->announceListTorrent();
		$torrent = new Torrent($fixture);
		list($wasAddition, $wasDeletion) = $this->replay($torrent,
			$this->retrackers(array(array('http://one.test/announce'))));

		$this->assertTrue($wasAddition === false, 'every configured tracker was already there');
		$this->assertTrue($wasDeletion === false, 'and there was nothing to delete');
		$this->assertTrue((string)$torrent === $fixture,
			'the torrent is unchanged, down to the creation date it came with');
	}

	/** An empty configuration is the same non-event on the no-announce-list branch. */
	public function testAnEmptyConfigurationChangesNothing()
	{
		$fixture = $this->announceOnlyTorrent();
		$torrent = new Torrent($fixture);
		list($wasAddition, $wasDeletion) = $this->replay($torrent, $this->retrackers(array()));

		$this->assertTrue($wasAddition === false && $wasDeletion === false, 'neither flag was raised');
		$this->assertTrue((string)$torrent === $fixture, 'the torrent is unchanged');
	}

	// ---- deletion --------------------------------------------------------

	/** A todelete entry that matches every tracker in a group removes the group. */
	public function testDeletingEveryTrackerInAGroupRemovesTheGroup()
	{
		$before = time();
		$torrent = new Torrent($this->announceListTorrent());
		list($wasAddition, $wasDeletion) = $this->replay($torrent,
			$this->retrackers(array(), array('two.test')));

		$this->assertTrue($wasAddition === false, 'there was nothing to add');
		$this->assertTrue($wasDeletion === true, 'the group was deleted');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList(array(array('http://one.test/announce'))),
			'created by'    => $this->bstr($this->ourCreator()),
			'creation date' => $this->bint($this->stampedDate($written, $before)),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected, 'the emptied group is gone from the written torrent');

		$reread = new Torrent($written);
		$this->assertTrue($reread->announce_list() === array(array('http://one.test/announce')),
			'and it reads back as one group');
	}

	/** Adding and deleting in the same run, on the torrent rtorrent saved. */
	public function testAdditionAndDeletionTogetherOnASessionTorrent()
	{
		$before = time();
		$torrent = new Torrent($this->sessionTorrent());
		list($wasAddition, $wasDeletion) = $this->replay($torrent,
			$this->retrackers(array(array('http://added.test/announce')), array('two.test')));

		$this->assertTrue($wasAddition === true && $wasDeletion === true, 'both flags were raised');

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'          => $this->bstr('http://one.test/announce'),
			'announce-list'     => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('http://added.test/announce'),
			)),
			'created by'        => $this->bstr($this->ourCreator()),
			'creation date'     => $this->bint($this->stampedDate($written, $before)),
			'info'              => $this->singleFileInfo(),
			'libtorrent_resume' => $this->resumeDictionary(),
		));
		$this->assertTrue($written === $expected,
			"the 'rtorrent' key is dropped, resume data and info are kept");

		$reread = new Torrent($written);
		$this->assertTrue(!isset($reread->rtorrent), 'the written torrent no longer carries rtorrent');
		$this->assertTrue(isset($reread->libtorrent_resume), 'it still carries its resume data');
	}

	/**
	 * A session torrent nothing has to be done to keeps its 'rtorrent' key:
	 * the script only drops it on the branch that rewrites the file.
	 */
	public function testASessionTorrentThatNeedsNothingKeepsItsRtorrentKey()
	{
		$fixture = $this->sessionTorrent();
		$torrent = new Torrent($fixture);
		list($wasAddition, $wasDeletion) = $this->replay($torrent, $this->retrackers(array()));

		$this->assertTrue($wasAddition === false && $wasDeletion === false, 'nothing to do');
		$this->assertTrue((string)$torrent === $fixture, 'the session torrent is untouched');
	}

	// ---- the pure helpers on their own -----------------------------------

	public function testClearTrackerDropsAGroupItEmpties()
	{
		$addition = array(array('http://a.test/x'), array('http://b.test/x', 'http://c.test/x'));
		$addition = clearTracker($addition, 'http://a.test/x');
		$this->assertEquals(array(1 => array('http://b.test/x', 'http://c.test/x')), $addition,
			'the emptied group is removed and the other is left alone');
	}

	public function testDeleteTrackersMatchesOnASubstringCaseInsensitively()
	{
		$list = array(array('http://ONE.test/announce'), array('http://two.test/announce'));
		$this->assertTrue(deleteTrackers($list, array('one.TEST')) === true,
			'a case insensitive substring match is a deletion');
		$this->assertEquals(array(1 => array('http://two.test/announce')), $list,
			'and it is taken out of the list by reference');
		$this->assertTrue(deleteTrackers($list, array('nothing.test')) === false,
			'a pattern that matches nothing is not a deletion');
	}
}
