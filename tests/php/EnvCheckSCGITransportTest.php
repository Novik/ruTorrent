<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/SCGITransportFixture.php');
require_once(__DIR__.'/TestTree.php');
require_once(__DIR__.'/PhpProcess.php');

class EnvCheckSCGITransportTest extends TestCase
{
	public function testDisabledTransportFunctionsAreRequiredWithoutAFatalError()
	{
		foreach(array('stream_socket_client', 'stream_set_blocking',
			'stream_get_meta_data', 'fread', 'fwrite', 'fclose', 'usleep') as $function)
		{
			$result = $this->checkEnvironment(null, null, $function);
			$this->assertEquals(1, $result['status'], $function.' is required for SCGI');
			$this->assertTrue(strpos($result['output'], '[FAIL] '.$function.'() available') !== false,
				'the checker names unavailable '.$function);
			$this->assertEquals('', $result['error'], 'unavailable '.$function.' does not crash the checker');
			$this->assertTrue(strpos($result['output'], 'Fatal error') === false,
				'unavailable '.$function.' leaves a complete diagnostic report');
		}
	}

	public function testDisabledClockIsRequired()
	{
		$result = $this->checkEnvironment(null, null, 'hrtime,microtime');
		$this->assertEquals(1, $result['status'], 'a clock is required for SCGI');
		$this->assertTrue(strpos($result['output'], '[FAIL] hrtime() or microtime() available') !== false,
			'the checker names the missing clock');
		$this->assertEquals('', $result['error'], 'a missing clock does not crash the checker');
		$this->assertTrue(strpos($result['output'], 'rtorrent reachable') === false,
			'without a clock the daemon is not probed');
		foreach(array('hrtime', 'microtime') as $disabled)
		{
			$result = $this->checkEnvironment(null, null, $disabled);
			$this->assertTrue(strpos($result['output'], '[OK  ] hrtime() or microtime() available') !== false,
				'either clock alone is enough');
		}
	}

	public function testInvalidTimeoutsAreReportedWithTheValueInUse()
	{
		$result = $this->checkEnvironment(null, null, '', '$rpcTimeOut="5s";$rpcTransferTimeOut=0;');
		$this->assertTrue(preg_match('/\\$rpcTimeOut +invalid value \'5s\'; using 30 seconds/', $result['output']) === 1,
			'an unusable connect timeout is reported with the 30 seconds used instead: '.$result['output']);
		$this->assertTrue(preg_match('/\\$rpcTransferTimeOut +invalid value 0; using 60 seconds, as for null/', $result['output']) === 1,
			'an unusable transfer budget is reported with the budget null gives');
		$result = $this->checkEnvironment(null, null, '', '$rpcTimeOut=5;$rpcTransferTimeOut=null;');
		$this->assertTrue(strpos($result['output'], 'invalid value') === false, 'usable settings are not reported');
	}

	public function testABusyDaemonIsNotReportedAsUnreachable()
	{
		$body = '<methodResponse><params><param><value><string>0.16.26</string></value></param></params></methodResponse>';
		$peer = SCGITransportFixture::startChunks(array(array(1.5, 'Content-Length: '.strlen($body)."\r\n\r\n".$body)));
		try
		{
			$result = $this->checkEnvironment(null, $peer, '', '$rpcTransferTimeOut=0.5;');
			$this->assertTrue(strpos($result['output'], 'did not answer within the 0.5-second transfer budget') !== false
				&& strpos($result['output'], 'could not reach rtorrent') === false,
				'a daemon slower than the configured budget is reported as busy: '.$result['output']);
		}
		finally { $peer->close(); }
	}

	public function testASilentDaemonHoldsTheCheckOnlyBriefly()
	{
		$body = '<methodResponse><params><param><value><string>0.16.26</string></value></param></params></methodResponse>';
		$peer = SCGITransportFixture::startChunks(array(array(3, 'Content-Length: '.strlen($body)."\r\n\r\n".$body)));
		try
		{
			$started = microtime(true);
			$result = $this->checkEnvironment(null, $peer, '', '$rpcTransferTimeOut=30;', 0.5);
			$this->assertTrue(strpos($result['output'], 'did not answer within 0.5 seconds -- it may be busy; the WebUI waits up to 30 seconds') !== false
				&& (microtime(true) - $started < 2.5),
				'the probe gives up long before the WebUI budget: '.$result['output']);
		}
		finally { $peer->close(); }
	}

	public function testTheProbeTellsAFaultFromAMissingVersionAndAnAbsentDaemon()
	{
		$fault = '<methodResponse><fault><value><struct></struct></value></fault></methodResponse>';
		$empty = '<methodResponse><params><param><value><i8>1</i8></value></param></params></methodResponse>';
		foreach(array($fault => 'answered system.client_version with a fault',
			$empty => 'answered system.client_version without a version') as $body => $message)
		{
			$peer = SCGITransportFixture::start('Content-Length: '.strlen($body)."\r\n\r\n".$body);
			try
			{
				$result = $this->checkEnvironment(null, $peer);
				$this->assertTrue(strpos($result['output'], $message) !== false, $message);
			}
			finally { $peer->close(); }
		}
		$result = $this->checkEnvironment();
		$this->assertTrue(strpos($result['output'], 'could not reach rtorrent at') !== false, 'an absent daemon cannot be reached');
	}

