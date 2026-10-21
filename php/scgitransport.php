<?php

/**
 * One framed SCGI exchange. This layer owns bytes and deadlines only; XML is
 * deliberately left to its caller, which knows the response it requested.
 */
class rSCGITransport
{
	const RESPONSE_RAW = 'raw';
	const RESPONSE_BODY = 'body';
	const MAX_HEADER_BYTES = 65536;
	const MAX_WRITE_BYTES = 65536;
	// Cap for both timeouts: PHP 7.4/8.1 turn the connect timeout into signed
	// 32-bit milliseconds, which wrap above this many seconds.
	const MAX_TRANSFER_TIMEOUT = 2147483;
	// rtorrent itself never sends a larger response body: 0.9.8's
	// SCgiTask::receive_write() and current master's max_response_size
	// (scgi_task.h) both stop at 100 << 20 bytes. It is also the default
	// limit, so by default no response the daemon can send is refused.
	const MAX_RESPONSE_BYTES = 104857600;
	// A larger response is collected in a temporary file while it arrives.
	const MEMORY_RESPONSE_BYTES = 1048576;
	// An idle exchange waits for the socket at most this long at first, then
	// twice as long each time up to the longest pause, so that a slow answer
	// costs few wakeups. Any progress starts over from the first. Where the
	// socket cannot be watched the waits are sleeps, and the short first ones
	// keep a prompt answer cheap.
	const FIRST_PAUSE = 0.00005;
	const LONGEST_PAUSE = 0.032;
	const OPTIONS = array('connectTimeout' => true, 'transferTimeout' => true, 'maxResponseBytes' => true,
		'responseMode' => true, 'temporaryDirectory' => true);

	public static function requiredFunctions()
	{
		return array('stream_socket_client', 'stream_set_blocking',
			'stream_get_meta_data', 'fread', 'fwrite', 'fclose', 'usleep');
	}

	/** Either is enough: hrtime() when available, microtime() otherwise. */
	public static function clockFunctions()
	{
		return array('hrtime', 'microtime');
	}

	/**
	 * One exchange with the configured daemon. $options override the
	 * configuration; see send() for their names.
	 */
	public static function sendConfigured($payload, $trusted, $options = array(), &$failure = null, &$connectError = null)
	{
		global $scgi_host, $scgi_port, $rpcTimeOut, $rpcTransferTimeOut, $rpcMaxResponseBytes, $tempDirectory;
		return static::send($scgi_host, $scgi_port, $payload, $trusted, $options + array(
			'connectTimeout' => $rpcTimeOut,
			'transferTimeout' => $rpcTransferTimeOut,
			'maxResponseBytes' => $rpcMaxResponseBytes,
			'temporaryDirectory' => $tempDirectory,
		), $failure, $connectError);
	}

