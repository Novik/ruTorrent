<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * These tests exercise the two HTTP doors, not a reimplementation of them.
 * Each case copies the production entrypoint and proxy byte-for-byte, then
 * stubs only their dependencies below that boundary in a fresh PHP server.
 */
class XMLRPCProxyEntrypointTest extends TestCase
{
	private $sourceRoot;

	public function setUp()
	{
		$this->sourceRoot = realpath(__DIR__ . '/../..');
		if($this->sourceRoot === false)
			throw new Exception('could not locate the production source root');
	}

	public function testHttprpcUnreadableInputReturnsClassified400()
	{
		$result = $this->runEntrypoint('action', 'unreadable', true);
		$this->assertHttp($result, '400 Bad Request', 'text/html; charset=UTF-8',
			'Could not read XMLRPC request.');
		$this->assertState($result['state'], 0, 1,
			array('xmlrpc-proxy: could not read request body'));
	}

	public function testHttprpcEmptyInputReturnsClassified400()
	{
		$result = $this->runEntrypoint('action', '', true);
		$this->assertHttp($result, '400 Bad Request', 'text/html; charset=UTF-8', 'Empty XMLRPC request.');
		$this->assertState($result['state'], 0, 1,
			array('xmlrpc-proxy: empty request body'));
	}

	public function testHttprpcInputFailuresDoNotLogWhenDisabled()
	{
		$unreadable = $this->runEntrypoint('action', 'unreadable', false);
		$this->assertHttp($unreadable, '400 Bad Request', 'text/html; charset=UTF-8',
			'Could not read XMLRPC request.');
		$this->assertState($unreadable['state'], 0, 1, array());

		$empty = $this->runEntrypoint('action', '', false);
		$this->assertHttp($empty, '400 Bad Request', 'text/html; charset=UTF-8', 'Empty XMLRPC request.');
		$this->assertState($empty['state'], 0, 1, array());
	}

	public function testHttprpcRefusalReturnsNamed403AndStops()
	{
		$result = $this->runEntrypoint('action', $this->deniedXml(), true);
		$this->assertHttp($result, '403 Forbidden', 'text/xml; charset=UTF-8');
		$this->assertTrue(strpos($result['body'], '<i4>-501</i4>') !== false,
			'httprpc refusal returns the XMLRPC -501 envelope');
		$this->assertTrue(strpos($result['body'],
			"The command 'execute.capture' was rejected by this server.") !== false,
			'httprpc refusal names the refused command');
		$this->assertCounts($result['state'], 0, 1);
	}

	public function testHttprpcTransportFailureReturnsNeutral500AndStops()
	{
		$result = $this->runEntrypoint('action', $this->allowedXml(), true, 'false');
		$this->assertHttp($result, '500 Server Error', 'text/html; charset=UTF-8',
			'Could not complete the rTorrent XMLRPC request.');
		$this->assertCounts($result['state'], 1, 1);
		$this->assertEquals($this->allowedXml(), $result['state']['payload'],
			'httprpc sends the admitted XML payload');
		$this->assertTrue($result['state']['trusted'] === false,
			'httprpc sends an ordinary admitted method as untrusted');
	}

	public function testRpc2UnreadableInputReturnsClassified400()
	{
		$result = $this->runEntrypoint('rpc2', 'unreadable', true);
		$this->assertHttp($result, '400 Bad Request', 'text/xml;charset=UTF-8');
		$this->assertTrue(strpos($result['body'], 'Could not read XMLRPC request.') !== false,
			'rpc2 unreadable input uses the exact client message');
		$this->assertTrue(strpos($result['body'], '<i4>-501</i4>') !== false,
			'rpc2 unreadable input returns the XMLRPC -501 envelope');
		$this->assertRpc2Log($result, 'could not read request body',
			'rpc2 logs the classified unreadable-input reason');
	}

