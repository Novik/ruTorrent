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

	/**
	 * $replies is a list, one per request: each entry is the list of values
	 * the daemon answers that request with.
	 */
	public function __construct($replies, $logFile)
	{
		$this->logFile = $logFile;
		@unlink($this->logFile);
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

	private function reply($values)
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?><methodResponse><params><param>'
			. '<value><array><data>';
		foreach ($values as $value) {
			$xml .= is_int($value)
				? '<value><i8>' . $value . '</i8></value>'
				: '<value><string>' . htmlspecialchars((string)$value, ENT_COMPAT, 'UTF-8')
					. '</string></value>';
		}
		return $xml . '</data></array></value></param></params></methodResponse>';
	}
}
