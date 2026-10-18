<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * Requests::makeCSRFCheck() answers a refused POST with 403 and exits, so
 * each case runs it in a child PHP that is given the request's headers and
 * the configured $enabledOrigins, and reads back ALLOW or Forbidden. The
 * child is started without a shell and exits on its own.
 */
class RequestsCSRFTest extends TestCase
{
	private function probe($headers, $enabledOrigins = array())
	{
		$code = '$_SERVER = json_decode(getenv("CSRF_TEST_SERVER"), true);'
			. '$enableCSRFCheck = true;'
			. '$enabledOrigins = json_decode(getenv("CSRF_TEST_ORIGINS"), true);'
			. 'require ' . var_export(__DIR__ . '/../../php/utility/requests.php', true) . ';'
			. 'Requests::makeCSRFCheck();'
			. 'echo "ALLOW";';
		$process = proc_open(
			array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-d', 'display_errors=stderr', '-r', $code),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes, null, array(
				'CSRF_TEST_SERVER' => json_encode(array_merge(array('REQUEST_METHOD' => 'POST'), $headers)),
				'CSRF_TEST_ORIGINS' => json_encode($enabledOrigins)));
		if (!is_resource($process)) {
			$this->assertTrue(false, 'child PHP started');
			return '';
		}
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$code = proc_close($process);
		$this->assertTrue($code === 0 && $errors === '',
			'child PHP exited cleanly and wrote no diagnostics: ' . $errors);
		return trim($output);
	}

	public function testAnIPv6LiteralHostIsTheRequestsOwnOrigin()
	{
		// A Host header is host[:port], and an IPv6 host has colons of its
		// own. Read up to the first colon, "[fd00::5]:8080" left "[" to
		// compare the Origin's host with, and every POST made through an
		// IPv6 address answered 403 Forbidden.
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '[fd00::5]:8080',
			'HTTP_ORIGIN' => 'http://[fd00::5]:8080',
		)), 'a POST from the IPv6 origin the page was served on');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '[::1]',
			'HTTP_REFERER' => 'http://[::1]/rutorrent/',
		)), 'the same by Referer, with a host that names no port');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => 'backend:8080',
			'HTTP_X_FORWARDED_HOST' => '[2001:db8::1]:8443',
			'HTTP_ORIGIN' => 'https://[2001:db8::1]:8443',
		)), 'and by the host a proxy forwarded');
	}

	public function testANameOrAnIPv4HostStillAdmitsItsOwnOrigin()
	{
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => 'backend:8080',
			'HTTP_ORIGIN' => 'http://backend:8080',
		)), 'a name with a port');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '192.0.2.7:8080',
			'HTTP_ORIGIN' => 'http://192.0.2.7:8080',
		)), 'an IPv4 address with a port');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => 'rutorrent.example',
			'HTTP_REFERER' => 'https://rutorrent.example/rutorrent/',
		)), 'a name without a port, by Referer');
	}

	public function testAConfiguredHostIsComparedAsADnsName()
	{
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '[::1]:8080',
			'HTTP_ORIGIN' => 'http://rutorrent.example',
		), array('RuTorrent.Example')), 'DNS names have no case');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '[::1]:8080',
			'HTTP_ORIGIN' => 'http://rutorrent.example.',
		), array('rutorrent.example')), 'and the root dot does not name another host');
		$this->assertEquals('Forbidden', $this->probe(array(
			'HTTP_HOST' => '[::1]:8080',
			'HTTP_ORIGIN' => 'http://www.rutorrent.example',
		), array('rutorrent.example')), 'a subdomain is another host');
	}

	public function testAConfiguredHostIsComparedAsADnsNameByReferer()
	{
		// The Referer branch has a comparison of its own, and the cases around
		// it only ever handed it a Referer spelled exactly like the host it had
		// to match. These are the three questions the Origin case above asks.
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '[::1]:8080',
			'HTTP_REFERER' => 'https://RuTorrent.Example/rutorrent/',
		), array('rutorrent.example')), 'the Referer spells the configured host in another case');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '[::1]:8080',
			'HTTP_REFERER' => 'https://rutorrent.example/rutorrent/',
		), array('RuTorrent.Example')), 'and the configured host is the one spelled differently');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '[::1]:8080',
			'HTTP_REFERER' => 'https://rutorrent.example./rutorrent/',
		), array('rutorrent.example')), 'and the root dot does not name another host here either');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => 'backend:8080',
			'HTTP_X_FORWARDED_HOST' => 'rutorrent.example',
			'HTTP_REFERER' => 'https://RuTorrent.Example/rutorrent/',
		)), 'a forwarded host is folded for the Referer as well');
		$this->assertEquals('Forbidden', $this->probe(array(
			'HTTP_HOST' => '[::1]:8080',
			'HTTP_REFERER' => 'https://www.rutorrent.example/rutorrent/',
		), array('rutorrent.example')), 'a subdomain is another host by Referer too');
	}

	public function testAHostIsComparedAsAStringAndNotANumber()
	{
		// "127.0" and "127.00" are both numeric strings, which PHP compares as
		// numbers unless it is told not to: loosely, one is admitted for the
		// other. They name two different hosts, and the check is an identity
		// test on the spelling.
		$this->assertEquals('Forbidden', $this->probe(array(
			'HTTP_HOST' => '127.00',
			'HTTP_ORIGIN' => 'http://127.0',
		)), 'an Origin that equals the Host only as a number');
		$this->assertEquals('Forbidden', $this->probe(array(
			'HTTP_HOST' => '127.00',
			'HTTP_REFERER' => 'http://127.0/rutorrent/',
		)), 'and the same by Referer');
		$this->assertEquals('ALLOW', $this->probe(array(
			'HTTP_HOST' => '127.0',
			'HTTP_ORIGIN' => 'http://127.0',
		)), 'while the host the page was served on is still admitted');
	}

	public function testAForeignOriginIsRefusedWhateverHostItNames()
	{
		foreach (array(
			'http://evil.test' => 'another host',
			'http://rutorrent.example@evil.test' => 'our name in the userinfo',
			'http://rutorrent.example.evil.test' => 'our name as a leading label',
			'http://evil.test/rutorrent.example' => 'our name in the path',
			'http://evil.test/?rutorrent.example' => 'our name in the query',
		) as $origin => $trap) {
			$this->assertEquals('Forbidden', $this->probe(array(
				'HTTP_HOST' => 'rutorrent.example',
				'HTTP_ORIGIN' => $origin,
			)), $trap . ', by Origin against the Host header');
			$this->assertEquals('Forbidden', $this->probe(array(
				'HTTP_HOST' => '[fd00::5]:8080',
				'HTTP_REFERER' => $origin . '/x',
			), array('rutorrent.example')), $trap . ', by Referer against a configured host');
		}
		$this->assertEquals('Forbidden', $this->probe(array(
			'HTTP_HOST' => '[fd00::5]:8080',
		)), 'a POST that names no origin at all');
	}
}
