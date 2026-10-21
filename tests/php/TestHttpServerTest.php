<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/TestHttpServer.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/TestTree.php');

/** Offers scripted ports before free ones, so a test can drive start()'s retry. */
class ScriptedPortHttpServer extends TestHttpServer
{
	public static $ports = array();
	public static $offered = array();

	protected static function choosePort()
	{
		$port = (count(self::$ports) > 0) ? array_shift(self::$ports) : parent::choosePort();
		self::$offered[] = $port;
		return $port;
	}
}

class TestHttpServerTest extends TestCase
{
	private function tree($label)
	{
		$tree = sys_get_temp_dir().'/rt-http-'.$label.'-'.uniqid('', true);
		mkdir($tree, 0700);
		return $tree;
	}

	/** Runs $code in a PHP child with TestHttpServer loaded and the tree as $argv[1]. */
	private function runChild($code, $tree, $arguments = array())
	{
		return PhpProcess::run(array_merge(PhpProcess::sameIni(),
			array('-d', 'display_errors=stderr'), $arguments, array('-r',
			'require '.var_export(__DIR__.'/TestHttpServer.php', true).';'.$code, $tree)),
			array('timeout' => 30));
	}

	/** A process that ignores SIGTERM, as a server stuck in a handler can. */
	private function startStubborn()
	{
		$process = proc_open(array('/bin/sh', '-c', 'trap "" TERM; echo ready; exec sleep 8'),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if(!is_resource($process))
			throw new Exception('could not start a process that ignores SIGTERM');
		$ready = fgets($pipes[1]);
		foreach($pipes as $pipe)
			fclose($pipe);
		if($ready !== "ready\n")
		{
			proc_terminate($process, 9);
			proc_close($process);
			throw new Exception('the process that ignores SIGTERM did not get ready');
		}
		return $process;
	}

	private static function setPrivate($object, $name, $value)
	{
		$property = new ReflectionProperty(get_class($object), $name);
		if(PHP_VERSION_ID < 80100)
			$property->setAccessible(true);
		$property->setValue($object, $value);
	}

	public function testProcessStartupFailureNamesItsCauseAndRemovesTheRouterWrapper()
	{
		$tree = $this->tree('start');
		file_put_contents($tree.'/router.php', '<?php return true;');
		try
		{
			$run = $this->runChild('$error=null;try { TestHttpServer::start($argv[1],array(),array(),$argv[1]."/router.php"); }'
				.'catch(Throwable $e) { $error=get_class($e).": ".$e->getMessage(); }'
				.'echo json_encode(array("error"=>$error,"left"=>array_merge('
				.'glob($argv[1]."/__test_router_*"),glob($argv[1]."/__test_http_*"))));',
				$tree, array('-d', 'log_errors=0', '-d', 'disable_functions=proc_open'));
			$result = json_decode($run['out'], true);
			$this->assertEquals(0, $run['code'], 'HTTP startup failure remains catchable');
			$this->assertEquals('', $run['err'], 'HTTP startup failure emits no unhandled error');
			$this->assertTrue(is_array($result)
				&& $result['error'] === 'Exception: could not start the HTTP test server process: proc_open is unavailable',
				'the failure names the process that could not start: '.json_encode($result['error'] ?? $run['out']));
			$this->assertTrue(is_array($result) && $result['left'] === array(),
				'failed HTTP startup removes its wrapper and readiness file');
		}
		finally { TestTree::remove($tree); }
	}

	public function testRouterReadinessDoesNotInvokeTheApplication()
	{
		$tree = $this->tree('router');
		$server = null;
		file_put_contents($tree.'/router.php', '<?php file_put_contents('.var_export($tree.'/calls', true)
			.', "routed\n", FILE_APPEND); echo "routed"; return true;');
		try
		{
			$server = TestHttpServer::start($tree, array(), array(), $tree.'/router.php');
			$this->assertTrue(!file_exists($tree.'/calls'), 'readiness never runs the application router');
			$result = TestHttpServer::rawGet($server->port(), '/application');
			$this->assertEquals('routed', $result['body'], 'ordinary requests reach the application router');
			$this->assertEquals("routed\n", file_get_contents($tree.'/calls'), 'one request invokes the router once');
			$server->close();
			$this->assertEquals(array(), glob($tree.'/__test_router_*'), 'closing the server removes its router wrapper');
		}
		finally
		{
			if($server !== null) $server->close();
			TestTree::remove($tree);
		}
	}

	public function testATakenPortIsRetriedOnAnotherOne()
	{
		$tree = $this->tree('retry');
		// Something else took the reserved port before php -S could bind it.
		$holder = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		$name = stream_socket_get_name($holder, false);
		$taken = (int)substr($name, strrpos($name, ':') + 1);
		$server = null;
		ScriptedPortHttpServer::$ports = array($taken);
		ScriptedPortHttpServer::$offered = array();
		try
		{
			$server = ScriptedPortHttpServer::start($tree);
			$this->assertTrue(count(ScriptedPortHttpServer::$offered) === 2 && ScriptedPortHttpServer::$offered[0] === $taken,
				'the taken port is tried first and another is chosen: '.json_encode(ScriptedPortHttpServer::$offered));
			$this->assertTrue($server->port() !== $taken && $server->port() === end(ScriptedPortHttpServer::$offered),
				'the server runs on the port chosen last');
			file_put_contents($tree.'/probe.txt', 'probe');
			$this->assertEquals('probe', TestHttpServer::rawGet($server->port(), '/probe.txt')['body'], 'and answers there');
		}
		finally
		{
			ScriptedPortHttpServer::$ports = array();
			if($server !== null) $server->close();
			fclose($holder);
			TestTree::remove($tree);
		}
	}

	public function testAForeignListenerWithoutTheTokenIsRejected()
	{
		$foreignTree = $this->tree('foreign');
		$tree = $this->tree('own');
		// Answers 200 to every path, the readiness path included.
		file_put_contents($foreignTree.'/router.php', '<?php file_put_contents('.var_export($foreignTree.'/hits', true)
			.', $_SERVER["REQUEST_URI"]."\n", FILE_APPEND); echo "foreign"; return true;');
		$foreign = null;
		ScriptedPortHttpServer::$offered = array();
		try
		{
			$foreign = TestHttpServer::start($foreignTree, array(), array(), $foreignTree.'/router.php');
			ScriptedPortHttpServer::$ports = array_fill(0, 20, $foreign->port());
			$error = null;
			try
			{
				ScriptedPortHttpServer::start($tree)->close();
			}
			catch(Exception $exception)
			{
				$error = $exception->getMessage();
			}
			$this->assertTrue(is_string($error) && strpos($error, 'HTTP test server did not start') === 0,
				'a listener that does not return the token is not taken for the server: '.json_encode($error));
			$this->assertTrue(is_string($error) && strpos($error, 'exited') !== false
				&& strpos($error, 'Address already in use') !== false,
				'the failure says the server exited and why: '.json_encode($error));
			$hits = is_file($foreignTree.'/hits') ? file_get_contents($foreignTree.'/hits') : '';
			$this->assertTrue(strpos($hits, '/__test_http_') !== false,
				'the readiness request reached the foreign listener and was answered 200');
			$this->assertTrue(count(array_unique(ScriptedPortHttpServer::$offered)) === 1,
				'every attempt was offered only the foreign port');
		}
		finally
		{
			ScriptedPortHttpServer::$ports = array();
			if($foreign !== null) $foreign->close();
			TestTree::remove($foreignTree);
			TestTree::remove($tree);
		}
	}

	public function testTheServerInheritsTheEnvironmentWithTheAddedVariables()
	{
		$tree = $this->tree('env');
		file_put_contents($tree.'/env.php',
			'<?php echo json_encode(array(getenv("PATH"), getenv("TMPDIR"), getenv("TEST_HTTP_ADDED")));');
		try
		{
			// A stock php.ini leaves E out of variables_order, and $_ENV empty.
			$run = $this->runChild('$server = TestHttpServer::start($argv[1], array("TEST_HTTP_ADDED" => "added"));'
				.'try { echo TestHttpServer::rawGet($server->port(), "/env.php")["body"]; } finally { $server->close(); }',
				$tree, array('-d', 'variables_order=GPCS'));
			$this->assertTrue(json_decode($run['out'], true) === array(getenv('PATH'), getenv('TMPDIR'), 'added'),
				'PATH and TMPDIR are inherited beside the added variable: '.json_encode($run));
		}
		finally { TestTree::remove($tree); }
	}

	public function testAResponseCutByTheClientTimeoutIsAnError()
	{
		$tree = $this->tree('stall');
		file_put_contents($tree.'/stall.php', '<?php while(ob_get_level()) ob_end_flush();'
			.' header("Content-Length: 100"); echo "partial"; flush(); sleep(4);');
		$server = null;
		try
		{
			$server = TestHttpServer::start($tree);
			$error = $result = null;
			try
			{
				$result = TestHttpServer::rawGet($server->port(), '/stall.php', 1);
			}
			catch(Exception $exception)
			{
				$error = $exception->getMessage();
			}
			$this->assertTrue(is_string($error) && strpos($error, 'timed out') !== false,
				'a response the client timeout cut short is not returned: '.json_encode($error ?? $result));
		}
		finally
		{
			if($server !== null) $server->close();
			TestTree::remove($tree);
		}
	}

	public function testCloseKillsAServerThatIgnoresSigterm()
	{
		$process = $this->startStubborn();
		$server = (new ReflectionClass('TestHttpServer'))->newInstanceWithoutConstructor();
		self::setPrivate($server, 'process', $process);
		$started = microtime(true);
		try
		{
			$server->close();
			$elapsed = microtime(true) - $started;
			$this->assertTrue($elapsed < 5, 'close() escalates to SIGKILL: it took '.round($elapsed, 1).' s');
		}
		finally
		{
			if(is_resource($process)) { proc_terminate($process, 9); proc_close($process); }
		}
	}

	public function testAServerDoesNotOutliveATestThatEndsByExitOrFatalError()
	{
		foreach(array('exit(3);', 'test_http_server_undefined_function();') as $ending)
		{
			$tree = $this->tree('shutdown');
			$port = $pid = 0;
			try
			{
				$run = $this->runChild('$server = TestHttpServer::start($argv[1]);'
					.'$process = new ReflectionProperty("TestHttpServer", "process");'
					.'if(PHP_VERSION_ID < 80100) $process->setAccessible(true);'
					.'echo $server->port(), " ", proc_get_status($process->getValue($server))["pid"], "\n";'.$ending,
					$tree, array('-d', 'log_errors=0', '-d', 'display_errors=0'));
				list($port, $pid) = array_map('intval', explode(' ', trim($run['out'])) + array(0, 0));
				$this->assertTrue($port > 0 && $pid > 0, 'the child started a server: '.json_encode($run));
				$client = @fsockopen('127.0.0.1', $port, $errno, $error, 1);
				$this->assertTrue($client === false, $ending.' leaves no server answering on its port');
				if($client !== false) fclose($client);
				$this->assertTrue(!self::isServer($pid, $tree), $ending.' leaves no php -S process behind');
			}
			finally
			{
				// A leaked server is this test's own; stop it before reporting.
				if(self::isServer($pid, $tree)) exec('kill -9 '.(int)$pid);
				TestTree::remove($tree);
			}
		}
	}

	public function testAForkedChildLeavesItsParentsServerRunning()
	{
		$tree = $this->tree('fork');
		try
		{
			// Inherited shutdown functions run in a forked child when it exits;
			// closing the server there would also remove its router wrapper.
			file_put_contents($tree.'/router.php', '<?php echo "routed"; return true;');
			$run = $this->runChild('if(!function_exists("pcntl_fork")) { echo "no pcntl"; exit(0); }'
				.'$server = TestHttpServer::start($argv[1], array(), array(), $argv[1]."/router.php");'
				.'$pid = pcntl_fork();'
				.'if($pid === 0) { file_put_contents($argv[1]."/child.port", TestHttpServer::start($argv[1])->port()); exit(0); }'
				.'pcntl_waitpid($pid, $status); $own = (int)file_get_contents($argv[1]."/child.port");'
				.'try { echo TestHttpServer::rawGet($server->port(), "/application")["body"],'
				.' (@fsockopen("127.0.0.1", $own, $n, $e, 1) === false) ? "+closed" : "+left"; } finally { $server->close(); }',
				$tree);
			$this->assertTrue($run['out'] === 'routed+closed' || $run['out'] === 'no pcntl',
				'the parent still reaches its server, and the child closed only its own: '.json_encode($run));
			if($run['out'] === 'no pcntl')
				echo "Skipped: no pcntl_fork in this runtime\n";
		}
		finally { TestTree::remove($tree); }
	}

	private static function isServer($pid, $tree)
	{
		$command = ($pid > 0) ? @file_get_contents('/proc/'.(int)$pid.'/cmdline') : false;
		return is_string($command) && strpos($command, "\0-S\0") !== false && strpos($command, $tree) !== false;
	}
}
