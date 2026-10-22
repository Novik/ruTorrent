<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * php/addtorrent.php answers an upload with a redirect back to itself,
 * carrying the result of every item. These tests run the production file and
 * the production Requests in a throwaway PHP server and read the Location
 * header off the wire, request headers and all.
 */
class AddTorrentRedirectTest extends TestCase
{
	private $sourceRoot;

	public function setUp()
	{
		$this->sourceRoot = realpath(__DIR__ . '/../..');
		if($this->sourceRoot === false)
			throw new Exception('could not locate the production source root');
	}

	/** An upload is answered with a 302 whose target carries the results. */
	public function testRedirectAnswersTheUploadWithItsResult()
	{
		$result = $this->post('/php/addtorrent.php', array('Host' => '127.0.0.1'));
		$this->assertTrue(strpos($result['status'], ' 302 ') !== false,
			'the upload is answered with a redirect: ' . $result['status']);
		$this->assertEquals('http://127.0.0.1/php/addtorrent.php?result[]=Failed&',
			$this->location($result), 'a direct install redirects to its own addtorrent.php');
	}

	/**
	 * The Host header names the server the client asked for, which on an
	 * install behind a rewriting proxy is not the one its users reach.
	 */
	public function testConfiguredPublicBaseDecidesTheRedirect()
	{
		$result = $this->post('/php/addtorrent.php', array('Host' => 'internal.test:8080'),
			'https://public.test/app');
		$this->assertEquals('https://public.test/app/php/addtorrent.php?result[]=Failed&',
			$this->location($result), 'the configured public base is the redirect target');
		$this->assertTrue(strpos($this->location($result), 'internal.test') === false,
			'the internal host does not reach the browser: ' . $this->location($result));
	}

	public function testProxiedTLSKeepsTheRedirectOnHttps()
	{
		$result = $this->post('/php/addtorrent.php',
			array('Host' => 'public.test', 'X-Forwarded-Proto' => 'https'));
		$this->assertEquals('https://public.test/php/addtorrent.php?result[]=Failed&',
			$this->location($result), 'a proxy that terminates TLS redirects to https');
	}

	/**
	 * A Location may be a relative reference, so an install whose public
	 * base cannot be determined is still redirected, to the request it made.
	 */
	public function testUnusablePublicBaseFallsBackToARelativeTarget()
	{
		$result = $this->post('/php/addtorrent.php', array('Host' => 'public.test'),
			'ftp://public.test/app');
		$this->assertEquals('./addtorrent.php?result[]=Failed&', $this->location($result),
			'a refused public base leaves a relative redirect');
	}

	public function testRedirectCarriesTheJsonRequestOnward()
	{
		$result = $this->post('/php/addtorrent.php?json=1', array('Host' => '127.0.0.1'));
		$this->assertEquals('http://127.0.0.1/php/addtorrent.php?result[]=Failed&json=1',
			$this->location($result), 'a json upload is redirected to the json result');
	}

	// ---- plumbing --------------------------------------------------------

	private function location($result)
	{
		return isset($result['headers']['location']) ? $result['headers']['location'] : '';
	}

	/**
	 * Serve the production addtorrent.php and the production Requests from a
	 * copied tree. Only what sits below them is stubbed: the redirect branch
	 * needs no rTorrent daemon and no Snoopy, but the requires at the top of
	 * the file have to resolve, and rTorrent::loadedHashes() is called.
	 */
	private function post($target, $headers, $publicURL = '')
	{
		$tree = sys_get_temp_dir() . '/rutorrent-addtorrent-redirect-' . uniqid('', true);
		$process = null;
		try
		{
			if(!mkdir($tree . '/php/utility', 0700, true) && !is_dir($tree . '/php/utility'))
				throw new Exception('could not create addtorrent fixture tree');
			foreach(array('php/addtorrent.php', 'php/utility/cachedecho.php',
				'php/utility/requests.php', 'php/utility/urlhost.php') as $relative)
			{
				$source = $this->sourceRoot . '/' . $relative;
				$target_file = $tree . '/' . $relative;
				if(!copy($source, $target_file) || (hash_file('sha256', $source) !== hash_file('sha256', $target_file)))
					throw new Exception('could not byte-copy production source ' . $relative);
			}
			$this->writeStubs($tree, $publicURL);

			$port = $this->reservePort();
			// "exec" replaces the shell proc_open() runs the string through, so the
			// server is the process proc_terminate() signals.
			$command = 'exec ' . escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d zlib.output_compression=0'
				. ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($tree);
			$process = proc_open($command, array(
				0 => array('pipe', 'r'),
				1 => array('file', $tree . '/server.out', 'a'),
				2 => array('file', $tree . '/server.err', 'a'),
			), $pipes, $tree);
			if(!is_resource($process))
				throw new Exception('could not start copied PHP server');
			fclose($pipes[0]);
			$this->waitForServer($port, $process, $tree . '/server.err');
			return $this->rawPost($port, $target, $headers);
		}
		finally
		{
			if(is_resource($process))
			{
				@proc_terminate($process);
				@proc_close($process);
			}
			$this->deleteTree($tree);
		}
	}

