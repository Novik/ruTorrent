<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/urlhost.php');

/**
 * The one host test, held to the cases that made it necessary: a host
 * mentioned in the path or query, in the userinfo, as a leading label of a
 * longer domain; an authority whose host has colons of its own; and the
 * two spellings of the same host that whoever writes the URL picks.
 */
class UrlHostTest extends TestCase
{
	public function testAHostIsTakenOutOfTheUrlNeverSearchedForInIt()
	{
		foreach (array(
			'http://tracker.example/ann?pk=x'                    => 'tracker.example',
			'https://tracker.example/forum/viewtopic.php?t=1'    => 'tracker.example',
			'https://evil.test/x/tracker.example/forum/dl.php'   => 'evil.test',               // the name is in the path
			'https://evil.test/?ref=tracker.example'             => 'evil.test',               // in the query
			'https://tracker.example@evil.test/forum/'           => 'evil.test',               // in the userinfo
			'https://tracker.example.evil.test/forum/'           => 'tracker.example.evil.test', // a leading label of another domain
			'not a url'                                          => null,
			''                                                   => null,
		) as $url => $expected) {
			$this->assertTrue(UrlHost::of($url) === $expected,
				$url . ' has the host ' . json_encode($expected));
		}
	}

	public function testTwoSpellingsOfTheSameHostAreOne()
	{
		$this->assertEquals('tracker.example', UrlHost::normalize('TRACKER.EXAMPLE.'), 'case and the root dot are folded');
		$this->assertEquals('tracker.example', UrlHost::of('http://TRACKER.EXAMPLE./ann'), 'and folded when read out of a URL');
		$this->assertTrue(UrlHost::isExactlyOneOf('tracker.example.', array('tracker.example')), 'the root dot names the same host');
		$this->assertTrue(UrlHost::isExactlyOneOf('Tracker.EXAMPLE', array('tracker.example')), 'DNS names have no case');
		$this->assertTrue(UrlHost::of('/forum/viewtopic.php?t=1') === null, 'a URL without a host has none');
		$this->assertTrue(UrlHost::of('http://.../x') === null, 'a normalized empty host is null');
		$this->assertTrue(UrlHost::normalize(array('not', 'a', 'string')) === '', 'a non-string folds to nothing');
		$this->assertTrue(!UrlHost::isExactlyOneOf('', array('tracker.example')), 'an empty host is nobody');
		$this->assertTrue(!UrlHost::isExactlyOneOf('tracker.example', array('')), 'an empty candidate matches nobody');
	}

	public function testAnExactListAdmitsNoSubdomain()
	{
		$hosts = array('bt.tracker.example', 'Mirror.Example.');
		$this->assertTrue(UrlHost::isExactlyOneOf('BT.tracker.example.', $hosts), 'case and the root dot are folded');
		$this->assertTrue(UrlHost::isExactlyOneOf('mirror.example', $hosts), 'on both sides');
		$this->assertTrue(!UrlHost::isExactlyOneOf('dl.mirror.example', $hosts), 'a subdomain is another host');
		$this->assertTrue(!UrlHost::isExactlyOneOf('tracker.example', $hosts), 'and so is the parent');
		$this->assertTrue(!UrlHost::isExactlyOneOf('tracker.example.evil.test', array('tracker.example')),
			'a leading label of a longer domain is another host');
		$this->assertTrue(!UrlHost::isExactlyOneOf('', array('')), 'an empty host is nobody');
		$this->assertTrue(!UrlHost::isExactlyOneOf(null, $hosts), 'a missing host is nobody');
	}

