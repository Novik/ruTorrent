<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/SCGITransportFixture.php');
require_once(__DIR__.'/TestHttpServer.php');
require_once(__DIR__.'/GlobalSettingsFixture.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/TestTree.php');

require_once(__DIR__.'/../../php/scgitransport.php');
require_once(__DIR__.'/../../php/xmlrpc.php');

/** Deterministic seams for failure/deadline branches; send() stays real. */
class SCGITransportInjected extends rSCGITransport
{
	public static $mode;
	public static $now;
	public static $peer;
	public static $readStep;
	public static $readCount;
	public static $readQueue;
	public static $step;
	public static $writeSize;
	public static $writeStep;
	public static $writePlan;
	public static $blocking;
	public static $sleeps;
	private static $calls;

	public static function prepare($mode)
	{
		self::finish();
		self::$mode = $mode;
		self::$now = 0.0;
		self::$step = 0.0;
		self::$writeSize = 65536;
		self::$writeStep = ($mode === 'write-zero') ? 0.25 : 0.0;
		self::$blocking = array();
		self::$readQueue = array("Content-Length: 1\r\n\r\nx");
		self::$readStep = ($mode === 'read-empty') ? 0.25 : 0.0;
		self::$readCount = 0;
		self::$writePlan = array();
		self::$sleeps = array();
		self::$calls = 0;
	}

	// A loop that never sleeps or advances the clock would spin forever here;
	// fail by name instead of hanging the suite.
	private static function count()
	{
		if(++self::$calls > 100000)
			throw new RuntimeException('the transport loops without sleeping');
	}

	public static function finish()
	{
		if(is_resource(self::$peer))
			fclose(self::$peer);
		self::$peer = null;
	}

	protected static function openSocket($address, &$errno, &$error, $timeout)
	{
		$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		if($pair === false)
			return false;
		self::$peer = $pair[1];
		return $pair[0];
	}

	protected static function configureBlocking($socket, $blocking)
	{
		self::$blocking[] = $blocking;
		return self::$mode !== 'socket-config';
	}


	protected static function writeBytes($socket, $bytes, $length)
	{
		self::count();
		self::$now += self::$writeStep;
		if(self::$writePlan)
			return min(array_shift(self::$writePlan), $length);
		if(self::$mode === 'write-false')
			return false;
		if(self::$mode === 'write-zero')
			return 0;
		return min(self::$writeSize, $length);
	}


	protected static function readBytes($socket, $length)
	{
		self::count();
		self::$now += self::$readStep;
		self::$readCount++;
		if(self::$mode === 'read-false')
			return false;
		if(in_array(self::$mode, array('read-empty', 'read-timeout-meta', 'read-eof-meta'), true))
			return '';
		if(count(self::$readQueue) === 0)
			return false;
		$chunk = array_shift(self::$readQueue);
		if(strlen($chunk) > $length)
		{
			array_unshift(self::$readQueue, substr($chunk, $length));
			return substr($chunk, 0, $length);
		}
		return $chunk;
	}

	protected static function socketMetadata($socket)
	{
		if(self::$readCount > 0 && (self::$mode === 'read-timeout-meta' || self::$mode === 'partial-read-timeout'))
			return array('timed_out' => true, 'eof' => false);
		if(self::$mode === 'read-eof-meta')
			return array('timed_out' => false, 'eof' => true);
		return array('timed_out' => false, 'eof' => false);
	}

	protected static function monotonicNow()
	{
		$value = self::$now;
		self::$now += self::$step;
		return $value;
	}

	protected static function sleep($seconds)
	{
		self::$sleeps[] = $seconds;
		self::$now += $seconds;
	}

	// Every wait is reported unwatchable, so it falls back to a recorded sleep.
	protected static function waitForSocket($socket, $writing, $seconds)
	{
		return false;
	}
}

class SCGITransportWakeProbe extends rSCGITransport
{
	public static $waits = array();
	public static $sleeps = array();

	public static function reset()
	{
		self::$waits = array();
		self::$sleeps = array();
	}

	public static function watch($socket, $writing, $seconds)
	{
		return parent::waitForSocket($socket, $writing, $seconds);
	}

	protected static function waitForSocket($socket, $writing, $seconds)
	{
		$ready = parent::waitForSocket($socket, $writing, $seconds);
		self::$waits[] = array('writing' => $writing, 'ready' => $ready);
		return $ready;
	}

	protected static function sleep($seconds)
	{
		self::$sleeps[] = $seconds;
		parent::sleep($seconds);
	}
}

class SCGITransportShortWriter extends rSCGITransport
{
	public static $offers = array();
	public static $slices = array();
	public static $zeros;

	public static function reset($zeros = 0)
	{
		self::$zeros = $zeros;
		self::$offers = array();
		self::$slices = array();
	}

	protected static function writeBytes($socket, $bytes, $length)
	{
		self::$offers[] = $length;
		self::$slices[] = $bytes;
		if(self::$zeros > 0)
		{
			self::$zeros--;
			return 0;
		}
		return fwrite($socket, substr($bytes, 0, min(37, $length)));
	}
}

/** Reaches openTemporary() with replaceable creation and opening steps. */
class SCGITransportTemporaryProbe extends rSCGITransport
{
	public static $create;
	public static $open;
	public static $opened = 0;

	public static function open($directory)
	{
		return static::openTemporary($directory);
	}

	public static function openAsTheTransportDoes($path)
	{
		return parent::openTemporaryPath($path);
	}

	protected static function createTemporaryPath($directory)
	{
		return self::$create ? call_user_func(self::$create, $directory) : parent::createTemporaryPath($directory);
	}

	protected static function openTemporaryPath($path)
	{
		self::$opened++;
		return self::$open ? call_user_func(self::$open, $path) : parent::openTemporaryPath($path);
	}
}

class SCGITransportNoConnect extends rSCGITransport
{
	public static $attempts = 0;
	public static $address;
	public static $timeout;

	protected static function openSocket($address, &$errno, &$error, $timeout)
	{
		self::$attempts++;
		self::$address = $address;
		self::$timeout = $timeout;
		return false;
	}
}

/** A temporary file that takes at most $capacity bytes, as a full disk would. */
class SCGITransportSpillStream
{
	public static $capacity;
	public static $readShort;
	public static $written;
	public $context;
	private $data = '';
	private $position = 0;

	public function stream_open($path, $mode, $options, &$openedPath)
	{
		return true;
	}

	public function stream_write($bytes)
	{
		$accepted = (string)substr($bytes, 0, max(0, self::$capacity - strlen($this->data)));
		$this->data .= $accepted;
		$this->position = strlen($this->data);
		self::$written += strlen($accepted);
		return strlen($accepted);
	}

	public function stream_read($count)
	{
		$end = strlen($this->data) - self::$readShort;
		$bytes = (string)substr($this->data, $this->position, max(0, min($count, $end - $this->position)));
		$this->position += strlen($bytes);
		return $bytes;
	}

	public function stream_eof()
	{
		return $this->position >= strlen($this->data) - self::$readShort;
	}

	public function stream_seek($offset, $whence)
	{
		if(($whence !== SEEK_SET) || ($offset < 0) || ($offset > strlen($this->data)))
			return false;
		$this->position = $offset;
		return true;
	}

	public function stream_tell()
	{
		return $this->position;
	}

	public function stream_stat()
	{
		return array('size' => strlen($this->data));
	}
}
stream_wrapper_register('scgitransport-spill', 'SCGITransportSpillStream');

class SCGITransportSpill extends rSCGITransport
{
	public static $directory;
	public static $opened;

	public static function reset($capacity, $readShort = 0)
	{
		self::$opened = 0;
		self::$directory = null;
		SCGITransportSpillStream::$capacity = $capacity;
		SCGITransportSpillStream::$readShort = $readShort;
		SCGITransportSpillStream::$written = 0;
	}

	protected static function openTemporary($directory = null)
	{
		self::$directory = $directory;
		self::$opened++;
		return fopen('scgitransport-spill://response', 'w+b');
	}
}

class SCGITransportTemporary extends rSCGITransport
{
	public static function inspect($directory)
	{
		$file = parent::openTemporary($directory);
		if(!is_resource($file))
			return false;
		try
		{
			$meta = stream_get_meta_data($file);
			$stat = fstat($file);
			return array($meta['uri'], $stat['nlink']);
		}
		finally { fclose($file); }
	}
}

class SCGITransportTest extends TestCase
{
	// Options most exchanges here use; a test names only what it changes.
	const BODY = array('connectTimeout' => 1, 'transferTimeout' => 1, 'maxResponseBytes' => 1024,
		'responseMode' => rSCGITransport::RESPONSE_BODY);

	// rtorrent's own response ceiling: 100 << 20 in 0.9.8 and current master.
	const DAEMON_RESPONSE_CAP = 104857600;

	private $sourceRoot;

	public function setUp()
	{
		$this->sourceRoot = realpath(__DIR__.'/../..');
		if($this->sourceRoot === false)
			throw new Exception('could not locate production source root');
	}

