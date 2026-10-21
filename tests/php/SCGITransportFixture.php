<?php

require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/TestTree.php');

/**
 * Disposable TCP or UNIX SCGI peer. It captures the parsed request, writes a
 * scripted reply, and can keep the connection open after the declared body.
 */
class SCGITransportFixture
{
	// sun_path holds 108 bytes, the last of them the terminating NUL.
	const MAX_SOCKET_PATH = 107;

	/** Fixtures this process started and has not closed yet, by object id. */
	private static $open = array();
	private static $hooked = false;
	private $owner;

	private $capture;
	private $dir;
	private $errorFile;
	private $host;
	private $port;
	private $process;
	private $release;

	public static function start($response, $holdOpen = false)
	{
		return self::startChunks(array(array(0, $response)), $holdOpen, false);
	}

	public static function startUnix($response, $holdOpen = false)
	{
		return self::startChunks(array(array(0, $response)), $holdOpen, true);
	}

	public static function startRepeatedBody($length)
	{
		return self::create(array(array(0, "Content-Length: ".$length."\r\n\r\n")), false,
			false, (int)$length);
	}

	/** Reads the request only after $holdSeconds, then answers with $chunks. */
	public static function startNotReading($holdSeconds = 5, $chunks = array())
	{
		return self::create($chunks, false, false, 0, $holdSeconds);
	}

	/** Each chunk is array(delay-in-seconds-before-write, bytes). */
	public static function startChunks($chunks, $holdOpen = false, $unix = false)
	{
		return self::create($chunks, $holdOpen, $unix);
	}

	private static function create($chunks, $holdOpen, $unix, $repeatBytes = 0, $readDelay = 0)
	{
		$fixture = new self();
		self::closeAtShutdown($fixture);
		try { $fixture->boot($chunks, $holdOpen, $unix, $repeatBytes, $readDelay); }
		catch(Throwable $error)
		{
			$fixture->close();
			throw $error;
		}
		return $fixture;
	}

	public function host()
	{
		return $this->host;
	}

	public function port()
	{
		return $this->port;
	}

	public function request()
	{
		for($i = 0; $i < 200; $i++)
		{
			if(is_file($this->capture))
			{
				$value = json_decode(file_get_contents($this->capture), true);
				if(is_array($value) && isset($value['header']) && isset($value['payload']))
					return array(
						'header' => base64_decode($value['header']),
						'payload' => base64_decode($value['payload']),
					);
			}
			usleep(5000);
		}
		throw new Exception('SCGI fixture did not capture a complete request: '.$this->errors());
	}

	public function errors()
	{
		return is_file($this->errorFile) ? trim(file_get_contents($this->errorFile)) : '';
	}

	/**
	 * A test that ends in a fatal error never reaches its finally block, and
	 * the peer and its directory would outlive it.
	 */
	private static function closeAtShutdown($fixture)
	{
		if(!self::$hooked)
		{
			self::$hooked = true;
			register_shutdown_function(function()
			{
				// A forked child inherits this hook and its parent's fixtures;
				// each process closes only the ones it started.
				foreach(self::$open as $fixture)
					if($fixture->owner === getmypid())
						$fixture->close();
			});
		}
		$fixture->owner = getmypid();
		self::$open[spl_object_id($fixture)] = $fixture;
	}

	public function close()
	{
		unset(self::$open[spl_object_id($this)]);
		if($this->release !== null)
			@touch($this->release);
		if(is_resource($this->process))
		{
			// A holding peer leaves on its own once it sees the release file.
			$status = proc_get_status($this->process);
			if(!empty($status['running']))
			{
				usleep(20000);
				$status = proc_get_status($this->process);
				if(!empty($status['running']))
					PhpProcess::stop($this->process);
			}
			@proc_close($this->process);
		}
		$this->process = null;
		$this->release = null;
		if($this->dir !== null)
			TestTree::remove($this->dir);
		$this->dir = null;
	}

