<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TorrentSequenceFixtures.php');

/**
 * Run the torrent-editing block from plugins/edit/action.php against real
 * Torrent objects, then assert the bencoded bytes handed to the daemon.
 * The RPC, request parsing and error paths outside that block are not exercised.
 */
class EditActionSequenceTest extends TestCase
{
	use TorrentSequenceFixtures;

	/** Extract all edits, refusing a missing anchor or a truncated sequence. */
	private static function editSequenceSource()
	{
		static $block = null;
		if ($block !== null) {
			return $block;
		}
		$path = __DIR__ . '/../../../plugins/edit/action.php';
		$source = file_get_contents($path);
		if ($source === false) {
			throw new RuntimeException("plugins/edit/action.php could not be read");
		}
		$startAnchor = 'if( !$torrent->errors() )';
		$endAnchor = "unset(\$torrent->{'rtorrent'});";
		$start = strpos($source, $startAnchor);
		if ($start === false) {
			throw new RuntimeException(
				"plugins/edit/action.php no longer contains {$startAnchor}; the edit sequence cannot be located");
		}
		$brace = strpos($source, '{', $start + strlen($startAnchor));
		$end = strpos($source, $endAnchor, $start);
		if ($brace === false || $end === false) {
			throw new RuntimeException(
				"plugins/edit/action.php no longer contains the end of the edit sequence");
		}
		// Into a local, not straight into the memo: a guard below that fires
		// after $block was already filled would abort only the FIRST test and
		// let the other ten run on an extraction it had just rejected.
		$cut = substr($source, $brace + 1, ($end + strlen($endAnchor)) - ($brace + 1));
		// A later setter would escape these assertions, so require the entire edit.
		$tailStart = $end + strlen($endAnchor);
		$handover = strpos($source, 'rTorrent::sendTorrent(', $tailStart);
		if ($handover === false) {
			throw new RuntimeException(
				"plugins/edit/action.php no longer hands the edited torrent to rTorrent::sendTorrent();"
				. " the extent of the edit sequence cannot be established");
		}
		$tail = substr($source, $tailStart, $handover - $tailStart);
		if (strpos($tail, '$torrent') !== false) {
			throw new RuntimeException(
				"plugins/edit/action.php uses \$torrent after {$endAnchor} and before"
				. " rTorrent::sendTorrent(); the extracted sequence is no longer the whole edit");
		}
		foreach (array('$req', 'rXMLRPC', 'sendTorrent', '$errors') as $beyond) {
			if (strpos($cut, $beyond) !== false) {
				throw new RuntimeException(
					"the extracted edit sequence ran past its end anchor: it mentions {$beyond}");
			}
		}
		foreach (array('$setPrivate', '$setTrackers', '$setComment') as $flag) {
			if (strpos($cut, $flag) === false) {
				throw new RuntimeException(
					"the extracted edit sequence is incomplete: it never gates on {$flag}");
			}
		}
		$block = $cut;
		return $block;
	}

