<?php

/**
 * An SCGI listener that answers like rtorrent, for tests that need to drive a
 * top level script rather than a function.
 *
 * plugins/edit/action.php reads php://input, talks to rtorrent over SCGI and
 * ends by printing json. Its d.erase and its reload are two separate calls, so
 * the only way to see that the erase happened and the reload did not is to be
 * the daemon on the other end of both.
 *
 * php/xmlrpc.php reads a reply by regex over <value><string> and <value><i8>,
 * so a reply here is that and nothing else; no HTTP framing is involved. The
 * request is an SCGI netstring header followed by the XMLRPC body, and the
 * method names are taken out of the body -- both the single <methodName> form
 * and the members of a system.multicall.
 */
class FakeRtorrentDaemon
{
	private $socket;
	private $pid;
	private $port;
	private $logFile;
	private $bodyLogFile;

	/**
	 * $replies is a list, one per request: each entry is the list of values
	 * the daemon answers that request with.
	 */
	public function __construct($replies, $logFile)
	{
		$this->logFile = $logFile;
		// The method log answers "was this called"; a shape test also has to
		// ask "carrying what", and the parameters are only in the body. Kept
		// in a second file so calls() stays a plain list of names.
		$this->bodyLogFile = $logFile . '.bodies';
		@unlink($this->logFile);
		@unlink($this->bodyLogFile);
		$this->socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		if ($this->socket === false) {
			throw new RuntimeException('cannot listen: ' . $errstr);
		}
		$name = stream_socket_get_name($this->socket, false);
		$this->port = (int)substr($name, strrpos($name, ':') + 1);

		$this->pid = pcntl_fork();
		if ($this->pid === -1) {
			throw new RuntimeException('cannot fork a daemon');
		}
		if ($this->pid === 0) {
			$this->serve($replies);
			exit(0);
		}
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
			// The child exits on its own when the replies run out; this is the
			// backstop for a test that sent fewer requests than that.
			posix_kill($this->pid, SIGTERM);
			pcntl_waitpid($this->pid, $status);
			$this->pid = 0;
		}
		if (is_resource($this->socket)) {
			@fclose($this->socket);
		}
	}

	private function serve($replies)
	{
		@fclose(STDOUT);
		foreach ($replies as $values) {
			$client = @stream_socket_accept($this->socket, 10);
			if ($client === false) {
				break;
			}
			$body = $this->readRequest($client);
			file_put_contents($this->bodyLogFile, $body . "\x00--REQ--\x00", FILE_APPEND);
			foreach ($this->methodNames($body) as $method) {
				file_put_contents($this->logFile, $method . "\n", FILE_APPEND);
			}
			@fwrite($client, $this->reply($values));
			@fclose($client);
		}
		@fclose($this->socket);
	}

	/** SCGI: "<len>:<headers>,<body>", with CONTENT_LENGTH in the headers. */
	private function readRequest($client)
	{
		stream_set_timeout($client, 10);
		$length = '';
		while (($char = fread($client, 1)) !== '' && $char !== false && $char !== ':') {
			$length .= $char;
		}
		$headers = '';
		$want = (int)$length;
		while (strlen($headers) < $want) {
			$chunk = fread($client, $want - strlen($headers));
			if ($chunk === '' || $chunk === false) {
				break;
			}
			$headers .= $chunk;
		}
		fread($client, 1); // the ',' that closes the netstring
		$fields = explode("\x00", $headers);
		$contentLength = 0;
		for ($i = 0; $i + 1 < count($fields); $i += 2) {
			if ($fields[$i] === 'CONTENT_LENGTH') {
				$contentLength = (int)$fields[$i + 1];
			}
		}
		$body = '';
		while (strlen($body) < $contentLength) {
			$chunk = fread($client, $contentLength - strlen($body));
			if ($chunk === '' || $chunk === false) {
				break;
			}
			$body .= $chunk;
		}
		return $body;
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
		$xml = '<?xml version="1.0" encoding="UTF-8"?><methodResponse><params><param>'
			. '<value><array><data>';
		foreach ($values as $value) {
			$xml .= is_int($value)
				? '<value><i8>' . $value . '</i8></value>'
				: '<value><string>' . self::escapeText((string)$value) . '</string></value>';
		}
		return $xml . '</data></array></value></param></params></methodResponse>';
	}
}
