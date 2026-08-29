<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/minifier.php');

// Exposes the protected look-ahead machinery so the buffer contract can be
// asserted directly rather than inferred from minified output.
class MinifierCleanupProbe extends \JShrink\Minifier
{
	public function primeInput($js)
	{
		$this->initialize($js, []);
	}

	public function primeLookAhead($char)
	{
		$this->c = $char;
	}

	public function nextChar()
	{
		return $this->getChar();
	}

	public function runClean()
	{
		$this->clean();
	}

	public function lookAheadIsSet()
	{
		return isset($this->c);
	}

	public function inputIsSet()
	{
		return isset($this->input);
	}

	public function optionsIsSet()
	{
		return isset($this->options);
	}
}

// JShrink's look-ahead buffer ($this->c) and its end-of-run cleanup are gated
// entirely on isset(). getChar() consumes the buffer only when isset($this->c)
// is true, and must leave it empty afterwards or the same character is emitted
// twice. clean() must return the object to the state a freshly constructed one
// is in, which for these untyped declared properties is null (isset() false).
//
// The output tests below are golden values: representative JavaScript in, the
// exact minified string out. They exist so any future change to the cleanup
// path has to produce byte-identical output or fail here. getplugins.php feeds
// the whole concatenated plugin bundle through Minifier::minify() in one pass,
// so a single character of drift there rewrites every plugin in the UI.
class MinifierCleanupTest extends TestCase
{
	private function minify($js)
	{
		return \JShrink\Minifier::minify($js);
	}

	public function testLookAheadBufferIsConsumedExactlyOnce()
	{
		$probe = new MinifierCleanupProbe();
		$probe->primeInput('XY');
		$probe->primeLookAhead('Q');

		$this->assertTrue($probe->lookAheadIsSet(),
			'a primed look-ahead buffer must read as set');
		$this->assertEquals('Q', $probe->nextChar(),
			'getChar() must return the buffered character');
		$this->assertTrue(!$probe->lookAheadIsSet(),
			'getChar() must clear the look-ahead buffer after consuming it');
		$this->assertEquals('X', $probe->nextChar(),
			'the character after the buffered one must come from the input, not the buffer again');
		$this->assertEquals('Y', $probe->nextChar(),
			'the input must keep advancing normally');
	}

	public function testFreshObjectHasNoLookAheadBuffer()
	{
		$probe = new MinifierCleanupProbe();
		$this->assertTrue(!$probe->lookAheadIsSet(),
			'a freshly constructed Minifier must have no look-ahead buffer');
	}

	public function testCleanRestoresTheFreshObjectState()
	{
		$probe = new MinifierCleanupProbe();
		$probe->primeInput('var a = 1;');
		$probe->primeLookAhead('Z');

		$this->assertTrue($probe->inputIsSet(), 'initialize() must set the input');
		$this->assertTrue($probe->optionsIsSet(), 'initialize() must set the options');

		$probe->runClean();

		$this->assertTrue(!$probe->lookAheadIsSet(),
			'clean() must leave the look-ahead buffer unset');
		$this->assertTrue(!$probe->inputIsSet(),
			'clean() must release the input');
		$this->assertTrue(!$probe->optionsIsSet(),
			'clean() must release the options');
	}

	public function testConditionalOneLineComment()
	{
		// Drives processOneLineComments(), which clears the look-ahead buffer
		// and then conditionally rewrites it.
		$this->assertEquals(
			'var a=1;var b=2;var c=3;',
			$this->minify("//@cc_on\nvar a = 1;\n//@if (1)\nvar b = 2;\n//@end\nvar c = 3;\n"),
			'conditional one-line comments minify to their historical output');
	}

	public function testFlaggedMultiLineComment()
	{
		$this->assertEquals(
			"/*! keep me */\nvar a=1;var b=2;",
			$this->minify("/*! keep me */\nvar a=1; /* drop me */ var b = 2;\n"),
			'a flagged comment is kept and an ordinary one dropped');
	}

	public function testDivisionLookahead()
	{
		// Drives getReal(): the buffer is filled with the character after '/'
		// and handed back through getChar().
		$this->assertEquals(
			'var x=a / b;var y=c / d;var z=1;',
			$this->minify("var x = a / b; var y = c /* c */ / d;\nvar z = 1;\n"),
			'division survives the look-ahead round trip');
	}

	public function testRegexCharacterClass()
	{
		$this->assertEquals(
			'var m=s.match(/themes\\/([^/]+)\\//);var t=1;',
			$this->minify("var m = s.match(/themes\\/([^/]+)\\//);\nvar t = 1;\n"),
			'a regex with a class-internal slash is preserved verbatim');
	}

	public function testStringsAndTemplates()
	{
		$this->assertEquals(
			'var s="a//b",t=\'c/*d*\'+e,u=`f/g${h}`;',
			$this->minify("var s = \"a//b\", t = 'c/*d*'+e, u = `f/g\${h}`;\n"),
			'comment-looking sequences inside string and template literals are untouched');
	}

	public function testKeywordThenRegex()
	{
		$this->assertEquals(
			'return/a/.test(x);typeof/b/;do{}while(0);',
			$this->minify("return /a/.test(x); typeof /b/; do { } while (0);\n"),
			'a regex directly after a keyword minifies to its historical output');
	}

	public function testRepeatedMinificationIsStable()
	{
		// Every minify() call builds its own Minifier and ends in clean(), so a
		// cleanup that left residue behind would show up as run-to-run drift.
		$js = "//@cc_on\nvar a = 1; /*! flag */ var b = s.match(/x\\/[^/]+\\//); var c = a / b;\n";
		$first = $this->minify($js);
		$this->assertEquals($first, $this->minify($js),
			'minifying the same source twice must give the same result');
		$this->assertEquals($first, $this->minify($js),
			'and a third time');
	}
}