	public function testRpc2EmptyInputReturnsClassified400()
	{
		$result = $this->runEntrypoint('rpc2', '', true);
		$this->assertHttp($result, '400 Bad Request', 'text/xml;charset=UTF-8');
		$this->assertTrue(strpos($result['body'], 'Empty XMLRPC request.') !== false,
			'rpc2 empty input uses the exact client message');
		$this->assertTrue(strpos($result['body'], 'post_max_size') === false,
			'rpc2 does not speculate about post_max_size in the client fault');
		$this->assertRpc2Log($result, 'empty request body',
			'rpc2 logs the classified empty-input reason');
	}

	public function testRpc2RefusalRendersTheSharedNamedMessage()
	{
		$result = $this->runEntrypoint('rpc2', $this->deniedXml(), true);
		$this->assertHttp($result, '403 Forbidden', 'text/xml;charset=UTF-8');
		$this->assertTrue(strpos($result['body'],
			"The command 'execute.capture' was rejected by this server.") !== false,
			'rpc2 refusal names the refused command');
		$this->assertTrue(strpos($result['body'], '<i4>-501</i4>') !== false,
			'rpc2 refusal returns the XMLRPC -501 envelope');
	}

	public function testBothDoorsDecideTheSameTrustForTheSameRequest()
	{
		$httprpc = $this->runEntrypoint('action', $this->viewActionXml(), true);
		$this->assertEquals(true, $httprpc['state']['trusted'],
			'httprpc sends a view-action multicall on a trusted connection');
		$this->assertTrue(in_array('xmlrpc-proxy: trusted: d.multicall2 (3 params)',
			$httprpc['state']['logs'], true),
			'httprpc rebuilt the multicall from the shared policy');

		$rpc2 = $this->runEntrypoint('rpc2', $this->viewActionXml(), true);
		$this->assertRpc2Log($rpc2, 'trusted: d.multicall2 (3 params)',
			'rpc2 reaches that same decision on that same request');
		$this->assertTrue(strpos($rpc2['rpc2logs'], 'untrusted') === false,
			'and neither door falls back to forwarding it untrusted');
	}

	public function testHttprpcNamesAMissingPolicyRatherThanStrippingInSilence()
	{
		$result = $this->runEntrypoint('action', $this->viewActionXml(), true, 'success', 'none');
		$this->assertTrue(in_array('xmlrpc-proxy: no $XMLRPCProxySafeParams is defined in '
			. 'conf/xmlrpc_proxy.php or plugins/httprpc/conf.php',
			$result['state']['logs'], true),
			'a tree with no policy says so rather than letting it read as a client fault');
		$this->assertEquals(false, $result['state']['trusted'],
			'with no policy every command parameter is stripped and the call goes untrusted');
	}

	/**
	 * "Stop all": what a client sends to act on a whole view. rtorrent refuses
	 * d.stop to an untrusted caller, so a door that forwards this untrusted
	 * answers a fault where the other door succeeds.
	 */
	private function viewActionXml()
	{
		return '<?xml version="1.0"?><methodCall><methodName>d.multicall2</methodName>'
			. '<params><param><value><string></string></value></param>'
			. '<param><value><string>default</string></value></param>'
			. '<param><value><string>d.stop=</string></value></param>'
			. '</params></methodCall>';
	}

	private function deniedXml()
	{
		return '<?xml version="1.0"?><methodCall><methodName>execute.capture</methodName>'
			. '<params><param><value><string></string></value></param></params></methodCall>';
	}

	private function allowedXml()
	{
		return '<?xml version="1.0"?><methodCall><methodName>system.client_version</methodName>'
			. '<params></params></methodCall>';
	}

