<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * php/addtorrent.php reflects the result[] and name[] query parameters into
 * the response it serves. These tests run the production file and the
 * production CachedEcho byte-for-byte in a throwaway PHP server and look at
 * the real bytes and the real headers that come back over HTTP.
 */
class AddTorrentResultTest extends TestCase
{
	private $sourceRoot;

	public function setUp()
	{
		$this->sourceRoot = realpath(__DIR__ . '/../..');
		if($this->sourceRoot === false)
			throw new Exception('could not locate the production source root');
	}

	// ---- what the response may not contain -------------------------------

	public function testResultIsNotReflectedAsMarkup()
	{
		$result = $this->get('?result[]=' . rawurlencode('<svg onload="alert(1)">'));
		$this->assertNoMarkup($result, 'result[] cannot put an element in the response');
	}

	public function testJsonResultIsNotReflectedAsMarkup()
	{
		$result = $this->get('?json=1&result[]=' . rawurlencode('<svg onload="alert(1)">'));
		$this->assertNoMarkup($result, 'result[] cannot put an element in the json response');
		$this->assertTrue(json_decode($result['body'], true) !== null,
			'the json response is valid json: ' . $result['body']);
	}

	public function testNameIsNotReflectedAsMarkup()
	{
		$result = $this->get('?result[]=Success&name[]=' . rawurlencode('<svg onload="alert(1)">'));
		$this->assertNoMarkup($result, 'name[] cannot put an element in the response');
	}

	/**
	 * name[] used to be run through htmlspecialchars() and then through
	 * rawurldecode(), in that order, so percent escapes that survived the
	 * escaping were turned back into the characters it had escaped.
	 */
	public function testPercentEncodedNameIsNotDecodedBackIntoMarkup()
	{
		$result = $this->get('?result[]=Success&name[]=%253Csvg%2520onload%253Dalert(1)%253E');
		$this->assertNoMarkup($result, 'a percent-encoded name[] is not decoded back into markup');
	}

	public function testResultCannotAddStatementsToTheScript()
	{
		$result = $this->get('?result[]=' . rawurlencode('Success"); alert(1); //'));
		$this->assertOneCall($result, 'a result[] that tries to close the call stays one argument');
	}

	public function testNameCannotAddStatementsToTheScript()
	{
		$result = $this->get('?result[]=Success&name[]=' . rawurlencode('a"); alert(1); //'));
		$this->assertOneCall($result, 'a name[] that tries to close the call stays one argument');
	}

	// ---- headers ---------------------------------------------------------

	public function testResponseForbidsContentTypeSniffing()
	{
		$result = $this->get('?result[]=Success');
		$this->assertEquals('nosniff', isset($result['headers']['x-content-type-options'])
			? $result['headers']['x-content-type-options'] : null,
			'the response carries X-Content-Type-Options: nosniff');
	}

	public function testJsonResponseForbidsContentTypeSniffing()
	{
		$result = $this->get('?json=1&result[]=Success');
		$this->assertEquals('nosniff', isset($result['headers']['x-content-type-options'])
			? $result['headers']['x-content-type-options'] : null,
			'the json response carries X-Content-Type-Options: nosniff');
		$this->assertEquals('application/json; charset=UTF-8',
			isset($result['headers']['content-type']) ? $result['headers']['content-type'] : null,
			'the json response is served as json');
	}

	// ---- what the response still has to be -------------------------------

	public function testSuccessWithNameProducesTheCallTheClientEvaluates()
	{
		$result = $this->get('?result[]=Success&name[]=' . rawurlencode('My Torrent'));
		$this->assertEquals('noty("My Torrent - "+theUILang["addTorrent"+"Success"],"success");',
			$result['body'], 'a successful add names the torrent and picks the success wording');
		$this->assertEquals('text/html; charset=UTF-8', isset($result['headers']['content-type'])
			? $result['headers']['content-type'] : null, 'the script response is served as text/html');
	}

	public function testFailureWithoutNameProducesTheErrorCall()
	{
		$result = $this->get('?result[]=FailedURL');
		$this->assertEquals('noty(""+theUILang["addTorrent"+"FailedURL"],"error");',
			$result['body'], 'a failure picks the matching wording and the error style');
	}

	public function testEveryResultProducesItsOwnCall()
	{
		$result = $this->get('?result[]=Success&result[]=Failed&name[]=' . rawurlencode('one')
			. '&name[]=' . rawurlencode('two'));
		$this->assertEquals(
			'noty("one - "+theUILang["addTorrent"+"Success"],"success");'
			. 'noty("two - "+theUILang["addTorrent"+"Failed"],"error");',
			$result['body'], 'each uploaded file gets its own notification');
	}

