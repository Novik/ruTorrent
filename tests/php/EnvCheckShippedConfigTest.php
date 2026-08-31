<?php

require_once(__DIR__ . '/TestCase.php');

// Only the class; the constant stops the checker from running its live probing.
define('RUTORRENT_REQUIREMENTS_LIB', true);
require_once(__DIR__ . '/../../env_check.php');

/**
 * env_check.php exists to tell an installer whether their configuration will
 * work. Nothing checked whether the configuration we ship passes it -- so a
 * default could be changed to something the checker rejects, and the first to
 * find out would be somebody installing ruTorrent for the first time.
 *
 * Two levels. The values themselves are asserted against the checker's own
 * validators, which needs no environment at all. Then the checker is run as it
 * ships, on the tree as it ships, and must report every required check passing.
 */
class EnvCheckShippedConfigTest extends TestCase
{
	private $root;

	public function setUp()
	{
		$this->root = dirname(dirname(__DIR__));
	}

	/** The shipped config read the way env_check reads it. */
	private function shippedConfig()
	{
		// The defaults are what an installer gets; an environment left over
		// from another test must not stand in for them.
		$saved = $_ENV;
		foreach (array('RU_LOG_FILE', 'RU_TOP_DIR', 'RU_SCGI_PORT', 'RU_SCGI_HOST',
			'RU_TEMP_DIRECTORY', 'RU_PROFILE_MASK', 'RU_PROFILE_PATH') as $key) {
			unset($_ENV[$key]);
		}
		include($this->root . '/conf/config.php');
		$_ENV = $saved;

		return array(
			'log_file' => $log_file,
			'topDirectory' => $topDirectory,
			'XMLRPCMountPoint' => $XMLRPCMountPoint,
			'scgi_host' => $scgi_host,
			'scgi_port' => $scgi_port,
			'profileMask' => $profileMask,
		);
	}

	public function testTheShippedLogFileIsOneTheCheckerAccepts()
	{
		$cfg = $this->shippedConfig();
		$this->assertTrue(Requirements::logFilePathValid($cfg['log_file']),
			'the shipped $log_file passes the rule env_check enforces: ' . var_export($cfg['log_file'], true));
	}

	public function testTheShippedMountPointIsOneTheCheckerAccepts()
	{
		$cfg = $this->shippedConfig();
		$this->assertTrue(Requirements::xmlrpcMountPointValid($cfg['XMLRPCMountPoint']),
			'the shipped $XMLRPCMountPoint is valid: ' . var_export($cfg['XMLRPCMountPoint'], true));
	}

	public function testTheShippedScgiAddressIsConfigured()
	{
		$cfg = $this->shippedConfig();
		$this->assertTrue(Requirements::scgiConfigured($cfg['scgi_host'], $cfg['scgi_port']),
			'the shipped SCGI address is one the checker recognises: '
				. Requirements::scgiLabel($cfg['scgi_host'], $cfg['scgi_port']));
	}

	public function testTheShippedTopDirectoryIsAbsolute()
	{
		$cfg = $this->shippedConfig();
		$this->assertTrue(Requirements::looksAbsolute($cfg['topDirectory']),
			'the shipped $topDirectory is an absolute path: ' . var_export($cfg['topDirectory'], true));
	}

	public function testTheShippedProfileMaskIsAFileMode()
	{
		$cfg = $this->shippedConfig();
		$this->assertTrue(is_int($cfg['profileMask']),
			'the shipped $profileMask reaches chmod as an integer');
		$this->assertEquals(0, $cfg['profileMask'] & ~0777,
			'and carries no bits outside a file mode');
	}

	/** Does conf/config.php complain about this mask? */
	private function configWarnsAbout($mask)
	{
		$saved = $_ENV;
		$_ENV['RU_PROFILE_MASK'] = $mask;
		$warned = false;
		set_error_handler(function ($errno, $errstr) use (&$warned) {
			if (strpos($errstr, 'RU_PROFILE_MASK') !== false) {
				$warned = true;
			}
			return true;
		});
		include($this->root . '/conf/config.php');
		restore_error_handler();
		$_ENV = $saved;

		return $warned;
	}

	/**
	 * The checker holds the mask rule separately from conf/config.php, for the
	 * same reason it holds the log path rule separately: it must run with
	 * nothing loaded. So the two are pinned to each other -- a mask the config
	 * complains about is one the checker must report, and the reverse.
	 */
	public function testTheCheckerAndTheConfigAgreeAboutEveryMask()
	{
		$masks = array('0777', '777', '0770', '0700', '02770', '1777', '0666x', 'garbage', '8');
		foreach ($masks as $mask) {
			$checkerAccepts = Requirements::profileMaskValid($mask);
			$configAccepts = !$this->configWarnsAbout($mask);
			$this->assertEquals($checkerAccepts, $configAccepts,
				'env_check and conf/config.php agree about ' . var_export($mask, true));
		}
	}

	/** And the installer is actually told, in the checker's own output. */
	public function testTheCheckerReportsAMaskItCouldNotRead()
	{
		$script = tempnam(sys_get_temp_dir(), 'envcheck') . '.php';
		file_put_contents($script, '<?php $_ENV["RU_PROFILE_MASK"] = "02770"; require '
			. var_export($this->root . '/env_check.php', true) . ';');
		$out = array();
		$code = 0;
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
		unlink($script);
		$text = implode("\n", $out);

		$this->assertTrue(strpos($text, 'RU_PROFILE_MASK') !== false,
			'the checker mentions the mask it could not read');
		$this->assertTrue(strpos($text, '[WARN') !== false,
			'and reports it as a warning');
		$this->assertEquals(0, $code,
			'without failing the run, because ruTorrent still works');
	}

	/**
	 * The checker itself, run as it ships. Its exit status covers the required
	 * checks only, so an unreachable rtorrent or a missing optional program
	 * does not enter into it.
	 */
	public function testTheCheckerPassesOnTheTreeAsItShips()
	{
		$out = array();
		$code = 0;
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/env_check.php')
			. ' 2>&1', $out, $code);
		$text = implode("\n", $out);

		$this->assertEquals(0, $code,
			'env_check exits clean on the tree as it ships');
		$this->assertTrue(strpos($text, '[FAIL') === false,
			'env_check prints no failure line on the tree as it ships');
	}
}
