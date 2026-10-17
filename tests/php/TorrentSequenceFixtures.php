<?php

require_once(dirname(__FILE__) . '/TestCase.php');
require_once(dirname(__FILE__) . '/../../php/Torrent.php');

/**
 * Bencode fixtures for the tests that replay a script's call sequence against
 * Torrent.
 *
 * A fixture is built here, by hand, so that it is its own expected output: the
 * class under test must never be the thing that produced the bytes a test
 * compares against. Dictionary keys are written in the order a bencoded
 * dictionary carries them -- ksort(SORT_STRING), i.e. plain byte order -- and
 * bdict() refuses an unsorted one rather than let a mis-ordered fixture pass
 * for a mis-ordered encoder.
 *
 * This is a trait and not a base class on purpose: the test runner only
 * instantiates classes whose immediate parent is TestCase, so a shared base
 * class would silently stop every test in this directory from running.
 */
trait TorrentSequenceFixtures
{
	// ---- bencode ---------------------------------------------------------

	protected function bstr($string)
	{
		return strlen($string) . ':' . $string;
	}

	protected function bint($integer)
	{
		return 'i' . $integer . 'e';
	}

	protected function blist($items)
	{
		return 'l' . implode('', $items) . 'e';
	}

	/** A bencoded dictionary. $pairs maps key => already encoded value, and
	 * must be given in byte order. */
	protected function bdict($pairs)
	{
		$previous = null;
		$encoded = 'd';
		foreach ($pairs as $key => $value) {
			$key = strval($key);
			if (!is_null($previous) && strcmp($previous, $key) >= 0) {
				throw new Exception("Fixture keys out of order: '{$previous}' before '{$key}'");
			}
			$previous = $key;
			$encoded .= $this->bstr($key) . $value;
		}
		return $encoded . 'e';
	}

	/** A bencoded list of bencoded lists of strings, i.e. an announce-list. */
	protected function bannounceList($groups)
	{
		$encoded = array();
		foreach ($groups as $group) {
			$strings = array();
			foreach ($group as $tracker) {
				$strings[] = $this->bstr($tracker);
			}
			$encoded[] = $this->blist($strings);
		}
		return $this->blist($encoded);
	}

	// ---- pieces of a torrent ---------------------------------------------

	protected function pieces()
	{
		return str_repeat("\x01", 20);
	}

	/** The info dictionary of a single file torrent. */
	protected function singleFileInfo($private = null)
	{
		$pairs = array(
			'length'       => $this->bint(1024),
			'name'         => $this->bstr('test.data'),
			'piece length' => $this->bint(16384),
			'pieces'       => $this->bstr($this->pieces()),
		);
		if (!is_null($private)) {
			$pairs['private'] = $this->bint($private ? 1 : 0);
		}
		return $this->bdict($pairs);
	}

	/** The info dictionary of a two file torrent. */
	protected function multiFileInfo($private = null)
	{
		$pairs = array(
			'files' => $this->blist(array(
				$this->bdict(array(
					'length' => $this->bint(600),
					'path'   => $this->blist(array($this->bstr('one.data'))),
				)),
				$this->bdict(array(
					'length' => $this->bint(400),
					'path'   => $this->blist(array($this->bstr('sub'), $this->bstr('two.data'))),
				)),
			)),
			'name'         => $this->bstr('payload'),
			'piece length' => $this->bint(16384),
			'pieces'       => $this->bstr($this->pieces()),
		);
		if (!is_null($private)) {
			$pairs['private'] = $this->bint($private ? 1 : 0);
		}
		return $this->bdict($pairs);
	}

	protected function resumeDictionary()
	{
		return $this->bdict(array(
			'bitfield' => $this->bint(1),
			'files'    => $this->blist(array(
				$this->bdict(array(
					'mtime'    => $this->bint(1234567891),
					'priority' => $this->bint(2),
				)),
			)),
		));
	}

	protected function rtorrentDictionary()
	{
		return $this->bdict(array(
			'directory' => $this->bstr('/downloads/test.data'),
			'tied_to_file' => $this->bstr('/session/AAAA.torrent'),
		));
	}

	// ---- who wrote the fixture ------------------------------------------

