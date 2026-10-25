<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * correctDirectory() turns a directory a user typed into the absolute
 * directory it names, always under $topDirectory, or refuses it.
 *
 * - A relative value is joined to rtorrent's default directory, or to the
 *   top directory when the default directory is not inside it.
 * - An absolute value inside the top directory is kept. One outside it is
 *   read as relative to the top directory: "/x" is <top>/x. With "/" as the
 *   top directory every absolute value is inside it.
 * - "//", "." and ".." are folded, and a value that would end outside the
 *   top directory is put under it the same way, so ".." cannot leave it.
 * - A URL or magnet link, a NUL byte, a name longer than NAME_MAX (255 bytes)
 *   and a result longer than PATH_MAX less its NUL (4095 bytes) are refused.
 * - "~" is an ordinary character.
 *
 * Here the default directory is the top directory unless a test says
 * otherwise. Every expectation is the exact string correctDirectory() leaves
 * behind.
 */
class UserDirectoryTest extends TestCase
{
	const TOP = '/torrents1/downloads/';

	private $previousSettings;
	private $hadTopDirectory;
	private $previousTopDirectory;

	public function setUp()
	{
		$this->hadTopDirectory = array_key_exists('topDirectory', $GLOBALS);
		$this->previousTopDirectory = $GLOBALS['topDirectory'] ?? null;
		$settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
		$settings->directory = '/torrents1/downloads';
		$settings->home = '/home/someone';
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();
		$this->previousSettings = self::swapSettings($settings);
	}

	public function tearDown()
	{
		self::swapSettings($this->previousSettings);
		if($this->hadTopDirectory)
			$GLOBALS['topDirectory'] = $this->previousTopDirectory;
		else
			unset($GLOBALS['topDirectory']);
	}

	private static function swapSettings($settings)
	{
		return Closure::bind(function ($settings) {
			$previous = rTorrentSettings::$theSettings;
			rTorrentSettings::$theSettings = $settings;
			return $previous;
		}, null, 'rTorrentSettings')->__invoke($settings);
	}

	/** [accepted, the directory afterwards] for $dir under $top. */
	private function correct($dir, $top = self::TOP)
	{
		$GLOBALS['topDirectory'] = $top;
		$ok = rTorrentSettings::get()->correctDirectory($dir);
		return array($ok, $dir);
	}

	private function assertResolves($input, $expected, $top = self::TOP)
	{
		list($ok, $dir) = $this->correct($input, $top);
		$this->assertTrue($ok === true, json_encode($input) . ' is accepted');
		$this->assertEquals($expected, $dir, json_encode($input) . ' resolves to ' . json_encode($expected));
		list($again, $twice) = $this->correct($dir, $top);
		$this->assertTrue(($again === true) && ($twice === $dir), 'and resolving the result again changes nothing');
	}

	private function assertRefused($input, $why, $top = self::TOP)
	{
		list($ok) = $this->correct($input, $top);
		$this->assertTrue($ok === false, 'refused: ' . $why);
	}

	// ---- rule 1: relative to the top directory ------------------------------

	public function testALeadingSlashIsUnderTheTopDirectory()
	{
		$this->assertResolves('/thisisthedownloaddir', '/torrents1/downloads/thisisthedownloaddir');
	}

	public function testARelativeNameIsUnderTheTopDirectory()
	{
		$this->assertResolves('thisisthedownloaddir', '/torrents1/downloads/thisisthedownloaddir');
		$this->assertResolves('Movies/2026/', '/torrents1/downloads/Movies/2026');
	}

	public function testAPathAlreadyUnderTheTopDirectoryIsKept()
	{
		$this->assertResolves('/torrents1/downloads/x', '/torrents1/downloads/x');
		$this->assertResolves('/torrents1/downloads/', '/torrents1/downloads');
		$this->assertResolves('/torrents1/downloads', '/torrents1/downloads');
	}

	public function testAPathThatOnlyLooksLikeTheTopDirectoryIsJoinedUnderIt()
	{
		$this->assertResolves('/torrents1/downloadsX', '/torrents1/downloads/torrents1/downloadsX');
		$this->assertResolves('torrents1/downloads/x', '/torrents1/downloads/torrents1/downloads/x');
	}

	// ---- rule 2: normalised before and after joining ------------------------

	public function testDotDotCannotClimbOutOfTheTopDirectory()
	{
		$this->assertResolves('../..', '/torrents1/downloads');
		$this->assertResolves('/../../tmp/x', '/torrents1/downloads/tmp/x');
		$this->assertResolves('/torrents1/downloads/../rtorrent-session', '/torrents1/downloads/torrents1/rtorrent-session');
		$this->assertResolves('a/../../b', '/torrents1/downloads/b');
	}

	public function testRepeatedSlashesAndDotsAreFolded()
	{
		$this->assertResolves('//etc', '/torrents1/downloads/etc');
		$this->assertResolves('/torrents1/downloads//trail/./dir/', '/torrents1/downloads/trail/dir');
		$this->assertResolves('./x//y/.', '/torrents1/downloads/x/y');
	}

	/** Run $test with $default as rtorrent's default directory. */
	private function withDefault($default, $test)
	{
		$settings = rTorrentSettings::get();
		$previous = $settings->directory;
		$settings->directory = $default;
		try { $test(); }
		finally { $settings->directory = $previous; }
	}

