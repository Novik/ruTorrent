<?php

require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/SCGITransportFixture.php');
require_once(__DIR__.'/../../php/scgitransport.php');

class SCGITransportNonResource extends rSCGITransport
{
	public static $result;
	protected static function openSocket($address, &$errno, &$error, $timeout)
	{
		return self::$result;
	}
}

class SCGITransportNoTemporary extends rSCGITransport
{
	public static $result;
	protected static function openTemporary($directory = null)
	{
		return self::$result;
	}
}

class SCGITransportAvailabilityTest extends TestCase
{
	public function testDisabledRequiredFunctionsRefuseWithoutWarnings()
	{
		// A disabled function is a host setting, not a daemon that is down: core
		// logs this code, while it stays quiet about connect-failed.
		foreach(array('stream_socket_client', 'stream_set_blocking', 'stream_get_meta_data',
			'fread', 'fwrite', 'fclose', 'usleep', 'hrtime,microtime') as $function)
		{
			$run = $this->runDisabled($function, 'unused', 1);
			$this->assertTrue($run === array(0, '{"length":null,"failure":"transport-unavailable"}', ''),
				'a disabled '.$function.' is a clean refusal: '.json_encode($run));
		}
	}

	public function testCoreLogsAnUnavailableTransport()
	{
		$log = sys_get_temp_dir().'/rutorrent-scgi-unavailable-'.uniqid('', true).'.log';
		try
		{
			$code = 'require '.var_export(realpath(__DIR__.'/../../php/xmlrpc.php'), true).';'
				.'$GLOBALS["scgi_host"]="127.0.0.1";$GLOBALS["scgi_port"]=1;$GLOBALS["rpcLogCalls"]=false;'
				.'$GLOBALS["log_file"]=$argv[1];var_export(rXMLRPCRequest::send("<x/>",true));';
			$run = PhpProcess::run(array('-d', 'disable_functions=stream_socket_client', '-d', 'display_errors=stderr',
				'-r', $code, $log), array('cwd' => realpath(__DIR__.'/../..')));
			$this->assertTrue($run['out'] === 'false' && $run['err'] === '',
				'a disabled transport fails the core call cleanly: '.json_encode($run));
			$this->assertTrue(is_file($log) && strpos(file_get_contents($log), 'rXMLRPCRequest: transport-unavailable') !== false,
				'the core logs a disabled transport, unlike a stopped daemon it stays quiet about');
		}
		finally { @unlink($log); }
	}

	public function testNonResourceSocketIsAConnectionRefusal()
	{
		foreach(array(false, null) as $unavailable)
		{
			SCGITransportNonResource::$result = $unavailable;
			$failure = null;
			$result = SCGITransportNonResource::send('unused', 1, '<x/>', true, array('connectTimeout' => 1), $failure);
			$this->assertTrue($result === null && $failure === 'connect-failed',
				'a '.var_export($unavailable, true).' socket result is refused before configuration');
		}
	}

	public function testOptionalTemporaryFunctionsCanBeDisabled()
	{
		$length = rSCGITransport::MEMORY_RESPONSE_BYTES + 37;
		// Every function the spill path calls; any of them may be disabled.
		foreach(array('tempnam', 'fopen', 'fstat', 'lstat', 'unlink', 'rewind', 'stream_get_contents',
			'sys_get_temp_dir', 'is_dir', 'is_writable', 'realpath', 'dirname') as $function)
		{
			$peer = SCGITransportFixture::startRepeatedBody($length);
			$before = glob(sys_get_temp_dir().'/rutorrent-scgi-*', GLOB_NOSORT);
			try
			{
				$run = $this->runDisabled($function, $peer->host(), $peer->port());
				$this->assertTrue($run === array(0, '{"length":'.$length.',"failure":null}', ''),
					'a disabled '.$function.' still returns the complete response: '.json_encode($run));
				$this->assertTrue(count(glob(sys_get_temp_dir().'/rutorrent-scgi-*', GLOB_NOSORT)) <= count($before),
					'a disabled '.$function.' leaves no response file behind');
			}
			finally { $peer->close(); }
		}
	}