	/**
	 * $options: 'connectTimeout' and 'transferTimeout' in seconds (see
	 * timeouts()), 'maxResponseBytes', 'responseMode' (RESPONSE_RAW by
	 * default) and 'temporaryDirectory' for a response above 1 MiB. Returns
	 * the response, or null with a classified $failure.
	 */
	public static function send($host, $port, $payload, $trusted = true, $options = array(),
		&$failure = null, &$connectError = null)
	{
		$failure = null;
		$connectError = null;
		$responseMode = $options['responseMode'] ?? self::RESPONSE_RAW;
		if($payload === '')
			return self::fail($failure, 'empty-request');
		// A misspelt option would otherwise fall back to its default unnoticed.
		foreach($options as $name => $value)
			if(!isset(self::OPTIONS[$name]))
				return self::fail($failure, 'invalid-options');
		if(($responseMode !== self::RESPONSE_RAW) && ($responseMode !== self::RESPONSE_BODY))
			return self::fail($failure, 'invalid-response-mode');
		foreach(self::requiredFunctions() as $function)
			if(!function_exists($function))
				return self::fail($failure, 'transport-unavailable');
		$clock = false;
		foreach(self::clockFunctions() as $function)
			$clock = $clock || function_exists($function);
		if(!$clock)
			return self::fail($failure, 'transport-unavailable');
		$limit = self::responseLimit($options['maxResponseBytes'] ?? null);
		list($connectTimeout, $transferTimeout) = self::timeouts($options['connectTimeout'] ?? null,
			$options['transferTimeout'] ?? null);

		// fsockopen()'s rule, which both callers relied on: only a positive port
		// is appended, and any transport prefix in the host is kept as written.
		$address = ($port > 0) ? $host.':'.(int)$port : $host;
		$socket = static::openSocket($address, $errno, $connectError, $connectTimeout);
		if(!is_resource($socket))
			return self::fail($failure, 'connect-failed');
		try
		{
			if(!static::configureBlocking($socket, false))
				return self::fail($failure, 'socket-config-failed');

			$header = "CONTENT_LENGTH\x00".strlen($payload)."\x00CONTENT_TYPE\x00text/xml\x00"
				."SCGI\x001\x00UNTRUSTED_CONNECTION\x00".($trusted ? '0' : '1')."\x00";
			$prefix = strlen($header).':'.$header.',';
			$deadline = static::monotonicNow() + $transferTimeout;
			if(!self::writeAll($socket, $prefix, $payload, $deadline, $failure))
				return null;
			return self::readResponse($socket, $limit, $responseMode, $failure, $deadline,
				$options['temporaryDirectory'] ?? null);
		}
		finally
		{
			fclose($socket);
		}
	}

	/**
	 * The connect and transfer budgets, in seconds, for raw settings. An
	 * unusable connect timeout means 30 seconds. A null or unusable transfer
	 * budget means the larger of PHP's positive default_socket_timeout (or 60)
	 * and the connect timeout. Both are capped at MAX_TRANSFER_TIMEOUT.
	 */
	public static function timeouts($connectTimeout, $transferTimeout)
	{
		$connect = self::seconds($connectTimeout) ?? 30.0;
		$transfer = self::seconds($transferTimeout);
		if($transfer === null)
		{
			$default = function_exists('ini_get') ? self::seconds(ini_get('default_socket_timeout')) : null;
			$transfer = max($default ?? 60.0, $connect);
		}
		return array($connect, $transfer);
	}

	/** A timeout setting that means what it says: null or finite positive seconds. */
	public static function isValidTimeout($value)
	{
		return ($value === null) || (self::seconds($value) !== null);
	}

	public static function isValidResponseLimit($value)
	{
		if(is_string($value))
			$value = trim($value);
		return ($value === null) || (is_numeric($value) && ($value >= 1) &&
			($value <= self::MAX_RESPONSE_BYTES) && (floor((float)$value) === (float)$value));
	}

	/** A log line for a failure; a timeout names the budget that ran out. */
	public static function describeFailure($failure)
	{
		global $rpcTimeOut, $rpcTransferTimeOut;
		if(($failure !== 'read-timeout') && ($failure !== 'write-timeout'))
			return $failure;
		list(, $transfer) = self::timeouts($rpcTimeOut, $rpcTransferTimeOut);
		return $failure.' after the '.$transfer.'-second transfer budget; raise $rpcTransferTimeOut for slower replies';
	}

	private static function fail(&$failure, $code)
	{
		$failure = $code;
		return null;
	}

	protected static function openSocket($address, &$errno, &$error, $timeout)
	{
		return @stream_socket_client($address, $errno, $error, $timeout, STREAM_CLIENT_CONNECT);
	}

	protected static function configureBlocking($socket, $blocking)
	{
		return @stream_set_blocking($socket, $blocking);
	}

	protected static function writeBytes($socket, $bytes, $length)
	{
		return @fwrite($socket, $bytes, $length);
	}

