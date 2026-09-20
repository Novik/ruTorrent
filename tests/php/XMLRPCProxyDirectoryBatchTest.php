<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * Resolve as much of a path as exists, the way rpc2_resolve_path() and
 * httprpcResolvePath() do, so the boundary is tested through the resolver the
 * production callers install rather than a lexical check on its own.
 */
function xmlrpcProxyDirectoryBatchResolve($path)
{
	$real = @realpath($path);
	if($real !== false)
		return $real;
	$parts = explode('/', trim($path, '/'));
	$tail = array();
	while(count($parts) > 0)
	{
		array_unshift($tail, array_pop($parts));
		$base = '/' . implode('/', $parts);
		$real = @realpath(($base === '') ? '/' : $base);
		if($real !== false)
			return rtrim($real, '/') . '/' . implode('/', $tail);
	}
	return '';
}

/**
 * $directoryCommands name the directory rtorrent writes a download into, and
 * the caller supplies the torrent, so they name the file too. They are held to
 * the boundary the caller states, in rebuildSafeLoadParam().
 *
 * That holding is a refusal on the single-call path, where a parameter the
 * boundary declined is stripped out of the rebuilt call. On the batched paths
 * nothing is stripped: a parameter that is not rebuilt sends the caller's own
 * bytes on instead, untrusted, and untrusted is a refusal only from rtorrent
 * 0.16.10. env_check.php supports 0.9.8, which is below it, so there the
 * boundary was stated, applied, and then forwarded past.
 *
 * Enumerated from $directoryCommands rather than from the two names written
 * here, so a third confined command is covered on the commit that adds it.
 */
class XMLRPCProxyDirectoryBatchTest extends TestCase
{
	private $root;
	private $inside;
	private $outside = '/etc/ru-directory-batch-outside';

	private $safe = array(
		'd.custom1.set', 'd.custom2.set', 'd.custom3.set', 'd.custom4.set',
		'd.custom5.set', 'd.custom.set', 'd.directory.set', 'd.directory_base.set',
		'd.priority.set', 'd.throttle_name.set', 'd.views.push_back_unique',
		'd.delete_tied', 'd.open', 'd.close', 'd.start', 'd.stop',
	);

	public function setUp()
	{
		$this->root = sys_get_temp_dir() . '/ru-directory-batch-root';
		$this->inside = $this->root . '/ok';
		@mkdir($this->root, 0700, true);
	}

	public function tearDown()
	{
		@rmdir($this->root);
	}

	/**
	 * The commands the class itself says it confines.
	 */
	private function confinedCommands()
	{
		$property = new ReflectionProperty('XMLRPCProxy', 'directoryCommands');
		$property->setAccessible(true);
		return $property->getValue();
	}

	private function decide($xml, $withBoundary = true)
	{
		$options = $withBoundary
			? array('directory' => array(
				'root'    => $this->root,
				'resolve' => 'xmlrpcProxyDirectoryBatchResolve'))
			: array();
		return XMLRPCProxy::decide($xml, 'sanitize', $this->safe, false, $options);
	}

	private function value($s)
	{
		return '<value><string>' . htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8')
			. '</string></value>';
	}

	private function multicall($command)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>d.multicall</methodName><params>';
		foreach(array('main', '', $command) as $p)
			$xml .= '<param>' . $this->value($p) . '</param>';
		return $xml . '</params></methodCall>';
	}

	private function systemMulticall($command)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data><value><struct>'
			. '<member><name>methodName</name><value><string>load.raw_start</string></value></member>'
			. '<member><name>params</name><value><array><data>';
		foreach(array('', 'TORRENTBYTES', $command) as $p)
			$xml .= $this->value($p);
		return $xml . '</data></array></value></member></struct></value></data></array>'
			. '</value></param></params></methodCall>';
	}

	public function testTheConfinedListIsReadable()
	{
		$commands = $this->confinedCommands();
		$this->assertTrue(count($commands) > 0,
			'XMLRPCProxy names the commands it confines (' . implode(', ', $commands) . ')');
	}

	/**
	 * The boundary holds on a d.multicall, not only on a single load.
	 */
	public function testAMulticallCannotCarryADirectoryOutsideTheBoundary()
	{
		$leaked = array();
		foreach($this->confinedCommands() as $command)
		{
			$decision = $this->decide($this->multicall($command . '=' . $this->outside));
			if(($decision['action'] !== 'reject') &&
				(strpos($decision['payload'], $this->outside) !== false))
				$leaked[] = $command;
		}
		$this->assertTrue(count($leaked) === 0,
			'a d.multicall naming a directory outside the boundary is refused'
			. (count($leaked) ? ', forwarded: ' . implode(', ', $leaked) : ''));
	}

	/**
	 * And on a system.multicall, where the commands sit inside a member rather
	 * than in the call's own parameters.
	 */
	public function testASystemMulticallCannotCarryADirectoryOutsideTheBoundary()
	{
		$leaked = array();
		foreach($this->confinedCommands() as $command)
		{
			$decision = $this->decide($this->systemMulticall($command . '=' . $this->outside));
			if(($decision['action'] !== 'reject') &&
				(strpos($decision['payload'], $this->outside) !== false))
				$leaked[] = $command;
		}
		$this->assertTrue(count($leaked) === 0,
			'a system.multicall member naming a directory outside the boundary is refused'
			. (count($leaked) ? ', forwarded: ' . implode(', ', $leaked) : ''));
	}

	/**
	 * Control: a directory the caller is entitled to is not refused, so a green
	 * above is the boundary and not a batch that fails whatever it carries.
	 */
	public function testADirectoryInsideTheBoundaryStillWorks()
	{
		foreach($this->confinedCommands() as $command)
		{
			$decision = $this->decide($this->multicall($command . '=' . $this->inside));
			$this->assertTrue($decision['action'] === 'send',
				$command . ' inside the boundary is still sent');
			$this->assertTrue($decision['trusted'] === true,
				$command . ' inside the boundary is rebuilt and sent trusted');
		}
	}

	/**
	 * Control: a caller that states no boundary is not policed here, which is
	 * what every caller had before the confinement existed. The httprpc door
	 * reaches decide() without one.
	 */
	public function testNoBoundaryStatedIsStillNotPoliced()
	{
		foreach($this->confinedCommands() as $command)
		{
			$decision = $this->decide($this->multicall($command . '=' . $this->outside), false);
			$this->assertTrue($decision['action'] === 'send',
				$command . ' is not refused when no boundary was stated');
		}
	}
}
