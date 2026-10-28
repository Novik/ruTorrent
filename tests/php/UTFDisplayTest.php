<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/../../php/utility/utf.php');

class UTFDisplayTest extends TestCase
{
	// What display() makes of valid text once an invalid byte sends it down
	// the byte walk: controls but tab, CR and LF as \xNN, a backslash doubled.
	private static function walked($text)
	{
		$map = array('\\'=>'\\\\', "\x7F"=>'\\x7F');
		for($byte = 0; $byte < 0x20; $byte++)
			if(!in_array($byte, array(9, 10, 13), true))
				$map[chr($byte)] = sprintf('\\x%02X', $byte);
		return(strtr($text, $map));
	}

	// display() walks "\xFF" plus $text by its own decoder. It keeps every
	// character of $text exactly when PCRE accepts $text, and what it shows
	// is UTF-8 either way.
	private static function walkMismatch($text)
	{
		$shown = UTF::display("\xFF".$text);
		$kept = ($shown === '\\xFF'.self::walked($text));
		return(($kept !== (preg_match('//u', $text) === 1)) || (preg_match('//u', $shown) !== 1));
	}

	public function testValidUnicodeRemainsByteIdentical()
	{
		foreach(array('', '0', '123', "ASCII \\xFF\t\r\n", "серия 中文 🙂", "\0\x7F",
			"\xC2\x80\xDF\xBF", "\xE0\xA0\x80\xED\x9F\xBF\xEF\xBF\xBF", "\xF0\x90\x80\x80\xF4\x8F\xBF\xBF") as $text)
		{
			$this->assertTrue(UTF::isValid($text) === true, 'strict UTF-8 is accepted');
			$this->assertTrue(UTF::display($text) === $text, 'valid text, controls and literal backslashes remain unchanged');
		}
	}

	public function testUnknownBytesKeepValidFragmentsAndLogLines()
	{
		$raw = "中文/серия\t\xF1\xE5\xF0\xE8\xFF\r\n\\xFF\0\x7F🙂";
		$expected = "中文/серия\t\\xF1\\xE5\\xF0\\xE8\\xFF\r\n\\\\xFF\\x00\\x7F🙂";
		$this->assertTrue(UTF::display($raw) === $expected, 'only invalid bytes and ambiguous controls are escaped in mixed text');
		$this->assertTrue(preg_match('//u', UTF::display($raw)) === 1, 'the display projection is strict UTF-8');
	}

	public function testAValidNameSpellingAnEscapeReadsLikeTheByte()
	{
		$this->assertTrue(UTF::display('a\\xFF.mkv') === UTF::display("a\xFF.mkv"),
			'labels are not unique across valid and invalid names, so names are compared by their bytes');
		$this->assertTrue(UTF::display("a\\xFF\xFF") === 'a\\\\xFF\\xFF',
			'inside one invalid name a literal backslash is doubled, so it reads apart from an escaped byte');
	}

	public function testInvalidSequencesAndNonStringsAreRejected()
	{
		$diagnostics = array();
		set_error_handler(function($number, $message) use (&$diagnostics) {
			$diagnostics[] = $message;
			return true;
		});
		try
		{
			foreach(array("\x80", "\xC0\x80", "\xC1\xBF", "\xE0\x9F\xBF", "\xED\xA0\x80", "\xF0\x8F\xBF\xBF",
				"\xF4\x90\x80\x80", "\xF5\x80\x80\x80", "\xFF", "\xE2\x82", "\xC2", "\xF0\x90\x80", "\xC2A") as $text)
			{
				$this->assertTrue(UTF::isValid($text) === false, 'malformed, overlong, surrogate and out-of-range sequences are rejected');
				$this->assertTrue(preg_match('//u', UTF::display($text)) === 1, 'each malformed input has a valid display projection');
			}
		}
		finally
		{
			restore_error_handler();
		}
		$this->assertTrue($diagnostics === array(), 'a sequence cut short at the end is not read past the string');
		foreach(array(null, false, 12, array(), new stdClass()) as $value)
			$this->assertTrue(UTF::isValid($value) === false, 'only strings can supply a Unicode label');
		$this->assertTrue((UTF::display(12) === '12') && (UTF::display(null) === ''), 'display() shows a scalar as its string');
	}

	public function testControlBoundary()
	{
		$this->assertTrue(UTF::display("a\x1F b\x7F\xFF") === 'a\\x1F b\\x7F\\xFF',
			'U+001F and U+007F are escaped while a space is kept');
	}

	public function testTheByteWalkAgreesWithPcreOnEveryOneAndTwoByteString()
	{
		$mismatches = 0;
		for($first = 0; $first < 256; $first++)
		{
			if(self::walkMismatch(chr($first)))
				$mismatches++;
			for($second = 0; $second < 256; $second++)
				if(self::walkMismatch(chr($first).chr($second)))
					$mismatches++;
		}
		$this->assertTrue($mismatches === 0, 'display() keeps exactly the one/two-byte strings PCRE accepts');
	}

	public function testTheByteWalkAgreesWithPcreOnLongSequenceBoundaries()
	{
		$mismatches = 0;
		foreach(array(0xDF, 0xE0, 0xE1, 0xEC, 0xED, 0xEE, 0xEF, 0xF0, 0xF1, 0xF3, 0xF4, 0xF5) as $first)
			foreach(array(0x7F, 0x80, 0x8F, 0x90, 0x9F, 0xA0, 0xBF, 0xC0) as $second)
				foreach(array(0x7F, 0x80, 0xBF, 0xC0) as $last)
					foreach(array('', "\x80") as $fourth)
						if(self::walkMismatch(chr($first).chr($second).chr($last).$fourth))
							$mismatches++;
		$this->assertTrue($mismatches === 0, 'three/four-byte lower, upper, surrogate and truncation boundaries agree');
	}

	public function testMixedByteCorpusRetainsEachValidFragment()
	{
		$parts = array('я', '界', '🙂', 'A', '\\', "\n", "\t", "\xFF", "\xC0", "\xF4");
		$shown = array('я', '界', '🙂', 'A', '\\\\', "\n", "\t", '\\xFF', '\\xC0', '\\xF4');
		$seed = 1847;
		$mismatches = 0;
		for($case = 0; $case < 1000; $case++)
		{
			$raw = "\xFF";
			$expected = '\\xFF';
			for($part = 0; $part < 40; $part++)
			{
				$seed = ($seed * 16807) % 2147483647;
				$index = $seed % count($parts);
				$raw .= $parts[$index];
				$expected .= $shown[$index];
			}
			if(UTF::display($raw) !== $expected) $mismatches++;
		}
		$this->assertTrue($mismatches === 0, 'one thousand deterministic mixed strings preserve Unicode, whitespace and escaped-byte identity');
	}
}