	private function runEntrypoint($door, $body, $logging, $send = 'success', $policy = 'shipped')
	{
		$tree = sys_get_temp_dir() . '/rutorrent-entrypoint-' . uniqid('', true);
		$process = null;
		try
		{
			if(!mkdir($tree, 0700, true) && !is_dir($tree))
				throw new Exception('could not create entrypoint fixture tree');
			$this->copyProductionTree($tree, $policy);
			$state = $tree . '/state.json';
			file_put_contents($state, json_encode(array(
				'sends' => 0, 'responses' => 0, 'logs' => array(),
				'payload' => null, 'trusted' => null,
			)));
			$this->writeStubs($tree);

			$port = $this->reservePort();
			$environment = array_merge($_ENV, array(
				'XMLRPC_ENTRYPOINT_STATE' => $state,
				'XMLRPC_ENTRYPOINT_LOGGING' => $logging ? '1' : '0',
				'XMLRPC_ENTRYPOINT_SEND' => $send,
				'XMLRPC_ENTRYPOINT_UNREADABLE' => ($body === 'unreadable') ? '1' : '0',
			));
			$command = escapeshellarg(PHP_BINARY)
				. ' -d auto_prepend_file=' . escapeshellarg($tree . '/prepend.php')
				. ' -d display_errors=0'
				. ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($tree);
			$process = proc_open($command, array(
				0 => array('pipe', 'r'),
				1 => array('file', $tree . '/server.out', 'a'),
				2 => array('file', $tree . '/server.err', 'a'),
			), $pipes, $tree, $environment);
			if(!is_resource($process))
				throw new Exception('could not start copied PHP entrypoint server');
			fclose($pipes[0]);
			$this->waitForServer($port, $process, $tree . '/server.err');
			$response = $this->rawPost($port, ($door === 'action')
				? '/plugins/httprpc/action.php' : '/rpc2.php', ($body === 'unreadable') ? '' : $body);
			$decoded = json_decode(file_get_contents($state), true);
			if(!is_array($decoded))
				throw new Exception('copied entrypoint did not write readable state');
			$response['state'] = $decoded;
			$response['rpc2logs'] = is_file($tree . '/rpc2.log')
				? file_get_contents($tree . '/rpc2.log') : '';
			return $response;
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

	private function copyProductionTree($tree, $policy = 'shipped')
	{
		$files = array(
			'plugins/httprpc/action.php',
			'php/xmlrpc_proxy.php',
			'rpc2.php',
		);
		// The shipped policy files, so that what a door decides here is what it
		// decides on an install rather than what this fixture invented. "none"
		// copies neither, which is a tree missing its configuration.
		if($policy === 'shipped')
		{
			$files[] = 'conf/xmlrpc_proxy.php';
			$files[] = 'plugins/httprpc/conf.php';
		}
		foreach($files as $relative)
		{
			$source = $this->sourceRoot . '/' . $relative;
			$target = $tree . '/' . $relative;
			if(!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true))
				throw new Exception('could not create copied source directory for ' . $relative);
			if(!copy($source, $target) || (hash_file('sha256', $source) !== hash_file('sha256', $target)))
				throw new Exception('could not byte-copy production source ' . $relative);
		}
	}

	private function writeStubs($tree)
	{
		if(!is_dir($tree . '/conf'))
			mkdir($tree . '/conf', 0700, true);
		file_put_contents($tree . '/php/xmlrpc.php', <<<'PHP'
<?php
function entrypoint_state($key, $value = null)
{
	$path = getenv('XMLRPC_ENTRYPOINT_STATE');
	$state = json_decode(file_get_contents($path), true);
	if(!is_array($state))
		$state = array('sends' => 0, 'responses' => 0, 'logs' => array(),
			'payload' => null, 'trusted' => null);
	if($key === 'log')
		$state['logs'][] = $value;
	elseif($key === 'send')
	{
		$state['sends']++;
		$state['payload'] = $value[0];
		$state['trusted'] = $value[1];
	}
	elseif($key === 'response')
		$state['responses']++;
	file_put_contents($path, json_encode($state));
}
class FileUtil
{
	public static function getPluginConf($plugin)
	{
		// Production evaluates the plugin's own conf file here. The logging
		// setting is appended after it, which is where a deployment's own
		// override lands too.
		$conf = dirname(__FILE__) . '/../plugins/' . $plugin . '/conf.php';
		return (is_file($conf) ? 'require("' . $conf . '");' : '')
			. '$XMLRPCProxyLog = '
			. ((getenv('XMLRPC_ENTRYPOINT_LOGGING') === '1') ? 'true' : 'false') . ';';
	}
	public static function toLog($message) { entrypoint_state('log', $message); }
}
class rXMLRPCRequest
{
	public static function send($payload, $trusted)
	{
		entrypoint_state('send', array($payload, $trusted));
		return (getenv('XMLRPC_ENTRYPOINT_SEND') === 'false') ? false : '';
	}
}
class CachedEcho
{
	public static function send($body, $type)
	{
		header('Content-Type: ' . $type . '; charset=UTF-8');
		entrypoint_state('response');
		echo $body;
		return;
	}
}
class JSON { public static function safeEncode($value) { return json_encode($value); } }
PHP
);
		file_put_contents($tree . '/plugins/httprpc/rpccache.php', "<?php\n");
		file_put_contents($tree . '/conf/config.php', <<<'PHP'
<?php
// A real directory rather than "/": the shipped policy leaves
// $XMLRPCProxyAllowRootDirectory false, and rpc2.php refuses to serve at all
// while the boundary is one that confines nothing.
$topDirectory = realpath(dirname(__FILE__) . '/..');
$log_file = dirname(__FILE__) . '/../rpc2.log';
$scgi_host = '127.0.0.1';
$scgi_port = 1;
PHP
);
		file_put_contents($tree . '/prepend.php', <<<'PHP'
<?php
$_SERVER['RUTORRENT_XMLRPC_ENDPOINT'] = 'on';
if(getenv('XMLRPC_ENTRYPOINT_UNREADABLE') === '1')
{
	class EntrypointUnreadableInput
	{
		public $context;
		public function stream_open($path, $mode, $options, &$openedPath) { return false; }
	}
	stream_wrapper_unregister('php');
	stream_wrapper_register('php', 'EntrypointUnreadableInput');
}
PHP
);
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
				throw new Exception('copied PHP entrypoint server exited: ' . @file_get_contents($errorFile));
			usleep(25000);
		}
		throw new Exception('copied PHP entrypoint server did not start');
	}