	public function testAStockInstallKeepsItsPaths()
	{
		$this->withDefault('/home/bob/downloads', function () {
			$this->assertResolves('Movies', '/home/bob/downloads/Movies', '/');
			$this->assertResolves('/Movies', '/Movies', '/');
			$this->assertResolves('../x', '/home/bob/x', '/');
			$this->assertResolves('/srv/x', '/srv/x', '/');
			$this->assertResolves('/../x', '/x', '/');
		});
	}

	public function testARelativeValueIsUnderTheDefaultDirectoryInsideTheTop()
	{
		$this->withDefault('/data/incoming', function () {
			$this->assertResolves('Movies', '/data/incoming/Movies', '/data/');
			$this->assertResolves('../Movies', '/data/Movies', '/data/');
			$this->assertResolves('../../../etc', '/data/etc', '/data/');
			$this->assertResolves('/etc', '/data/etc', '/data/');
		});
	}

	public function testADefaultDirectoryOutsideTheTopIsNotUsed()
	{
		$this->withDefault('/home/bob', function () {
			$this->assertResolves('Movies', '/data/Movies', '/data/');
			$this->assertResolves('/Movies', '/data/Movies', '/data/');
		});
	}

	// ---- rule 3: refused before anything is joined --------------------------

	public function testAUrlIsRefused()
	{
		$this->assertRefused('http://x/y', 'an http URL');
		$this->assertRefused('/torrents1/downloads/ftp://x', 'a scheme after a real directory');
		$this->assertRefused('Movies/see https://example.org', 'a URL inside a name');
		$this->assertRefused('svn+ssh://host/repo', 'a scheme with + in it');
	}

	public function testAMagnetLinkIsRefused()
	{
		$magnet = 'magnet:?xt=urn:btih:0123456789ABCDEF0123456789ABCDEF01234567&dn=x'
			. '&tr=udp%3A%2F%2Ftracker.example%3A1337';
		$this->assertRefused($magnet, 'a magnet link');
		$this->assertRefused('/torrents1/downloads/' . $magnet, 'one pasted after a real directory');
		$this->assertRefused('MAGNET:?xt=urn:btih:0123456789ABCDEF0123456789ABCDEF01234567', 'in any case');
	}

	public function testNamesThatOnlyLookLikeLinksAreAccepted()
	{
		$this->assertResolves('Magnet: The Movie', '/torrents1/downloads/Magnet: The Movie');
		$this->assertResolves('Series: Season 1', '/torrents1/downloads/Series: Season 1');
		$this->assertResolves('x:/y', '/torrents1/downloads/x:/y');
		$this->assertResolves('http:', '/torrents1/downloads/http:');
	}

	public function testANulByteIsRefused()
	{
		$this->assertRefused("Movies\0/x", 'a NUL byte');
	}

	public function testANameOfNameMaxIsAcceptedAndOneMoreIsRefused()
	{
		$this->assertResolves(str_repeat('a', 255), '/torrents1/downloads/' . str_repeat('a', 255));
		$this->assertRefused(str_repeat('a', 256), 'a 256-byte name');
		$this->assertRefused('/x/' . str_repeat('a', 256) . '/..', 'even one that ".." would remove');
	}

	/** A relative value that, joined under the top directory, is exactly $length bytes. */
	private function relativeOfFinalLength($length)
	{
		$room = $length - strlen('/torrents1/downloads');
		$value = '';
		while (strlen($value) + 201 <= $room) {
			$value .= '/' . str_repeat('d', 200);
		}
		return $value . '/' . str_repeat('e', $room - strlen($value) - 1);
	}

	public function testAResultOfPathMaxLessOneIsAcceptedAndOneMoreIsRefused()
	{
		$ok = $this->relativeOfFinalLength(4095);
		list($accepted, $dir) = $this->correct($ok);
		$this->assertTrue($accepted === true && strlen($dir) === 4095, 'a 4095-byte result is accepted');
		$this->assertEquals('/torrents1/downloads' . $ok, $dir, 'and is the value joined under the top directory');
		$this->assertRefused($this->relativeOfFinalLength(4096), 'a 4096-byte result, which with its NUL is past PATH_MAX');

		// Under "/" nothing is outside the top directory, so only the length can refuse these.
		$path = '/torrents1/downloads' . $this->relativeOfFinalLength(4095);
		$this->assertResolves($path, $path, '/');
		$this->assertRefused('/torrents1/downloads' . $this->relativeOfFinalLength(4096), 'a 4096-byte path under "/"', '/');
	}

	// ---- rule 4: "~" is not expanded ----------------------------------------

	public function testATildeIsAnOrdinaryCharacter()
	{
		$this->assertResolves('~/x', '/torrents1/downloads/~/x');
		$this->assertResolves('~', '/torrents1/downloads/~');
	}

	// ---- a reload keeps the rules it had ------------------------------------

	public function testAReportedDirectoryIsJudgedAsBefore()
	{
		$GLOBALS['topDirectory'] = self::TOP;
		$dir = '/torrents1/rtorrent-session';
		$this->assertTrue(rTorrentSettings::get()->correctReportedDirectory($dir) === false,
			'a directory rtorrent reports outside the top directory is still refused, not moved under it');
		$dir = '/torrents1/downloads/x/../y';
		$this->assertTrue(rTorrentSettings::get()->correctReportedDirectory($dir) === true
			&& $dir === '/torrents1/downloads/y', 'and one inside it is normalised as before');
	}
}
