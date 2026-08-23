<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * A call this filter refuses must be reported as a refusal that names the
 * command, not as "could not reach rtorrent" -- rtorrent never sees a refused
 * call, and reporting a refusal as an outage sends the customer to restart a
 * client that is up and answering.
 *
 * decide() already separates a refusal (action 'reject') from a call it
 * forwards (action 'send'); the door acts on that. These tests pin two things
 * the door needs from it: that a refusal carries the name of the command it
 * refused, and that the fault the door renders from it is the -501 rpc2.php
 * answers for the same refusals, naming the command and blaming this server.
 */
class XMLRPCProxyRejectionTest extends TestCase
{
	private $safe = array('d.custom1.set', 'd.directory.set');
	private $opts;

	public function setUp()
	{
		// The directory boundary action.php passes; irrelevant to these
		// refusals but present so decide() runs the same code path it does live.
		$this->opts = array('directory' => array('root' => '/', 'resolve' => null));
	}

	private function call($method, $params = array())
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . $method
			. '</methodName><params>';
		foreach($params as $p)
			$xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
				. '</string></value></param>';
		$xml .= '</params></methodCall>';
		return XMLRPCProxy::decide($xml, 'sanitize', $this->safe, false, $this->opts);
	}

	// --- a refusal carries the name of the command it refused ---

	public function testADeniedMethodIsRejectedAndNamed()
	{
		$d = $this->call('execute.capture', array('', '/bin/id'));
		$this->assertTrue($d['action'] === 'reject',
			'execute.capture is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'execute.capture',
			'the refusal names execute.capture');
	}

	public function testSystemShutdownIsRejectedAndNamed()
	{
		$d = $this->call('system.shutdown');
		$this->assertTrue($d['action'] === 'reject',
			'system.shutdown is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'system.shutdown',
			'the refusal names system.shutdown');
	}

	public function testAMulticallCarryingADeniedCommandNamesThatCommand()
	{
		// The command the multicall smuggles is what the caller must be told
		// about, not the multicall wrapper.
		$d = $this->call('d.multicall2', array('', 'main', 'execute.capture=/bin/id'));
		$this->assertTrue($d['action'] === 'reject',
			'a multicall carrying execute.capture is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'execute.capture',
			'the refusal names the carried command, execute.capture');
	}

	public function testSystemMulticallCarryingADeniedMemberNamesIt()
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data><value><struct>'
			. '<member><name>methodName</name><value><string>system.shutdown</string></value></member>'
			. '<member><name>params</name><value><array><data></data></array></value></member>'
			. '</struct></value></data></array></value></param></params></methodCall>';
		$d = XMLRPCProxy::decide($xml, 'sanitize', $this->safe, false, $this->opts);
		$this->assertTrue($d['action'] === 'reject',
			'a system.multicall carrying system.shutdown is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'system.shutdown',
			'the refusal names the carried member, system.shutdown');
	}

	// --- the split the door relies on: a forwardable call is sent, so a real
	//     outage on that call is still reported as an outage ---

	public function testAForwardableCallIsSentNotRefused()
	{
		$d = $this->call('system.client_version');
		$this->assertTrue($d['action'] === 'send',
			'system.client_version is forwarded, so a genuine connection '
			. 'failure on it is still an outage');
		$this->assertTrue(!isset($d['method']) || $d['method'] === null,
			'a forwarded call names no refused command');
	}

	// --- the fault the door renders names the command and blames this server ---

	public function testRejectionFaultNamesTheCommandAndBlamesThisServer()
	{
		$this->assertTrue(method_exists('XMLRPCProxy', 'rejectionFault'),
			'the proxy renders a refusal fault the door can return');
		if(!method_exists('XMLRPCProxy', 'rejectionFault'))
			return;

		$fault = XMLRPCProxy::rejectionFault('execute.capture');
		$this->assertTrue(strpos($fault, '<i4>-501</i4>') !== false,
			'the fault carries faultCode -501, as rpc2.php does');
		$this->assertTrue(
			strpos($fault, "The command 'execute.capture' was rejected by this server.") !== false,
			'the fault names the command and says this server refused it');
		$this->assertTrue(strpos($fault, 'Is rTorrent running') === false,
			'the fault does not blame rtorrent for an outage that did not happen');
	}

	public function testRejectionFaultWithoutACommandStillBlamesThisServer()
	{
		if(!method_exists('XMLRPCProxy', 'rejectionFault'))
		{
			$this->assertTrue(false, 'the proxy renders a refusal fault');
			return;
		}
		$fault = XMLRPCProxy::rejectionFault(null);
		$this->assertTrue(strpos($fault, '<i4>-501</i4>') !== false,
			'the generic refusal fault still carries -501');
		$this->assertTrue(
			strpos($fault, 'This XMLRPC call was rejected by this server.') !== false,
			'the generic refusal still says this server refused it');
		$this->assertTrue(strpos($fault, 'Is rTorrent running') === false,
			'and still does not blame rtorrent');
	}
}