	private function rawPost($port, $path, $body)
	{
		$socket = @fsockopen('127.0.0.1', $port, $errno, $error, 2);
		if($socket === false)
			throw new Exception('could not connect raw HTTP client: ' . $error);
		$request = "POST " . $path . " HTTP/1.1\r\nHost: 127.0.0.1\r\n"
			. "Content-Type: text/xml\r\nContent-Length: " . strlen($body)
			. "\r\nConnection: close\r\n\r\n" . $body;
		if(fwrite($socket, $request) === false)
			throw new Exception('could not write raw HTTP request');
		$raw = stream_get_contents($socket);
		fclose($socket);
		if($raw === false || strpos($raw, "\r\n\r\n") === false)
			throw new Exception('copied entrypoint returned no complete HTTP response');
		list($headerBlock, $responseBody) = explode("\r\n\r\n", $raw, 2);
		$headers = explode("\r\n", $headerBlock);
		$status = array_shift($headers);
		$values = array();
		foreach($headers as $header)
		{
			$position = strpos($header, ':');
			if($position !== false)
				$values[strtolower(trim(substr($header, 0, $position)))] = trim(substr($header, $position + 1));
		}
		return array('status' => $status, 'headers' => $values, 'body' => $responseBody);
	}

	private function assertHttp($result, $status, $type, $body = null)
	{
		$this->assertTrue(strpos($result['status'], $status) !== false,
			'HTTP status is ' . $status);
		$this->assertEquals($type, isset($result['headers']['content-type'])
			? $result['headers']['content-type'] : null, 'HTTP content type is ' . $type);
		if($body !== null)
			$this->assertEquals($body, $result['body'], 'HTTP body is exact');
	}

	private function assertState($state, $sends, $responses, $logs)
	{
		$this->assertCounts($state, $sends, $responses);
		$this->assertEquals($logs, $state['logs'], 'recorded log lines are exact');
	}

	private function assertCounts($state, $sends, $responses)
	{
		$this->assertEquals($sends, $state['sends'], 'transport send count is exact');
		$this->assertEquals($responses, $state['responses'], 'CachedEcho response count is exact');
	}

	private function assertRpc2Log($result, $needle, $message)
	{
		$this->assertTrue(strpos($result['rpc2logs'], $needle) !== false, $message);
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
