<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/BencodeReaderFixture.php');
require_once(__DIR__ . '/../../php/Torrent.php');

/**
 * What a bencoded integer is worth after this class has read it and written
 * it back out.
 *
 * It matters for the same reason the dictionary round trip does, and
 * TorrentDictionaryRoundTripTest states the reason at length: every torrent
 * ruTorrent loads is re-encoded by this class before rtorrent sees it, and
 * Torrent::hash_info() takes the info hash over that re-encoding. A length
 * that comes back as a different number is a torrent describing a different
 * file, under an info hash that is no longer the file's.
 *
 * The same invariant applies: for any input, either the bytes read are the
 * bytes written and the info hash is the file's own, or errors() says the
 * class cannot do that. A third outcome -- a different number, accepted --
 * is what these tests are here to exclude.
 *
 * Three ranges behave differently and all three are pinned below. Up to
 * PHP_INT_MAX a value is exactly representable and must survive. Beyond it
 * no PHP scalar holds the value, so it is reported. In between sits the
 * range a float silently loses: above 2^53 consecutive integers stop being
 * distinguishable, and from 1e16 up PHP writes a double as 1.0E+16, which
 * is not a bencoded integer and which a client reading the output would
 * refuse outright.
 */
class TorrentIntegerRoundTripTest extends TestCase
{
	use BencodeReaderFixture;

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

	/**
	 * Metainfo carrying $digits verbatim, as the value of a top level key or
	 * of info's length.
	 *
	 * The digits go in as text rather than as a PHP integer because the
	 * values worth testing are the ones a PHP integer cannot hold: a literal
	 * past PHP_INT_MAX is a float by the time the test file is compiled, and
	 * would state a different fixture from the one written here.
	 */
	private function metainfoWithInteger($digits, $where)
	{
		$bytes = ($where === 'info')
			? $this->bencode($this->metainfoAround($this->info(array('length' => 0))))
			: $this->bencode($this->metainfoAround($this->info(), array('z' => 0)));
		$marker = ($where === 'info') ? '6:lengthi0e' : '1:zi0e';
		$this->assertTrue(substr_count($bytes, $marker) === 1,
			'the fixture carries ' . $marker . ' once');
		return str_replace($marker, str_replace('i0e', 'i' . $digits . 'e', $marker), $bytes);
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
	 * The invariant, on $bytes. $expected names the branch the input is
	 * supposed to take, so a change that starts refusing everything fails
	 * here as loudly as one that starts accepting everything.
	 */
	private function assertWrittenBackOrReported($what, $bytes, $expected)
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
		$this->assertTrue(strpos($this->messages($errors), 'integer') !== false,
			$what . ': the report names an integer: ' . $this->messages($errors));
		// intval() saturates rather than failing, so a value past PHP_INT_MAX
		// turns into PHP_INT_MAX: a number the torrent never carried, in the
		// range a reader has no reason to doubt. Reported or not, that one is
		// not an answer this class may write.
		$this->assertTrue(strpos($written, (string)PHP_INT_MAX) === false,
			$what . ': what it writes is not PHP_INT_MAX'
			. ' (wrote ' . $this->printable($written) . ')');
		// What every caller in this repository does with that report.
		$this->assertTrue(!(!$torrent->errors()), $what . ': if(!$torrent->errors()) refuses it');
	}

	/** Both places an integer sits in metainfo, for one value. */
	private function assertIntegerEverywhere($what, $digits, $expected)
	{
		$this->assertWrittenBackOrReported($what . ', beside info',
			$this->metainfoWithInteger($digits, 'top level'), $expected);
		$this->assertWrittenBackOrReported($what . ', as the length inside info',
			$this->metainfoWithInteger($digits, 'info'), $expected);
	}

	/** The values a torrent actually carries: sizes, dates, piece lengths. */
	public function testOrdinaryIntegersAreWrittenBack()
	{
		foreach (array(
			'zero' => '0',
			'one' => '1',
			'minus one' => '-1',
			'a piece length' => '16384',
			'a creation date' => '1700000000',
			'a terabyte' => '1099511627776',
		) as $what => $digits) {
			$this->assertIntegerEverywhere($what, $digits, 'written back');
		}
	}

	/**
	 * Above 2^53 a double can no longer tell consecutive integers apart, so
	 * the odd ones come back as their even neighbour. 2^53 itself and the
	 * value below it are here so the test says where the loss begins rather
	 * than only that it exists.
	 */
	public function testIntegersAroundTwoToTheFiftyThreeAreWrittenBack()
	{
		foreach (array(
			'2^53 - 1' => '9007199254740991',
			'2^53' => '9007199254740992',
			'2^53 + 1' => '9007199254740993',
			'2^53 + 3' => '9007199254740995',
		) as $what => $digits) {
			$this->assertIntegerEverywhere($what, $digits, 'written back');
		}
	}