	private function writeStubs($tree, $publicURL)
	{
		file_put_contents($tree . '/php/Snoopy.class.inc', "<?php\nclass Snoopy {}\n");
		file_put_contents($tree . '/php/rtorrent.php', "<?php\n"
			. "require_once(" . var_export($tree . '/php/utility/cachedecho.php', true) . ");\n"
			. "require_once(" . var_export($tree . '/php/utility/requests.php', true) . ");\n"
			. "class rTorrent { public static function loadedHashes() { return array(); } }\n"
			. "\$publicURL = " . var_export($publicURL, true) . ";\n"
			. "\$phpUseGzip = false;\n");
		file_put_contents($tree . '/php/utility/fileutil.php', "<?php\nclass FileUtil {}\n");
		file_put_contents($tree . '/php/utility/utility.php', "<?php\nclass Utility {}\n");
	}

	private function reservePort()
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		if($socket === false)
			throw new Exception('could not reserve local test port: ' . $error);
		$name = stream_socket_get_name($socket, false);
		fclose($socket);
		$parts = explode(':', $name);
		return intval(array_pop($parts));
	}

	private function waitForServer($port, $process, $errorFile)
	{
		for($i = 0; $i < 100; $i++)
		{
			$socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.05);
			if($socket !== false)
			{
				fclose($socket);
				return;
			}
			$status = proc_get_status($process);
			if(!$status['running'])
				throw new Exception('copied PHP server exited: ' . @file_get_contents($errorFile));
			usleep(25000);
		}
		throw new Exception('copied PHP server did not start');
	}

	/** A POST with no body at all: the redirect branch takes no upload. */
	private function rawPost($port, $path, $headers)
	{
		$socket = @fsockopen('127.0.0.1', $port, $errno, $error, 2);
		if($socket === false)
			throw new Exception('could not connect raw HTTP client: ' . $error);
		$request = "POST " . $path . " HTTP/1.1\r\n";
		foreach($headers as $name => $value)
			$request .= $name . ": " . $value . "\r\n";
		$request .= "Content-Length: 0\r\nConnection: close\r\n\r\n";
		if(fwrite($socket, $request) === false)
			throw new Exception('could not write raw HTTP request');
		$raw = stream_get_contents($socket);
		fclose($socket);
		if($raw === false || strpos($raw, "\r\n\r\n") === false)
			throw new Exception('copied addtorrent.php returned no complete HTTP response');
		list($headerBlock, $body) = explode("\r\n\r\n", $raw, 2);
		$lines = explode("\r\n", $headerBlock);
		$status = array_shift($lines);
		$values = array();
		foreach($lines as $header)
		{
			$position = strpos($header, ':');
			if($position !== false)
				$values[strtolower(trim(substr($header, 0, $position)))] = trim(substr($header, $position + 1));
		}
		return array('status' => $status, 'headers' => $values, 'body' => $body);
	}

	private function deleteTree($path)
	{
		if(!is_string($path) || $path === '' || !is_dir($path))
			return;
		foreach(scandir($path) as $entry)
		{
			if($entry === '.' || $entry === '..')
				continue;
			$child = $path . '/' . $entry;
			if(is_dir($child) && !is_link($child))
				$this->deleteTree($child);
			else
				@unlink($child);
		}
		@rmdir($path);
	}
}
