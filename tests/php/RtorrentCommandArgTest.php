<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * rtorrent does not receive the directory as a typed XMLRPC parameter. It
 * receives a command string -- d.directory.set="<path>" -- that its own parser
 * (src/rpc/parse.cc) takes apart, so the path is quoted by us and unquoted by
 * rtorrent.
 *
 * That parser ends a quoted argument at the first unescaped '"' and treats '\'
 * as an escape, so a path carrying either character has to be escaped before
 * it is wrapped, exactly as XMLRPCProxy::rebuildSafeLoadParam() already does
 * for the command strings it rebuilds.
 */
class RtorrentCommandArgTest extends TestCase
{
	public function testPlainPathIsWrappedInQuotes()
	{
		$this->assertEquals(
			'"/home/user/downloads"',
			rTorrent::quoteCommandArg('/home/user/downloads'),
			'a path with nothing to escape is just quoted');
	}

	/**
	 * The reported bug: adding a torrent into a path with a double quote left
	 * the download with no directory, because
	 * d.directory.set="/x/Richard "Popcorn" Wylie" ends the argument at the
	 * quote before "Popcorn".
	 */
	public function testDoubleQuoteIsEscapedSoItDoesNotEndTheArgument()
	{
		$this->assertEquals(
			'"/x/Richard \"Popcorn\" Wylie"',
			rTorrent::quoteCommandArg('/x/Richard "Popcorn" Wylie'),
			'a quote in the path is escaped, not left to close the argument');
	}

	public function testBackslashIsEscaped()
	{
		$this->assertEquals(
			'"/x/a\\\\b"',
			rTorrent::quoteCommandArg('/x/a\b'),
			'a backslash is doubled, so rtorrent unescapes it back to one');
	}

	/**
	 * The ordering trap: escaping the quote first and the backslash afterwards
	 * would re-escape the backslash that the quote escaping just introduced,
	 * turning \" into \\" -- an escaped backslash followed by a live quote,
	 * which ends the argument again. A single str_replace() pass avoids it.
	 */
	public function testBackslashBeforeQuoteIsEscapedInOnePass()
	{
		$this->assertEquals(
			'"/x/a\\\\\"b"',
			rTorrent::quoteCommandArg('/x/a\"b'),
			'a backslash followed by a quote survives as an escaped pair');
	}

	/**
	 * rtorrent splits a command's arguments on ',' before it unquotes them, so
	 * a comma is only safe while it sits inside the quotes we add.
	 */
	public function testCommaStaysInsideTheArgument()
	{
		$this->assertEquals(
			'"/x/Earth, Wind & Fire"',
			rTorrent::quoteCommandArg('/x/Earth, Wind & Fire'),
			'a comma is carried inside the quotes rather than splitting the argument');
	}

	public function testEmptyValueIsStillAQuotedArgument()
	{
		$this->assertEquals(
			'""',
			rTorrent::quoteCommandArg(''),
			'an empty value quotes to an empty argument');
	}

	/**
	 * Guard against the fix being undone at either call site: sendTorrent()
	 * and sendMagnet() both used to concatenate the raw path between two
	 * literal quotes, which is the defect these tests exist for.
	 */
	public function testNoCallSiteConcatenatesTheRawDirectory()
	{
		$source = file_get_contents(__DIR__ . '/../../php/rtorrent.php');
		$this->assertEquals(
			0,
			preg_match('/"\s*\.\s*\$directory\s*\.\s*"/', $source),
			'no call site wraps $directory in quotes without escaping it');
	}
}
