<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/SCGITransportFixture.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/TestTree.php');

class SCGITransportFixtureTest extends TestCase
{
	public function testInvalidScriptLeavesNoFixtureDirectory()
	{
		$this->assertStartupCleanup('SCGITransportFixture::startChunks(array(array("invalid")))', '',
			'Exception: SCGI fixture chunk must contain delay and bytes');
	}

	public function testUnavailableProcessCreationLeavesNoRepeatedBodyDirectory()
	{
		$this->assertStartupCleanup('SCGITransportFixture::startRepeatedBody(10)', 'proc_open',
			'Exception: could not start SCGI fixture peer: proc_open is unavailable');
	}

	public function testASocketPathLongerThanSunPathIsRefusedByName()
	{
		// The fixture adds 49 bytes to TMPDIR: /rutorrent-scgi-<uniqid>/scgi.sock.
		$parent = sys_get_temp_dir();
		$room = 58 - strlen($parent) - 1;
		$fitting = ($room > 0) ? $parent.'/'.str_repeat('f', $room) : null;
		$tooLong = $parent.'/'.str_repeat('l', max(1, $room + 1));
		$expected = strlen($tooLong) + 49;
		if($fitting !== null)
			mkdir($fitting, 0700);
		mkdir($tooLong, 0700);
		try
		{
			if($fitting === null)
				echo "Skipped: a ".strlen($parent)."-byte TMPDIR leaves no room for a 107-byte socket path\n";
			else
			{
				$run = $this->runChild('$f = SCGITransportFixture::startUnix("x"); $c = stream_socket_client($f->host(), $n, $e, 3);'
					.'fwrite($c, "17:CONTENT_LENGTH\0001\000,x"); $r = $f->request(); fclose($c); $f->close(); echo $r["payload"];',
					array('TMPDIR' => $fitting));
				$this->assertTrue($run['out'] === 'x' && $run['err'] === '',
					'a 107-byte socket path is served: '.json_encode($run));
				$this->assertEquals(array(), glob($fitting.'/*'), 'and the served fixture leaves nothing in TMPDIR');
			}
			$run = $this->runChild('try { SCGITransportFixture::startUnix("x"); echo "started"; }'
				.' catch(Throwable $e) { echo $e->getMessage(); }', array('TMPDIR' => $tooLong));
			$this->assertTrue(strpos($run['out'], 'SCGI fixture socket path is '.$expected.' bytes') === 0
				&& strpos($run['out'], 'at most 107') !== false,
				'a '.$expected.'-byte socket path is refused by its length: '.json_encode($run));
			$this->assertEquals(array(), glob($tooLong.'/*'), 'the refusal leaves nothing in TMPDIR');
		}
		finally
		{
			if($fitting !== null)
				TestTree::remove($fitting);
			TestTree::remove($tooLong);
		}
	}

