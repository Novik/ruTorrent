<?php

require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/TestTree.php');

/**
 * Disposable TCP or UNIX SCGI peer. It captures the parsed request, writes a
 * scripted reply, and can keep the connection open after the declared body.
 */
class SCGITransportFixture
{
    // sun_path holds 108 bytes, the last of them the terminating NUL.
    public const MAX_SOCKET_PATH = 107;

    /**
     * How long the test waits for a peer that is still running to publish its
     * endpoint or capture a request. The peer is a new PHP process: on a busy
     * CI runner, or with Xdebug loaded, its start alone can take most of a
     * second. A peer that exits ends the wait at once, so the bound only
     * matters for one that hangs. The peer waits as long for its client,
     * which is often a new PHP process too.
     */
    public const PEER_WAIT_SECONDS = 30;

    /** Fixtures this process started and has not closed yet, by object id. */
    private static $open = [];
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
        return self::startChunks([[0, $response]], $holdOpen, false);
    }

    public static function startUnix($response, $holdOpen = false)
    {
        return self::startChunks([[0, $response]], $holdOpen, true);
    }

    public static function startRepeatedBody($length)
    {
        return self::create(
            [[0, "Content-Length: " . $length . "\r\n\r\n"]],
            false,
            false,
            (int) $length,
        );
    }

    /** Reads the request only after $holdSeconds, then answers with $chunks. */
    public static function startNotReading($holdSeconds = 5, $chunks = [])
    {
        return self::create($chunks, false, false, 0, $holdSeconds);
    }

    /** Each chunk is array(delay-in-seconds-before-write, bytes). */
    public static function startChunks($chunks, $holdOpen = false, $unix = false)
    {
        return self::create($chunks, $holdOpen, $unix);
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
        $capture = $this->capture;
        return $this->waitForPeer('capture a complete request', function () use ($capture) {
            $value = is_file($capture) ? json_decode(file_get_contents($capture), true) : null;
            if (is_array($value) && isset($value['header']) && isset($value['payload'])) {
                return [
                    'header' => base64_decode($value['header']),
                    'payload' => base64_decode($value['payload']),
                ];
            }
            return null;
        });
    }

    public function errors()
    {
        return is_file($this->errorFile) ? trim(file_get_contents($this->errorFile)) : '';
    }

    public function close()
    {
        unset(self::$open[spl_object_id($this)]);
        if ($this->release !== null) {
            @touch($this->release);
        }
        if (is_resource($this->process)) {
            // A holding peer leaves on its own once it sees the release file.
            $status = proc_get_status($this->process);
            if (!empty($status['running'])) {
                usleep(20000);
                $status = proc_get_status($this->process);
                if (!empty($status['running'])) {
                    PhpProcess::stop($this->process);
                }
            }
            @proc_close($this->process);
        }
        $this->process = null;
        $this->release = null;
        if ($this->dir !== null) {
            TestTree::remove($this->dir);
        }
        $this->dir = null;
    }

    private static function create($chunks, $holdOpen, $unix, $repeatBytes = 0, $readDelay = 0)
    {
        $fixture = new self();
        self::closeAtShutdown($fixture);
        try {
            $fixture->boot($chunks, $holdOpen, $unix, $repeatBytes, $readDelay);
        } catch (Throwable $error) {
            $fixture->close();
            throw $error;
        }
        return $fixture;
    }

    /**
     * A test that ends in a fatal error never reaches its finally block, and
     * the peer and its directory would outlive it.
     */
    private static function closeAtShutdown($fixture)
    {
        if (!self::$hooked) {
            self::$hooked = true;
            register_shutdown_function(function () {
                // A forked child inherits this hook and its parent's fixtures;
                // each process closes only the ones it started.
                foreach (self::$open as $fixture) {
                    if ($fixture->owner === getmypid()) {
                        $fixture->close();
                    }
                }
            });
        }
        $fixture->owner = getmypid();
        self::$open[spl_object_id($fixture)] = $fixture;
    }

    private function boot($chunks, $holdOpen, $unix, $repeatBytes, $readDelay)
    {
        $this->dir = sys_get_temp_dir() . '/rutorrent-scgi-' . uniqid('', true);
        $socketPath = $this->dir . '/scgi.sock';
        // PHP cuts a longer path with a silenced notice, and the peer then
        // binds a name outside its directory or fails with an empty error.
        if ($unix && (strlen($socketPath) > self::MAX_SOCKET_PATH)) {
            throw new Exception('SCGI fixture socket path is ' . strlen($socketPath) . ' bytes; a UNIX socket path'
                . ' holds at most ' . self::MAX_SOCKET_PATH . ' (sun_path). Shorten TMPDIR: ' . $socketPath);
        }
        if (!mkdir($this->dir, 0700, true)) {
            throw new Exception('could not create SCGI fixture directory');
        }
        $endpointFile = $this->dir . '/endpoint';
        $this->capture = $this->dir . '/capture.json';
        $this->errorFile = $this->dir . '/err';
        $this->release = $this->dir . '/release';
        $configFile = $this->dir . '/config.json';
        $listen = $unix ? 'unix://' . $socketPath : 'tcp://127.0.0.1:0';
        $configChunks = [];
        foreach ($chunks as $chunk) {
            if (!is_array($chunk) || (count($chunk) !== 2)) {
                throw new Exception('SCGI fixture chunk must contain delay and bytes');
            }
            $configChunks[] = [
                'delay' => max(0, (int) round(((float) $chunk[0]) * 1000000)),
                'data' => base64_encode($chunk[1]),
            ];
        }
        file_put_contents($configFile, json_encode([
            'listen' => $listen,
            'hold' => $holdOpen,
            'chunks' => $configChunks,
            'repeat' => $repeatBytes,
            'readDelay' => max(0, (int) round($readDelay * 1000000)),
            'accept' => self::PEER_WAIT_SECONDS,
        ]));
        $script = "<?php\nrequire_once " . var_export(__DIR__ . '/SCGIRequest.php', true) . ";\n" . <<<'PHP'
$config = json_decode(file_get_contents($argv[1]), true);
// A client that stops reading before the reply is complete is a case the
// tests provoke. The write that meets it fails, and the peer exits below
// without a notice; any other diagnostic is reported as usual.
set_error_handler(function($number, $message)
{
	return preg_match('/^fwrite\(\): send of [0-9]+ bytes failed with errno=(32|104) /i', $message) === 1;
});
$server = @stream_socket_server($config['listen'], $errno, $error);
if($server === false)
{
	fwrite(STDERR, "server: $error\n");
	exit(2);
}
// The test reads these files while the peer writes them: each appears whole.
function publish($file, $data)
{
	file_put_contents($file . '.tmp', $data);
	rename($file . '.tmp', $file);
}
publish($argv[2], stream_socket_get_name($server, false));
$client = @stream_socket_accept($server, $config['accept']);
if($client === false)
	exit(3);
if($config['readDelay'] > 0)
	usleep($config['readDelay']);
publish($argv[3], json_encode(array_map('base64_encode', SCGIRequest::read($client))));
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
        $scriptFile = $this->dir . '/peer.php';
        file_put_contents($scriptFile, $script);
        $command = [PHP_BINARY, $scriptFile, $configFile, $endpointFile,
            $this->capture, $this->release];
        if (!function_exists('proc_open')) {
            throw new Exception('could not start SCGI fixture peer: proc_open is unavailable');
        }
        $this->process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', $this->dir . '/out', 'a'],
            2 => ['file', $this->errorFile, 'a'],
        ], $pipes, $this->dir);
        if (!is_resource($this->process)) {
            throw new Exception('could not start SCGI fixture peer');
        }
        fclose($pipes[0]);
        $endpoint = $this->waitForPeer('publish an endpoint', function () use ($endpointFile) {
            return is_file($endpointFile) ? trim(file_get_contents($endpointFile)) : null;
        });
        if ($unix) {
            $this->host = $listen;
            $this->port = 0;
            return;
        }
        $position = strrpos($endpoint, ':');
        $this->host = '127.0.0.1';
        $this->port = ($position === false) ? 0 : intval(substr($endpoint, $position + 1));
        if ($this->port <= 0) {
            throw new Exception('SCGI fixture peer published no port: ' . json_encode($endpoint));
        }
    }

    /**
     * Polls $ready until it returns a value other than null, for as long as
     * the peer runs and at most PEER_WAIT_SECONDS. A failure names what the
     * peer did not do, how it ended and what it wrote to stderr.
     */
    private function waitForPeer($what, $ready)
    {
        $started = microtime(true);
        while (true) {
            $value = $ready();
            if ($value !== null) {
                return $value;
            }
            if (!is_resource($this->process)) {
                throw new Exception('SCGI fixture is closed and did not ' . $what);
            }
            $status = proc_get_status($this->process);
            if (empty($status['running'])) {
                // It may have finished its work just before it exited.
                $value = $ready();
                if ($value !== null) {
                    return $value;
                }
                throw new Exception('SCGI fixture peer exited with code ' . $status['exitcode']
                    . ' and did not ' . $what . ': ' . $this->errors());
            }
            $elapsed = microtime(true) - $started;
            if ($elapsed >= self::PEER_WAIT_SECONDS) {
                throw new Exception('SCGI fixture peer did not ' . $what . ' in '
                    . round($elapsed, 1) . ' s and is still running: ' . $this->errors());
            }
            usleep(5000);
        }
    }
}
