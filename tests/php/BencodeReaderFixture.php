<?php

/**
 * A bencode reader for tests, independent of php/Torrent.php.
 *
 * A test that asks the class under test to parse its own output can only show
 * that the class is self-consistent, which is the one thing a client does not
 * care about. Everything here is written from BEP 3 instead, and it is strict
 * on purpose: a malformed number, an unterminated container, a string shorter
 * than its own length, a dictionary key that is not a string or a pair of keys
 * out of order is an exception rather than a best effort. Anything it accepts
 * is metainfo a client would accept, so acceptance is worth asserting.
 *
 * assertBencodeReaderIsStrict() pins that strictness, so a suite using this
 * trait proves the reader still refuses what it claims to refuse.
 */
trait BencodeReaderFixture
{
	/** Read one bencoded value at $at, moving $at past it. */
	protected function bdecode($data, &$at)
	{
		if (!isset($data[$at])) {
			throw new Exception('a value was expected at offset ' . $at);
		}
		$type = $data[$at];
		if ($type === 'i') {
			return $this->bdecodeInteger($data, $at);
		}
		if (($type === 'l') || ($type === 'd')) {
			return $this->bdecodeContainer($data, $at, $type);
		}
		return $this->bdecodeString($data, $at);
	}

	private function bdecodeInteger($data, &$at)
	{
		$end = strpos($data, 'e', $at);
		if ($end === false) {
			throw new Exception('an integer without its terminator at offset ' . $at);
		}
		$digits = substr($data, $at + 1, $end - $at - 1);
		if (preg_match('/^(0|-?[1-9][0-9]*)$/D', $digits) !== 1) {
			throw new Exception('not a bencoded integer: ' . json_encode($digits));
		}
		$at = $end + 1;
		return (int)$digits;
	}

	private function bdecodeContainer($data, &$at, $type)
	{
		$at++;
		$value = array();
		// Kept beside $value because PHP cannot hold a key of "0" as a string,
		// which is the whole subject of some of the suites using this trait:
		// the reader must not lose a distinction it is there to measure.
		$keys = array();
		while (isset($data[$at]) && ($data[$at] !== 'e')) {
			if ($type === 'l') {
				$value[] = $this->bdecode($data, $at);
				continue;
			}
			$key = $this->bdecodeString($data, $at);
			if ($keys && (strcmp(end($keys), $key) >= 0)) {
				throw new Exception('dictionary keys out of order or repeated at offset ' . $at
					. ': ' . json_encode(end($keys)) . ' then ' . json_encode($key));
			}
			$keys[] = $key;
			$value[$key] = $this->bdecode($data, $at);
		}
		if (!isset($data[$at])) {
			throw new Exception('a container without its terminator');
		}
		$at++;
		return ($type === 'd') ? new BencodeDictionary($keys, $value) : $value;
	}

	/** A string, and the only shape a dictionary key may take. */
	private function bdecodeString($data, &$at)
	{
		if (isset($data[$at]) && (strpos('ild', $data[$at]) !== false)) {
			throw new Exception('a ' . $data[$at] . ' value where a string was required, at offset ' . $at);
		}
		$colon = strpos($data, ':', $at);
		if ($colon === false) {
			throw new Exception('a string without its length at offset ' . $at);
		}
		$length = substr($data, $at, $colon - $at);
		if (preg_match('/^(0|[1-9][0-9]*)$/D', $length) !== 1) {
			throw new Exception('not a bencoded string length: ' . json_encode($length));
		}
		if (($colon + 1 + (int)$length) > strlen($data)) {
			throw new Exception('a string shorter than the ' . $length . ' bytes it declares');
		}
		$at = $colon + 1 + (int)$length;
		return substr($data, $colon + 1, (int)$length);
	}

	/** The one value in $bytes, which has to account for all of $bytes. */
	protected function bencodeValue($bytes, $what)
	{
		$at = 0;
		$parsed = $this->bdecode($bytes, $at);
		$this->assertTrue($at === strlen($bytes),
			$what . ': the value parses with nothing left over (' . $at . ' of ' . strlen($bytes) . ')');
		return $parsed;
	}