	/**
	 * Run that block against $torrent with the variables action.php has
	 * already assembled from the request in scope. $announce_list is what the
	 * script built out of the tracker textarea and $trackersCount is the
	 * number of non-empty lines in it; the three set_* flags are the tick
	 * boxes. eval() shares this method's scope, so the block reads and writes
	 * exactly these locals, as it does the script's globals in production.
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

		eval(self::editSequenceSource());
	}

	// ---- trackers --------------------------------------------------------

	/**
	 * More than one tracker: announce holds the first and announce-list holds
	 * the groups. The list the torrent came with is cleared first, so what is
	 * written is the new list and nothing of the old one.
	 */
	public function testSettingSeveralTrackersReplacesBothKeys()
	{
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
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
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
		$torrent = new Torrent($this->announceListTorrent());
		$this->replay($torrent, array('announce_list' => array(array('http://only.test/announce'))));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://only.test/announce'),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
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
		$torrent = new Torrent($this->announceListTorrent());
		$this->replay($torrent, array('announce_list' => array()));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
			'info'          => $this->singleFileInfo(),
		));
		$this->assertTrue($written === $expected, 'neither tracker key is written');
	}

	// ---- the private flag ------------------------------------------------

	/** Marking a public torrent private adds info/private, which changes its hash. */
	public function testMarkingATorrentPrivateWritesTheFlagIntoInfo()
	{
		$torrent = new Torrent($this->announceOnlyTorrent());
		$was = $torrent->hash_info();
		$this->assertTrue($torrent->is_private() === false, 'the fixture is public');

		$this->replay($torrent, array('private' => true));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
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
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$this->assertTrue($torrent->is_private() === true, 'the fixture is private');

		$this->replay($torrent, array('private' => false));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'comment'       => $this->bstr('from the tracker'),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
			'encoding'      => $this->bstr('UTF-8'),
			'info'          => $this->multiFileInfo(false),
		));
		$this->assertTrue($written === $expected, 'info/private is written as 0 and the files are untouched');
		$this->assertTrue($torrent->is_private() === false, 'the getter reads it as public');
	}

	// ---- the comment -----------------------------------------------------

	public function testSettingACommentReplacesTheOne()
	{
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$this->replay($torrent, array('comment' => "  edited by the owner \n"));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'comment'       => $this->bstr('edited by the owner'),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
			'encoding'      => $this->bstr('UTF-8'),
			'info'          => $this->multiFileInfo(true),
		));
		$this->assertTrue($written === $expected, 'the comment is trimmed and written');
	}

	/** A comment box left blank drops the key. */
	public function testAnEmptyCommentDropsTheKey()
	{
		$torrent = new Torrent($this->privateMultiFileTorrent());
		$this->replay($torrent, array('comment' => "   "));

		$written = (string)$torrent;
		$expected = $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
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
			'created by'        => $this->bstr($this->sourceCreator()),
			'creation date'     => $this->bint($this->sourceDate()),
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
			'created by'        => $this->bstr($this->sourceCreator()),
			'creation date'     => $this->bint($this->sourceDate()),
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

	// ---- the file's own author and date ----------------------------------

	/**
	 * An edit changes trackers, a comment or the private flag. It does not
	 * make ruTorrent the author of the file, and it does not move the day the
	 * release was created -- both of which travel out to the "Created On"
	 * column and to the history plugin as fact.
	 *
	 * Torrent setters preserve both keys while the actual edit block changes
	 * the requested fields. These assertions identify lost authorship directly.
	 */
	public function testAnEditKeepsTheTorrentsOwnAuthorAndCreationDate()
	{
		$torrent = new Torrent($this->announceListTorrent());
		$this->replay($torrent, array(
			'private'       => true,
			'announce_list' => array(array('http://new.test/announce')),
			'comment'       => 'edited',
		));

		$this->assertTrue($torrent->meta('created by') === $this->sourceCreator(),
			'the author the file came with is still the author');
		$this->assertTrue($torrent->meta('creation date') == $this->sourceDate(),
			'and the date it came with is still the date');
	}

	/**
	 * A torrent that carried neither authorship key must not acquire one
	 * from this host's clock or this class's name during an edit.
	 */
	public function testAnEditInventsNeitherKeyForATorrentThatCarriedNone()
	{
		$torrent = new Torrent($this->bdict(array(
			'announce' => $this->bstr('http://one.test/announce'),
			'info'     => $this->singleFileInfo(),
		)));
		$this->assertTrue($torrent->errors() === false, 'the fixture parses');

		$this->replay($torrent, array('comment' => 'edited'));

		$expected = $this->bdict(array(
			'announce' => $this->bstr('http://one.test/announce'),
			'comment'  => $this->bstr('edited'),
			'info'     => $this->singleFileInfo(),
		));
		$this->assertTrue((string)$torrent === $expected, 'neither key is invented');
	}
}
