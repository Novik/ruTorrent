<?php

require_once(__DIR__ . '/TestCase.php');

// Load only the Requirements class from the checker (skip its live probing).
define('RUTORRENT_REQUIREMENTS_LIB', true);
require_once(__DIR__ . '/../../env_check.php');

class RequirementsTest extends TestCase
{
	public function testRtorrentSupportedVersions()
	{
		foreach (array('0.9.8', '0.9.8-1', '0.16.0', '0.16.14', '0.16.17') as $v) {
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
		$this->assertTrue(Requirements::looksAbsolute('C:\\torrents'), 'windows absolute');
		$this->assertTrue(!Requirements::looksAbsolute('relative/path'), 'relative is not absolute');
		$this->assertTrue(!Requirements::looksAbsolute(''), 'empty is not absolute');
	}

	public function testScgiLabel()
	{
		$this->assertEquals('127.0.0.1:5000', Requirements::scgiLabel('127.0.0.1', 5000));
		$this->assertEquals('unix:///tmp/rpc.sock', Requirements::scgiLabel('unix:///tmp/rpc.sock', 0));
	}
}