	/**
	 * The creator every decoded fixture below carries.
	 *
	 * These fixtures stand for a .torrent an external program wrote and
	 * ruTorrent then opened, so both keys have to name that program: they are
	 * what a script that edits the file must leave alone. Every builder below
	 * reads them from here, so no two fixtures can drift apart. An expectation
	 * that spells the same two values out literally instead -- several still
	 * do -- is correct today but will not follow a change made here.
	 */
	protected function sourceCreator()
	{
		return 'uTorrent/3.5.5';
	}

	/** The creation date every decoded fixture below carries. */
	protected function sourceDate()
	{
		return 1234567890;
	}

	// ---- whole torrents --------------------------------------------------

	/** A plain public torrent with a single tracker and no announce-list. */
	protected function announceOnlyTorrent()
	{
		return $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
			'info'          => $this->singleFileInfo(),
		));
	}

	/** A torrent with no tracker at all -- retrackers has a branch for it. */
	protected function trackerlessTorrent()
	{
		return $this->bdict(array(
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
			'info'          => $this->singleFileInfo(),
		));
	}

	/** A torrent that already carries an announce-list of two groups. */
	protected function announceListTorrent()
	{
		return $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'announce-list' => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce', 'udp://two.test/announce2'),
			)),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
			'info'          => $this->singleFileInfo(),
		));
	}

	/** A private, multi file torrent carrying a comment. */
	protected function privateMultiFileTorrent()
	{
		return $this->bdict(array(
			'announce'      => $this->bstr('http://one.test/announce'),
			'comment'       => $this->bstr('from the tracker'),
			'created by'    => $this->bstr($this->sourceCreator()),
			'creation date' => $this->bint($this->sourceDate()),
			'encoding'      => $this->bstr('UTF-8'),
			'info'          => $this->multiFileInfo(true),
		));
	}

	/**
	 * A torrent as rtorrent leaves it in its session directory: the two keys
	 * it adds are there, and this is what every one of these scripts actually
	 * opens.
	 */
	protected function sessionTorrent()
	{
		return $this->bdict(array(
			'announce'          => $this->bstr('http://one.test/announce'),
			'announce-list'     => $this->bannounceList(array(
				array('http://one.test/announce'),
				array('udp://two.test/announce'),
			)),
			'created by'        => $this->bstr($this->sourceCreator()),
			'creation date'     => $this->bint($this->sourceDate()),
			'info'              => $this->singleFileInfo(),
			'libtorrent_resume' => $this->resumeDictionary(),
			'rtorrent'          => $this->rtorrentDictionary(),
		));
	}

	// ---- reading what was written ----------------------------------------

	/**
	 * The value the class stamps into 'created by' for a torrent it BUILT.
	 *
	 * Torrent::touch() writes it only for such a torrent: from the
	 * constructor's build branch, and again from every setter called
	 * afterwards. A setter on a torrent that was decoded from bytes calls
	 * touch() too, but there it writes nothing, because 'created by' and
	 * 'creation date' name whoever produced the metainfo and an edit is not
	 * authorship. So this is the expected creator of a create-path torrent,
	 * and never of one of the decoded fixtures above -- those keep
	 * sourceCreator().
	 */
	protected function ourCreator()
	{
		return 'ruTorrent (PHP Class - Adrien Gibrat)';
	}

	/**
	 * The 'creation date' of a written torrent, read out of the bytes rather
	 * than off the object: which of the two stores holds it is exactly what
	 * these tests must not depend on.
	 */
	protected function writtenCreationDate($written)
	{
		return preg_match('/13:creation datei(\d+)e/', $written, $match) ? intval($match[1]) : null;
	}

	/** Assert that the class stamped a torrent it BUILT, and hand the value
	 * back so the expected bytes can be built with it. The value is the time
	 * of the write rather than of the build: touch() runs again on every
	 * setter called after the build, and each one moves the date forward.
	 * Only the create path reaches this: see ourCreator(). */
	protected function stampedDate($written, $notBefore)
	{
		$stamped = $this->writtenCreationDate($written);
		$this->assertTrue(!is_null($stamped) && $stamped >= $notBefore && $stamped <= time(),
			'The class stamped the torrent it built with the time of the write');
		return $stamped;
	}

	/** Assert that a script's setters left a decoded torrent's own creation
	 * date alone, and hand it back so the expected bytes can be built with
	 * it. The value is the fixture's, so this fails both if the date moved
	 * and if the key was dropped. */
	protected function preservedDate($written)
	{
		$this->assertTrue($this->writtenCreationDate($written) === $this->sourceDate(),
			'The setters left the creation date the file came with alone');
		return $this->sourceDate();
	}
}
