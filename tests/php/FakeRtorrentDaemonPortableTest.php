<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/RtorrentProbeFixture.php');
require_once(__DIR__ . '/TestTree.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');

/** A test-owned callable whose class has to be loaded in the worker. */
class FakeRtorrentPortableResponder
{
	private $requests = 0;

	public function __invoke($body, $methods)
	{
		return array(++$this->requests, implode(',', $methods), $body);
	}
}

class FakeRtorrentDaemonPortableTest extends TestCase
{
	private $base;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/fake-daemon-portable-' . getmypid();
		mkdir($this->base, 0700);
	}

	public function tearDown()
	{
		TestTree::remove($this->base);
	}

	private function child($mode, $withoutIni = true, $disablePcntl = true, $environment = array())
	{
		$arguments = array();
		if($withoutIni)
			$arguments[] = '-n';
		if($disablePcntl)
			array_push($arguments, '-d', 'disable_functions=pcntl_fork,pcntl_waitpid,posix_kill');
		// conf/config.php reads RU_LOG_FILE from $_ENV, which a php.ini
		// without E in variables_order leaves empty.
		array_push($arguments, '-d', 'variables_order=EGPCS', '-d', 'display_errors=stderr', '-r',
			'require ' . var_export(__DIR__ . '/FakeRtorrentDaemonPortableTest.php', true) . ';'
			. 'FakeRtorrentDaemonPortableTest::scenario('
			. var_export($mode, true) . ',' . var_export($this->base, true) . ');');
		@unlink($this->base . '/finally.pids');
		// A peer that never starts cannot hang the regression past this timeout.
		$run = PhpProcess::run($arguments, array('timeout' => 15, 'env' => $environment));
		$finally = is_file($this->base . '/finally.pids')
			? file($this->base . '/finally.pids', FILE_IGNORE_NEW_LINES) : array();
		$this->assertTrue(!$run['timedOut'] && $run['code'] === 0 && $run['out'] === "OK\n"
			&& $run['err'] === '' && count($finally) === 1,
			$mode . ($withoutIni ? ' under php -n' : ' with php.ini')
			. ': exit=' . $run['code'] . ', stdout=' . json_encode($run['out'])
			. ', stderr=' . json_encode($run['err']) . ', finally ran in ' . json_encode($finally));
	}

	public function testQueuedRepliesWorkWithoutPcntlOrPosix()
	{
		$this->child('queue');
		$this->child('queue', false);
	}

	public function testSerializableRespondersRetainStateAndMulticallMethods()
	{
		$this->child('responder');
	}

	public function testTheProbeFixtureRespondsWithoutPcntlOrPosix()
	{
		$this->child('probe-fixture');
	}

	public function testStopReapsTheWorkerAndReleasesItsListener()
	{
		$this->child('stop');
	}

	public function testWorkerStartupFailureIsVisible()
	{
		$this->child('startup-failure');
	}

	public function testClosureRepliesKeepTheirExistingForkSemantics()
	{
		$this->child('closure', false, false);
		$this->child('closure-unavailable');
	}

	public function testAConnectionWithoutARequestDoesNotStopTheWorker()
	{
		$this->child('empty-client');
	}

	public function testAForkedDaemonSurvivesAConnectionWithoutARequest()
	{
		$this->child('fork-empty-client', false, false);
	}

	public function testAForkedDaemonThatFailsNeverReturnsIntoTheTest()
	{
		$this->child('fork-closure-throws', false, false);
	}

	public function testTheRequestLogStaysInThePrivateFile()
	{
		// The dropped reply in the queue makes rXMLRPCRequest log a failure
		// line. Nothing can create this RU_LOG_FILE, so a line that went to
		// conf/config.php's log instead of the private one is a warning on
		// stderr.
		@unlink($this->base . '/errors.log');
		$this->child('queue', true, true, array('RU_LOG_FILE' => '/proc/rutorrent-none/errors.log'));
		$this->assertTrue(is_file($this->base . '/errors.log')
			&& strpos(file_get_contents($this->base . '/errors.log'), 'rXMLRPCRequest: ') !== false,
			'the failure line is in the private log');
	}

	private static function expect($condition, $message)
	{
		if(!$condition)
			throw new RuntimeException($message);
	}

	private static function request($daemon, $body)
	{
		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['scgi_port'] = $daemon->port();
		$GLOBALS['rpcTimeOut'] = 3;
		// A forked daemon that died leaves the listener open in this process,
		// so a request would otherwise wait for default_socket_timeout.
		$GLOBALS['rpcTransferTimeOut'] = 3;
		$GLOBALS['rpcLogCalls'] = false;
		return rXMLRPCRequest::send($body);
	}

	/** One client that sends nothing and one that stops inside the netstring. */
	private static function connectWithoutRequest($daemon)
	{
		foreach(array('', '24:CONTENT_LENGTH') as $partial)
		{
			$client = @stream_socket_client('tcp://127.0.0.1:' . $daemon->port(), $errno, $error, 3);
			self::expect(is_resource($client), 'a client connects to the daemon: ' . $error);
			if($partial !== '')
				fwrite($client, $partial);
			fclose($client);
		}
	}

	/** Invoked by an isolated PHP process, including one with no loaded ini. */
	public static function scenario($mode, $base)
	{
		$daemon = null;
		$code = 0;
		$GLOBALS['log_file'] = $base . '/errors.log';
		try
		{
			$body = "<methodCall><methodName>probe</methodName><params>line\n2</params></methodCall>";
			if(in_array($mode, array('closure', 'closure-unavailable', 'fork-empty-client', 'fork-closure-throws'), true))
			{
				$available = function_exists('pcntl_fork') && function_exists('pcntl_waitpid')
					&& function_exists('posix_kill');
				$reply = ($mode === 'fork-closure-throws')
					? function($request) { throw new RuntimeException('scripted closure failure'); }
					: function($request) { return array($request); };
				try
				{
					$daemon = new FakeRtorrentDaemon($reply, $base . '/closure');
				}
				catch(RuntimeException $error)
				{
					self::expect(!$available && strpos($error->getMessage(), 'closure reply requires') !== false,
						'an unavailable closure has an actionable refusal');
				}
				self::expect($available === ($daemon !== null), 'closures fork only with pcntl and posix');
				if($daemon !== null && $mode === 'fork-closure-throws')
				{
					self::expect(self::request($daemon, $body) === false, 'a closure that throws sends no reply');
					// The child reports and exits on its own; a child that
					// returned into this frame runs the finally block below.
					$report = $base . '/closure.server.err';
					for($i = 0; $i < 100; $i++)
					{
						clearstatcache();
						if(strpos((string)@file_get_contents($report), "\n") !== false
							|| is_file($base . '/finally.pids'))
							break;
						usleep(50000);
					}
					self::expect(strpos((string)@file_get_contents($report),
						'RuntimeException: scripted closure failure') !== false,
						'the forked child names its failure in its error file');
				}
				elseif($daemon !== null)
				{
					if($mode === 'fork-empty-client')
						self::connectWithoutRequest($daemon);
					self::expect(strpos(self::request($daemon, $body), '&lt;methodCall&gt;') !== false,
						'the closure receives the request body');
					self::expect($daemon->calls() === array('probe'), 'only the request is logged as one');
				}
			}
			else
			{
				// PHP 7.4 with Xdebug can report a disabled pcntl function as present.
				$disabled = array_map('trim', explode(',', ini_get('disable_functions')));
				self::expect(array_diff(array('pcntl_fork', 'pcntl_waitpid', 'posix_kill'), $disabled) === array(),
					'the parent disables its fork, wait and signal functions');
				if($mode === 'empty-client')
				{
					$daemon = new FakeRtorrentDaemon(array(array(1)), $base . '/empty-client');
					self::connectWithoutRequest($daemon);
					self::expect(strpos((string)self::request($daemon, $body), '<i8>1</i8>') !== false,
						'the worker still answers after connections without a request');
					self::expect($daemon->calls() === array('probe'), 'only the request is logged as one');
				}
				elseif($mode === 'queue')
				{
					$rawBody = "0\x00exact\x80";
					$raw = "Status: 200 OK\r\nContent-Length: " . strlen($rawBody) . "\r\n\r\n" . $rawBody;
					$pacedChunks = array('prefix', '0', 'suffix');
					$pacedHeader = "Status: 200 OK\r\nContent-Length: " . strlen(implode('', $pacedChunks)) . "\r\n\r\n";
					$pacedChunks[0] = $pacedHeader . $pacedChunks[0];
					$daemon = new FakeRtorrentDaemon(array(array(7, "a&<b>\"\\\x80"),
						FakeRtorrentDaemon::fault('missing & gone', -500),
						FakeRtorrentDaemon::raw($raw), null,
						FakeRtorrentDaemon::paced($pacedChunks, 20000)), $base . '/queue');
					$reply = self::request($daemon, $body);
					self::expect(strpos($reply, '<i8>7</i8>') !== false
						&& strpos($reply, "a&amp;&lt;b&gt;\"\\\x80") !== false, 'scalar XML preserves literal bytes');
					$reply = self::request($daemon, $body);
					self::expect(strpos($reply, '<i4>-500</i4>') !== false
						&& strpos($reply, 'missing &amp; gone') !== false, 'fault code and message survive');
					self::expect(self::request($daemon, $body) === $raw, 'raw replies preserve every byte');
					self::expect(self::request($daemon, $body) === false, 'a dropped reply fails the SCGI exchange');
					self::expect(self::request($daemon, $body) === $pacedHeader . 'prefix0suffix', 'paced pieces include the single zero');
					self::expect($daemon->calls() === array_fill(0, 5, 'probe'), 'every request is logged once');
					self::expect($daemon->bodies() === array_fill(0, 5, $body), 'body logs retain record boundaries');
					self::expect($daemon->received('probe') && !$daemon->received('absent'), 'received uses the method log');
				}
				elseif($mode === 'responder')
				{
					$daemon = new FakeRtorrentDaemon(new FakeRtorrentPortableResponder(), $base . '/responder', false, array(__DIR__ . '/FakeRtorrentDaemonPortableTest.php'));
					$multi = '<methodCall><methodName>system.multicall</methodName>'
						. '<name>methodName</name><value><string>first</string></value>'
						. '<name>methodName</name><value><string>second</string></value></methodCall>';
					self::expect(strpos(self::request($daemon, $multi), '<i8>1</i8>') !== false, 'callable starts with its first state');
					$reply = self::request($daemon, $multi);
					self::expect(strpos($reply, '<i8>2</i8>') !== false
						&& strpos($reply, '<string>first,second</string>') !== false
						&& strpos($reply, '&lt;methodCall&gt;') !== false, 'callable retains state and receives body and parsed methods');
					self::expect($daemon->calls() === array('first', 'second', 'first', 'second'), 'multicall log contains its members');
				}
				elseif($mode === 'probe-fixture')
				{
					// Most of the tests that drive a top level script run
					// against this one daemon, so whether its reply plan can
					// be serialized decides whether the suite needs pcntl.
					$daemon = new FakeRtorrentDaemon(RtorrentProbeFixture::responder(),
						$base . '/probe-fixture', false, RtorrentProbeFixture::replyClassFiles());
					$version = '<methodCall><methodName>system.client_version</methodName></methodCall>';
					self::expect(strpos(self::request($daemon, $version),
						'<string>' . RtorrentProbeFixture::VERSION . '</string>') !== false,
						'the probe fixture answers a version request');
					self::expect($daemon->calls() === array('system.client_version'),
						'the request reached the worker');
				}
				elseif($mode === 'stop')
				{
					$daemon = new FakeRtorrentDaemon(array(array(1), array(2)), $base . '/stop');
					$port = $daemon->port();
					self::expect(strpos(self::request($daemon, $body), '<i8>1</i8>') !== false, 'the worker was serving requests');
					$start = microtime(true);
					$daemon->stop();
					self::expect(microtime(true) - $start < 5, 'stop does not wait for the ten-second accept timeout');
					$daemon->stop();
					$socket = @stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $error);
					self::expect(is_resource($socket), 'stop releases the listener before returning');
					fclose($socket);
				}
				elseif($mode === 'startup-failure')
				{
					try
					{
						$daemon = new FakeRtorrentDaemon(array(array(1)), $base . '/startup', false, array($base . '/missing-responder.php'));
						throw new RuntimeException('a missing responder file must fail startup');
					}
					catch(RuntimeException $error)
					{
						self::expect(strpos($error->getMessage(), 'did not announce a port') !== false
							&& strpos($error->getMessage(), 'missing-responder.php') !== false, 'startup names the worker failure');
					}
				}
			}
			echo "OK\n";
		}
		catch(Throwable $error)
		{
			fwrite(STDERR, $error->getMessage() . "\n");
			$code = 1;
		}
		finally
		{
			if($daemon !== null) $daemon->stop();
			// Only this process may get here: a forked daemon that returned
			// into this frame would add a second line.
			file_put_contents($base . '/finally.pids', getmypid() . "\n", FILE_APPEND);
		}
		if($code !== 0) exit($code);
	}
}