	public function testCompleteDeclaredFrameReturnsBeforePeerEOF()
	{
		$body = '<methodResponse><params><param><value><string>ok</string></value></param></params></methodResponse>';
		$headers = "Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($body)."\r\n\r\n";
		$peer = SCGITransportFixture::start($headers.$body, true);
		try
		{
			$failure = 'not-cleared';
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', false,
				array('maxResponseBytes' => 4096, 'responseMode' => rSCGITransport::RESPONSE_RAW) + self::BODY,
				$failure);
			$this->assertTrue($result === $headers.$body,
				'a complete Content-Length frame is returned verbatim before the peer closes');
			$this->assertTrue($failure === null, 'a complete frame clears the failure code');
		}
		finally { $peer->close(); }
	}

	public function testRequestFramingAndBothTrustBitsAreExact()
	{
		foreach(array(array(true, '0'), array(false, '1')) as $case)
		{
			list($trusted, $untrusted) = $case;
			$payload = '<request trust="'.$untrusted.'"/>';
			$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
			try
			{
				$failure = null;
				$result = rSCGITransport::send($peer->host(), $peer->port(), $payload, $trusted,
					self::BODY, $failure);
				$request = $peer->request();
				$expected = "CONTENT_LENGTH\0".strlen($payload)."\0CONTENT_TYPE\0text/xml\0"
					."SCGI\0"."1\0UNTRUSTED_CONNECTION\0".$untrusted."\0";
				$this->assertTrue($result === 'ok' && $failure === null,
					'trust '.$untrusted.' request receives the framed response');
				$this->assertEquals($expected, $request['header'],
					'trust '.$untrusted.' emits exact SCGI fields and ASCII 1');
				$this->assertEquals($payload, $request['payload'],
					'trust '.$untrusted.' preserves the payload byte for byte');
			}
			finally { $peer->close(); }
		}
	}

	public function testTcpAndUnixNonPositivePortsReturnExactRawAndBodyModes()
	{
		$raw = "Status: 200 OK\r\nContent-Length: 3\r\n\r\nraw";
		$tcp = SCGITransportFixture::start($raw);
		try
		{
			$failure = null;
			$result = rSCGITransport::send($tcp->host(), $tcp->port(), '<tcp/>', true,
				array('responseMode' => rSCGITransport::RESPONSE_RAW) + self::BODY,
				$failure);
			$this->assertTrue($result === $raw && $failure === null,
				'a real TCP peer returns the exact raw frame');
		}
		finally { $tcp->close(); }

		// fsockopen() appended a port only when it was positive, so these all
		// named the UNIX socket alone.
		foreach(array(0, '0', -1, null) as $port)
		{
			$unix = SCGITransportFixture::startUnix("Content-Length: 4\r\n\r\nunix");
			try
			{
				$failure = null;
				$result = rSCGITransport::send($unix->host(), $port, '<unix/>', true, self::BODY,
					$failure);
				$this->assertTrue($result === 'unix' && $failure === null,
					'UNIX-domain transport accepts legacy port '.var_export($port, true));
			}
			finally { $unix->close(); }
		}
	}

	public function testConfiguredTransportPrefixesReachTheOriginalEndpoint()
	{
		$frame = "Status: 200 OK\r\nContent-Length: 2\r\n\r\nok";
		foreach(array('', 'tcp://') as $prefix)
			foreach(array(rSCGITransport::RESPONSE_RAW, rSCGITransport::RESPONSE_BODY) as $mode)
			{
				$peer = SCGITransportFixture::start($frame);
				try
				{
					$failure = null;
					$result = rSCGITransport::send($prefix.$peer->host(), $peer->port(), '<configured/>',
						true, array('maxResponseBytes' => 1024, 'responseMode' => $mode) + self::BODY, $failure);
					$expected = ($mode === rSCGITransport::RESPONSE_RAW) ? $frame : 'ok';
					$this->assertTrue($result === $expected && $failure === null,
						var_export($prefix, true).' configured prefix returns the exact '.$mode.' reply');
					if($result !== null)
					{
						$request = $peer->request();
						$this->assertEquals('<configured/>', $request['payload'],
							'the configured endpoint receives the complete request');
					}
				}
				finally { $peer->close(); }
			}
	}

	public function testAddressIsComposedAsFsockopenComposedIt()
	{
		// No connection is made, so bracketed IPv6 forms are checked even where
		// the host running this suite has no IPv6 loopback interface.
		$unix = 'unix:///run/rtorrent/rtorrent.sock';
		$cases = array(
			array($unix, 0, $unix),
			array($unix, '0', $unix),
			array($unix, -1, $unix),
			array($unix, null, $unix),
			array('127.0.0.1:5000', 0, '127.0.0.1:5000'),
			array('127.0.0.1', 5000, '127.0.0.1:5000'),
			array('localhost', '5000', 'localhost:5000'),
			array('tcp://127.0.0.1', 5000, 'tcp://127.0.0.1:5000'),
			array('[::1]', 5000, '[::1]:5000'),
			array('tcp://[::1]', 5000.0, 'tcp://[::1]:5000'),
		);
		foreach($cases as $case)
		{
			$failure = null;
			SCGITransportNoConnect::send($case[0], $case[1], '<configured/>', true,
				array('connectTimeout' => 1, 'transferTimeout' => 1, 'maxResponseBytes' => 1024), $failure);
			$this->assertTrue(SCGITransportNoConnect::$address === $case[2],
				$case[0].' with port '.var_export($case[1], true).' connects to '.$case[2]);
		}
	}

	public function testInvalidInputsMakeZeroConnectAttempts()
	{
		$cases = array(
			array('', null, null, rSCGITransport::RESPONSE_RAW, 'empty-request'),
			array('<x/>', null, null, 'invalid', 'invalid-response-mode'),
		);
		SCGITransportNoConnect::$attempts = 0;
		foreach($cases as $case)
		{
			$failure = null;
			$result = SCGITransportNoConnect::send('unused', 1, $case[0], true,
				array('transferTimeout' => $case[1], 'maxResponseBytes' => $case[2], 'responseMode' => $case[3]) + self::BODY,
				$failure);
			$this->assertTrue($result === null && $failure === $case[4],
				$case[4].' rejects its invalid input');
		}
		$failure = null;
		$result = SCGITransportNoConnect::send('unused', 1, '<x/>', true, array('transferTimeOut' => 5) + self::BODY, $failure);
		$this->assertTrue($result === null && $failure === 'invalid-options', 'a misspelt option is refused, not ignored');
		$this->assertEquals(0, SCGITransportNoConnect::$attempts,
			'all invalid inputs are rejected before any socket connect attempt');
	}

	/** The pauses $sleeps took against the backoff: first, doubling, capped. */
	private function assertBackoff($sleeps, $message)
	{
		$expected = rSCGITransport::FIRST_PAUSE;
		$shaped = count($sleeps) > 0;
		// The last pause may be cut short by the deadline.
		for($i = 0; $i < count($sleeps) - 1; $i++)
		{
			$shaped = $shaped && (abs($sleeps[$i] - $expected) < 1e-9);
			$expected = min($expected * 2, rSCGITransport::LONGEST_PAUSE);
		}
		$this->assertTrue($shaped, $message.': '.json_encode($sleeps));
	}

	public function testIdleReadsBackOffAndSpendTheBudgetAsleep()
	{
		SCGITransportInjected::prepare('read-empty');
		SCGITransportInjected::$readStep = 0.0;
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<x/>', true, self::BODY, $failure);
			$sleeps = SCGITransportInjected::$sleeps;
			$this->assertTrue($result === null && $failure === 'read-timeout', 'a silent peer still ends at the deadline');
			$this->assertBackoff($sleeps, 'idle reads start at the first pause and double up to the longest');
			$this->assertTrue(count($sleeps) < 60 && array_sum($sleeps) > 0.95 && array_sum($sleeps) <= 1.0 + 1e-9,
				'a one-second wait is spent asleep in a few dozen wakeups, the last one cut to the deadline: '
				.count($sleeps).' / '.array_sum($sleeps));
			// The README documents these two.
			$this->assertTrue((rSCGITransport::FIRST_PAUSE === 0.00005) && (rSCGITransport::LONGEST_PAUSE === 0.032),
				'pauses run from 50 microseconds to 32 milliseconds');
		}
		finally { SCGITransportInjected::finish(); }
	}

	public function testReadProgressStartsTheBackoffOver()
	{
		SCGITransportInjected::prepare('read-trickle');
		SCGITransportInjected::$readQueue = array('', '', "Content-Length: 2\r\n\r\n", '', '', 'x', '', 'y');
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<x/>', true, self::BODY, $failure);
			$first = rSCGITransport::FIRST_PAUSE;
			$this->assertTrue($result === 'xy' && $failure === null, 'the trickled response completes');
			$this->assertTrue(SCGITransportInjected::$sleeps === array($first, 2 * $first, $first, 2 * $first, $first),
				'each read that brings bytes starts the pauses over: '.json_encode(SCGITransportInjected::$sleeps));
		}
		finally { SCGITransportInjected::finish(); }
	}

	public function testBlockedWritesBackOffAndWriteProgressStartsOver()
	{
		SCGITransportInjected::prepare('write-zero');
		SCGITransportInjected::$writeStep = 0.0;
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<x/>', true, self::BODY, $failure);
			$sleeps = SCGITransportInjected::$sleeps;
			$this->assertTrue($result === null && $failure === 'write-timeout', 'a peer that never reads ends at the deadline');
			$this->assertBackoff($sleeps, 'blocked writes start at the first pause and double up to the longest');
			$this->assertTrue(count($sleeps) < 60 && array_sum($sleeps) > 0.95,
				'a one-second blocked write is spent asleep, not spinning: '.count($sleeps));
		}
		finally { SCGITransportInjected::finish(); }

		SCGITransportInjected::prepare('write-plan');
		SCGITransportInjected::$writePlan = array(0, 0, 5, 0);
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<x/>', true, self::BODY, $failure);
			$first = rSCGITransport::FIRST_PAUSE;
			$this->assertTrue($result === 'x' && $failure === null, 'the request completes after partial writes');
			$this->assertTrue(SCGITransportInjected::$sleeps === array($first, 2 * $first, $first),
				'written bytes start the pauses over: '.json_encode(SCGITransportInjected::$sleeps));
		}
		finally { SCGITransportInjected::finish(); }
	}

	/** The waits of SCGITransportWakeProbe in one direction. */
	private function probeWaits($writing)
	{
		$waits = array();
		foreach(SCGITransportWakeProbe::$waits as $wait)
			if($wait['writing'] === $writing)
				$waits[] = $wait['ready'];
		return $waits;
	}

	public function testAReplyEndsTheReadPauseWhenItArrives()
	{
		$peer = SCGITransportFixture::startChunks(array(array(0.2, "Content-Length: 1\r\n\r\nx")));
		try
		{
			SCGITransportWakeProbe::reset();
			$failure = null;
			$result = SCGITransportWakeProbe::send($peer->host(), $peer->port(), '<x/>', true, self::BODY, $failure);
			$waits = $this->probeWaits(false);
			$this->assertTrue($result === 'x' && $failure === null, 'the late reply completes');
			$this->assertTrue(SCGITransportWakeProbe::$sleeps === array() && (end($waits) === 1),
				'the client watched the socket and the reply woke it: '.json_encode(SCGITransportWakeProbe::$waits));
			$this->assertTrue(count($waits) < 30,
				'an idle read waits for readability rather than returning at once: '.count($waits));
		}
		finally { $peer->close(); }
	}

	public function testABlockedWriteWaitsForRoomInTheSocket()
	{
		$peer = SCGITransportFixture::startNotReading(0.2, array(array(0, "Content-Length: 1\r\n\r\nx")));
		try
		{
			SCGITransportWakeProbe::reset();
			$failure = null;
			$result = SCGITransportWakeProbe::send($peer->host(), $peer->port(), str_repeat('x', 8 * 1048576),
				true, array('transferTimeout' => 10) + self::BODY, $failure);
			$this->assertTrue($result === 'x' && $failure === null, 'the request is written once the peer reads');
			$this->assertTrue(SCGITransportWakeProbe::$sleeps === array() && (count($this->probeWaits(true)) > 0),
				'a blocked write watched the socket for room: '.json_encode(SCGITransportWakeProbe::$waits));
		}
		finally { $peer->close(); }
	}

	public function testTheWaitWatchesTheDirectionOfTheExchange()
	{
		$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		try
		{
			$this->assertTrue(SCGITransportWakeProbe::watch($pair[0], true, 1.0) === 1,
				'a socket with room to write ends the wait at once');
			$this->assertTrue(SCGITransportWakeProbe::watch($pair[0], false, 0.01) === 0,
				'a socket with nothing to read waits out its time');
			fwrite($pair[1], 'x');
			$this->assertTrue(SCGITransportWakeProbe::watch($pair[0], false, 1.0) === 1,
				'a byte to read ends the wait');
		}
		finally
		{
			fclose($pair[0]);
			fclose($pair[1]);
		}
	}

	public function testTheConnectTimeoutBoundsAConnectionTheListenerCannotQueue()
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
			$failure = null;
			$started = microtime(true);
			$result = rSCGITransport::send('127.0.0.1', (int)substr($address, strrpos($address, ':') + 1), '<x/>', true,
				array('connectTimeout' => 0.5) + self::BODY, $failure);
			$elapsed = microtime(true) - $started;
			$this->assertTrue($result === null && $failure === 'connect-failed' && ($elapsed >= 0.45) && ($elapsed < 3),
				'the configured timeout bounds a connection that is never accepted: '.$elapsed.' s');
		}
		finally
		{
			foreach($queued as $client)
				fclose($client);
			fclose($server);
		}
	}

	public function testTheDefaultResponseKeepsTheDaemonHeaders()
	{
		$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
		try
		{
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<x/>', true, array('transferTimeout' => 3), $failure);
			$this->assertTrue($result === "Content-Length: 2\r\n\r\nok", 'without a response mode the raw frame is returned');
		}
		finally { $peer->close(); }
	}

	public function testATimeoutLogLineNamesTheBudgetInUse()
	{
		$globals = $this->preserveConfiguration();
		$previous = ini_get('default_socket_timeout');
		try
		{
			ini_set('default_socket_timeout', '60');
			$GLOBALS['rpcTimeOut'] = 90;
			$GLOBALS['rpcTransferTimeOut'] = null;
			foreach(array('read-timeout', 'write-timeout') as $failure)
				$this->assertEquals($failure.' after the 90-second transfer budget; raise $rpcTransferTimeOut for slower replies',
					rSCGITransport::describeFailure($failure), 'a longer connect timeout is the budget null gives');
			$GLOBALS['rpcTransferTimeOut'] = 7.5;
			$this->assertEquals('read-timeout after the 7.5-second transfer budget; raise $rpcTransferTimeOut for slower replies',
				rSCGITransport::describeFailure('read-timeout'), 'a configured budget is named as it is used');
			$this->assertEquals('truncated-body', rSCGITransport::describeFailure('truncated-body'),
				'other failures keep their classified name');
		}
		finally
		{
			ini_set('default_socket_timeout', $previous);
			$globals->restore();
		}
	}

	public function testConfiguredConnectTimeoutReachesTheSocket()
	{
		$globals = $this->preserveConfiguration();
		try
		{
			$GLOBALS['scgi_host'] = '127.0.0.1';
			$GLOBALS['scgi_port'] = 1;
			foreach(array(array(5, 5.0), array('2.5', 2.5), array(0, 30.0), array('5s', 30.0), array(null, 30.0)) as $case)
			{
				$GLOBALS['rpcTimeOut'] = $case[0];
				SCGITransportNoConnect::$timeout = null;
				$failure = null;
				SCGITransportNoConnect::sendConfigured('<x/>', true, array(), $failure);
				$this->assertTrue(SCGITransportNoConnect::$timeout === $case[1] && $failure === 'connect-failed',
					'$rpcTimeOut = '.var_export($case[0], true).' connects within '.$case[1].' seconds');
			}
		}
		finally { $globals->restore(); }
	}

	public function testTimeoutSettingsResolveByOneRule()
	{
		$previous = ini_get('default_socket_timeout');
		$cap = (float)rSCGITransport::MAX_TRANSFER_TIMEOUT;
		$cases = array(
			// connect, transfer, default_socket_timeout => connect, transfer
			array(0.2, null, '60', 0.2, 60.0, 'a short connect timeout does not shorten the default budget'),
			array(5, null, '1', 5.0, 5.0, 'a longer connect timeout raises a short default budget'),
			array(5, null, '90', 5.0, 90.0, 'a longer default_socket_timeout is the budget'),
			array(5, null, '-1', 5.0, 60.0, 'an unusable default_socket_timeout means 60 seconds'),
			array(120, 0, '60', 120.0, 120.0, 'zero means the same as null'),
			array(5, -1, '60', 5.0, 60.0, 'a negative budget means the same as null'),
			array(5, INF, '60', 5.0, 60.0, 'an infinite budget means the same as null'),
			array(5, '3m', '60', 5.0, 60.0, 'a non-numeric budget means the same as null'),
			array(5, ' 7 ', '60', 5.0, 7.0, 'surrounding spaces are ignored on every PHP version'),
			array(5, 1e19, '60', 5.0, $cap, 'a huge budget is capped'),
			array('5s', null, '60', 30.0, 60.0, 'an unusable connect timeout means 30 seconds'),
			array(1e19, 2, '60', $cap, 2.0, 'a huge connect timeout is capped'),
		);
		try
		{
			foreach($cases as $case)
			{
				ini_set('default_socket_timeout', $case[2]);
				$resolved = rSCGITransport::timeouts($case[0], $case[1]);
				$this->assertTrue($resolved === array($case[3], $case[4]), $case[5].': '.var_export($resolved, true));
			}
		}
		finally { ini_set('default_socket_timeout', $previous); }
	}

	public function testTemporaryFileHonoursADirectoryWithATrailingSlash()
	{
		$directory = sys_get_temp_dir().'/rutorrent-scgi-slash-'.uniqid('', true);
		mkdir($directory, 0700);
		SCGITransportTemporaryProbe::$create = SCGITransportTemporaryProbe::$open = null;
		try
		{
			$file = SCGITransportTemporaryProbe::open($directory.'/');
			$this->assertTrue(is_resource($file), 'FileUtil::getTempDirectory()\'s trailing slash still allows a spill');
			$uri = is_resource($file) ? stream_get_meta_data($file)['uri'] : '';
			$this->assertTrue(dirname($uri) === realpath($directory) && !file_exists($uri),
				'the spill is created in that directory and its name is already gone');
			if(is_resource($file)) fclose($file);
		}
		finally { TestTree::remove($directory); }
	}

	public function testTemporaryFileIsRefusedUnlessItIsTheFreshFileItCreated()
	{
		$base = sys_get_temp_dir().'/rutorrent-scgi-spill-'.uniqid('', true);
		mkdir($base.'/spill', 0700, true);
		mkdir($base.'/elsewhere', 0700);
		$victim = $base.'/victim';
		// Each case is built so that only the check it names can refuse it.
		$cases = array(
			'tempnam fell back to another directory' => array(function($directory) use ($base) {
				return tempnam($base.'/elsewhere', 'x'); }, null, 'victim'),
			'the name is a planted symbolic link' => array(function($directory) use ($victim) {
				symlink($victim, $directory.'/planted'); return $directory.'/planted'; }, null, 'victim'),
			'the name is a hard link to another empty file' => array(function($directory) use ($victim) {
				link($victim, $directory.'/hard'); return $directory.'/hard'; }, null, ''),
			'the name already holds data' => array(function($directory) {
				file_put_contents($directory.'/full', 'data'); return $directory.'/full'; }, null, 'victim'),
			'the name is replaced before it is opened' => array(null, function($path) use ($victim) {
				unlink($path); symlink($victim, $path);
				return SCGITransportTemporaryProbe::openAsTheTransportDoes($path); }, 'victim'),
			// Our own empty file passes every check but the identity one.
			'the name is replaced by a link to an empty file before it is opened' => array(null, function($path) use ($victim) {
				unlink($path); symlink($victim, $path);
				return SCGITransportTemporaryProbe::openAsTheTransportDoes($path); }, ''),
			'the name is replaced by a dangling link before it is opened' => array(null, function($path) use ($base) {
				unlink($path); symlink($base.'/absent', $path);
				return SCGITransportTemporaryProbe::openAsTheTransportDoes($path); }, 'victim'),
		);
		// A FIFO opens like an empty file, and writes to it would block.
		if(function_exists('posix_mkfifo'))
			$cases['the name is not a regular file'] = array(function($directory) {
				posix_mkfifo($directory.'/fifo', 0600); return $directory.'/fifo'; }, null, 'victim');
		try
		{
			foreach($cases as $name => $case)
			{
				file_put_contents($victim, $case[2]);
				SCGITransportTemporaryProbe::$create = $case[0];
				SCGITransportTemporaryProbe::$open = $case[1];
				SCGITransportTemporaryProbe::$opened = 0;
				$file = SCGITransportTemporaryProbe::open($base.'/spill');
				$this->assertTrue($file === false, $name.': no spill is used');
				// Opening a FIFO or device can itself have effects.
				if($name === 'the name is not a regular file')
					$this->assertTrue(SCGITransportTemporaryProbe::$opened === 0, $name.': it is not even opened');
				$this->assertTrue(file_get_contents($victim) === $case[2], $name.': no other file is touched');
				$this->assertTrue(count(scandir($base.'/spill')) === 2 && count(scandir($base.'/elsewhere')) === 2
					&& !file_exists($base.'/absent'), $name.': nothing is created or left behind');
			}
		}
		finally
		{
			SCGITransportTemporaryProbe::$create = SCGITransportTemporaryProbe::$open = null;
			TestTree::remove($base);
		}
	}

	public function testAWorldWritableDirectoryNeedsTheStickyBit()
	{
		// The rule getplugins.php warns by: without the sticky bit, any user
		// may rename or replace a name in a world-writable directory.
		$base = sys_get_temp_dir().'/rutorrent-scgi-spill-'.uniqid('', true);
		mkdir($base, 0700);
		try
		{
			foreach(array(0777 => false, 0703 => false, 01777 => true, 0770 => true, 0755 => true, 0700 => true) as $mode => $used)
			{
				chmod($base, $mode);
				clearstatcache();
				SCGITransportTemporaryProbe::$opened = 0;
				$file = SCGITransportTemporaryProbe::open($base);
				$this->assertTrue(is_resource($file) === $used && (SCGITransportTemporaryProbe::$opened === ($used ? 1 : 0)),
					sprintf('a %04o directory is %s', $mode, $used ? 'used' : 'not used'));
				if(is_resource($file))
					fclose($file);
				$this->assertTrue(count(scandir($base)) === 2, sprintf('a %04o directory is left empty', $mode));
			}
		}
		finally
		{
			chmod($base, 0700);
			TestTree::remove($base);
		}
	}

	public function testAnEmptyTemporaryDirectoryMeansTheSystemOne()
	{
		// realpath('') would be the working directory, under FPM the web root.
		$asked = array();
		SCGITransportTemporaryProbe::$create = function($directory) use (&$asked) {
			$asked[] = $directory;
			return tempnam($directory, 'rutorrent-scgi-');
		};
		try
		{
			foreach(array('', false, null) as $setting)
			{
				$file = SCGITransportTemporaryProbe::open($setting);
				$this->assertTrue(is_resource($file) && (end($asked) === realpath(sys_get_temp_dir())),
					var_export($setting, true).' collects a response in the system temporary directory');
				if(is_resource($file))
					fclose($file);
			}
		}
		finally { SCGITransportTemporaryProbe::$create = null; }
	}

	public function testATemporaryFileWhoseNameCannotBeRemovedIsNotUsed()
	{
		$base = sys_get_temp_dir().'/rutorrent-scgi-spill-'.uniqid('', true);
		mkdir($base, 0700);
		chmod($base, 0500);
		clearstatcache();
		$protected = !is_writable($base);
		chmod($base, 0700);
		if(!$protected)
		{
			TestTree::remove($base);
			echo "Skipped: directory permissions do not bind this user\n";
			return;
		}
		SCGITransportTemporaryProbe::$create = function($directory) {
			$path = tempnam($directory, 'x');
			chmod($directory, 0500);
			return $path;
		};
		try
		{
			$file = SCGITransportTemporaryProbe::open($base);
			$this->assertTrue($file === false, 'a file whose name would outlive the worker is not used');
		}
		finally
		{
			SCGITransportTemporaryProbe::$create = null;
			chmod($base, 0700);
			TestTree::remove($base);
		}
	}

	public function testResponseLimitSettingsResolveToAUsableLimit()
	{
		// rtorrent never sends a larger body than its own ceiling, so a missing,
		// larger or unusable setting means that ceiling instead of a refusal of
		// every request; only a usable smaller number lowers it.
		$cap = self::DAEMON_RESPONSE_CAP;
		$cases = array(
			array(null, $cap, 'a missing setting'),
			array(1, 1, 'one byte'),
			array(67108864, 67108864, 'a 64 MiB integer'),
			array('0004', 4, 'a decimal string with leading zeros'),
			array(1e8, 100000000, 'a float in exponent form'),
			array($cap, $cap, 'exactly the daemon ceiling'),
			array($cap + 1, $cap, 'a value above the daemon ceiling'),
			array('999999999999999999999', $cap, 'an overflowing decimal string'),
			array('64M', $cap, 'php.ini shorthand'),
			array(0, $cap, 'zero'),
			array(-1, $cap, 'a negative number'),
			array('', $cap, 'an empty string'),
			array(false, $cap, 'false'),
			array(true, $cap, 'true'),
			array(1.5, $cap, 'a fractional byte count'),
			array('1000 ', 1000, 'a number with a trailing space, on every PHP version'),
			array(' 1000', 1000, 'a number with a leading space'),
		);
		foreach($cases as $case)
		{
			$this->assertTrue($this->declaredLengthOutcome($case[0], $case[1]) === 'read-failed'
				&& $this->declaredLengthOutcome($case[0], $case[1] + 1) === 'response-too-large',
				$case[2].' limits a response body to '.$case[1].' bytes');
		}
	}

	public function testShortWritesAreRetriedInSegmentedBoundedSlices()
	{
		$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
		SCGITransportShortWriter::reset(1);
		$payload = str_repeat('p', 70000);
		$header = "CONTENT_LENGTH\x00".strlen($payload)."\x00CONTENT_TYPE\x00text/xml\x00"
			."SCGI\x001\x00UNTRUSTED_CONNECTION\x000\x00";
		$expectedPrefix = strlen($header).':'.$header.',';
		try
		{
			$failure = null;
			$result = SCGITransportShortWriter::send($peer->host(), $peer->port(), $payload, true,
				array('transferTimeout' => 2) + self::BODY,
				$failure);
			$request = $peer->request();
			$this->assertTrue($result === 'ok' && $failure === null,
				'zero and positive short writes are retried until the exact request is complete');
			$this->assertTrue(count(SCGITransportShortWriter::$offers) > 2
				&& max(SCGITransportShortWriter::$offers) <= rSCGITransport::MAX_WRITE_BYTES,
				'every writer offer is bounded and the short-write loop executed');
			$this->assertEquals(substr($expectedPrefix.$payload, 0, rSCGITransport::MAX_WRITE_BYTES),
				SCGITransportShortWriter::$slices[0],
				'the first writer offer combines the prefix and first payload bytes');
			$this->assertEquals($payload, $request['payload'],
				'the captured payload proves no bytes were lost after short writes');
		}
		finally { $peer->close(); }
	}

	public function testWriteDeadlineDoesNotResetAfterProgress()
	{
		SCGITransportInjected::prepare('deadline');
		SCGITransportInjected::$step = 0.25;
		SCGITransportInjected::$writeSize = 32;
		$failure = null;
		$result = SCGITransportInjected::send('unused', 1, '<request/>', true,
			array('transferTimeout' => 0.6) + self::BODY,
			$failure);
		SCGITransportInjected::finish();
		$this->assertTrue($result === null && $failure === 'write-timeout',
			'one absolute write deadline expires despite positive progress');
	}

	public function testTransferStaysNonblockingThroughWritesAndReads()
	{
		SCGITransportInjected::prepare('deadline');
		SCGITransportInjected::$writeSize = 32;
		SCGITransportInjected::$writeStep = 0.1;
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<request/>', true,
				array('transferTimeout' => 0.75) + self::BODY,
				$failure);
			$this->assertTrue($result === 'x' && $failure === null,
				'bounded nonblocking writes preserve a complete short-written request');
			$this->assertTrue(SCGITransportInjected::$blocking === array(false),
				'the socket stays nonblocking through the entire transfer');

		}
		finally { SCGITransportInjected::finish(); }
	}

	public function testWriteAndReadShareOneDeadline()
	{
		SCGITransportInjected::prepare('deadline');
		SCGITransportInjected::$writeSize = 32;
		SCGITransportInjected::$writeStep = 0.1;
		SCGITransportInjected::$readStep = 0.2;
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<request/>', true,
				array('transferTimeout' => 0.4) + self::BODY,
				$failure);
			$this->assertTrue($result === null && $failure === 'read-timeout',
				'time spent writing consumes the budget available to the response');
		}
		finally { SCGITransportInjected::finish(); }
	}

	public function testHugeTransferTimeoutHasAPortableDeadlineAndConnectBudget()
	{
		foreach(array(PHP_INT_MAX, 1e19, 1e300) as $timeout)
		{
			SCGITransportInjected::prepare('timeout-capture');
			SCGITransportInjected::$readStep = 2147484;
			try
			{
				$failure = null;
				$result = SCGITransportInjected::send('unused', 1, '<request/>', true,
					array('transferTimeout' => $timeout) + self::BODY,
					$failure);
				$this->assertTrue($result === null && $failure === 'read-timeout',
					'a huge finite timeout is capped before creating the transfer deadline');
				SCGITransportNoConnect::send('unused', 1, '<request/>', true,
					array('connectTimeout' => $timeout), $failure);
				$this->assertEquals(2147483, SCGITransportNoConnect::$timeout,
					'the connection system call receives a timeout below signed millisecond overflow');
			}
			finally { SCGITransportInjected::finish(); }
		}
	}

	public function testTemporaryResponseUsesConfiguredDirectoryAndUnlinksImmediately()
	{
		$directory = sys_get_temp_dir().'/rutorrent-scgi-spill-'.uniqid();
		if(!mkdir($directory, 0700))
			throw new Exception('could not create temporary response test directory');
		try
		{
			$opened = SCGITransportTemporary::inspect($directory);
			$this->assertTrue(is_array($opened) && dirname($opened[0]) === $directory,
				'the temporary response is opened in the configured directory');
			$this->assertTrue(is_array($opened) && $opened[1] === 0 && count(scandir($directory)) === 2,
				'the open temporary response has no name to survive worker termination');
		}
		finally { TestTree::remove($directory); }
	}

	public function testWriteAndSocketFailuresKeepDistinctStableCodes()
	{
		$cases = array(
			'socket-config' => 'socket-config-failed',
			'write-false' => 'write-failed',
			'write-zero' => 'write-timeout',
		);
		foreach($cases as $mode => $expected)
		{
			SCGITransportInjected::prepare($mode);
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<request/>', true, self::BODY,
				$failure);
			SCGITransportInjected::finish();
			$this->assertTrue($result === null && $failure === $expected,
				$mode.' maps to '.$expected);
		}
	}

	public function testNonReadingPeerCannotHoldAWriteBeyondItsBudget()
	{
		$peer = SCGITransportFixture::startNotReading(5);
		try
		{
			$failure = null;
			$started = microtime(true);
			$result = rSCGITransport::send($peer->host(), $peer->port(), str_repeat('x', 8 * 1048576),
				true, array('transferTimeout' => 0.3) + self::BODY, $failure);
			$this->assertTrue($result === null && $failure === 'write-timeout',
				'a peer that accepts without reading exhausts the write budget');
			$this->assertTrue(microtime(true) - $started < 2,
				'a stalled write has a generous outer bound independent of the peer');
		}
		finally { $peer->close(); }
	}

	public function testSignalsCannotExtendWriteOrReadDeadlines()
	{
		foreach(array('pcntl_fork', 'pcntl_async_signals', 'pcntl_signal', 'pcntl_waitpid', 'posix_kill') as $function)
			if(!function_exists($function))
			{
				echo "Skipped: signal deadline test requires pcntl and posix\n";
				return;
			}
		foreach(array('write', 'read') as $phase)
		{
			$peer = SCGITransportFixture::startNotReading(3);
			try
			{
				$code = 'require '.$this->phpLiteral($this->sourceRoot.'/php/scgitransport.php').';'
					.'pcntl_async_signals(true);$caught=0;'
					.'pcntl_signal(SIGUSR1,function()use(&$caught){$caught++;},false);'
					.'$receiver=getmypid();$sender=pcntl_fork();if($sender<0)throw new Exception("could not fork signal sender");'
					.'if($sender===0){for($i=0;$i<10;$i++){usleep(50000);posix_kill($receiver,SIGUSR1);}exit(0);}'
					.'$started=microtime(true);$failure=null;try{'
					.'$result=rSCGITransport::send($argv[1],(int)$argv[2],($argv[3]==="write"?str_repeat("x",8*1048576):"<x/>"),true,array("connectTimeout" => 1, "transferTimeout" => 0.2, "responseMode" => rSCGITransport::RESPONSE_BODY),$failure);'
					.'$elapsed=microtime(true)-$started;'
					.'}finally{posix_kill($sender,SIGTERM);while(pcntl_waitpid($sender,$status)<0&&pcntl_get_last_error()===PCNTL_EINTR){}}'
					.'echo json_encode(array("refused"=>$result===null,"failure"=>$failure,"elapsed"=>$elapsed,"signals"=>$caught));';
				$run = PhpProcess::run(array('-r', $code, $peer->host(), (string)$peer->port(), $phase),
					array('cwd' => $this->sourceRoot));
				$value = json_decode($run['out'], true);
				$this->assertTrue($run['code'] === 0 && is_array($value) && $value['refused']
					&& $value['failure'] === $phase.'-timeout' && $value['signals'] >= 2,
					$phase.': a real signal storm keeps the stalled transfer timeout classification');
				$this->assertTrue(is_array($value) && $value['elapsed'] < 0.5,
					$phase.': interrupted transfers finish within a generous outer bound of the original deadline');
			}
			finally { $peer->close(); }
		}
	}

	public function testReadProgressDoesNotResetTheTransferDeadline()
	{
		SCGITransportInjected::prepare('read-trickle');
		SCGITransportInjected::$readQueue = array("Content-Length: 2\r\n\r\n", 'x', 'y');
		SCGITransportInjected::$readStep = 0.06;
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<x/>', true,
				array('transferTimeout' => 0.15) + self::BODY,
				$failure);
			$this->assertTrue($result === null && $failure === 'read-timeout',
				'a final body byte arriving after the original deadline is refused');
		}
		finally { SCGITransportInjected::finish(); }
	}

	public function testFragmentedReadsCompleteWithinOneTransferBudget()
	{
		SCGITransportInjected::prepare('read-trickle');
		SCGITransportInjected::$readQueue = array("Content-Length: 2\r\n\r\n", 'x', 'y');
		SCGITransportInjected::$readStep = 0.03;
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<x/>', true,
				array('transferTimeout' => 0.15) + self::BODY,
				$failure);
			$this->assertTrue($result === 'xy' && $failure === null,
				'fragments received inside the original deadline remain complete');
		}
		finally { SCGITransportInjected::finish(); }
	}

	public function testTimedOutReadCannotPublishItsPartialBytes()
	{
		SCGITransportInjected::prepare('partial-read-timeout');
		SCGITransportInjected::$readQueue = array("Content-Length: 2\r\n\r\nxy");
		try
		{
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<x/>', true, self::BODY,
				$failure);
			$this->assertTrue($result === null && $failure === 'read-timeout',
				'a read marked timed out is refused even when its bytes complete the frame');
		}
		finally { SCGITransportInjected::finish(); }
	}

	public function testRealTricklingPeerCannotExtendTheTransferDeadline()
	{
		$peer = SCGITransportFixture::startChunks(array(
			array(0, "Content-Length: 5\r\n\r\n"), array(0.04, 'a'),
			array(0.04, 'b'), array(0.04, 'c'), array(0.04, 'd'), array(0.04, 'e')));
		try
		{
			$failure = null;
			$started = microtime(true);
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<x/>', true,
				array('transferTimeout' => 0.12) + self::BODY, $failure);
			$this->assertTrue($result === null && $failure === 'read-timeout',
				'continued progress cannot keep a real response alive beyond the total budget');
			$this->assertTrue(microtime(true)-$started < 2.0,
				'the expired request also completes within a generous outer bound');
		}
		finally { $peer->close(); }
	}

	public function testReadBudgetIsIndependentFromTightConnectBudget()
	{
		$peer = SCGITransportFixture::startChunks(array(
			array(0.06, "Content-Length: 4\r\n\r\n"),
			array(0.04, 's'), array(0.04, 'l'), array(0.04, 'o'), array(0.04, 'w'),
		), false, false);
		try
		{
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true,
				array('connectTimeout' => 0.01) + self::BODY, $failure);
			$this->assertTrue($result === 'slow' && $failure === null,
				'a reply received within its total budget is independent from the connect timeout');
		}
		finally { $peer->close(); }
	}

	public function testTransferTimeoutIsFiniteAndPreservesFractions()
	{
		$cases = array(
			array('1e309', 30, true, 'a non-finite timeout falls back to sixty seconds'),
			array('1e309', 61, false, 'the finite fallback still expires'),
			array(0.25, 0.2, true, 'a fraction inside the budget succeeds'),
			array(0.25, 0.3, false, 'a fraction beyond the budget is refused'),
		);
		foreach($cases as $case)
		{
			SCGITransportInjected::prepare('timeout-capture');
			SCGITransportInjected::$readStep = $case[1];
			try
			{
				$failure = null;
				$result = SCGITransportInjected::send('unused', 1, '<request/>', true,
					array('transferTimeout' => $case[0]) + self::BODY,
					$failure);
				$this->assertTrue($case[2] ? ($result === 'x' && $failure === null)
					: ($result === null && $failure === 'read-timeout'), $case[3]);
			}
			finally { SCGITransportInjected::finish(); }
		}
	}

	public function testReadTimeoutMetadataIsNotCollapsedWithEOFOrReadError()
	{
		$cases = array(
			'read-timeout-meta' => 'read-timeout',
			'read-eof-meta' => 'closed-before-headers',
			'read-empty' => 'read-timeout',
			'read-false' => 'read-failed',
		);
		foreach($cases as $mode => $expected)
		{
			SCGITransportInjected::prepare($mode);
			$failure = null;
			$result = SCGITransportInjected::send('unused', 1, '<request/>', true, self::BODY,
				$failure);
			SCGITransportInjected::finish();
			$this->assertTrue($result === null && $failure === $expected,
				$mode.' remains classified as '.$expected);
		}
	}

	public function testClosedBeforeHeadersIsClassified()
	{
		$this->assertRejectedFrame('', 1024, 'closed-before-headers');
	}

	public function testTruncatedHeadersAreClassified()
	{
		$this->assertRejectedFrame('Content-Length: 1', 1024, 'truncated-headers');
	}

	public function testTruncatedBodyIsClassified()
	{
		$this->assertRejectedFrame("Content-Length: 4\r\n\r\nabc", 1024, 'truncated-body');
	}

	public function testSilentOpenPeerIsReadTimeout()
	{
		$peer = SCGITransportFixture::startChunks(array(array(3, "Content-Length: 1\r\n\r\nx")));
		try
		{
			$failure = null;
			$started = microtime(true);
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true,
				array('transferTimeout' => 0.25) + self::BODY,
				$failure);
			$this->assertTrue($result === null && $failure === 'read-timeout',
				'a silent open peer is a read timeout, not EOF');
			$this->assertTrue(microtime(true) - $started < 2,
				'the socket read timeout bounds a silent peer before its delayed response');
		}
		finally { $peer->close(); }
	}

	public function testReadTimeoutShrinksAfterEarlierResponseProgress()
	{
		$peer = SCGITransportFixture::startChunks(array(
			array(0.65, "Content-Length: 1\r\n\r\n"), array(2, 'x')));
		try
		{
			$failure = null;
			$started = microtime(true);
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<x/>', true, self::BODY,
				$failure);
			$this->assertTrue($result === null && $failure === 'read-timeout',
				'a delayed body is refused after earlier header progress');
			$this->assertTrue(microtime(true) - $started < 1.45,
				'every read uses the remaining budget instead of the original socket timeout');
		}
		finally { $peer->close(); }
	}

	public function testCapturedRtorrentHeaderIsAcceptedVerbatim()
	{
		// Header format: 0.9.8 src/rpc/scgi_task.cc:234 and master src/rpc/scgi_task.h:47-49.
		$body = str_repeat('x', 125);
		$peer = SCGITransportFixture::start("Status: 200 OK\r\nContent-Type: text/xml\r\n"
			."Content-Length: 125\r\n\r\n".$body);
		try
		{
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true, self::BODY,
				$failure);
			$this->assertTrue($result === $body && $failure === null,
				'the captured rTorrent Content-Length 125 header is accepted verbatim');
		}
		finally { $peer->close(); }
	}

	public function testContentLengthIsCaseInsensitiveWithOwsAndLeadingZeros()
	{
		$peer = SCGITransportFixture::start("Status: 200 OK\r\ncontent-length:\t0004 \t\r\n\r\ntest");
		try
		{
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true, self::BODY,
				$failure);
			$this->assertTrue($result === 'test' && $failure === null,
				'case-insensitive Content-Length accepts outer OWS and leading zeros');
		}
		finally { $peer->close(); }
	}

	public function testMissingContentLengthFailsClosed()
	{
		$this->assertRejectedFrame("Status: 200 OK\r\n\r\nx", 1024, 'missing-content-length');
		$this->assertRejectedFrame("Status: 200 OK\r\nX-Content-Length: 1\r\n\r\nx", 1024,
			'missing-content-length');
	}

	public function testDuplicateContentLengthFailsClosed()
	{
		$this->assertRejectedFrame("Content-Length: 1\r\ncontent-length: 1\r\n\r\nx", 1024,
			'duplicate-content-length');
	}

	public function testMalformedContentLengthsFailClosed()
	{
		$cases = array(
			array("Content-Length: \r\n\r\n", 'malformed-content-length'),
			array("Content-Length: +1\r\n\r\nx", 'malformed-content-length'),
			array("Content-Length: 1 0\r\n\r\n0123456789", 'malformed-content-length'),
		);
		foreach($cases as $case)
			$this->assertRejectedFrame($case[0], 4, $case[1]);
	}

	public function testZeroContentLengthFailsClosed()
	{
		$this->assertRejectedFrame("Content-Length: 0\r\n\r\n", 4, 'zero-content-length');
	}

	public function testOversizeContentLengthsFailClosed()
	{
		$cases = array(
			"Content-Length: 10\r\n\r\n0123456789",
			"Content-Length: 999999999999999999999\r\n\r\n",
		);
		foreach($cases as $frame)
			$this->assertRejectedFrame($frame, 4, 'response-too-large');
	}

	public function testHeaderGrammarRejectsBareLfObsFoldAndInvalidNames()
	{
		$cases = array(
			"Content-Length: 1\nX: y\r\n\r\nx",
			"Content-Length: 1\r\n folded\r\n\r\nx",
			"Bad Name: x\r\nContent-Length: 1\r\n\r\nx",
			"X-Test\n: x\r\nContent-Length: 1\r\n\r\nx",
			"NoColon\r\nContent-Length: 1\r\n\r\nx",
			"X-Bad: \x00\r\nContent-Length: 1\r\n\r\nx",
			"X-Bad: \x7f\r\nContent-Length: 1\r\n\r\nx",
		);
		foreach($cases as $frame)
			$this->assertRejectedFrame($frame, 1024, 'malformed-header');
	}

	public function testHeaderBoundaryIncludesSplitDelimiterButRejectsOneByteMore()
	{
		$base = "Content-Length: 1\r\nX:";
		$header = $base.str_repeat('a', rSCGITransport::MAX_HEADER_BYTES - strlen($base));
		$delimiter = "\r\n\r\n";
		foreach(array(1, 2, 3) as $split)
		{
			$peer = SCGITransportFixture::startChunks(array(
				array(0, $header.substr($delimiter, 0, $split)),
				array(0.01, substr($delimiter, $split).'x'),
			), false, false);
			try
			{
				$failure = null;
				$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true, self::BODY,
					$failure);
				$this->assertTrue($result === 'x' && $failure === null,
					'the exact header ceiling accepts delimiter split '.$split.' at its boundary');
			}
			finally { $peer->close(); }
		}

		$this->assertRejectedFrame($header.'a'."\r\n\r\nx", 1024, 'headers-too-large');
	}

	public function testCoalescedSuffixIsDiscardedBeforeAccumulation()
	{
		$frame = "Content-Length: 1\r\n\r\nx".str_repeat('z', 262144);
		$peer = SCGITransportFixture::start($frame, true);
		try
		{
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true, self::BODY,
				$failure);
			$this->assertTrue($result === 'x' && $failure === null,
				'only the declared body enters the response accumulator');
		}
		finally { $peer->close(); }
	}

	public function testTransportDoesNotPerformXmlValidation()
	{
		$body = 'not XML at all';
		$peer = SCGITransportFixture::start("Content-Length: ".strlen($body)."\r\n\r\n".$body);
		try
		{
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true, self::BODY,
				$failure);
			$this->assertTrue($result === $body && $failure === null,
				'transport returns a framed non-XML body for its consumer to validate');
		}
		finally { $peer->close(); }
	}

	public function testDefaultLimitPassesTheDaemonCeilingWithinA128MiBWorker()
	{
		// The largest response rtorrent can send passes the default limit and is
		// held in memory once, so a default 128M worker can still receive it.
		$length = self::DAEMON_RESPONSE_CAP;
		$peer = SCGITransportFixture::startRepeatedBody($length);
		try
		{
			$code = 'require '.$this->phpLiteral($this->sourceRoot.'/php/scgitransport.php').';'
				.'$f=null;$r=rSCGITransport::send($argv[1],(int)$argv[2],"<x/>",true,array("connectTimeout" => 2, "transferTimeout" => 30, "responseMode" => rSCGITransport::RESPONSE_RAW),$f);'
				.'echo json_encode(array("length"=>is_string($r)?strlen($r):null,"failure"=>$f));';
			$run = PhpProcess::run(array('-d', 'memory_limit=128M', '-r', $code,
				$peer->host(), (string)$peer->port()), array('cwd' => $this->sourceRoot));
			$value = json_decode($run['out'], true);
			$expected = strlen("Content-Length: ".$length."\r\n\r\n") + $length;
			$this->assertTrue($run['code'] === 0 && is_array($value)
				&& $value['length'] === $expected && $value['failure'] === null,
				'a 100 MiB raw response passes the default limit at memory_limit=128M');
			$this->assertTrue(strpos($run['err'], 'Allowed memory size') === false,
				'the 100 MiB raw response does not trigger a PHP memory fatal');
		}
		finally { $peer->close(); }
	}

	public function testLargeResponseCompletesWithoutAUsableTemporaryDirectory()
	{
		// A response above 1 MiB is collected in the temporary directory when it
		// can be written there. When it cannot, the request must still complete
		// from memory. $tempDirectory names a regular file, so FileUtil cannot
		// fall back to another directory and the spill really is unavailable.
		$body = $this->patternedBody(2 * 1048576 + 37);
		$frame = "Status: 200 OK\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body;
		$peer = SCGITransportFixture::start($frame);
		$notDirectory = sys_get_temp_dir().'/rutorrent-scgi-no-temp-'.uniqid('', true);
		file_put_contents($notDirectory, '');
		$log = sys_get_temp_dir().'/rutorrent-scgi-no-temp-'.uniqid('', true).'.log';
		try
		{
			$code = 'require '.$this->phpLiteral($this->sourceRoot.'/php/xmlrpc.php').';'
				.'$GLOBALS["scgi_host"]=$argv[1];$GLOBALS["scgi_port"]=(int)$argv[2];'
				.'$GLOBALS["rpcTimeOut"]=2;$GLOBALS["rpcTransferTimeOut"]=30;$GLOBALS["rpcLogCalls"]=false;'
				.'$GLOBALS["log_file"]=$argv[3];$GLOBALS["tempDirectory"]=$argv[4];$GLOBALS["tempDirectory_init_done"]=true;'
				.'$r=rXMLRPCRequest::send("<x/>",true);'
				.'echo is_string($r)?hash("sha256",$r):"false";';
			$run = PhpProcess::run(array('-r', $code, $peer->host(), (string)$peer->port(), $log, $notDirectory),
				array('cwd' => $this->sourceRoot));
			$passed = ($run['code'] === 0) && (trim($run['out']) === hash('sha256', $frame));
			$message = 'a 2 MiB response completes in memory when no temporary file can be created';
			if(!$passed)
				$message .= ': '.json_encode(array(is_file($log) ? file_get_contents($log) : '', $run['err']));
			$this->assertTrue($passed, $message);
		}
		finally { @unlink($log); @unlink($notDirectory); $peer->close(); }
	}

	public function testFullTemporaryFileHandsEveryByteBackToMemory()
	{
		$body = $this->patternedBody(2 * 1048576 + 37);
		$frame = "Status: 200 OK\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body;
		// Full from the start, inside the first piece, and in the middle of the
		// body with a short write at the boundary.
		foreach(array(0, 1000, 1048576 + 5) as $capacity)
		{
			SCGITransportSpill::reset($capacity);
			$peer = SCGITransportFixture::start($frame);
			try
			{
				$failure = null;
				$connectError = null;
				$directory = sys_get_temp_dir().'/configured-response';
				$result = SCGITransportSpill::send($peer->host(), $peer->port(), '<x/>', true,
					array('transferTimeout' => 30, 'maxResponseBytes' => null, 'responseMode' => rSCGITransport::RESPONSE_RAW,
						'temporaryDirectory' => $directory) + self::BODY,
					$failure, $connectError);
				$this->assertTrue(SCGITransportSpill::$opened === 1
					&& SCGITransportSpillStream::$written === $capacity,
					'the temporary file took '.$capacity.' bytes before it was full');
				$this->assertTrue(SCGITransportSpill::$directory === $directory,
					'the direct transport forwards its configured directory to the temporary opener');
				$this->assertTrue($result === $frame && $failure === null,
					'a temporary file full after '.$capacity.' bytes leaves the exact frame in memory');
			}
			finally { $peer->close(); }
		}
	}

	public function testConfiguredTransportForwardsItsTemporaryDirectory()
	{
		$globals = $this->preserveConfiguration();
		$body = $this->patternedBody(rSCGITransport::MEMORY_RESPONSE_BYTES + 37);
		$frame = "Content-Length: ".strlen($body)."\r\n\r\n".$body;
		try
		{
			$GLOBALS['tempDirectory'] = sys_get_temp_dir().'/configured-spill';
			foreach(array(null, sys_get_temp_dir().'/explicit-spill') as $override)
			{
				$peer = SCGITransportFixture::start($frame);
				try
				{
					$GLOBALS['scgi_host'] = $peer->host();
					$GLOBALS['scgi_port'] = $peer->port();
					$GLOBALS['rpcTimeOut'] = 1;
					$GLOBALS['rpcTransferTimeOut'] = 5;
					$GLOBALS['rpcMaxResponseBytes'] = null;
					SCGITransportSpill::reset(PHP_INT_MAX);
					$failure = null;
					$connectError = null;
					$options = array('responseMode' => rSCGITransport::RESPONSE_RAW);
					if($override !== null)
						$options['temporaryDirectory'] = $override;
					$result = SCGITransportSpill::sendConfigured('<x/>', true, $options, $failure);
					$this->assertTrue($result === $frame && $failure === null
						&& SCGITransportSpill::$directory === ($override ?? $GLOBALS['tempDirectory']),
						'the configured adapter forwards the selected temporary directory to its opener');
				}
				finally { $peer->close(); }
			}
		}
		finally { $globals->restore(); }
	}

	public function testTemporaryFileMustReturnTheWholeResponse()
	{
		$body = $this->patternedBody(2 * 1048576 + 37);
		$frame = "Status: 200 OK\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body;
		foreach(array(0 => array(true, null), 1 => array(false, 'read-failed')) as $readShort => $expected)
		{
			SCGITransportSpill::reset(PHP_INT_MAX, $readShort);
			$peer = SCGITransportFixture::start($frame);
			try
			{
				$failure = null;
				$result = SCGITransportSpill::send($peer->host(), $peer->port(), '<x/>', true,
					array('connectTimeout' => 1, 'transferTimeout' => 30, 'responseMode' => rSCGITransport::RESPONSE_RAW),
					$failure);
				$this->assertTrue(SCGITransportSpill::$opened === 1
					&& SCGITransportSpillStream::$written === strlen($frame),
					'a response above 1 MiB is collected in the temporary file');
				$this->assertTrue(($result === $frame) === $expected[0] && $failure === $expected[1],
					$readShort ? 'a temporary file that returns one byte less fails the request'
						: 'the temporary file returns the exact frame');
			}
			finally { $peer->close(); }
		}

		SCGITransportSpill::reset(PHP_INT_MAX);
		$small = "Content-Length: 1048576\r\n\r\n".str_repeat('s', 1048576);
		$peer = SCGITransportFixture::start($small);
		try
		{
			$failure = null;
			$result = SCGITransportSpill::send($peer->host(), $peer->port(), '<x/>', true,
				array('connectTimeout' => 1, 'transferTimeout' => 30, 'responseMode' => rSCGITransport::RESPONSE_RAW),
				$failure);
			$this->assertTrue($result === $small && $failure === null && SCGITransportSpill::$opened === 0,
				'a response of 1 MiB stays in memory without a temporary file');
		}
		finally { $peer->close(); }
	}

	public function testCoreConsumerUsesLegacyDefaultsAndStaysSilentWhileTheDaemonIsDown()
	{
		$globals = $this->preserveConfiguration();
		$log = sys_get_temp_dir().'/rutorrent-scgi-core-'.uniqid('', true).'.log';
		try
		{
			$GLOBALS['scgi_host'] = 'unix://'.sys_get_temp_dir().'/missing-scgi-'.uniqid().'.sock';
			$GLOBALS['scgi_port'] = 0;
			$GLOBALS['rpcTimeOut'] = 0.1;
			unset($GLOBALS['rpcTransferTimeOut'], $GLOBALS['rpcMaxResponseBytes']);
			$GLOBALS['rpcLogCalls'] = false;
			$GLOBALS['log_file'] = $log;
			$GLOBALS['profileMask'] = 0600;
			$warnings = array();
			set_error_handler(function($severity, $message) use (&$warnings) {
				if(error_reporting() & $severity)
					$warnings[] = $message;
				return true;
			});
			try { $result = rXMLRPCRequest::send('<request/>', true); }
			finally { restore_error_handler(); }
			$lines = is_file($log) ? file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
			$this->assertTrue($result === false, 'core maps transport null strictly to legacy false');
			$this->assertTrue(count($warnings) === 0,
				'missing new globals use legacy-safe defaults without warnings: '.json_encode($warnings));
			$this->assertTrue(count($lines) === 0,
				'an unreachable daemon adds no log line on each poll, as before: '.json_encode($lines));
		}
		finally { @unlink($log); $globals->restore(); }
	}

	public function testCoreConsumerForwardsItsConfiguredLimit()
	{
		$globals = $this->preserveConfiguration();
		global $scgi_host, $scgi_port, $rpcTimeOut, $rpcTransferTimeOut;
		global $rpcMaxResponseBytes, $rpcLogCalls, $log_file;
		$peer = SCGITransportFixture::start("Content-Length: 2\r\n\r\nok");
		$log_file = sys_get_temp_dir().'/rutorrent-scgi-core-limit-'.uniqid('', true).'.log';
		try
		{
			$scgi_host = $peer->host();
			$scgi_port = $peer->port();
			$rpcTimeOut = 1;
			$rpcTransferTimeOut = 1;
			$rpcMaxResponseBytes = 1;
			$rpcLogCalls = false;
			$result = rXMLRPCRequest::send('<request/>', true);
			$lines = is_file($log_file) ? file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
			$this->assertTrue($result === false, 'core maps configured response-limit refusal to false');
			$this->assertTrue(count($lines) === 1 && preg_match('/rXMLRPCRequest: response-too-large$/', $lines[0]),
				'core forwards its configured limit and logs that classified result once');
		}
		finally { @unlink($log_file); $peer->close(); $globals->restore(); }
	}


	public function testCoreConsumerReturnsExactRawFrameWithLegacyDefaults()
	{
		$globals = $this->preserveConfiguration();
		global $scgi_host, $scgi_port, $rpcTimeOut, $rpcLogCalls;
		$frame = "Status: 200 OK\r\nContent-Length: 2\r\n\r\nok";
		$peer = SCGITransportFixture::start($frame, true);
		try
		{
			$scgi_host = $peer->host();
			$scgi_port = $peer->port();
			$rpcTimeOut = 1;
			unset($GLOBALS['rpcTransferTimeOut'], $GLOBALS['rpcMaxResponseBytes']);
			$rpcLogCalls = false;
			$result = rXMLRPCRequest::send('<request/>', true);
			$this->assertEquals($frame, $result,
				'core consumer returns exact raw headers, delimiter and body');
		}
		finally { $peer->close(); $globals->restore(); }
	}

	public function testCopiedRealRpc2UsesBodyModeOverUnixWithLegacyGlobals()
	{
		$body = '<?xml version="1.0"?><methodResponse><params></params></methodResponse>';
		$peer = SCGITransportFixture::startChunks(array(array(0, "Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body)), false, true);
		try
		{
			$result = $this->runCopiedRpc2($peer->host(), '0', $this->allowedXml(), array('legacy' => true));
			$this->assertTrue(strpos($result['status'], '200 OK') !== false,
				'copied real rpc2 endpoint completes a UNIX SCGI request');
			$this->assertEquals($body, $result['body'], 'rpc2 returns body mode without daemon headers');
			$this->assertEquals((string)strlen($body), $result['headers']['content-length'],
				'rpc2 publishes the exact framed body length');
			$serverClean = strpos($result['serverError'], 'Warning') === false
				&& strpos($result['serverError'], 'Notice') === false;
			$message = 'legacy config without new globals produces no warning';
			if(!$serverClean)
				$message .= ': '.json_encode($result['serverError']);
			$this->assertTrue($serverClean, $message);
			$request = $peer->request();
			$this->assertTrue(strpos($request['header'], "UNTRUSTED_CONNECTION\0"."1\0") !== false,
				'rpc2 forwards an ordinary admitted request as untrusted');
		}
		finally { $peer->close(); }
	}

	public function testCopiedRealRpc2ConnectFailureKeepsItsHintAndConnectorError()
	{
		$host = 'unix://'.sys_get_temp_dir().'/missing-rpc2-'.uniqid().'.sock';
		$result = $this->runCopiedRpc2($host, '0', $this->allowedXml(), array('legacy' => true));
		$this->assertTrue(strpos($result['status'], '502 Bad Gateway') !== false,
			'copied real rpc2 maps transport null to HTTP 502');
		$this->assertTrue(substr_count($result['body'], 'Could not reach rTorrent over XMLRPC. Is rTorrent running?') === 1,
			'rpc2 keeps its connection-failure hint');
		$logged = preg_match_all('/ rpc2: cannot reach rtorrent at '.preg_quote($host, '/').': \S[^\n]*$/m',
			$result['rpcLog'], $matches) === 1;
		$this->assertTrue($logged, 'rpc2 logs the connector error once, as before'
			.($logged ? '' : ': '.json_encode($result['rpcLog'])));
		$this->assertTrue(strpos($result['rpcLog'], $this->allowedXml()) === false,
			'rpc2 transport diagnostics do not include request payloads');
	}

	public function testCopiedRealRpc2ForwardsTransferBudgetAndResponseLimit()
	{
		$body = '<?xml version="1.0"?><methodResponse><params></params></methodResponse>';
		$peer = SCGITransportFixture::startChunks(array(
			array(1.2, "Content-Length: ".strlen($body)."\r\n\r\n"),
			array(0, $body),
		), false, false);
		try
		{
			$result = $this->runCopiedRpc2($peer->host(), (string)$peer->port(), $this->allowedXml(), array(
				'connect' => 0.05, 'transfer' => 3, 'max' => 1024, 'socketDefault' => 1,
			));
			$this->assertTrue(strpos($result['status'], '200 OK') !== false && $result['body'] === $body,
				'rpc2 forwards transfer timeout independently from tight connect timeout');
		}
		finally { $peer->close(); }

		$oversize = SCGITransportFixture::startChunks(array(array(0, "Content-Length: 200\r\n\r\n".str_repeat("x", 200))));
		try
		{
			// The cap refuses the declared length as soon as the header arrives, so
			// the budgets only bound a peer that is slow to answer. One second was
			// not enough under parallel load: a stalled fixture made rpc2 log
			// read-timeout instead of the refusal under test.
			$result = $this->runCopiedRpc2($oversize->host(), (string)$oversize->port(), $this->allowedXml(), array(
				'connect' => 10, 'transfer' => 10, 'max' => 160,
			));
			$this->assertTrue(strpos($result['status'], '502 Bad Gateway') !== false,
				'rpc2 forwards the configured response cap to the transport');
			$this->assertTrue(preg_match_all('/ rpc2: response-too-large$/m', $result['rpcLog'], $matches) === 1,
				'rpc2 logs the configured response-cap refusal exactly once');
		}
		finally { $oversize->close(); }
	}

	public function testCopiedRpc2LegacyTransferBudgetIncludesALargerConnectTimeout()
	{
		$body = '<methodResponse><params></params></methodResponse>';
		$peer = SCGITransportFixture::startChunks(array(
			array(1.2, "Content-Length: ".strlen($body)."\r\n\r\n".$body)));
		try
		{
			$result = $this->runCopiedRpc2($peer->host(), (string)$peer->port(), $this->allowedXml(),
				array('legacy' => true, 'connect' => 3, 'socketDefault' => 1));
			$this->assertTrue(strpos($result['status'], '200 OK') !== false && $result['body'] === $body,
				'the legacy rpc2 transfer budget preserves a connect timeout above the socket default');
		}
		finally { $peer->close(); }
	}

	public function testCopiedRpc2ConfiguredTransferTimeoutHasABoundedRefusal()
	{
		$peer = SCGITransportFixture::startChunks(array(array(3, "Content-Length: 1\r\n\r\nx")));
		try
		{
			$result = $this->runCopiedRpc2($peer->host(), (string)$peer->port(), $this->allowedXml(),
				array('transfer' => 0.25, 'connect' => 1, 'socketDefault' => 2));
			$this->assertTrue(strpos($result['status'], '502 Bad Gateway') !== false
				&& preg_match_all('/ rpc2: read-timeout after the [0-9.]+-second transfer budget; raise \$rpcTransferTimeOut for slower replies$/m',
					$result['rpcLog'], $matches) === 1,
				'rpc2 forwards its configured transfer budget as a classified timeout');
			$this->assertTrue($result['elapsed'] < 1.5,
				'the configured rpc2 budget refuses before the larger socket default');
		}
		finally { $peer->close(); }
	}

	public function testCopiedRealRpc2RejectsATruncatedDeclaredBody()
	{
		$body = '<?xml version="1.0"?><methodResponse><params></params></methodResponse>';
		$peer = SCGITransportFixture::start("Content-Length: ".(strlen($body)+1)."\r\n\r\n".$body);
		try
		{
			$result = $this->runCopiedRpc2($peer->host(), (string)$peer->port(),
				$this->allowedXml(), array('legacy' => true));
			$this->assertTrue(strpos($result['status'], '502 Bad Gateway') !== false,
				'rpc2 refuses a complete-looking XML body shorter than its declared length');
			$this->assertTrue(strpos($result['body'], '<fault>') !== false
				&& strpos($result['body'], 'Could not complete the rTorrent XMLRPC request.') !== false,
				'rpc2 returns a fault rather than the incomplete daemon frame');
			$this->assertTrue(preg_match_all('/ rpc2: truncated-body$/m', $result['rpcLog'], $matches) === 1,
				'rpc2 logs the classified framing failure once');
		}
		finally { $peer->close(); }
	}

	public function testCoreRejectsAFrameWhoseDeclaredBodyEndsEarly()
	{
		$globals = $this->preserveConfiguration();
		global $scgi_host, $scgi_port, $rpcTimeOut, $rpcLogCalls, $log_file;
		$body = '<methodResponse><params></params></methodResponse>';
		$peer = SCGITransportFixture::start("Content-Length: ".(strlen($body)+1)."\r\n\r\n".$body);
		$log_file = sys_get_temp_dir().'/rutorrent-scgi-core-truncated-'.uniqid('', true).'.log';
		try
		{
			$scgi_host = $peer->host(); $scgi_port = $peer->port(); $rpcTimeOut = 1; $rpcLogCalls = false;
			$this->assertTrue(rXMLRPCRequest::send('<request/>') === false,
				'core refuses an incomplete declared frame even when its XML looks complete');
			$lines = is_file($log_file) ? file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
			$this->assertTrue(count($lines) === 1 && preg_match('/rXMLRPCRequest: truncated-body$/', $lines[0]),
				'core logs the classified framing failure once');
		}
		finally { @unlink($log_file); $peer->close(); $globals->restore(); }
	}

	/** Sends only a header declaring $length; the body read then fails unless the limit refused it. */
	private function declaredLengthOutcome($limit, $length)
	{
		SCGITransportInjected::prepare('declared-length');
		SCGITransportInjected::$readQueue = array("Content-Length: ".$length."\r\n\r\n");
		try
		{
			$failure = null;
			SCGITransportInjected::send('unused', 1, '<x/>', true,
				array('maxResponseBytes' => $limit) + self::BODY, $failure);
			return $failure;
		}
		finally { SCGITransportInjected::finish(); }
	}

	private function patternedBody($length)
	{
		$body = '';
		for($i = 0; strlen($body) < $length; $i++)
			$body .= sprintf('%07d,', $i);
		return substr($body, 0, $length);
	}

	private function assertRejectedFrame($frame, $limit, $expected)
	{
		$peer = SCGITransportFixture::start($frame);
		try
		{
			$failure = null;
			$result = rSCGITransport::send($peer->host(), $peer->port(), '<request/>', true,
				array('maxResponseBytes' => $limit, 'responseMode' => rSCGITransport::RESPONSE_RAW) + self::BODY,
				$failure);
			$this->assertTrue($result === null && $failure === $expected,
				$expected.' rejects its malformed frame');
		}
		finally { $peer->close(); }
	}

	private function allowedXml()
	{
		return '<?xml version="1.0"?><methodCall><methodName>system.client_version</methodName>'
			.'<params></params></methodCall>';
	}

	private function runCopiedRpc2($host, $port, $body, $options)
	{
		$tree = sys_get_temp_dir().'/rutorrent-scgi-rpc2-'.uniqid('', true);
		$server = null;
		try
		{
			if(!mkdir($tree, 0700, true))
				throw new Exception('could not create copied rpc2 tree');
			foreach(array('rpc2.php', 'php/xmlrpc_proxy.php', 'php/scgitransport.php') as $relative)
			{
				$source = $this->sourceRoot.'/'.$relative;
				$target = $tree.'/'.$relative;
				if(!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true))
					throw new Exception('could not create copied rpc2 source directory');
				if(!copy($source, $target) || hash_file('sha256', $source) !== hash_file('sha256', $target))
					throw new Exception('could not byte-copy production '.$relative);
			}
			mkdir($tree.'/conf', 0700, true);
			$config = <<<'PHP'
<?php
$scgi_host = getenv('SCGI_TEST_HOST');
$scgi_port = getenv('SCGI_TEST_PORT');
$topDirectory = '/';
$XMLRPCProxyAllowRootDirectory = true;
$XMLRPCProxy = 'sanitize';
$XMLRPCProxyLog = true;
$log_file = getenv('SCGI_TEST_RPC_LOG');
PHP;
			$config .= "\n\$rpcTimeOut = ".var_export(isset($options['connect']) ? $options['connect'] : 0.05, true).";\n";
			if(empty($options['legacy']))
			{
				$config .= "\$rpcTransferTimeOut = ".var_export(isset($options['transfer']) ? $options['transfer'] : 0.2, true).";\n";
				$config .= "\$rpcMaxResponseBytes = ".var_export(isset($options['max']) ? $options['max'] : 104857600, true).";\n";
			}
			file_put_contents($tree.'/conf/config.php', $config);
			$prepend = "<?php\n\$_SERVER['RUTORRENT_XMLRPC_ENDPOINT'] = 'on';\n";
			file_put_contents($tree.'/prepend.php', $prepend);
			$rpcLog = $tree.'/rpc2.log';
			$serverError = $tree.'/server.err';

			// TestHttpServer adds these to the inherited environment.
			$environment = array(
				'SCGI_TEST_HOST' => $host,
				'SCGI_TEST_PORT' => (string)$port,
				'SCGI_TEST_RPC_LOG' => $rpcLog,
			);
			$arguments = array('-d', 'auto_prepend_file='.$tree.'/prepend.php');
			if(isset($options['socketDefault']))
				$arguments = array_merge($arguments, array('-d', 'default_socket_timeout='.$options['socketDefault']));
			$server = TestHttpServer::start($tree, $environment, $arguments);
			// php -S can report inherited runtime configuration before it serves
			// copied code. Keep that transcript, but judge rpc2 from this boundary.
			clearstatcache(true, $serverError);
			$requestErrorOffset = is_file($serverError) ? filesize($serverError) : 0;
			if($requestErrorOffset === false)
				throw new Exception('could not establish copied rpc2 diagnostic boundary');
			$started = microtime(true);
			$result = TestHttpServer::rawPost($server->port(), '/rpc2.php', $body);
			$result['elapsed'] = microtime(true) - $started;
			$result['rpcLog'] = is_file($rpcLog) ? file_get_contents($rpcLog) : '';
			$allServerError = is_file($serverError) ? file_get_contents($serverError) : '';
			$result['serverStartupError'] = substr($allServerError, 0, $requestErrorOffset);
			$result['serverError'] = substr($allServerError, $requestErrorOffset);
			return $result;
		}
		finally
		{
			if($server !== null)
				$server->close();
			TestTree::remove($tree);
		}
	}


	private function preserveConfiguration()
	{
		return GlobalSettingsFixture::scgi();
	}

	private function phpLiteral($value)
	{
		return var_export($value, true);
	}

}
