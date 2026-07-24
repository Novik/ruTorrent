<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/minifier.php');

// Regression tests for JShrink regex minification.
//
// A '/' that appears inside a regex character class ([...]) is a literal
// slash, not the end of the regex literal. Older JShrink treated the first
// unescaped '/' as the terminator, so a pattern like /[^/]/ was cut mid-regex
// and everything after it was mangled -- producing an "unterminated regular
// expression literal" SyntaxError in the browser. Because getplugins.php
// minifies the whole concatenated plugin bundle in one pass, a single such
// regex in any plugin took the entire UI down (empty torrent list).
//
// v1.8.1 tracks the character class and only ends the regex on a '/' that is
// outside a class. These tests lock that behaviour in.
class MinifierTest extends TestCase
{
	private function minify($js)
	{
		return \JShrink\Minifier::minify($js);
	}

	public function testClassInternalSlashIsNotTheRegexTerminator()
	{
		$min = $this->minify("var m = String(x).match(/a\\/([^/]+)\\//);\nvar SENTINEL = 1;");
		$this->assertTrue(strpos($min, 'SENTINEL') !== false,
			'code after a class-internal-slash regex must survive minification (no truncation)');
		$this->assertTrue(strpos($min, '([^/]+)') !== false,
			'the character class must be preserved intact');
	}

	public function testAutodlThemesRegex()
	{
		// The exact regex that broke plugin loading in production.
		$min = $this->minify("var p = theme.path.match(/themes\\/([^/]+)\\//);\nvar AFTER = 2;");
		$this->assertTrue(strpos($min, 'AFTER') !== false,
			'the themes regex must not truncate the following code');
		$this->assertTrue(strpos($min, 'themes\\/([^/]+)\\/') !== false,
			'the themes regex literal must be preserved');
	}

	public function testUriParserCharacterClasses()
	{
		// Announce-URL parser used by several plugins: multiple '/'-in-class.
		$min = $this->minify("var u = s.match(/^(?:([^:/?#]+):)?([^?#/]*)/);\nvar TAIL = 3;");
		$this->assertTrue(strpos($min, 'TAIL') !== false,
			'the URI-parser regex must not truncate the following code');
		$this->assertTrue(strpos($min, '[^:/?#]') !== false,
			'the URI character class must be preserved');
	}

	public function testPlainRegexStillMinifies()
	{
		// Regression guard: an ordinary regex (no character class) is unaffected.
		$min = $this->minify("var r = x.replace(/foo\\/bar/g, '');\nvar Z = 4;");
		$this->assertTrue(strpos($min, 'Z') !== false && strpos($min, 'foo\\/bar') !== false,
			'a plain regex still minifies and the following code survives');
	}

	public function testDivisionIsNotMistakenForRegex()
	{
		// Regression guard: '/' used as division must not begin a regex parse.
		$min = $this->minify("var q = (a + b) / c / d;\nvar W = 5;");
		$this->assertTrue(strpos($min, 'W') !== false,
			'division operators must not be parsed as a regex literal');
	}
}
