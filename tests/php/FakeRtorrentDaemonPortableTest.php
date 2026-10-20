<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
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
		foreach(glob($this->base . '/*') as $file)
			unlink($file);
		rmdir($this->base);
	}

	private function child($mode, $withoutIni = true, $disablePcntl = true)
	{
		$command = array(PHP_BINARY);
		if($withoutIni)
			$command[] = '-n';
		if($disablePcntl)
		{
			$command[] = '-d';
			$command[] = 'disable_functions=pcntl_fork,pcntl_waitpid,posix_kill';
		}
		$source = 'require ' . var_export(__DIR__ . '/FakeRtorrentDaemonPortableTest.php', true) . ';'
			. 'FakeRtorrentDaemonPortableTest::scenario('
			. var_export($mode, true) . ',' . var_export($this->base, true) . ');';
		$command = array_merge($command, array('-d', 'display_errors=stderr', '-r', $source));
		$process = proc_open($command, array(0 => array('pipe', 'r'),
			1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if(!is_resource($process))
			throw new RuntimeException('cannot start the fixture test child');
		fclose($pipes[0]);
		// Pipe streams do not honor stream_set_timeout(); wait on both without
		// blocking so a peer that never starts cannot hang the regression itself.
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$out = $errors = '';
		$deadline = microtime(true) + 15;
		while((!feof($pipes[1]) || !feof($pipes[2])) && microtime(true) < $deadline)
		{
			$read = array();
			foreach(array(1, 2) as $fd)
				if(!feof($pipes[$fd])) $read[] = $pipes[$fd];
			$write = $except = null;
			if(stream_select($read, $write, $except, 0, 100000) > 0)
			{
				foreach($read as $pipe)
				{
					$chunk = stream_get_contents($pipe);
					if($pipe === $pipes[1]) $out .= $chunk;
					else $errors .= $chunk;
				}
			}
		}
		$timedOut = !feof($pipes[1]) || !feof($pipes[2]);
		if($timedOut) proc_terminate($process);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$code = proc_close($process);
		$this->assertTrue(!$timedOut && $code === 0 && $out === "OK\n" && $errors === '',
			$mode . ($withoutIni ? ' under php -n' : ' with php.ini')
			. ': exit=' . $code . ', stdout=' . json_encode($out) . ', stderr=' . json_encode($errors));
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
		$GLOBALS['rpcLogCalls'] = false;
		return rXMLRPCRequest::send($body);
	}

	/** Invoked by an isolated PHP process, including one with no loaded ini. */
	public static function scenario($mode, $base)
	{
		$daemon = null;
		$code = 0;
		try
		{
			$body = "<methodCall><methodName>probe</methodName><params>line\n2</params></methodCall>";
			if($mode === 'closure' || $mode === 'closure-unavailable')
			{
				$available = function_exists('pcntl_fork') && function_exists('pcntl_waitpid')
					&& function_exists('posix_kill');
				try
				{
					$daemon = new FakeRtorrentDaemon(function($request) { return array($request); }, $base . '/closure');
					self::expect($available, 'closures cannot fork without pcntl and posix');
					self::expect(strpos(self::request($daemon, $body), '&lt;methodCall&gt;') !== false,
						'the closure receives the request body');
				}
				catch(RuntimeException $error)
				{
					self::expect(!$available && strpos($error->getMessage(), 'closure reply requires') !== false,
						'an unavailable closure has an actionable refusal');
				}
			}
			else
			{
				self::expect(!function_exists('pcntl_fork') && !function_exists('posix_kill'),
					'the parent cannot fork or signal through posix');
				if($mode === 'queue')
				{
					$raw = "Status: 200 OK\r\n\r\n0\x00exact\x80";
					$daemon = new FakeRtorrentDaemon(array(array(7, "a&<b>\"\\\x80"),
						FakeRtorrentDaemon::fault('missing & gone', -500),
						FakeRtorrentDaemon::raw($raw), null,
						FakeRtorrentDaemon::paced(array('prefix', '0', 'suffix'), 20000)), $base . '/queue');
					$reply = self::request($daemon, $body);
					self::expect(strpos($reply, '<i8>7</i8>') !== false
						&& strpos($reply, "a&amp;&lt;b&gt;\"\\\x80") !== false, 'scalar XML preserves literal bytes');
					$reply = self::request($daemon, $body);
					self::expect(strpos($reply, '<i4>-500</i4>') !== false
						&& strpos($reply, 'missing &amp; gone') !== false, 'fault code and message survive');
					self::expect(self::request($daemon, $body) === $raw, 'raw replies preserve every byte');
					self::expect(self::request($daemon, $body) === '', 'null drops the reply');
					self::expect(self::request($daemon, $body) === 'prefix0suffix', 'paced pieces include the single zero');
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
		}
		if($code !== 0) exit($code);
	}
}