	/**
	 * From 1e16 up a double is written as 1.0E+16. That is not a bencoded
	 * integer, so the output is not metainfo any more -- the strict reader
	 * in assertWrittenBackOrReported refuses it, which is what a client
	 * reading the written torrent would do.
	 */
	public function testIntegersPhpWouldWriteInExponentNotationAreWrittenBack()
	{
		foreach (array(
			'1e16' => '10000000000000000',
			'1e17 + 1' => '100000000000000001',
			'2^62' => '4611686018427387904',
			'PHP_INT_MAX' => '9223372036854775807',
			'PHP_INT_MIN' => '-9223372036854775808',
		) as $what => $digits) {
			$this->assertIntegerEverywhere($what, $digits, 'written back');
		}
	}

	/**
	 * BEP 3 puts no bound on a bencoded integer, and past PHP_INT_MAX no PHP
	 * scalar holds the value. There is nothing to write back, so it is
	 * reported rather than written back as a number the torrent never
	 * carried.
	 */
	public function testIntegersBeyondWhatPhpHoldsAreReported()
	{
		foreach (array(
			'PHP_INT_MAX + 1' => '9223372036854775808',
			'PHP_INT_MIN - 1' => '-9223372036854775809',
			'twenty digits' => '99999999999999999999',
			'twenty six digits' => '99999999999999999999999999',
		) as $what => $digits) {
			$this->assertIntegerEverywhere($what, $digits, 'reported');
		}
	}

	/**
	 * A body between i and e that is not a decimal number at all. None of
	 * these can be written back as they were read, and before they were
	 * reported each was accepted as some other number: "+5" as 5, "12a" as
	 * 12, an empty body as 0.
	 */
	public function testIntegerBodiesThatAreNotNumbersAreReported()
	{
		foreach (array(
			'a leading plus' => '+5',
			'trailing rubbish' => '12a',
			'a decimal point' => '1.5',
			'an empty body' => '',
			'a leading zero' => '0123',
			'negative zero' => '-0',
		) as $what => $digits) {
			$this->assertIntegerEverywhere($what, $digits, 'reported');
		}
	}

	/**
	 * The report has to name the value, because the caller's next question
	 * is which key in the torrent it came from and a message that only says
	 * an integer was refused does not help answer it.
	 */
	public function testTheReportNamesTheValue()
	{
		$torrent = new Torrent($this->metainfoWithInteger('99999999999999999999', 'info'));
		$this->assertTrue(strpos($this->messages($torrent->errors()), '99999999999999999999') !== false,
			'the report quotes the integer it could not write back: '
			. $this->messages($torrent->errors()));
	}

	/**
	 * What the written value is, independent of the invariant above, so a
	 * reader of this file can see the sizes involved without running it.
	 */
	public function testTheWrittenValueIsTheValueRead()
	{
		foreach (array('9007199254740993', '9223372036854775807', '-9223372036854775808') as $digits) {
			$written = (string)(new Torrent($this->metainfoWithInteger($digits, 'info')));
			$this->assertTrue(strpos($written, '6:lengthi' . $digits . 'e') !== false,
				'the length written back is ' . $digits . ', not something near it'
				. ' (wrote ' . $this->printable($written) . ')');
		}
	}

	/**
	 * An integer is an integer whatever php.ini says. A double is not: the
	 * digits PHP prints for one come from the precision setting, so before
	 * this was an integer the bytes written depended on configuration that
	 * has nothing to do with the torrent.
	 */
	public function testTheWrittenValueDoesNotDependOnThePrecisionSetting()
	{
		$was = ini_get('precision');
		$bytes = $this->metainfoWithInteger('9007199254740993', 'info');
		foreach (array('14', '17', '-1') as $precision) {
			ini_set('precision', $precision);
			$written = (string)(new Torrent($bytes));
			$this->assertTrue($written === $bytes,
				'with precision=' . $precision . ' the torrent is written back unchanged'
				. ' (wrote ' . $this->printable($written) . ')');
		}
		ini_set('precision', $was);
	}

	/**
	 * The type and not only the bytes. A caller that compares a length with
	 * === against a number it worked out itself gets the answer it expects,
	 * and the value no longer carries the float formatting that php.ini
	 * decides.
	 */
	public function testADecodedIntegerIsAnInteger()
	{
		$torrent = new Torrent($this->metainfoWithInteger('1099511627776', 'info'));
		$this->assertTrue($torrent->info['length'] === 1099511627776,
			'a decoded length is an integer rather than a float');
		$this->assertTrue($torrent->piece_length() === 16384,
			'a decoded piece length is an integer rather than a float');
	}

	/**
	 * A negative zero is not a bencoded integer and is reported as one of
	 * the bad bodies above, but it is still what the torrent carries, and
	 * writing 0 in its place would be exactly the silent change the report
	 * exists to prevent. It goes back out as it came in.
	 */
	public function testANegativeZeroIsReportedAndWrittenBackUnchanged()
	{
		$bytes = $this->metainfoWithInteger('-0', 'info');
		$torrent = new Torrent($bytes);
		$this->assertTrue($torrent->errors() !== false, 'a negative zero is reported');
		$this->assertTrue((string)$torrent === $bytes,
			'a negative zero is written back as it was read, not as 0'
			. ' (wrote ' . $this->printable((string)$torrent) . ')');
	}

	public function testTheBencodeReaderIsStrict()
	{
		$this->assertBencodeReaderIsStrict();
	}
}