	public function testAnAuthorityIsReadAsAHostAndAPort()
	{
		// What Requests::makeCSRFCheck() reads a Host header with. The old
		// reading, strtok($host, ':'), stopped at the first colon: right for
		// a name or an IPv4 address, wrong for an IPv6 literal.
		$this->assertEquals('backend', UrlHost::of('http://backend:8080'), 'a name with a port');
		$this->assertEquals('192.0.2.7', UrlHost::of('http://192.0.2.7:8080'), 'an IPv4 address with a port');
		$this->assertEquals('[::1]', UrlHost::of('http://[::1]:8080'), 'an IPv6 literal with a port');
		$this->assertEquals('[fd00::5]', UrlHost::of('http://[fd00::5]'), 'and without one');
		$this->assertTrue(UrlHost::isExactlyOneOf(UrlHost::of('http://[FD00::5]:8080/x'), array('[fd00::5]')),
			'an IPv6 literal is compared without regard to case');
	}

	public function testParsedAuthorityEdgesAreStableAcrossPhpVersions()
	{
		// UrlHost compares the parsed host byte for byte after folding case and
		// trailing dots; IDNs are not converted.
		$this->assertEquals('xn--tst-qla.example', UrlHost::of('https://xn--tst-qla.example/x'),
			'an IDN A-label remains an A-label');
		$this->assertEquals('täst.example', UrlHost::of('https://täst.example/x'),
			'a Unicode host is not silently converted to punycode');
		$this->assertTrue(!UrlHost::isExactlyOneOf('täst.example', array('xn--tst-qla.example')),
			'different IDN spellings do not gain identity without an explicit converter');
		$this->assertEquals('[2001:db8::1]', UrlHost::of('https://[2001:db8::1]:8443/x'),
			'parse_url retains IPv6 brackets in the host');
		$this->assertTrue(UrlHost::isExactlyOneOf('[2001:db8::1]', array('[2001:db8::1]')),
			'a bracketed IPv6 authority can match a declared host');
		$this->assertTrue(UrlHost::of('tracker.example/x') === null,
			'a bare name and path are not a URL authority');
		$this->assertEquals('tracker.example', UrlHost::of('//tracker.example/x'),
			'parse_url exposes the host in a scheme-relative reference');
		$this->assertEquals('tracker.example', UrlHost::of('https://user@evil@tracker.example:443/x'),
			'the last at-sign separates userinfo from the actual host');
		$this->assertEquals('tracker.example', UrlHost::of('https://tracker.example:8443/download'),
			'of() returns the host without its port');
	}

	/**
	 * php/util.php resolves a class by including utility/<lowercase>.php
	 * relative to php/, so a helper that ships anywhere else can be named
	 * only by a file that requires it by path. This one shipped in php/
	 * while its docblock invited plugins to take it, and a plugin that did
	 * -- naming the class and requiring nothing -- got a fatal.
	 *
	 * Read from a child PHP: this file requires urlhost.php by path for the
	 * cases above, so in this process the class is loaded whatever the
	 * autoloader could or could not have done.
	 */
	public function testUrlHostShipsWhereTheAutoloaderResolvesClasses()
	{
		$php = dirname(dirname(__DIR__)) . '/php';
		$code = 'require ' . var_export($php . '/util.php', true) . ';'
			. 'echo realpath((new ReflectionClass("UrlHost"))->getFileName()), "\n";'
			. 'echo realpath((new ReflectionClass("Math"))->getFileName()), "\n";';
		$process = proc_open(
			array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-d', 'display_errors=stderr', '-r', $code),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($process)) {
			$this->assertTrue(false, 'child PHP started');
			return;
		}
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		proc_close($process);
		$lines = explode("\n", trim($output));

		$this->assertTrue($errors === '', 'the bootstrap writes no diagnostics: ' . $errors);
		// Nothing requires math.php, so the autoloader is the only thing that
		// can have found Math: this is the directory it looks in.
		$this->assertTrue(isset($lines[1]) && $lines[1] === realpath($php . '/utility/math.php'),
			'the autoloader resolves classes from php/utility: ' . var_export($lines, true));
		$this->assertTrue(isset($lines[0]) && $lines[0] === realpath($php . '/utility/urlhost.php'),
			'and UrlHost ships in that directory, so naming it is enough: ' . var_export($lines, true));
	}
}