	public function testCloseKillsAPeerThatIgnoresSigterm()
	{
		$process = proc_open(array('/bin/sh', '-c', 'trap "" TERM; echo ready; exec sleep 8'),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		$this->assertTrue(is_resource($process), 'a process that ignores SIGTERM starts');
		$ready = fgets($pipes[1]);
		foreach($pipes as $pipe)
			fclose($pipe);
		$this->assertEquals("ready\n", $ready, 'it ignores SIGTERM before close() is called');
		$fixture = (new ReflectionClass('SCGITransportFixture'))->newInstanceWithoutConstructor();
		$property = new ReflectionProperty('SCGITransportFixture', 'process');
		if(PHP_VERSION_ID < 80100)
			$property->setAccessible(true);
		$property->setValue($fixture, $process);
		$started = microtime(true);
		try
		{
			$fixture->close();
			$elapsed = microtime(true) - $started;
			$this->assertTrue($elapsed < 5, 'close() escalates to SIGKILL: it took '.round($elapsed, 1).' s');
		}
		finally
		{
			if(is_resource($process)) { proc_terminate($process, 9); proc_close($process); }
		}
	}

	public function testAFixtureDoesNotOutliveATestThatEndsByAFatalError()
	{
		$directory = $this->privateTemp();
		$pid = 0;
		try
		{
			$run = $this->runChild('$f = SCGITransportFixture::start("x");'
				.'$p = new ReflectionProperty("SCGITransportFixture", "process");'
				.'if(PHP_VERSION_ID < 80100) $p->setAccessible(true);'
				.'echo proc_get_status($p->getValue($f))["pid"], "\n"; scgi_fixture_undefined_function();',
				array('TMPDIR' => $directory), array('-d', 'display_errors=0'));
			$pid = (int)trim($run['out']);
			$this->assertTrue($pid > 0, 'the child started a peer: '.json_encode($run));
			$this->assertEquals(array(), glob($directory.'/rutorrent-scgi-*'), 'the fatal error leaves no fixture directory');
			$this->assertTrue(!self::isPeer($pid, $directory), 'and no peer process');
		}
		finally
		{
			// A leaked peer is this test's own; stop it before reporting.
			if(self::isPeer($pid, $directory)) exec('kill -9 '.(int)$pid);
			TestTree::remove($directory);
		}
	}

	public function testAForkedChildLeavesItsParentsFixtureInPlace()
	{
		$directory = $this->privateTemp();
		try
		{
			// Inherited shutdown functions run in a forked child when it exits;
			// closing the fixture there would remove the parent's directory.
			$run = PhpProcess::run(array('-d', 'display_errors=stderr', '-r',
				'require '.var_export(__DIR__.'/SCGITransportFixture.php', true).';'
				.'if(!function_exists("pcntl_fork")) { echo "no pcntl"; exit(0); }'
				.'$f = SCGITransportFixture::start("x"); $pid = pcntl_fork();'
				.'if($pid === 0) { SCGITransportFixture::start("y"); exit(0); }'
				.'pcntl_waitpid($pid, $status); $c = stream_socket_client("tcp://".$f->host().":".$f->port(), $n, $e, 3);'
				.'fwrite($c, "17:CONTENT_LENGTH\0001\000,x");'
				.'try { echo $f->request()["payload"], count(glob(sys_get_temp_dir()."/rutorrent-scgi-*")); }'
				.' finally { fclose($c); $f->close(); }'),
				array('env' => array('TMPDIR' => $directory), 'timeout' => 30));
			$this->assertTrue(($run['out'] === 'x1' && $run['err'] === '') || $run['out'] === 'no pcntl',
				'the parent still captures its request, and the child closed only its own fixture: '.json_encode($run));
			if($run['out'] === 'no pcntl')
				echo "Skipped: no pcntl_fork in this runtime\n";
		}
		finally { TestTree::remove($directory); }
	}

	private function privateTemp()
	{
		$directory = sys_get_temp_dir().'/rt-fixture-'.uniqid('', true);
		mkdir($directory, 0700);
		return $directory;
	}

	/** Runs $code in a PHP child with the fixture loaded. */
	private function runChild($code, $environment, $arguments = array())
	{
		return PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d', 'display_errors=stderr', '-d', 'log_errors=0'),
			$arguments, array('-r', 'require '.var_export(__DIR__.'/SCGITransportFixture.php', true).';'.$code)),
			array('env' => $environment, 'timeout' => 30));
	}

	private static function isPeer($pid, $directory)
	{
		$command = ($pid > 0) ? @file_get_contents('/proc/'.(int)$pid.'/cmdline') : false;
		return is_string($command) && strpos($command, $directory.'/rutorrent-scgi-') !== false;
	}

	private function assertStartupCleanup($call, $disabled, $cause)
	{
		$directory = $this->privateTemp();
		try
		{
			$run = $this->runChild('$error=null;try { '.$call.'; } catch(Throwable $e) { $error=get_class($e).": ".$e->getMessage(); }'
				.'echo json_encode(array("error"=>$error,"left"=>glob(sys_get_temp_dir()."/rutorrent-scgi-*")));',
				array('TMPDIR' => $directory), array('-d', 'disable_functions='.$disabled));
			$result = json_decode($run['out'], true);
			$this->assertEquals(0, $run['code'], 'fixture startup error remains catchable');
			$this->assertEquals('', $run['err'], 'fixture startup failure emits no unhandled error');
			$this->assertTrue(is_array($result) && $result['error'] === $cause,
				'the startup failure names its cause: '.json_encode($result['error'] ?? $run['out']));
			$this->assertTrue(is_array($result) && $result['left'] === array(),
				'failed startup removes its private fixture directory');
		}
		finally { TestTree::remove($directory); }
	}
}