	public function testAnIdleWaitCostsLittleCpuWithOrWithoutSelect()
	{
		$source = realpath(__DIR__.'/../../php/scgitransport.php');
		$code = 'require '.var_export($source, true).';'
			.'$cpu=function(){$u=getrusage();return $u["ru_utime.tv_sec"]+$u["ru_utime.tv_usec"]/1e6+$u["ru_stime.tv_sec"]+$u["ru_stime.tv_usec"]/1e6;};'
			.'$f=null;$before=$cpu();'
			.'rSCGITransport::send($argv[1],(int)$argv[2],"<x/>",true,array("connectTimeout" => 1, "transferTimeout" => 0.6, "responseMode" => rSCGITransport::RESPONSE_BODY),$f);'
			.'echo json_encode(array("failure"=>$f,"cpu"=>$cpu()-$before));';
		// Waiting through a 0.6-second silence takes a few dozen wakeups; a
		// wait that sleeps too briefly, or not at all, burns far more.
		foreach(array('' => 'select', 'stream_select' => 'sleep') as $disabled => $kind)
		{
			$peer = SCGITransportFixture::startChunks(array(array(3, "Content-Length: 1\r\n\r\nx")));
			try
			{
				$run = PhpProcess::run(array('-d', 'disable_functions='.$disabled, '-r', $code, $peer->host(), (string)$peer->port()));
				$result = json_decode($run['out'], true);
				$this->assertTrue(is_array($result) && ($result['failure'] === 'read-timeout') && ($result['cpu'] < 0.03),
					'an idle wait through '.$kind.' costs little CPU: '.$run['out'].$run['err']);
			}
			finally { $peer->close(); }
		}
	}

	public function testEitherClockIsEnough()
	{
		foreach(array('hrtime', 'microtime') as $function)
		{
			$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
			try
			{
				$run = $this->runDisabled($function, $peer->host(), $peer->port());
				$this->assertTrue($run === array(0, '{"length":2,"failure":null}', ''),
					'the transport times itself without '.$function.': '.json_encode($run));
			}
			finally { $peer->close(); }
		}
	}

	public function testDisabledSelectStillCompletesTransport()
	{
		$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
		try
		{
			$run = $this->runDisabled('stream_select', $peer->host(), $peer->port());
			$this->assertTrue($run === array(0, '{"length":2,"failure":null}', ''),
				'a disabled select does not prevent a bounded nonblocking exchange: '.json_encode($run));
		}
		finally { $peer->close(); }
	}

	public function testSocketAboveSelectDescriptorCeilingStillCompletes()
	{
		if(function_exists('posix_getrlimit'))
		{
			$limits = posix_getrlimit();
			$soft = $limits['soft openfiles'] ?? null;
			$hard = $limits['hard openfiles'] ?? null;
			// Containers often start with a soft limit of 1024 under a far higher
			// hard one; raise the soft limit for this process when it can be.
			if(is_numeric($soft) && ($soft < 1200) && !(function_exists('posix_setrlimit')
				&& (!is_numeric($hard) || ($hard >= 1200))
				&& @posix_setrlimit(POSIX_RLIMIT_NOFILE, 1200, is_numeric($hard) ? (int)$hard : POSIX_RLIMIT_INFINITY)))
			{
				echo "Skipped: descriptor ceiling test requires 1200 open files\n";
				return;
			}
		}
		$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
		$files = array();
		try
		{
			for($i = 0; $i < 1100; $i++)
			{
				$file = @fopen('/dev/null', 'r');
				if(!is_resource($file))
					throw new Exception('could not open enough descriptors for the high socket test');
				$files[] = $file;
			}
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<x/>', true,
				array('connectTimeout' => 1, 'transferTimeout' => 3, 'maxResponseBytes' => 1024, 'responseMode' => rSCGITransport::RESPONSE_BODY), $failure);
			$this->assertTrue($result === 'ok' && $failure === null,
				'a socket above descriptor 1024 completes without select');
		}
		finally
		{
			foreach($files as $file) fclose($file);
			$peer->close();
		}
	}

	public function testNonResourceTemporaryResultFallsBackToMemory()
	{
		$length = rSCGITransport::MEMORY_RESPONSE_BYTES + 37;
		foreach(array(false, null) as $unavailable)
		{
			SCGITransportNoTemporary::$result = $unavailable;
			$peer = SCGITransportFixture::startRepeatedBody($length);
			try
			{
				$failure = null;
				$result = SCGITransportNoTemporary::send($peer->host(), $peer->port(), '<x/>', true,
					array('connectTimeout' => 1, 'transferTimeout' => 5, 'responseMode' => rSCGITransport::RESPONSE_BODY), $failure);
				$this->assertTrue($result === str_repeat('x', $length) && $failure === null,
					'a '.var_export($unavailable, true).' temporary stream falls back to the exact body');
			}
			finally { $peer->close(); }
		}
	}

	private function runDisabled($function, $host, $port)
	{
		$source = realpath(__DIR__.'/../../php/scgitransport.php');
		$code = 'require '.var_export($source, true).';'
			.'$f=null;$r=rSCGITransport::send($argv[1],(int)$argv[2],"<x/>",true,array("connectTimeout" => 1, "transferTimeout" => 5, "responseMode" => rSCGITransport::RESPONSE_BODY),$f);'
			.'echo json_encode(array("length"=>is_string($r)?strlen($r):null,"failure"=>$f));';
		$run = PhpProcess::run(array('-d', 'disable_functions='.$function,
			'-d', 'display_errors=stderr', '-d', 'log_errors=0', '-r', $code, $host, (string)$port));
		return array($run['code'], $run['out'], $run['err']);
	}
}