	protected static function readBytes($socket, $length)
	{
		return @fread($socket, $length);
	}

	protected static function socketMetadata($socket)
	{
		return stream_get_meta_data($socket);
	}

	protected static function sleep($seconds)
	{
		usleep(max(1, (int)($seconds * 1000000)));
	}

	/** Waits up to $seconds for the socket to become ready; false when it cannot be watched. */
	protected static function waitForSocket($socket, $writing, $seconds)
	{
		if(!function_exists('stream_select'))
			return false;
		$read = $writing ? null : array($socket);
		$write = $writing ? array($socket) : null;
		$except = null;
		return @stream_select($read, $write, $except, 0, max(1, (int)($seconds * 1000000)));
	}

	protected static function createTemporaryPath($directory)
	{
		return @tempnam($directory, 'rutorrent-scgi-');
	}

	// Neither creates nor truncates: a name replaced by a link is never
	// turned into a new file, and an existing one is never emptied.
	protected static function openTemporaryPath($path)
	{
		return @fopen($path, 'r+b');
	}

	protected static function openTemporary($directory)
	{
		// Spill is optional: a host may disable filesystem functions entirely.
		foreach(array('tempnam', 'fopen', 'fstat', 'lstat', 'unlink', 'rewind', 'stream_get_contents',
			'sys_get_temp_dir', 'is_dir', 'is_writable', 'realpath', 'dirname') as $function)
			if(!function_exists($function))
				return false;
		// realpath('') is the working directory, under FPM the web root.
		$directory = @realpath((is_string($directory) && ($directory !== '')) ? $directory : sys_get_temp_dir());
		if(($directory === false) || !@is_dir($directory) || !@is_writable($directory))
			return false;
		// Without the sticky bit any user may rename or replace a name in a
		// world-writable directory, so, as getplugins.php warns, it is unsafe:
		// the response stays in memory.
		$permissions = @lstat($directory);
		if(($permissions === false) || ((($permissions['mode'] & 0002) !== 0) && (($permissions['mode'] & 01000) === 0)))
			return false;
		$path = static::createTemporaryPath($directory);
		if($path === false)
			return false;
		// tempnam() quietly creates the file in the system directory when it
		// cannot use the one asked for. The descriptor is used only for the
		// regular, empty, single-link file the name held when checked.
		$created = @lstat($path);
		$file = ((dirname($path) === $directory) && ($created !== false) && (($created['mode'] & 0170000) === 0100000))
			? static::openTemporaryPath($path) : false;
		$opened = is_resource($file) ? @fstat($file) : false;
		// No response bytes are written until the name is gone, even if the
		// worker is killed before PHP can close the descriptor.
		$unlinked = @unlink($path);
		if($unlinked && ($opened !== false) && ($opened['dev'] === $created['dev']) && ($opened['ino'] === $created['ino'])
			&& (($opened['mode'] & 0170000) === 0100000) && ($opened['nlink'] === 1) && ($opened['size'] === 0))
			return $file;
		if(is_resource($file))
			fclose($file);
		return false;
	}

	protected static function monotonicNow()
	{
		if(function_exists('hrtime'))
		{
			$value = hrtime();
			return ((float)$value[0]) + (((float)$value[1]) / 1000000000.0);
		}
		// hrtime can be explicitly disabled even on supported PHP versions;
		// send() refuses before any I/O when microtime is disabled too.
		return microtime(true);
	}

	// Finite positive seconds, capped; null for anything else.
	private static function seconds($value)
	{
		if(is_string($value))
			$value = trim($value);
		return (is_numeric($value) && is_finite((float)$value) && ($value > 0))
			? min((float)$value, (float)self::MAX_TRANSFER_TIMEOUT) : null;
	}

	// env_check reports invalid configuration; request polling stays quiet.
	private static function responseLimit($value)
	{
		if(($value !== null) && self::isValidResponseLimit($value))
			return (int)(is_string($value) ? trim($value) : $value);
		return self::MAX_RESPONSE_BYTES;
	}

