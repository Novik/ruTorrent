<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../plugins/check_port/parse.php');

/**
 * What Globalping's probe printed, read back as a port state.
 *
 * The bodies below are what api.globalping.io actually returned for those
 * targets, not shapes invented for the test. The distinction they exist to
 * pin cost a wrong answer once already: the counter Globalping puts on each
 * attempt, tcp_conn=N, is printed on the lines that got no answer as well,
 * so a rule that looked for the counter alone called every port open --
 * including one that was not reachable at all.
 */
class GlobalpingParseTest extends TestCase
{
	/** 8.8.8.8:53, a port that answers. */
	private function replied()
	{
		return "PING dns.google (8.8.8.8) on port 53.\n"
			. "Reply from dns.google (8.8.8.8) on port 53: tcp_conn=1 time=1.01 ms\n"
			. "Reply from dns.google (8.8.8.8) on port 53: tcp_conn=2 time=1.13 ms\n"
			. "\n--- dns.google (8.8.8.8) ping statistics ---\n"
			. "3 packets transmitted, 3 received, 0% packet loss, time 1002 ms\n";
	}

	/** 8.8.8.8:81, a port that is filtered rather than refused. */
	private function silent()
	{
		return "PING dns.google (8.8.8.8) on port 81.\n"
			. "No reply from dns.google (8.8.8.8) on port 81: tcp_conn=1\n"
			. "No reply from dns.google (8.8.8.8) on port 81: tcp_conn=2\n"
			. "\n--- dns.google (8.8.8.8) ping statistics ---\n"
			. "3 packets transmitted, 0 received, 100% packet loss, time 2003 ms\n";
	}

	public function testAPortThatRepliesIsOpen()
	{
		$this->assertEquals(2, check_port_parse_globalping($this->replied()),
			'a reply on the port is the only evidence that it is open');
	}

	/**
	 * The case the counter alone got wrong. Every "No reply" line carries
	 * tcp_conn=N too, so this must not read as open.
	 */
	public function testAPortThatNeverRepliesIsNotOpen()
	{
		$this->assertTrue(strpos($this->silent(), 'tcp_conn=') !== false,
			'the no-reply output does carry the attempt counter');
		$this->assertEquals(0, check_port_parse_globalping($this->silent()),
			'and it is still not an open port');
	}

	/**
	 * Nor is it a closed one. A probe that heard nothing cannot tell a closed
	 * port from a filtered one, and saying "closed" would send someone looking
	 * at a firewall rule that is doing exactly what they meant it to.
	 */
	public function testSilenceIsUnknownRatherThanClosed()
	{
		$this->assertTrue(check_port_parse_globalping($this->silent()) !== 1,
			'no reply is not reported as closed');
	}

	public function testAnExplicitRefusalIsClosed()
	{
		$raw = "PING host (192.0.2.1) on port 81.\n"
			. "connection refused from host (192.0.2.1) on port 81\n";
		$this->assertEquals(1, check_port_parse_globalping($raw),
			'a refusal is the one answer that proves the port is closed');
	}

	public function testAnEmptyOrAbsentOutputIsUnknown()
	{
		$this->assertEquals(0, check_port_parse_globalping(''), 'an empty output says nothing');
		$this->assertEquals(0, check_port_parse_globalping(false),
			'and neither does a measurement that carried no output at all');
	}

	public function testOutputInAnUnfamiliarShapeIsUnknown()
	{
		$this->assertEquals(0, check_port_parse_globalping("something Globalping has never printed\n"),
			'an answer nobody recognises is unknown rather than a guess');
	}

	/**
	 * "Reply from" has to start a line. Globalping names the host in the
	 * header line, and a hostname could otherwise carry the phrase into a
	 * verdict.
	 */
	public function testTheReplyMarkerIsAnchoredToTheStartOfALine()
	{
		$raw = "PING no-Reply from tcp_conn=1 (192.0.2.1) on port 81.\n"
			. "No reply from no-Reply from tcp_conn=1 (192.0.2.1) on port 81: tcp_conn=1\n";
		$this->assertEquals(0, check_port_parse_globalping($raw),
			'the phrase appearing mid-line is not a reply');
	}
}
