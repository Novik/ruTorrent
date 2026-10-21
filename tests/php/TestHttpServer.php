<?php

require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/TestTree.php');

/** Owns a disposable php -S process and the HTTP client shared by endpoint tests. */
class TestHttpServer
{
	const ATTEMPTS = 5;

	/** Servers this process started and has not closed yet, by object id. */
	private static $open = array();
	private static $hooked = false;
	private $owner;

	private $process;
	private $port;
	private $routerFile;

	/**
	 * $environment is added to the inherited one: getenv(), because $_ENV is
	 * empty when variables_order has no E.
	 */
	public static function start($tree, $environment = array(), $arguments = array(), $router = null)
	{
		$server = new self();
		$ready = '/__test_http_'.uniqid('', true);
		$token = bin2hex(random_bytes(16));
		file_put_contents($tree.$ready, $token);
		$errorFile = $tree.'/server.err';
		try
		{
			if(!function_exists('proc_open'))
				throw new Exception('could not start the HTTP test server process: proc_open is unavailable');
			$cause = '';
			// Reserving a port and starting php -S cannot be atomic; retry a lost port.
			for($attempt = 0; $attempt < self::ATTEMPTS; $attempt++)
			{
				$server->port = static::choosePort();
				$command = array_merge(array(PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1',
					'-d', 'error_reporting=-1'), $arguments,
					array('-S', '127.0.0.1:'.$server->port, '-t', $tree));
				if($router !== null)
				{
					$server->routerFile = $tree.'/__test_router_'.uniqid('', true).'.php';
					$wrapper = '<?php if(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)==='
						.var_export($ready, true).') return false; return require '.var_export($router, true).';';
					if(file_put_contents($server->routerFile, $wrapper) !== strlen($wrapper))
						throw new Exception('could not create HTTP test router');
					$command[] = $server->routerFile;
				}
				clearstatcache(true, $errorFile);
				$errorOffset = is_file($errorFile) ? filesize($errorFile) : 0;
				$server->process = proc_open($command, array(0 => array('pipe', 'r'),
					1 => array('file', $tree.'/server.out', 'a'), 2 => array('file', $errorFile, 'a')),
					$pipes, $tree, array_merge(getenv(), $environment));
				if(!is_resource($server->process))
					throw new Exception('could not start the HTTP test server process');
				self::closeAtShutdown($server);
				fclose($pipes[0]);
				$cause = 'server gave no answer with the readiness token';
				for($i = 0; $i < 100; $i++)
				{
					$status = proc_get_status($server->process);
					if(!$status['running'])
					{
						$cause = 'server exited with code '.$status['exitcode'].': '
							.trim((string)@file_get_contents($errorFile, false, null, $errorOffset));
						break;
					}
					$client = @fsockopen('127.0.0.1', $server->port, $errno, $error, 0.05);
					if($client !== false)
					{
						// Empty connects stall php -S 7.4; a full GET also proves this is our server.
						stream_set_timeout($client, 1);
						fwrite($client, 'GET '.$ready." HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");
						$response = stream_get_contents($client);
						fclose($client);
						if(is_string($response) && substr($response, -strlen($token)) === $token)
							return $server;
					}
					usleep(25000);
				}
				$server->close();
			}
			throw new Exception('HTTP test server did not start in '.self::ATTEMPTS
				.' attempts; last attempt on 127.0.0.1:'.$server->port.': '.$cause);
		}
		catch(Throwable $error)
		{
			$server->close();
			throw $error;
		}
		finally { @unlink($tree.$ready); }
	}

	/** A port free at the time of asking; the seam a test uses to offer a taken one. */
	protected static function choosePort()
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		if($socket === false) throw new Exception('could not reserve HTTP test port: '.$error);
		$name = stream_socket_get_name($socket, false);
		fclose($socket);
		return (int)substr($name, strrpos($name, ':')+1);
	}

	/**
	 * A test that ends in a fatal error or exit() never reaches its finally
	 * block, and php -S would outlive it.
	 */
	private static function closeAtShutdown($server)
	{
		if(!self::$hooked)
		{
			self::$hooked = true;
			register_shutdown_function(function()
			{
				// A forked child inherits this hook and its parent's servers;
				// each process closes only the ones it started.
				foreach(self::$open as $server)
					if($server->owner === getmypid())
						$server->close();
			});
		}
		$server->owner = getmypid();
		self::$open[spl_object_id($server)] = $server;
	}

	public function port() { return $this->port; }

	public function close()
	{
		unset(self::$open[spl_object_id($this)]);
		if(is_resource($this->process))
		{
			// An exited server may already be reaped; do not signal its PID again.
			$status = proc_get_status($this->process);
			if(!empty($status['running']))
				PhpProcess::stop($this->process);
			@proc_close($this->process);
		}
		$this->process = null;
		if($this->routerFile !== null)
		{
			@unlink($this->routerFile);
			$this->routerFile = null;
		}
	}

	public static function rawPost($port, $path, $body, $contentType = 'text/xml', $timeout = 10)
	{
		return self::request($port, $path, 'POST', $body, $contentType, $timeout);
	}

	public static function rawGet($port, $path, $timeout = 10)
	{
		return self::request($port, $path, 'GET', '', 'text/xml', $timeout);
	}

	private static function request($port, $path, $method, $body, $contentType, $timeout)
	{
		$socket = @fsockopen('127.0.0.1', $port, $errno, $error, 2);
		if($socket === false) throw new Exception('could not connect HTTP test client: '.$error);
		try
		{
			stream_set_timeout($socket, $timeout);
			$request = $method.' '.$path." HTTP/1.1\r\nHost: 127.0.0.1\r\nContent-Type: ".$contentType."\r\n"
				.'Content-Length: '.strlen($body)."\r\nConnection: close\r\n\r\n".$body;
			if(fwrite($socket, $request) !== strlen($request)) throw new Exception('could not write HTTP test request');
			$raw = stream_get_contents($socket);
			// What arrived before the timeout is a truncated response, not a short one.
			if(stream_get_meta_data($socket)['timed_out'])
				throw new Exception('HTTP test response timed out after '.$timeout.' s with '
					.strlen((string)$raw).' bytes read');
		}
		finally { fclose($socket); }
		if($raw === false || strpos($raw, "\r\n\r\n") === false)
			throw new Exception('HTTP test server returned no complete response');
		list($headerBlock, $responseBody) = explode("\r\n\r\n", $raw, 2);
		$headers = explode("\r\n", $headerBlock);
		$status = array_shift($headers);
		$values = array();
		foreach($headers as $header)
		{
			$position = strpos($header, ':');
			if($position !== false)
				$values[strtolower(trim(substr($header, 0, $position)))] = trim(substr($header, $position+1));
		}
		return array('status' => $status, 'headers' => $values, 'body' => $responseBody);
	}

}
