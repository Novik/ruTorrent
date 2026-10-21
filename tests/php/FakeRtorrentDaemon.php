<?php

require_once(__DIR__.'/SCGIRequest.php');

/**
 * An SCGI listener that answers like rtorrent, for tests that need to drive a
 * top level script rather than a function.
 *
 * plugins/edit/action.php reads php://input, talks to rtorrent over SCGI and
 * ends by printing json. Its d.erase and its reload are two separate calls, so
 * the only way to see that the erase happened and the reload did not is to be
 * the daemon on the other end of both.
 *
 * Ordinary and fault replies use the same SCGI Content-Length framing as
 * rtorrent. raw() and paced() entries preserve their exact scripted bytes.
 * The request is an SCGI netstring header followed by the XMLRPC body, and
 * the method names are taken out of the body -- both the
 * single <methodName> form and the members of a system.multicall.
 */
class FakeRtorrentDaemon
{
	private $socket;
	private $pid = 0;
	private $process;
	private $port;
	private $logFile;
	private $bodyLogFile;

	const FAULT = '__fault__';
	const RAW = '__raw__';
	const PACED = '__paced__';

	/**
	 * A reply entry that makes the daemon answer with a fault instead of
	 * values, for a test that needs the caller's request to fail.
	 */
	public static function fault($message, $code = -501)
	{
		return array(self::FAULT => array('code' => $code, 'message' => $message));
	}

	/**
	 * A reply entry that makes the daemon answer with exactly these bytes and
	 * close the connection: an answer captured from a real rtorrent, SCGI
	 * headers and all, or one cut short the way a daemon that stopped
	 * mid-write leaves it.
	 */
	public static function raw($bytes)
	{
		return array(self::RAW => $bytes);
	}

	/**
	 * A reply entry that makes the daemon write these pieces in order, waiting
	 * $pause microseconds between them, and close. The wait is what makes each
	 * piece a read of its own on the other end: a reader blocked in fread()
	 * gets the piece that woke it, not the whole reply. An answer written in
	 * one call cannot show where a reader's reads fall, because the kernel is
	 * free to hand it over in any division at all -- including, under TCP, one
	 * that puts a single byte in a read by itself.
	 */
	public static function paced($pieces, $pause = 300000)
	{
		return array(self::PACED => array('pieces' => $pieces, 'pause' => $pause));
	}

	/**
	 * $replies is a list, one per request: each entry is the list of values
	 * the daemon answers that request with, self::fault(...) or
	 * self::raw(...), self::paced(...), or null to close without a reply.
	 *
	 * It may instead be a Closure taking the request body and returning one of
	 * those, for a caller whose own next request depends on the answer to
	 * this one -- rTorrentSettings::obtain() is seven round trips and chooses
	 * several of them from what it has been told so far. Such a daemon
	 * answers for as long as it is asked rather than for a fixed count.
	 * Serializable plans run through proc_open(); a Closure retains the optional
	 * pcntl path. A serializable responder object instead receives the body and
	 * method names. $replyClassFiles must define its test-owned class before the
	 * worker unserializes it.
	 */
	public function __construct($replies, $logFile, $worker = false, $replyClassFiles = array())
	{
		$this->logFile = $logFile;
		// Keep bodies separate so calls() remains a plain method-name list.
		$this->bodyLogFile = $logFile . '.bodies';
		if($worker)
		{
			$this->listen();
			fwrite(STDOUT, $this->port . "\n");
			fflush(STDOUT);
			$this->serve($replies);
			return;
		}
		@unlink($this->logFile);
		@unlink($this->bodyLogFile);
		@unlink($this->logFile . '.server.err');
		if($replies instanceof Closure)
		{
			if(!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')
				|| !function_exists('posix_kill'))
				throw new RuntimeException('a closure reply requires pcntl and posix; use a serializable reply plan');
			$this->listen();
			$this->pid = pcntl_fork();
			if($this->pid === -1)
			{
				fclose($this->socket);
				throw new RuntimeException('cannot fork a daemon');
			}
			if($this->pid === 0)
			{
				// The child must never return into the test that forked it:
				// an exception unwinding from here would run that test's
				// catch and finally blocks, and the rest of the run, twice.
				$code = 0;
				try
				{
					$this->serve($replies);
				}
				catch(Throwable $error)
				{
					@file_put_contents($this->logFile . '.server.err',
						get_class($error) . ': ' . $error->getMessage() . "\n", FILE_APPEND);
					$code = 1;
				}
				exit($code);
			}
			return;
		}
		$this->startProcess($replies, $replyClassFiles);
	}

