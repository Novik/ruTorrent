<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * One safe-parameter policy, for every entry point.
 *
 * conf/xmlrpc_proxy.php states which command names a caller may attach to a
 * load.* or to a multicall. A second $XMLRPCProxySafeParams elsewhere in the
 * shipped tree replaces it for whichever entry point loads that file, and
 * nothing about two lists says they are meant to agree: they drift, and a
 * client that works through one entry point is refused through the other.
 *
 * A shipped file may still restate the policy. This asserts only that it does
 * not restate it differently.
 */
class XMLRPCProxyPolicyParityTest extends TestCase
{
	private $root = null;
	private $reference = null;

	public function setUp()
	{
		$this->root = realpath(__DIR__ . '/../..');
		$this->reference = $this->safeParamsOf($this->root . '/conf/xmlrpc_proxy.php');
	}

	/**
	 * The list a policy file defines on its own, or null when it defines none.
	 * Evaluated inside a closure so one file's other variables cannot reach the
	 * next.
	 */
	private function safeParamsOf($file)
	{
		$read = function ($f) {
			$XMLRPCProxySafeParams = null;
			require($f);
			return $XMLRPCProxySafeParams;
		};
		return $read($file);
	}

	/** Every shipped conf file that assigns the list, other than the reference. */
	private function otherDefiners()
	{
		$found = array();
		foreach (array('/conf', '/plugins') as $sub) {
			$dir = $this->root . $sub;
			if (!is_dir($dir)) {
				continue;
			}
			$walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
				$dir, FilesystemIterator::SKIP_DOTS));
			foreach ($walk as $file) {
				$path = $file->getPathname();
				if (!preg_match('`/(conf|conf\.local|xmlrpc_proxy)\.php$`', $path)) {
					continue;
				}
				if ($path === $this->root . '/conf/xmlrpc_proxy.php') {
					continue;
				}
				$src = @file_get_contents($path);
				if (($src !== false) && preg_match('`\$XMLRPCProxySafeParams\s*=`', $src)) {
					$found[] = $path;
				}
			}
		}
		sort($found);
		return $found;
	}

	public function testTheSharedPolicyCarriesTheViewActions()
	{
		$this->assertTrue(is_array($this->reference) && (count($this->reference) > 0),
			'conf/xmlrpc_proxy.php defines $XMLRPCProxySafeParams');
		// A client sends these as a multicall over a view to stop or resume
		// everything. Left out, the multicall carries a command this side does
		// not rebuild, so it goes to rtorrent untrusted -- which refuses d.stop
		// and d.start to an untrusted caller.
		foreach (array('d.open', 'd.close', 'd.start', 'd.stop') as $action) {
			$this->assertTrue(in_array($action, (array)$this->reference, true),
				"the shared policy allows {$action}");
		}
	}

	public function testEveryOtherDefinitionOfThePolicyAgreesWithIt()
	{
		$reference = (array)$this->reference;
		sort($reference);
		foreach ($this->otherDefiners() as $path) {
			$theirs = (array)$this->safeParamsOf($path);
			sort($theirs);
			$name = substr($path, strlen($this->root) + 1);
			$this->assertEquals(json_encode($reference), json_encode($theirs),
				"{$name} states the same safe-parameter policy as conf/xmlrpc_proxy.php");
		}
	}
}
