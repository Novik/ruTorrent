<?php

require_once(__DIR__ . '/TestCase.php');

// The mocks stand in for what plugins/httprpc/action.php loads before calling
// the proxy. The reply is a marker so the fixture can pin what process()
// returns, not merely that it returned something.
if(!class_exists('FileUtil'))
{
	class FileUtil
	{
		public static $log = array();
		public static function toLog($msg) { self::$log[] = $msg; }
	}
}
if(!class_exists('rXMLRPCRequest'))
{
	class rXMLRPCRequest
	{
		public static $lastPayload = null;
		public static $lastTrusted = null;
		public static $sent = 0;
		public static function send($data, $trusted)
		{
			self::$lastPayload = $data;
			self::$lastTrusted = $trusted;
			self::$sent++;
			return 'SCGI-REPLY';
		}
	}
}

require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * process() is decide() plus a send. These tests exist to keep that a refactor:
 * the fixture records what process() did before decide() was split out of it,
 * and nothing here describes what the proxy ought to do — XMLRPCProxyTest
 * covers that. If a change alters a decision, it shows up as a diff to the
 * fixture, which is the point.
 *
 * Comparisons are identity, not equality: '' == null and 0 == false, and this
 * suite is about bytes.
 */
class XMLRPCProxyContractTest extends TestCase
{
	private $cases = array();

	public function setUp()
	{
		$this->cases = require(__DIR__ . '/XMLRPCProxyContractFixture.php');
	}

	private function callProcess($case)
	{
		FileUtil::$log = array();
		rXMLRPCRequest::$lastPayload = null;
		rXMLRPCRequest::$lastTrusted = null;
		rXMLRPCRequest::$sent = 0;

		$returned = XMLRPCProxy::process($case['request'], $case['mode'],
			$case['enableLog'], $case['safeParams']);

		return array(
			'returned' => $returned,
			'sends'    => rXMLRPCRequest::$sent,
			'trusted'  => rXMLRPCRequest::$lastTrusted,
			'payload'  => rXMLRPCRequest::$lastPayload,
			'log'      => FileUtil::$log,
		);
	}

	public function testFixtureCoversEveryBranch()
	{
		$this->assertTrue(count($this->cases) > 0, 'the contract fixture loaded');

		$seen = array();
		foreach($this->cases as $case)
		{
			if($case['sends'] === 0)
				$seen['reject'] = true;
			else if($case['trusted'] === true)
				$seen['trusted'] = true;
			else
				$seen['untrusted'] = true;
		}
		$this->assertTrue(isset($seen['reject']), 'a rejected request is covered');
		$this->assertTrue(isset($seen['trusted']), 'a trusted forward is covered');
		$this->assertTrue(isset($seen['untrusted']), 'an untrusted forward is covered');
	}

	public function testProcessDoesExactlyWhatItDidBefore()
	{
		foreach($this->cases as $name => $case)
		{
			$actual = $this->callProcess($case);

			$this->assertTrue($actual['returned'] === $case['returned'],
				$name.' — returns the same thing');
			$this->assertTrue($actual['sends'] === $case['sends'],
				$name.' — sends the same number of times');
			$this->assertTrue($actual['trusted'] === $case['trusted'],
				$name.' — sends on the same trust');
			$this->assertTrue($actual['payload'] === $case['payload'],
				$name.' — sends the same bytes');
			$this->assertTrue($actual['log'] === $case['log'],
				$name.' — logs the same lines, in the same order');
		}
	}

	public function testDecideReachesTheSameDecisionProcessActsOn()
	{
		foreach($this->cases as $name => $case)
		{
			$decision = XMLRPCProxy::decide($case['request'], $case['mode'],
				$case['safeParams']);

			$this->assertTrue($decision['action'] === (($case['sends'] === 1) ? 'send' : 'reject'),
				$name.' — decides to send exactly when process() sent');

			if($case['sends'] === 1)
			{
				$this->assertTrue($decision['payload'] === $case['payload'],
					$name.' — decides on the same bytes');
				$this->assertTrue($decision['trusted'] === $case['trusted'],
					$name.' — decides on the same trust');
			}
			else
			{
				$this->assertTrue($decision['payload'] === '',
					$name.' — a rejection carries no payload');
				$this->assertTrue($decision['trusted'] === false,
					$name.' — a rejection is never trusted');
			}
		}
	}

	public function testDecideSendsNothingItself()
	{
		foreach($this->cases as $name => $case)
		{
			rXMLRPCRequest::$sent = 0;
			FileUtil::$log = array();

			XMLRPCProxy::decide($case['request'], $case['mode'], $case['safeParams']);

			$this->assertTrue(rXMLRPCRequest::$sent === 0,
				$name.' — decide() reaches no socket');
			$this->assertTrue(FileUtil::$log === array(),
				$name.' — decide() writes no log of its own');
		}
	}

	public function testDecideReportsItsReasoningWhateverLoggingIsSetTo()
	{
		foreach($this->cases as $name => $case)
		{
			$decision = XMLRPCProxy::decide($case['request'], $case['mode'],
				$case['safeParams']);

			// One line per decision, whether or not the caller wants it logged.
			// The switch belongs to the caller, which is why the fixture has
			// cases with logging off that still send identical bytes.
			$this->assertTrue(count($decision['log']) === 1,
				$name.' — reports exactly one line');

			if($case['enableLog'])
			{
				$expected = array();
				foreach($case['log'] as $line)
					$expected[] = substr($line, strlen('xmlrpc-proxy: '));
				$this->assertTrue($decision['log'] === $expected,
					$name.' — reports what process() logged, without the prefix');
			}
		}
	}

	/**
	 * The reason for the split: an endpoint that only needs to filter a request
	 * should not have to load ruTorrent to do it. Checked in a fresh process,
	 * because in this one the mocks above are already defined and would hide a
	 * dependency rather than reveal it.
	 */
	public function testDecideRunsWithNothingElseLoaded()
	{
		if(!function_exists('exec') || (PHP_BINARY === ''))
		{
			// A host that forbids starting a process cannot answer this; say so
			// rather than report a dependency that was never looked for.
			$this->assertTrue(true, 'no second process available, dependency check not run');
			return;
		}

		$proxy = realpath(__DIR__ . '/../../php/xmlrpc_proxy.php');
		$this->assertTrue($proxy !== false, 'the proxy file is where the test expects it');

		$script = 'require ' . var_export($proxy, true) . ';'
			. '$d = XMLRPCProxy::decide("not xml at all", "sanitize", array());'
			. 'echo $d["action"], "|", $d["trusted"] ? "trusted" : "untrusted", "|", $d["payload"];';

		$output = array();
		$status = 1;
		@exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=E_ALL -d display_errors=1 -r '
			. escapeshellarg($script) . ' 2>&1', $output, $status);

		$this->assertTrue($status === 0,
			'decide() runs with neither FileUtil nor rXMLRPCRequest defined: '
			. implode(' / ', $output));
		$this->assertTrue(implode("\n", $output) === 'send|untrusted|not xml at all',
			'and reaches the same decision there');
	}
}