	public function testNameKeepsItsOwnPunctuationAndAccents()
	{
		$result = $this->get('?result[]=Success&name[]=' . rawurlencode("Bob's 100% Fu\xc3\x9f [2024].torrent"));
		$decoded = json_decode(substr($result['body'], strlen('noty('),
			strpos($result['body'], '+theUILang') - strlen('noty(')), true);
		$this->assertEquals("Bob's 100% Fu\xc3\x9f [2024].torrent - ", $decoded,
			'the name reaches the client unchanged: ' . $result['body']);
	}

	public function testJsonBranchReportsTheResult()
	{
		$result = $this->get('?json=1&result[]=Success');
		$this->assertEquals(array('result' => 'Success'), json_decode($result['body'], true),
			'the json branch reports the result: ' . $result['body']);
	}

	// ---- plumbing --------------------------------------------------------

	/** No byte of the response may be an angle bracket a browser could parse. */
	private function assertNoMarkup($result, $message)
	{
		$body = $result['body'];
		$this->assertTrue(strpos($body, '<') === false,
			$message . ' -- no "<" in ' . $body);
		$this->assertTrue(strpos($body, '>') === false,
			$message . ' -- no ">" in ' . $body);
	}

	/**
	 * The response is one noty() call. A payload that tries to close the
	 * argument list cannot appear as written, and the only double quotes in
	 * the body are the eight the template itself writes.
	 */
	private function assertOneCall($result, $message)
	{
		$body = $result['body'];
		$this->assertTrue(strpos($body, 'alert(1)') !== false,
			$message . ' -- the payload is present at all: ' . $body);
		$this->assertTrue(strpos($body, '"); alert(1)') === false,
			$message . ' -- the payload cannot close an argument: ' . $body);
		$this->assertEquals(1, substr_count($body, 'noty('),
			$message . ' -- exactly one call: ' . $body);
		$this->assertEquals(8, substr_count($body, '"'),
			$message . ' -- only the eight quotes the template writes: ' . $body);
	}

	/**
	 * Serve the production addtorrent.php and CachedEcho from a copied tree.
	 * Only what sits below them is stubbed: addtorrent.php's result branch
	 * needs no rTorrent and no Snoopy, but the requires at the top of the file
	 * have to resolve.
	 */
	private function get($query)
	{
		$tree = sys_get_temp_dir() . '/rutorrent-addtorrent-' . uniqid('', true);
		$process = null;
		try
		{
			if(!mkdir($tree . '/php/utility', 0700, true) && !is_dir($tree . '/php/utility'))
				throw new Exception('could not create addtorrent fixture tree');
			foreach(array('php/addtorrent.php', 'php/utility/cachedecho.php') as $relative)
			{
				$source = $this->sourceRoot . '/' . $relative;
				$target = $tree . '/' . $relative;
				if(!copy($source, $target) || (hash_file('sha256', $source) !== hash_file('sha256', $target)))
					throw new Exception('could not byte-copy production source ' . $relative);
			}
			$this->writeStubs($tree);

			$port = $this->reservePort();
			$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d zlib.output_compression=0'
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
			return $this->rawGet($port, '/php/addtorrent.php' . $query);
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

	private function writeStubs($tree)
	{
		file_put_contents($tree . '/php/Snoopy.class.inc', "<?php\nclass Snoopy {}\n");
		file_put_contents($tree . '/php/rtorrent.php', "<?php\n"
			. "require_once(" . var_export($tree . '/php/utility/cachedecho.php', true) . ");\n"
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

	private function rawGet($port, $path)
	{
		$socket = @fsockopen('127.0.0.1', $port, $errno, $error, 2);
		if($socket === false)
			throw new Exception('could not connect raw HTTP client: ' . $error);
		$request = "GET " . $path . " HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n";
		if(fwrite($socket, $request) === false)
			throw new Exception('could not write raw HTTP request');
		$raw = stream_get_contents($socket);
		fclose($socket);
		if($raw === false || strpos($raw, "\r\n\r\n") === false)
			throw new Exception('copied addtorrent.php returned no complete HTTP response');
		list($headerBlock, $body) = explode("\r\n\r\n", $raw, 2);
		$headers = explode("\r\n", $headerBlock);
		$status = array_shift($headers);
		$values = array();
		foreach($headers as $header)
		{
			$position = strpos($header, ':');
			if($position !== false)
				$values[strtolower(trim(substr($header, 0, $position)))] = trim(substr($header, $position + 1));
		}
		return array('status' => $status, 'headers' => $values, 'body' => $body);
	}

	private function deleteTree($path)
	{
		if(!is_dir($path))
			return;
		foreach(scandir($path) as $entry)
			if(($entry !== '.') && ($entry !== '..'))
			{
				$child = $path . '/' . $entry;
				if(is_dir($child))
					$this->deleteTree($child);
				else
					@unlink($child);
			}
		@rmdir($path);
	}
}
