<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * Thrown by the settings stub once it has seen the commands, so the request
 * stops before it opens a socket.
 */
class RtorrentCommandArgCaptured extends Exception
{
}

/**
 * rXMLRPCRequest::run() hands its commands to
 * rTorrentSettings::get()->patchDeprecatedRequest() before it sends anything,
 * which is the seam these tests capture at: replacing the settings singleton
 * with this subclass yields the parameters sendTorrent() actually emitted,
 * without a daemon and without reading the source.
 */
class RtorrentCommandArgSettings extends rTorrentSettings
{
	public $captured = array();

	public function correctDirectory(&$dir, $resolve_links = false)
	{
		return true;
	}

	public function patchDeprecatedRequest($commands)
	{
		$this->captured = $commands;
		throw new RtorrentCommandArgCaptured();
	}
}

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
	private $settings;
	private $previousSettings;
	private $torrentFile;

	public function setUp()
	{
		// newInstanceWithoutConstructor() because rTorrentSettings' constructor
		// reaches for a live daemon; the two fields the load path reads are set
		// by hand instead.
		$stub = new ReflectionClass('RtorrentCommandArgSettings');
		$this->settings = $stub->newInstanceWithoutConstructor();
		$this->settings->iVersion = 0x904;
		$this->settings->aliases = array();

		$reflection = new ReflectionClass('rTorrentSettings');
		$property = $reflection->getProperty('theSettings');
		$property->setAccessible(true);
		$this->previousSettings = $property->getValue();
		$property->setValue(null, $this->settings);

		$this->torrentFile = tempnam(sys_get_temp_dir(), 'rtca') . '.torrent';
		file_put_contents($this->torrentFile, $this->minimalTorrent());
	}

	public function tearDown()
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		$property = $reflection->getProperty('theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $this->previousSettings);

		if ($this->torrentFile && file_exists($this->torrentFile)) {
			@unlink($this->torrentFile);
		}
	}

	private function minimalTorrent()
	{
		$info = 'd6:lengthi1e4:name9:test.data12:piece lengthi16384e6:pieces20:'
			. str_repeat("\x01", 20) . 'e';
		return 'd8:announce28:http://example.test/announce4:info' . $info . 'e';
	}

	/**
	 * Run a load through sendTorrent() and return the command parameters it
	 * put on the wire, as plain strings.
	 */
	private function loadInto($directory, $addPath = true)
	{
		try {
			rTorrent::sendTorrent($this->torrentFile, true, $addPath, $directory,
				null, true, false);
		} catch (RtorrentCommandArgCaptured $stop) {
			// expected: the request was stopped at the seam
		}

		$parameters = array();
		foreach ($this->settings->captured as $command) {
			foreach ($command->params as $param) {
				$parameters[] = (string)$param->value;
			}
		}
		return $parameters;
	}

	// ---- what sendTorrent() actually emits -------------------------------

	/**
	 * The reported bug: adding a torrent into a path with a double quote left
	 * the download with no directory, because
	 * d.directory.set="/x/Richard "Popcorn" Wylie" ends the argument at the
	 * quote before "Popcorn".
	 */
	public function testSendTorrentEscapesAQuoteInTheDirectory()
	{
		$parameters = $this->loadInto('/x/Richard "Popcorn" Wylie');
		$this->assertTrue(
			in_array('d.set_directory="/x/Richard \"Popcorn\" Wylie"', $parameters, true),
			'sendTorrent() emits the directory with the quote escaped');
	}

	public function testSendTorrentEscapesABackslashInTheDirectory()
	{
		$parameters = $this->loadInto('/x/a\b');
		$this->assertTrue(
			in_array('d.set_directory="/x/a\\\\b"', $parameters, true),
			'sendTorrent() emits the directory with the backslash doubled');
	}

	/**
	 * Without add-path the same value goes to d.set_directory_base, so the
	 * escaping has to hold on that branch too.
	 */
	public function testSendTorrentEscapesTheBaseDirectoryToo()
	{
		$parameters = $this->loadInto('/x/Richard "Popcorn" Wylie', false);
		$this->assertTrue(
			in_array('d.set_directory_base="/x/Richard \"Popcorn\" Wylie"', $parameters, true),
			'the directory_base branch escapes the quote as well');
	}

	/**
	 * The directory rtorrent is told to create is a typed parameter of its own,
	 * never part of a command string, so it must stay unquoted and unescaped.
	 */
	public function testTheMkdirArgumentIsNotQuoted()
	{
		$parameters = $this->loadInto('/x/Richard "Popcorn" Wylie');
		$this->assertTrue(
			in_array('/x/Richard "Popcorn" Wylie', $parameters, true),
			'mkdir receives the path verbatim as its own parameter');
	}

	// ---- the quoting itself ----------------------------------------------

	public function testPlainPathIsWrappedInQuotes()
	{
		$this->assertEquals(
			'"/home/user/downloads"',
			rTorrent::quoteCommandArg('/home/user/downloads'),
			'a path with nothing to escape is just quoted');
	}

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
	 * str_replace() works through the arrays left to right and can revisit a
	 * value an earlier pair inserted, so the pairs are ordered backslash first,
	 * quote second. Reversed, the backslash added while escaping the quote gets
	 * doubled in turn, yielding \\" -- an escaped backslash and a live quote,
	 * which ends the argument again.
	 */
	public function testBackslashBeforeQuoteSurvivesTheReplacementOrder()
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
}
