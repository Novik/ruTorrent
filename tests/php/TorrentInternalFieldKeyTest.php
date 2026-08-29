<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');

/**
 * NOT PART OF THE EQUIVALENCE SET.
 *
 * Every other test in this branch passes both before and after the rework of
 * php/Torrent.php, which is what makes them useful as a check on that merge.
 * This one does not: it is the one behaviour the rework deliberately changes,
 * and it fails on a Torrent that still stores the torrent's keys as dynamic
 * properties. It is here so the difference is written down and measured
 * rather than argued about, and it goes green when the rework lands.
 *
 * What it is about: a torrent's top level dictionary is data, and its keys
 * come from whoever wrote the file. Seven of the names a torrent may use --
 * errors, filename, basedir, pointer, data, log_callback, err_callback -- are
 * also the names of the object's own fields. Assigning the key straight onto
 * $this, as the constructor used to, writes over the field.
 *
 * The one that matters is 'errors'. Torrent::errors() answers out of the same
 * field, so a torrent carrying an 'errors' key makes errors() return that
 * key's value, and every caller in this repository -- retrackers/update.php,
 * edit/action.php, addtorrent.php, rTorrent::sendTorrent(),
 * rTorrent::getSource() -- is guarded by if(!$torrent->errors()). A torrent
 * that parsed perfectly is refused as unreadable, and on the add path
 * addtorrent.php deletes the uploaded file. The key is also lost: a field is
 * not written back out, so the torrent that does get through is not the
 * torrent that was read.
 */
class TorrentInternalFieldKeyTest extends TestCase
{
	use TorrentSequenceFixtures;

	private function torrentWithKey($key, $value)
	{
		$pairs = array(
			'announce' => $this->bstr('http://one.test/announce'),
			'info'     => $this->singleFileInfo(),
			$key       => $value,
		);
		ksort($pairs, SORT_STRING);
		return $this->bdict($pairs);
	}

	/** A torrent carrying an 'errors' key parses, and is not an error. */
	public function testATorrentCarryingAnErrorsKeyIsNotBroken()
	{
		$fixture = $this->torrentWithKey('errors', $this->bstr('a tracker message'));
		$torrent = new Torrent($fixture);
		$this->assertTrue($torrent->errors() === false,
			'a torrent key called errors is not the object error list');
		$this->assertTrue((string)$torrent === $fixture, 'and it is written back out');
	}

	/** The same for the other six. */
	public function testATorrentCarryingAKeyNamedLikeAnyInternalFieldKeepsIt()
	{
		foreach (array('filename', 'basedir', 'pointer', 'data', 'log_callback', 'err_callback') as $key) {
			$fixture = $this->torrentWithKey($key, $this->bstr('kept'));
			$torrent = new Torrent($fixture);
			$this->assertTrue($torrent->errors() === false, "a torrent carrying {$key} parses");
			$this->assertTrue((string)$torrent === $fixture, "and the {$key} key is written back out");
		}
	}

	/**
	 * The consequence, as the callers meet it: the add path refuses a torrent
	 * whose only fault is the name of one of its keys.
	 */
	public function testTheAddPathDoesNotRefuseATorrentOverTheNameOfAKey()
	{
		$torrent = new Torrent($this->torrentWithKey('errors', $this->bstr('a tracker message')));
		$refused = (bool)$torrent->errors();
		$this->assertTrue($refused === false,
			'if(!$torrent->errors()) lets the torrent through to rtorrent');
	}
}
