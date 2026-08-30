<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/check_port/parse.php');

/**
 * The shape of a yougetsignal answer belongs to a third party. It changed once
 * already -- the old endpoint was replaced by a GraphQL API, and the only
 * symptom in the interface was a port whose state stayed unknown, with nothing
 * to say why. These pin the shape so the next move is a failing test instead.
 *
 * Bodies are the real ones: nmap behind the API reports open, closed or
 * filtered, and filtered -- nothing answered -- is the same news as closed for
 * a port check.
 */
class YouGetSignalParseTest extends TestCase
{
	private function body($state, $kind = 'success')
	{
		return json_encode(array('data' => array('networkToolRunPortCheck' => array(
			'output' => array(
				'toolType' => 'PortCheck',
				'parsed' => array(
					'kind' => $kind,
					'nmapVersion' => '7.93',
					'hostIpAddress' => '8.8.8.8',
					'port' => array(
						'number' => 80,
						'state' => $state,
						'protocol' => 'tcp',
						'service' => 'http',
					),
				),
			),
		))));
	}

	public function testAnOpenPortIsOpen()
	{
		$this->assertEquals(2, check_port_parse_yougetsignal($this->body('open')),
			'an open port reads as open');
	}

	public function testAClosedPortIsClosed()
	{
		$this->assertEquals(1, check_port_parse_yougetsignal($this->body('closed')),
			'a closed port reads as closed');
	}

	public function testAFilteredPortIsClosed()
	{
		// Nothing answered. The user cannot be reached either way.
		$this->assertEquals(1, check_port_parse_yougetsignal($this->body('filtered')),
			'a filtered port reads as closed');
	}

	public function testAScanThatDidNotSucceedIsUnknown()
	{
		$this->assertEquals(0, check_port_parse_yougetsignal($this->body('open', 'failure')),
			'a kind other than success is not read as a result');
	}

	public function testAStateNobodyKnowsIsUnknown()
	{
		$this->assertEquals(0, check_port_parse_yougetsignal($this->body('unfiltered')),
			'an unrecognised state is unknown rather than a guess');
	}

	public function testAnAnswerWithoutTheResultIsUnknown()
	{
		$this->assertEquals(0, check_port_parse_yougetsignal(
			'{"data":{"networkToolRunPortCheck":{"output":{"toolType":"PortCheck"}}}}'),
			'an answer carrying no parsed result is unknown');
	}

	public function testAGraphQlErrorIsUnknown()
	{
		$this->assertEquals(0, check_port_parse_yougetsignal(
			'{"errors":[{"message":"Cannot query field"}],"data":null}'),
			'a GraphQL error is unknown');
	}

	public function testSomethingThatIsNotJsonIsUnknown()
	{
		// What the old endpoint returns today: an HTML page, or nothing at all.
		$this->assertEquals(0, check_port_parse_yougetsignal('<!DOCTYPE html><html></html>'),
			'a page instead of an answer is unknown');
		$this->assertEquals(0, check_port_parse_yougetsignal(''),
			'an empty body is unknown');
		$this->assertEquals(0, check_port_parse_yougetsignal('error code: 522'),
			'an edge error is unknown');
	}
}
