<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');
require_once(__DIR__ . '/SCGITransportFixture.php');
require_once(__DIR__ . '/PhpProcess.php');

class SCGIConsumersTest extends TestCase
{
	private $previousGlobals;
	private $base;

	public function setUp()
	{
		$this->previousGlobals = GlobalSettingsFixture::scgi();
		$this->base = sys_get_temp_dir() . '/rutorrent-scgi-consumer-' . uniqid('', true);
		mkdir($this->base, 0700);
		$GLOBALS['rpcLogCalls'] = false;
		$GLOBALS['log_file'] = $this->base . '/errors.log';
		$GLOBALS['tempDirectory'] = $this->base;
		$GLOBALS['tempDirectory_init_done'] = false;
		unset($GLOBALS['rpcMaxResponseBytes']);
	}

	public function tearDown()
	{
		foreach(glob($this->base . '/*') as $file)
			@unlink($file);
		@rmdir($this->base);
		$this->previousGlobals->restore();
	}

	private function usePeer($peer, $connectTimeout, $transferTimeout)
	{
		$GLOBALS['scgi_host'] = $peer->host();
		$GLOBALS['scgi_port'] = $peer->port();
		$GLOBALS['rpcTimeOut'] = $connectTimeout;
		$GLOBALS['rpcTransferTimeOut'] = $transferTimeout;
	}

	public function testCoreCollectsALargeResponseWhereRuTorrentKeepsTemporaryFiles()
	{
		// With $tempDirectory unset, ruTorrent's own detection starts from
		// upload_tmp_dir, which only a new PHP process can be given. The name
		// is removed before any byte is written, so the directory's
		// modification time is what shows that the file was created there.
		$spill = $this->base . '/spill';
		mkdir($spill, 0700);
		touch($spill, time() - 3600);
		$length = rSCGITransport::MEMORY_RESPONSE_BYTES + 37;
		$peer = SCGITransportFixture::startRepeatedBody($length);
		try
		{
			$code = 'require ' . var_export(realpath(__DIR__ . '/../../php/xmlrpc.php'), true) . ';'
				. '$scgi_host = $argv[1]; $scgi_port = (int)$argv[2]; $rpcTimeOut = 3; $rpcTransferTimeOut = 10;'
				. '$tempDirectory = null; $tempDirectory_init_done = false; $log_file = $argv[3];'
				. '$failure = null; $result = rXMLRPCRequest::send("<request/>", true, $failure, rSCGITransport::RESPONSE_BODY);'
				. 'echo is_string($result) ? strlen($result) : $failure;';
			$run = PhpProcess::run(array('-d', 'upload_tmp_dir=' . $spill, '-r', $code,
				$peer->host(), (string)$peer->port(), $this->base . '/child.log'));
			clearstatcache();
			$this->assertTrue($run['out'] === (string)$length, 'the large response completes: ' . json_encode($run));
			$this->assertTrue((filemtime($spill) > time() - 3600) && (count(scandir($spill)) === 2),
				'the core collected it in the directory ruTorrent detected and left nothing there');
		}
		finally
		{
			$peer->close();
			@rmdir($spill);
		}
	}

	public function testCoreForwardsTheCallersTrustToTheScgiHeader()
	{
		foreach(array(true, false) as $trusted)
		{
			$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
			try
			{
				$this->usePeer($peer, 3, 3);
				$this->assertTrue(rXMLRPCRequest::send('<request/>', $trusted) !== false,
					'the core completes a ' . ($trusted ? 'trusted' : 'untrusted') . ' call');
				$request = $peer->request();
				$this->assertTrue(strpos($request['header'], "UNTRUSTED_CONNECTION\0" . ($trusted ? '0' : '1') . "\0") !== false,
					'the core preserves the caller trust in the SCGI request');
				$this->assertEquals('<request/>', $request['payload'], 'the payload reaches the peer unchanged');
			}
			finally { $peer->close(); }
		}
	}

	public function testCoreForwardsBodyModeWithoutDaemonHeaders()
	{
		$body = '<?xml version="1.0"?><methodResponse><params></params></methodResponse>';
		$peer = SCGITransportFixture::start("Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: "
			. strlen($body) . "\r\n\r\n" . $body);
		try
		{
			$this->usePeer($peer, 3, 3);
			$failure = null;
			$result = rXMLRPCRequest::send('<request/>', true, $failure, rSCGITransport::RESPONSE_BODY);
			$this->assertTrue($result === $body && $failure === null,
				'the core forwards body mode and returns exactly the XML without SCGI headers');
		}
		finally { $peer->close(); }
	}

	public function testCoreForwardsItsTransferBudgetAndFailureReason()
	{
		$peer = SCGITransportFixture::startChunks(array(array(1.5, "Content-Length: 2\r\n\r\nok")));
		try
		{
			$this->usePeer($peer, 3, 0.3);
			$failure = null;
			$start = microtime(true);
			$result = rXMLRPCRequest::send('<request/>', true, $failure);
			$elapsed = microtime(true) - $start;
			$this->assertTrue($result === false && $failure === 'read-timeout',
				'the configured core budget refuses the delayed response and names the failure');
			$this->assertTrue($elapsed < 1.2,
				'the core returns before the delayed response: ' . $elapsed . ' seconds');
			$log = is_file($GLOBALS['log_file']) ? file_get_contents($GLOBALS['log_file']) : '';
			$this->assertTrue(strpos($log, 'rXMLRPCRequest: read-timeout after the 0.3-second transfer budget; '
				. 'raise $rpcTransferTimeOut for slower replies') !== false,
				'the log line names the budget that ran out and the setting that changes it: ' . $log);
		}
		finally { $peer->close(); }
	}

	public function testCoreDefaultBudgetPreservesALongerConnectionTimeout()
	{
		$previous = ini_get('default_socket_timeout');
		$peer = SCGITransportFixture::startChunks(array(array(1.3, "Content-Length: 2\r\n\r\nok")));
		try
		{
			ini_set('default_socket_timeout', '1');
			$this->usePeer($peer, 3, null);
			$this->assertEquals("Content-Length: 2\r\n\r\nok", rXMLRPCRequest::send('<request/>'),
				'a null transfer budget preserves a configured connection timeout longer than the PHP default');
		}
		finally
		{
			ini_set('default_socket_timeout', $previous);
			$peer->close();
		}
	}
}