	private function listen()
	{
		$this->socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		if($this->socket === false)
			throw new RuntimeException('cannot listen: ' . $errstr);
		$name = stream_socket_get_name($this->socket, false);
		$this->port = (int)substr($name, strrpos($name, ':') + 1);
	}

	private function startProcess($replies, $replyClassFiles)
	{
		$source = 'require ' . var_export(__FILE__, true) . ';';
		// Test-owned serializable responders must be defined before the
		// worker unserializes its plan, also when pcntl is unavailable.
		foreach($replyClassFiles as $file)
			$source .= 'require_once '.var_export($file, true).';';
		$source .= 'FakeRtorrentDaemon::runWorker();';
		// An argv command owns the PHP process directly, without a shell to orphan it.
		$command = array(PHP_BINARY, '-d', 'display_errors=stderr', '-r', $source);
		$plan = serialize(array('replies' => $replies, 'logFile' => $this->logFile));
		$this->process = proc_open($command, array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('file', $this->logFile . '.server.err', 'a'),
		), $pipes, dirname($this->logFile));
		if(!is_resource($this->process))
			throw new RuntimeException('cannot start a daemon process');
		$sent = 0;
		while($sent < strlen($plan))
		{
			$written = @fwrite($pipes[0], substr($plan, $sent));
			if($written === false || $written === 0) break;
			$sent += $written;
		}
		fclose($pipes[0]);
		stream_set_timeout($pipes[1], 10);
		$ready = ($sent === strlen($plan)) ? fgets($pipes[1]) : false;
		fclose($pipes[1]);
		if(!is_string($ready) || !preg_match('/^[1-9][0-9]*\n$/D', $ready))
		{
			$this->stop();
			throw new RuntimeException('fake daemon process did not announce a port: '
				. @file_get_contents($this->logFile . '.server.err'));
		}
		$this->port = (int)$ready;
	}

	public static function runWorker()
	{
		$plan = @unserialize(stream_get_contents(STDIN));
		if(!is_array($plan) || !array_key_exists('replies', $plan) || !isset($plan['logFile']))
			throw new RuntimeException('invalid fake daemon reply plan');
		new self($plan['replies'], $plan['logFile'], true);
	}

	public function port()
	{
		return $this->port;
	}

	/** The method names the daemon was asked for, in order. */
	public function calls()
	{
		$log = file_exists($this->logFile) ? trim(file_get_contents($this->logFile)) : '';
		return $log === '' ? array() : explode("\n", $log);
	}

	/**
	 * The full XMLRPC request bodies the daemon was sent, in order. Separated
	 * by a record marker rather than a newline because a body may contain one.
	 */
	public function bodies()
	{
		if(!file_exists($this->bodyLogFile))
			return array();
		$log = file_get_contents($this->bodyLogFile);
		if($log === '' || $log === false)
			return array();
		$records = explode("\x00--REQ--\x00", $log);
		array_pop($records);
		return $records;
	}

	public function received($method)
	{
		return in_array($method, $this->calls(), true);
	}

	public function stop()
	{
		if ($this->pid > 0) {
			posix_kill($this->pid, SIGTERM);
			pcntl_waitpid($this->pid, $status);
			$this->pid = 0;
		}
		if (is_resource($this->process)) {
			$status = proc_get_status($this->process);
			if($status['running']) @proc_terminate($this->process);
			@proc_close($this->process);
			$this->process = null;
		}
		if (is_resource($this->socket)) @fclose($this->socket);
	}

	private function serve($replies)
	{
		@fclose(STDOUT);
		$dynamic = is_callable($replies);
		$index = 0;
		while ($dynamic || $index < count($replies)) {
			$client = @stream_socket_accept($this->socket, 10);
			if ($client === false) {
				break;
			}
			try {
				$body = SCGIRequest::read($client)['payload'];
			} catch (RuntimeException $error) {
				// A client that connected and sent nothing, or stopped inside
				// the request, is not a request: it gets no reply and uses no
				// reply entry.
				@fclose($client);
				continue;
			}
			file_put_contents($this->bodyLogFile, $body . "\x00--REQ--\x00", FILE_APPEND);
			$methods = $this->methodNames($body);
			foreach ($methods as $method) {
				file_put_contents($this->logFile, $method . "\n", FILE_APPEND);
			}
			$values = $dynamic ? $replies($body, $methods) : $replies[$index];
			$index++;
			if (isset($values[self::PACED])) {
				$this->writePaced($client, $values[self::PACED]);
			} elseif ($values !== null) {
				// A null plan deliberately drops the reply after recording the call.
				@fwrite($client, $this->reply($values));
			}
			@fclose($client);
		}
		@fclose($this->socket);
	}

	private function writePaced($client, $paced)
	{
		$first = true;
		foreach ($paced['pieces'] as $piece) {
			if (!$first) {
				usleep($paced['pause']);
			}
			$first = false;
			// The reader may have stopped and closed before the last piece;
			// that is the failure such a test is looking for, not an error here.
			@fwrite($client, $piece);
			@fflush($client);
		}
	}

	private function methodNames($body)
	{
		$names = array();
		if (preg_match('|<methodName>(.*)</methodName>|Us', $body, $outer)) {
			if ($outer[1] !== 'system.multicall') {
				return array($outer[1]);
			}
		}
		if (preg_match_all(
			'|<name>methodName</name><value><string>(.*)</string></value>|Us', $body, $inner)) {
			$names = $inner[1];
		}
		return $names;
	}

	/**
	 * Escape a value for XML text content the way rtorrent's XMLRPC layer does:
	 * '&', '<' and '>' and nothing else. It is byte-wise on purpose -- rtorrent
	 * answers with whatever bytes the filesystem gave it, including a path that
	 * is not valid UTF-8, and a quote or a backslash arrives literally rather
	 * than as an entity. htmlspecialchars() here would escape the quote and
	 * drop a non-UTF-8 value, and the reading side treats both of those
	 * differently from the bytes themselves.
	 */
	private static function escapeText($value)
	{
		return strtr($value, array('&' => '&amp;', '<' => '&lt;', '>' => '&gt;'));
	}

	private function reply($values)
	{
		if (isset($values[self::FAULT])) {
			return self::frame(self::faultReply($values[self::FAULT]));
		}
		if (isset($values[self::RAW])) {
			return $values[self::RAW];
		}
		$xml = '<?xml version="1.0" encoding="UTF-8"?><methodResponse><params><param>'
			. '<value><array><data>';
		foreach ($values as $value) {
			$xml .= is_int($value)
				? '<value><i8>' . $value . '</i8></value>'
				: '<value><string>' . self::escapeText((string)$value) . '</string></value>';
		}
		return self::frame($xml . '</data></array></value></param></params></methodResponse>');
	}

	private static function frame($body)
	{
		return "Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: "
			. strlen($body) . "\r\n\r\n" . $body;
	}

	private static function faultReply($fault)
	{
		return '<?xml version="1.0" encoding="UTF-8"?><methodResponse><fault><value><struct>'
			. '<member><name>faultCode</name><value><i4>' . (int)$fault['code']
			. '</i4></value></member>'
			. '<member><name>faultString</name><value><string>'
			. self::escapeText($fault['message'])
			. '</string></value></member></struct></value></fault></methodResponse>';
	}
}
