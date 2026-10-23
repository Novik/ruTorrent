<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/TestTree.php');

class RtorrentProbeFixtureTest extends TestCase
{
	private function probe($disabled, $blockDriver = false, $partialWrite = false)
	{
		$root = sys_get_temp_dir() . '/probe-cleanup-test-' . uniqid('', true);
		// Choose the umask rather than inherit the runner's: under `umask 0177`
		// this tree and the one the child creates would not be traversable.
		$mask = umask(0077);
		mkdir($root, 0700);
		// Keep a reference to the acquired process across the write exception:
		// garbage collection must not hide a missing explicit stop().
		$source = ($partialWrite ? 'function file_put_contents($path, $source) { return strlen($source) - 1; }' : '')
			. 'require ' . var_export(__DIR__ . '/RtorrentProbeFixture.php', true) . ';'
			. '$base = sys_get_temp_dir()."/rutorrent-probe-fixture-".getmypid();'
			. ($blockDriver ? 'mkdir($base."/probe.php", 0700, true); set_error_handler(function($severity, $message) { if (strpos($message, "file_put_contents(") === false) return false; $GLOBALS["ownedProcesses"] = get_resources("process"); throw new RuntimeException($message); });' : '')
			. ($partialWrite ? 'mkdir($base."/settings", 0700, true); $f=fopen($base."/settings/rtorrent.dat", "w"); fwrite($f, "stale cache"); fclose($f);' : '')
			. 'try { $ok = RtorrentProbeFixture::warmSettingsCache(sys_get_temp_dir()."/profile");'
			. 'echo json_encode(array("ok"=>$ok, "cache"=>is_file(sys_get_temp_dir()."/profile/settings/rtorrent.dat"))); }'
			. 'catch(Throwable $error) { echo json_encode(array("error"=>$error->getMessage())); }'
			. 'echo "\n", $base, "\n", count(get_resources("process"));';
		try
		{
			// Xdebug (loaded by hosted PHP 7.4) adds call stacks and notices to the
			// diagnostics compared below; UnreadableDirectoryTest turns it off the same way.
			$args = array_merge(PhpProcess::sameIni(), array('-d', 'display_errors=stderr', '-d', 'xdebug.mode=off', '-d', 'disable_functions=' . $disabled,
				'-r', $source));
			$run = PhpProcess::run($args, array('env' => array('TMPDIR' => $root)));
			$lines = explode("\n", trim($run['out']));
			$finished = !$run['timedOut'] && $run['code'] === 0;
			$this->assertTrue($finished, 'the probe child finishes' . ($finished ? '' : ': ' . $run['err']));
			// Startup diagnostics ("in Unknown on line 0") belong to the PHP build,
			// not to the probe, so only the other lines have to match the injection.
			$unexpected = implode("\n", preg_grep('/ in Unknown on line 0\s*$/',
				explode("\n", $run['err']), PREG_GREP_INVERT));
			$expectedWarning = PHP_VERSION_ID < 80000 && $disabled !== '';
			if ($expectedWarning) {
				$file = __DIR__ . ($disabled === 'proc_open' ? '/FakeRtorrentDaemon.php' : '/RtorrentProbeFixture.php');
				$unexpected = preg_replace('/^(?:PHP )?Warning: ' . preg_quote($disabled, '/')
					. '\(\) has been disabled for security reasons in ' . preg_quote($file, '/')
					. ' on line [0-9]+\r?$/m', '', $unexpected, -1, $warnings);
				if ($disabled === 'proc_open') {
					$unexpected = preg_replace('/^(?:PHP )?Notice: Undefined variable: pipes in '
						. preg_quote($file, '/') . ' on line [0-9]+\r?$/m', '', $unexpected);
				}
			} else {
				$warnings = 0;
			}
			$diagnosticsMatch = trim($unexpected) === '' && $warnings === ($expectedWarning ? 1 : 0);
			$this->assertTrue($diagnosticsMatch, 'child diagnostics match the injected failure'
				. ($diagnosticsMatch ? '' : ': ' . $run['err']));
			$this->assertTrue(isset($lines[1]) && strpos($lines[1], $root . '/rutorrent-probe-fixture-') === 0,
				'the child identifies its exact owned tree');
			$this->assertTrue(isset($lines[1]) && !file_exists($lines[1]), 'the exact probe tree is removed');
			$this->assertTrue(isset($lines[2]) && $lines[2] === '0', 'no daemon process handle remains owned');
			return json_decode($lines[0], true);
		}
		finally
		{
			TestTree::remove($root);
			umask($mask);
		}
	}

	public function testDisabledProcessCreationRemovesTheSetupTree()
	{
		$result = $this->probe('proc_open');
		$this->assertTrue(isset($result['error']) && strpos($result['error'],
			PHP_VERSION_ID >= 80000 ? 'proc_open' : 'cannot start a daemon process') !== false,
			'the original constructor refusal is preserved');
	}

	public function testFailureAfterDaemonOwnershipStopsTheDaemon()
	{
		$result = $this->probe('', true);
		$this->assertTrue(isset($result['error']) && strpos($result['error'], 'file_put_contents(') !== false,
			'a driver-write exception refuses without manufacturing a cache');
	}

	public function testFailedDriverWriteRefusesAndStopsTheDaemon()
	{
		$result = $this->probe('file_put_contents');
		$this->assertTrue(isset($result['error']) || $result['ok'] === false,
			'a failed driver write cannot manufacture a cache');
	}

	public function testPartialDriverWriteRefusesWithoutUsingAnExistingCache()
	{
		// PHP 8 removes disabled functions, allowing a byte-count fault here.
		// PHP 7.4's real disabled-function NULL path is covered separately.
		if (PHP_VERSION_ID < 80000) return;
		$result = $this->probe('file_put_contents', false, true);
		$this->assertTrue(isset($result['ok']) && $result['ok'] === false,
			'a partial driver write refuses before executing the driver');
	}

	public function testSuccessfulProbeWarmsTheSettingsCache()
	{
		$result = $this->probe('');
		$this->assertTrue(isset($result['ok']) && $result['ok'] && $result['cache'],
			'the real settings handshake produces a warm cache');
	}
}