	/** The metainfo in $bytes: a dictionary carrying an info dictionary. */
	protected function metainfo($bytes, $what)
	{
		$parsed = $this->bencodeValue($bytes, $what);
		$this->assertTrue($parsed instanceof BencodeDictionary,
			$what . ': the metainfo is a dictionary');
		$info = $parsed->get('info');
		$this->assertTrue($info instanceof BencodeDictionary,
			$what . ': it has an info dictionary in it');
		return $info;
	}

	/**
	 * Write $value as bencode: a BencodeDictionary as a dictionary with its
	 * keys in the byte order a dictionary requires, a PHP array as a list, an
	 * integer as an integer and anything else as a string.
	 *
	 * A fixture written by hand is a run of lengths and terminators to count,
	 * and a miscount reads as a defect in the class under test. This states
	 * the fixture as a value instead, and it can state the dictionaries a PHP
	 * array cannot hold.
	 */
	protected function bencode($value)
	{
		if ($value instanceof BencodeDictionary) {
			$keys = $value->keys();
			usort($keys, 'strcmp');
			$out = 'd';
			foreach ($keys as $key) {
				$out .= strlen($key) . ':' . $key . $this->bencode($value->get($key));
			}
			return $out . 'e';
		}
		if (is_array($value)) {
			$out = 'l';
			foreach ($value as $item) {
				$out .= $this->bencode($item);
			}
			return $out . 'e';
		}
		if (is_int($value)) {
			return 'i' . $value . 'e';
		}
		return strlen($value) . ':' . $value;
	}

	/** A dictionary fixture, keeping each key as the string it is written as. */
	protected function dict($pairs)
	{
		$keys = array();
		foreach ($pairs as $key => $ignored) {
			$keys[] = (string)$key;
		}
		return new BencodeDictionary($keys, $pairs);
	}

	protected function assertBencodeReaderIsStrict()
	{
		foreach (array(
			'd3:fooe' => 'a dictionary with a key and no value',
			'di1ei2ee' => 'a dictionary key that is an integer',
			'dl1:ae1:be' => 'a dictionary key that is a list',
			'd1:b1:x1:a1:ye' => 'dictionary keys out of order',
			'd1:a1:x1:a1:ye' => 'a repeated dictionary key',
			'i-0e' => 'a negative zero',
			'i01e' => 'a leading zero',
			'ie' => 'an empty integer',
			'3:ab' => 'a string shorter than its length',
			'l1:ae1:b' => 'bytes after the end of the value',
			'd1:a1:b' => 'a dictionary without its terminator',
			'' => 'no value at all',
		) as $bytes => $what) {
			$refused = false;
			try {
				$at = 0;
				$this->bdecode($bytes, $at);
				// Not an exception, but not the whole input either.
				$refused = ($at !== strlen($bytes));
			} catch (Exception $error) {
				$refused = true;
			}
			$this->assertTrue($refused, 'the bencode reader refuses ' . $what);
		}
	}
}

/**
 * A bencoded dictionary, holding its keys as the strings they were written as.
 *
 * PHP coerces a numeric-string array key to an integer, so an array cannot
 * record the difference between the dictionary {"0": x} and the list [x], and
 * cannot record that a dictionary was empty rather than a list. A reader meant
 * to measure how a dictionary survives a round trip has to keep what an array
 * drops, which is why keys live in their own list here.
 */
class BencodeDictionary
{
	private $keys;
	private $values;

	public function __construct($keys, $values)
	{
		$this->keys = $keys;
		$this->values = $values;
	}

	/** The keys, in the order they were written. */
	public function keys()
	{
		return $this->keys;
	}

	public function has($key)
	{
		return in_array((string)$key, $this->keys, true);
	}

	/** The value of $key, or null when the dictionary has no such key. */
	public function get($key)
	{
		return $this->has($key) ? $this->values[$key] : null;
	}

	public function count()
	{
		return count($this->keys);
	}
}