	private static function writeAll($socket, $prefix, $payload, $deadline, &$failure)
	{
		$offset = 0;
		$wait = self::FIRST_PAUSE;
		$prefixLength = strlen($prefix);
		$length = $prefixLength + strlen($payload);
		while($offset < $length)
		{
			$remaining = $deadline - static::monotonicNow();
			if($remaining <= 0)
			{
				$failure = 'write-timeout';
				return false;
			}
			$offered = min(self::MAX_WRITE_BYTES, $length - $offset);
			// Coalesce the netstring prefix with the first payload bytes: two
			// small TCP writes otherwise wait for a Nagle/delayed ACK round trip.
			$slice = ($offset < $prefixLength)
				? substr($prefix, $offset).substr($payload, 0, $offered - ($prefixLength - $offset))
				: substr($payload, $offset - $prefixLength, $offered);
			$written = static::writeBytes($socket, $slice, $offered);
			$remaining = $deadline - static::monotonicNow();
			if($remaining <= 0)
			{
				$failure = 'write-timeout';
				return false;
			}
			if($written === false)
			{
				$failure = 'write-failed';
				return false;
			}
			if($written === 0)
				self::pause($socket, true, $remaining, $wait);
			else
			{
				$offset += $written;
				$wait = self::FIRST_PAUSE;
			}
		}
		return true;
	}

	// PHP's blocking stream timeout starts over after EINTR, so the socket
	// stays non-blocking and an idle exchange waits here; every caller checks
	// the deadline again. stream_select() ends the wait as soon as the socket
	// is ready. It may be disabled, and it fails on EINTR and, on PHP 8, for a
	// descriptor above FD_SETSIZE (PHP 7.4 then waits without watching it);
	// the wait is then a sleep, which returns early when interrupted.
	private static function pause($socket, $writing, $remaining, &$wait)
	{
		$seconds = min($wait, $remaining);
		$wait = min($wait * 2, self::LONGEST_PAUSE);
		if(static::waitForSocket($socket, $writing, $seconds) === false)
			static::sleep($seconds);
	}

	private static function readResponse($socket, $limit, $responseMode, &$failure, $deadline, $temporaryDirectory)
	{
		$received = '';
		while(($delimiter = strpos($received, "\r\n\r\n")) === false)
		{
			if(strlen($received) > self::MAX_HEADER_BYTES + 3)
				return self::fail($failure, 'headers-too-large');
			$chunk = self::readPart($socket, min(8192, self::MAX_HEADER_BYTES + 4 - strlen($received)), $deadline, $failure);
			if($chunk === null) return null;
			if(($chunk === false) || ($chunk === ''))
				return self::readFailure($socket, $failure,
					($received === '') ? 'closed-before-headers' : 'truncated-headers');
			$received .= $chunk;
		}
		$header = substr($received, 0, $delimiter);
		$declared = self::contentLength($header, $limit, $failure);
		if($declared === null)
			return null;
		$tail = substr($received, $delimiter + 4, $declared);
		$received = '';
		// A string that keeps growing is copied whenever it has to move, which
		// needs twice its size; a large response goes to a temporary file instead
		// and is held in memory once, when it is read back. Without a usable
		// temporary file it is collected in memory.
		$file = ($declared > self::MEMORY_RESPONSE_BYTES) ? static::openTemporary($temporaryDirectory) : false;
		if(!is_resource($file))
			$file = false;
		$stored = 0;
		$memory = '';
		try
		{
			$first = (($responseMode === self::RESPONSE_RAW) ? $header."\r\n\r\n" : '').$tail;
			if(!self::store($file, $stored, $memory, $first))
				return self::fail($failure, 'read-failed');
			$bodyBytes = strlen($tail);
			while($bodyBytes < $declared)
			{
				$chunk = self::readPart($socket, min(65536, $declared - $bodyBytes), $deadline, $failure);
				if($chunk === null) return null;
				if(($chunk === false) || ($chunk === ''))
					return self::readFailure($socket, $failure, 'truncated-body');
				if(!self::store($file, $stored, $memory, $chunk))
					return self::fail($failure, 'read-failed');
				$bodyBytes += strlen($chunk);
			}
			if($file === false)
				return $memory;
			$result = self::load($file, $stored);
			$file = false;
			return ($result === null) ? self::fail($failure, 'read-failed') : $result;
		}
		finally
		{
			if($file !== false)
				fclose($file);
		}
	}

