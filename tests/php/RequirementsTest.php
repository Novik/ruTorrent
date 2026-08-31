<?php

require_once(__DIR__ . '/TestCase.php');

// Load only the Requirements class from the checker (skip its live probing).
define('RUTORRENT_REQUIREMENTS_LIB', true);
require_once(__DIR__ . '/../../env_check.php');

class RequirementsTest extends TestCase
{
	public function testRtorrentSupportedVersions()
	{
		foreach (array('0.9.8', '0.9.8-1', '0.16.0', '0.16.14', '0.16.17', '0.16.19') as $v) {
			list($ok, ) = Requirements::rtorrentSupport($v);
			$this->assertTrue($ok, "rtorrent $v should be supported");
		}
	}

	public function testRtorrentUnsupportedVersions()
	{
		// 0.9.6/0.9.7 (not the 0.9.8 baseline), the 0.10-0.15 gap, and future 0.17+
		foreach (array('0.9.6', '0.9.7', '0.13.8', '0.15.4', '0.17.0', '1.0.0', '') as $v) {
			list($ok, ) = Requirements::rtorrentSupport($v);
			$this->assertTrue(!$ok, "rtorrent '$v' should NOT be flagged supported");
		}
	}

	public function testRtorrent0981NotMistakenFor098()
	{
		// 0.9.81 must not match the 0.9.8 baseline (word-boundary guard).
		list($ok, ) = Requirements::rtorrentSupport('0.9.81');
		$this->assertTrue(!$ok, '0.9.81 must not be treated as the 0.9.8 baseline');
	}

	public function testPhpMeetsMinimum()
	{
		$this->assertTrue(Requirements::phpMeetsMinimum('7.4.0'), '7.4.0 meets 7.4.0');
		$this->assertTrue(Requirements::phpMeetsMinimum('8.2.10'), '8.2.10 meets minimum');
		$this->assertTrue(!Requirements::phpMeetsMinimum('7.3.33'), '7.3 is below minimum');
		$this->assertTrue(!Requirements::phpMeetsMinimum('5.6.40'), '5.6 is below minimum');
	}

	public function testUnixSocketDetection()
	{
		$this->assertTrue(Requirements::isUnixSocket('unix:///var/run/rpc.sock'), 'unix:// is a socket');
		$this->assertTrue(!Requirements::isUnixSocket('127.0.0.1'), 'host is not a socket');
		$this->assertEquals('/var/run/rpc.sock', Requirements::unixSocketPath('unix:///var/run/rpc.sock'));
		$this->assertTrue(Requirements::unixSocketPath('127.0.0.1') === null, 'no path for a TCP host');
	}

	public function testScgiConfigured()
	{
		$this->assertTrue(Requirements::scgiConfigured('127.0.0.1', 5000), 'host + valid port');
		$this->assertTrue(Requirements::scgiConfigured('unix:///tmp/rpc.sock', 0), 'unix socket, port ignored');
		$this->assertTrue(!Requirements::scgiConfigured('127.0.0.1', 0), 'port 0 over TCP is invalid');
		$this->assertTrue(!Requirements::scgiConfigured('127.0.0.1', 70000), 'port out of range');
		$this->assertTrue(!Requirements::scgiConfigured('', 5000), 'empty host');
		$this->assertTrue(!Requirements::scgiConfigured('unix://', 0), 'unix socket with no path');
	}

	public function testXmlrpcMountPointValid()
	{
		$this->assertTrue(Requirements::xmlrpcMountPointValid('/RPC2'), '/RPC2 is valid');
		$this->assertTrue(!Requirements::xmlrpcMountPointValid(''), 'empty is invalid');
		$this->assertTrue(!Requirements::xmlrpcMountPointValid(null), 'null is invalid');
		$this->assertTrue(!Requirements::xmlrpcMountPointValid('RPC2'), 'must start with /');
	}