	private function boot($chunks, $holdOpen, $unix, $repeatBytes, $readDelay)
	{
		$this->dir = sys_get_temp_dir().'/rutorrent-scgi-'.uniqid('', true);
		$socketPath = $this->dir.'/scgi.sock';
		// PHP cuts a longer path with a silenced notice, and the peer then
		// binds a name outside its directory or fails with an empty error.
		if($unix && (strlen($socketPath) > self::MAX_SOCKET_PATH))
			throw new Exception('SCGI fixture socket path is '.strlen($socketPath).' bytes; a UNIX socket path'
				.' holds at most '.self::MAX_SOCKET_PATH.' (sun_path). Shorten TMPDIR: '.$socketPath);
		if(!mkdir($this->dir, 0700, true))
			throw new Exception('could not create SCGI fixture directory');
		$endpointFile = $this->dir.'/endpoint';
		$this->capture = $this->dir.'/capture.json';
		$this->errorFile = $this->dir.'/err';
		$this->release = $this->dir.'/release';
		$configFile = $this->dir.'/config.json';
		$listen = $unix ? 'unix://'.$socketPath : 'tcp://127.0.0.1:0';
		$configChunks = array();
		foreach($chunks as $chunk)
		{
			if(!is_array($chunk) || (count($chunk) !== 2))
				throw new Exception('SCGI fixture chunk must contain delay and bytes');
			$configChunks[] = array(
				'delay' => max(0, (int)round(((float)$chunk[0]) * 1000000)),
				'data' => base64_encode($chunk[1]),
			);
		}
		file_put_contents($configFile, json_encode(array(
			'listen' => $listen,
			'hold' => $holdOpen,
			'chunks' => $configChunks,
			'repeat' => $repeatBytes,
			'readDelay' => max(0, (int)round($readDelay * 1000000)),
		)));
		$script = "<?php\nrequire_once ".var_export(__DIR__.'/SCGIRequest.php', true).";\n".<<<'PHP'
$config = json_decode(file_get_contents($argv[1]), true);
$server = @stream_socket_server($config['listen'], $errno, $error);
if($server === false)
{
	fwrite(STDERR, "server: $error\n");
	exit(2);
}
file_put_contents($argv[2], stream_socket_get_name($server, false));
$client = @stream_socket_accept($server, 5);
if($client === false)
	exit(3);
if($config['readDelay'] > 0)
	usleep($config['readDelay']);
file_put_contents($argv[3], json_encode(array_map('base64_encode', SCGIRequest::read($client))));
foreach($config['chunks'] as $chunk)
{
	if($chunk['delay'] > 0)
		usleep($chunk['delay']);
	$data = base64_decode($chunk['data']);
	$offset = 0;
	while($offset < strlen($data))
	{
		$written = fwrite($client, substr($data, $offset));
		if($written === false || $written === 0)
			exit(13);
		$offset += $written;
	}
}
if($config['repeat'] > 0)
{
	$block = str_repeat('x', 65536);
	$left = (int)$config['repeat'];
	while($left > 0)
	{
		$data = ($left >= strlen($block)) ? $block : substr($block, 0, $left);
		$offset = 0;
		while($offset < strlen($data))
		{
			$written = fwrite($client, substr($data, $offset));
			if($written === false || $written === 0)
				exit(14);
			$offset += $written;
			$left -= $written;
		}
	}
}
if($config['hold'])
	for($i = 0; $i < 500 && !is_file($argv[4]); $i++)
		usleep(10000);
fclose($client);
fclose($server);
PHP;
		$scriptFile = $this->dir.'/peer.php';
		file_put_contents($scriptFile, $script);
		$command = array(PHP_BINARY, $scriptFile, $configFile, $endpointFile,
			$this->capture, $this->release);
		if(!function_exists('proc_open'))
			throw new Exception('could not start SCGI fixture peer: proc_open is unavailable');
		$this->process = proc_open($command, array(
			0 => array('pipe', 'r'),
			1 => array('file', $this->dir.'/out', 'a'),
			2 => array('file', $this->errorFile, 'a'),
		), $pipes, $this->dir);
		if(!is_resource($this->process))
			throw new Exception('could not start SCGI fixture peer');
		fclose($pipes[0]);
		for($i = 0; $i < 200; $i++)
		{
			if(is_file($endpointFile))
			{
				$endpoint = trim(file_get_contents($endpointFile));
				if($unix)
				{
					$this->host = $listen;
					$this->port = 0;
					return;
				}
				$position = strrpos($endpoint, ':');
				$this->host = '127.0.0.1';
				$this->port = ($position === false) ? 0 : intval(substr($endpoint, $position + 1));
				if($this->port > 0)
					return;
			}
			$status = proc_get_status($this->process);
			if(isset($status['running']) && !$status['running'])
				throw new Exception('SCGI fixture peer exited: '.$this->errors());
			usleep(5000);
		}
		throw new Exception('SCGI fixture peer did not publish an endpoint: '.$this->errors());
	}
}