	private static function readPart($socket, $length, $deadline, &$failure)
	{
		$wait = self::FIRST_PAUSE;
		while(true)
		{
			if(static::monotonicNow() >= $deadline)
				return self::fail($failure, 'read-timeout');
			$chunk = static::readBytes($socket, $length);
			$metadata = static::socketMetadata($socket);
			$remaining = $deadline - static::monotonicNow();
			if(!empty($metadata['timed_out']) || $remaining <= 0)
				return self::fail($failure, 'read-timeout');
			if(($chunk !== '') || !empty($metadata['eof']))
				return $chunk;
			self::pause($socket, false, $remaining, $wait);
		}
	}

	private static function store(&$file, &$stored, &$memory, $bytes)
	{
		if($file !== false)
		{
			$written = self::append($file, $bytes);
			$stored += $written;
			if($written === strlen($bytes))
				return true;
			// The file stopped taking bytes (a full disk, for example): continue
			// in memory from exactly what it holds.
			$memory = self::load($file, $stored);
			$file = false;
			if($memory === null)
				return false;
			$bytes = substr($bytes, $written);
		}
		$memory .= $bytes;
		return true;
	}

	/** Reads the temporary file back and closes it; null unless all $length bytes return. */
	private static function load($file, $length)
	{
		$bytes = @rewind($file) ? @stream_get_contents($file) : false;
		fclose($file);
		return (is_string($bytes) && (strlen($bytes) === $length)) ? $bytes : null;
	}

	/** Returns how many leading bytes of $bytes the file took. */
	private static function append($file, $bytes)
	{
		$written = @fwrite($file, $bytes);
		return ($written === false) ? 0 : $written;
	}

	private static function readFailure($socket, &$failure, $closed)
	{
		$meta = static::socketMetadata($socket);
		if(isset($meta['eof']) && $meta['eof'])
			return self::fail($failure, $closed);
		return self::fail($failure, 'read-failed');
	}

	private static function contentLength($header, $limit, &$failure)
	{
		$count = 0;
		$value = null;
		foreach(explode("\r\n", $header) as $line)
		{
			$colon = strpos($line, ':');
			$fieldValue = ($colon === false) ? '' : substr($line, $colon + 1);
			if(($colon === false) || !preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', substr($line, 0, $colon)) ||
				!preg_match('/^[\x09\x20-\x7E\x80-\xFF]*$/D', $fieldValue))
				return self::fail($failure, 'malformed-header');
			if(strcasecmp(substr($line, 0, $colon), 'Content-Length') === 0)
			{
				$count++;
				$value = trim($fieldValue, " \t");
			}
		}
		if($count === 0)
			return self::fail($failure, 'missing-content-length');
		if($count > 1)
			return self::fail($failure, 'duplicate-content-length');
		if(!preg_match('/^[0-9]+$/D', $value))
			return self::fail($failure, 'malformed-content-length');
		$value = ltrim($value, '0');
		if($value === '')
			return self::fail($failure, 'zero-content-length');
		$limitString = (string)$limit;
		if((strlen($value) > strlen($limitString)) ||
			((strlen($value) === strlen($limitString)) && (strcmp($value, $limitString) > 0)))
			return self::fail($failure, 'response-too-large');
		return (int)$value;
	}
}