	public function testTheProbeConnectsWithinTheConfiguredTimeout()
	{
		// With its accept queue full, the listener drops further connection
		// attempts, so connecting waits for the timeout instead of failing.
		$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
			stream_context_create(array('socket' => array('backlog' => 0))));
		$address = stream_socket_get_name($server, false);
		$queued = array();
		try
		{
			while((count($queued) < 8) && (($client = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1)) !== false))
				$queued[] = $client;
			$port = (int)substr($address, strrpos($address, ':') + 1);
			$started = microtime(true);
			$result = $this->checkEnvironment(null, null, '', '$scgi_host="127.0.0.1";$scgi_port='.$port.';$rpcTimeOut=0.5;');
			$this->assertTrue(strpos($result['output'], 'could not reach rtorrent at') !== false
				&& (microtime(true) - $started < 3), 'the probe gives up connecting after $rpcTimeOut');
			$started = microtime(true);
			$result = $this->checkEnvironment(null, null, '', '$scgi_host="127.0.0.1";$scgi_port='.$port.';$rpcTimeOut=30;', 0.5);
			$this->assertTrue(strpos($result['output'], '(this check connects within 0.5 seconds; the WebUI waits up to 30)') !== false
				&& (microtime(true) - $started < 3), 'a long $rpcTimeOut is capped like the transfer, and the report says so');
		}
		finally
		{
			foreach($queued as $client)
				fclose($client);
			fclose($server);
		}
	}

	public function testInvalidResponseLimitIsReportedOnce()
	{
		foreach(array('64M', 0, -1, 104857601) as $value)
		{
			$result = $this->checkEnvironment($value);
			$this->assertTrue(substr_count($result['output'], '[WARN] $rpcMaxResponseBytes') === 1,
				'invalid response cap '.var_export($value, true).' is diagnosed once');
			$this->assertTrue(strpos($result['output'], '104857600 bytes (100 MiB)') !== false,
				'the diagnostic names the effective fallback limit');
		}
		foreach(array(null, 64, '67108864', 1e8) as $value)
		{
			$result = $this->checkEnvironment($value);
			$this->assertTrue(strpos($result['output'], '[WARN] $rpcMaxResponseBytes') === false,
				'valid response cap '.var_export($value, true).' needs no warning');
		}
	}

	public function testVersionProbeHonoursTheConfiguredResponseLimit()
	{
		$body = '<methodResponse><params><param><value><string>0.16.26</string></value></param></params></methodResponse>';
		$peer = SCGITransportFixture::start('Content-Length: '.strlen($body)."\r\n\r\n".$body);
		try
		{
			$result = $this->checkEnvironment(64, $peer);
			$this->assertTrue(strpos($result['output'], 'sent no usable SCGI reply (response-too-large)') !== false,
				'the probe applies $rpcMaxResponseBytes like the WebUI: '.$result['output']);
		}
		finally { $peer->close(); }
	}

	public function testVersionProbeRejectsAnIncompleteSCGIFrame()
	{
		$body = '<methodResponse><params><param><value><string>0.16.25</string></value></param></params></methodResponse>';
		$peer = SCGITransportFixture::start('Content-Length: '.(strlen($body)+1)."\r\n\r\n".$body);
		try
		{
			$result = $this->checkEnvironment(null, $peer);
			$this->assertTrue(strpos($result['output'], 'found 0.16.25') === false
				&& strpos($result['output'], 'sent no usable SCGI reply (truncated-body)') !== false,
				'the diagnostic never publishes a version from an incomplete daemon frame');
			$this->assertTrue(strpos($peer->request()['header'], "UNTRUSTED_CONNECTION\x000\x00") !== false,
				'the diagnostic uses the common trusted SCGI request format');
		}
		finally { $peer->close(); }
	}

	private function checkEnvironment($limit = null, $peer = null, $disabled = '', $extraConfig = '', $probeSeconds = null)
	{
		$directory = sys_get_temp_dir().'/rutorrent-env-scgi-'.uniqid('', true);
		mkdir($directory.'/php', 0700, true);
		mkdir($directory.'/conf', 0700);
		try
		{
			foreach(array('env_check.php', 'php/scgitransport.php') as $file)
				copy(__DIR__.'/../../'.$file, $directory.'/'.$file);
			$host = $peer === null ? 'unix://'.$directory.'/absent.sock' : $peer->host();
			$port = $peer === null ? 0 : $peer->port();
			file_put_contents($directory.'/conf/config.php', '<?php $scgi_host='.var_export($host, true)
				.';$scgi_port='.$port.';$XMLRPCMountPoint="/RPC2";$rpcMaxResponseBytes='.var_export($limit, true).';'
				.$extraConfig);
			$arguments = array('-n', '-d', 'disable_functions='.$disabled);
			if($probeSeconds !== null)
			{
				file_put_contents($directory.'/probe.php', '<?php define("ENV_CHECK_PROBE_SECONDS", '.var_export($probeSeconds, true).');');
				$arguments = array_merge($arguments, array('-d', 'auto_prepend_file='.$directory.'/probe.php'));
			}
			$run = PhpProcess::run(array_merge($arguments, array($directory.'/env_check.php')));
			return array('status' => $run['code'], 'output' => $run['out'], 'error' => $run['err']);
		}
		finally
		{
			TestTree::remove($directory);
		}
	}
}