	public function testLooksAbsolute()
	{
		$this->assertTrue(Requirements::looksAbsolute('/torrents/data'), 'unix absolute');
		$this->assertEquals(
			DIRECTORY_SEPARATOR === '\\',
			Requirements::looksAbsolute('C:\\torrents'),
			'Windows drive paths are absolute only on Windows'
		);
		$this->assertTrue(!Requirements::looksAbsolute('relative/path'), 'relative is not absolute');
		$this->assertTrue(!Requirements::looksAbsolute(''), 'empty is not absolute');
	}

	public function testLogFilePathIsStableAcrossEntryPointDirectories()
	{
		$this->assertTrue(Requirements::logFilePathValid('/tmp/errors.log'), 'absolute log path');
		$this->assertTrue(Requirements::logFilePathValid('php://stderr'), 'stream log URI');
		$this->assertTrue(Requirements::logFilePathValid('file:///tmp/errors.log'), 'absolute file URI');
		$this->assertTrue(Requirements::logFilePathValid('file://localhost/tmp/errors.log'), 'localhost file URI');
		$this->assertEquals(
			DIRECTORY_SEPARATOR === '\\',
			Requirements::logFilePathValid('file://server/share/errors.log'),
			'A remote file authority is a UNC path only on Windows'
		);
		$this->assertTrue(!Requirements::logFilePathValid('errors.log'), 'relative log path is unstable');
	}

	public function testLogFileStreamScheme()
	{
		$this->assertEquals('php', Requirements::logFileStreamScheme('php://stderr'));
		$this->assertEquals('custom-1', Requirements::logFileStreamScheme('custom-1://sink'));
		$this->assertEquals('MyScheme', Requirements::logFileStreamScheme('MyScheme://sink'));
		$this->assertTrue(Requirements::logFileStreamScheme('/tmp/errors.log') === null, 'plain path has no stream scheme');
	}

	public function testLogFileStreamAvailability()
	{
		$wrappers = array('file', 'http', 'php', 'MyScheme');
		$this->assertTrue(
			Requirements::logFileStreamAvailable('php://stderr', $wrappers),
			'A registered log stream wrapper is available'
		);
		$this->assertTrue(
			Requirements::logFileStreamAvailable('PHP://stderr', $wrappers),
			'A built-in log stream wrapper is case-insensitive'
		);
		$this->assertTrue(
			Requirements::logFileStreamAvailable('MyScheme://sink', $wrappers),
			'A mixed-case custom wrapper keeps its registered spelling'
		);
		$this->assertTrue(
			!Requirements::logFileStreamAvailable('myscheme://sink', $wrappers),
			'A custom wrapper with the wrong case is unavailable'
		);
		$this->assertTrue(
			!Requirements::logFileStreamAvailable('nosuch://sink', $wrappers),
			'An unregistered log stream wrapper is unavailable'
		);
	}

	public function testRutorrentHandlersIgnoresRtorrentBuiltins()
	{
		// A freshly started rtorrent that no ruTorrent has ever talked to.
		$this->assertEquals(array(), Requirements::rutorrentHandlers(array('1_prepare', '~_save_full')));
		$this->assertEquals(array(), Requirements::rutorrentHandlers(array()));
	}

	public function testRutorrentHandlersFindsPluginKeys()
	{
		$keys = array('1_prepare', '_exratio1', '_ratio', 'addtime', 'thistory', '~_save_full');
		$this->assertEquals(array('_exratio1', '_ratio', 'addtime', 'thistory'),
			Requirements::rutorrentHandlers($keys));
	}

	public function testRutorrentHandlersKeepsPerUserSuffixes()
	{
		// Multi-user installs suffix every key with the ruTorrent username.
		$keys = array('1_prepare', '_exratio1bob', 'addtimebob', '~_save_full');
		$this->assertEquals(array('_exratio1bob', 'addtimebob'), Requirements::rutorrentHandlers($keys));
	}

	public function testRutorrentHandlersToleratesNonArray()
	{
		$this->assertEquals(array(), Requirements::rutorrentHandlers(null));
	}

	public function testScgiLabel()
	{
		$this->assertEquals('127.0.0.1:5000', Requirements::scgiLabel('127.0.0.1', 5000));
		$this->assertEquals('unix:///tmp/rpc.sock', Requirements::scgiLabel('unix:///tmp/rpc.sock', 0));
	}
}
