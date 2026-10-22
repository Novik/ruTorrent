<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GetpluginsHarness.php');

/**
 * What php/getplugins.php reports about the temp directory.
 *
 * Two of these checks are the ones the script already had: badTempPath, for a
 * directory the web server user cannot use, and badTempPath2, for one the
 * rTorrent user cannot reach. They are here because a harness that cannot
 * observe the diagnostics a script already sends cannot be trusted to observe
 * a new one.
 */
class GetpluginsTempPathTest extends TestCase
{
	private $harness = null;

	public function tearDown()
	{
		$this->release();
	}

	private function release()
	{
		if($this->harness !== null)
		{
			$this->harness->release();
			$this->harness = null;
		}
	}

	private function harness($name)
	{
		$this->release();
		$this->harness = new GetpluginsHarness($name);
		return $this->harness;
	}

	public function testTheScriptRunsAndItsDiagnosticsAreReadable()
	{
		$harness = $this->harness('runs');
		$output = $harness->run(array('RU_TEMP_DIRECTORY' => $harness->directory('temp', 0700)));
		$this->assertTrue(GetpluginsHarness::ran($output),
			'getplugins.php ran to the point of describing the install');
		// With no rTorrent the script says so, which is a diagnostic reaching
		// the harness through the same path the temp directory ones take.
		$this->assertTrue(GetpluginsHarness::reported($output, 'badLinkTorTorrent'),
			'a run with no rTorrent reports badLinkTorTorrent');
	}

	public function testATempDirectoryTheScriptCannotUseIsReported()
	{
		$harness = $this->harness('unusable');
		$absent = $harness->uncreatablePath('denied');
		$output = $harness->run(array('RU_TEMP_DIRECTORY' => $absent));
		$this->assertTrue(!is_dir($absent),
			'the configured temp directory is one the script could not create');
		$this->assertTrue(GetpluginsHarness::reported($output, 'badTempPath'),
			'a temp directory that is not there is reported as badTempPath');
	}

	public function testAUsableTempDirectoryIsNotReported()
	{
		$harness = $this->harness('usable');
		$output = $harness->run(array('RU_TEMP_DIRECTORY' => $harness->directory('temp', 0700)));
		$this->assertTrue(!GetpluginsHarness::reported($output, 'badTempPath'),
			'a temp directory the script can read and write is not reported');
	}

	public function testATempDirectoryTheRtorrentUserCannotReachIsReported()
	{
		$harness = $this->harness('unreachable');
		$harness->withRtorrent(4242, '4242');
		$temp = $harness->directory('reachable/temp', 0700);
		$output = $harness->run(array('RU_TEMP_DIRECTORY' => $temp));
		$this->assertTrue(GetpluginsHarness::reported($output, 'badTempPath2'),
			'a temp directory closed to the rTorrent user is reported as badTempPath2');
	}

	public function testATempDirectoryTheRtorrentUserCanReachIsNotReported()
	{
		$harness = $this->harness('reachable');
		$harness->withRtorrent(4242, '4242');
		// doesUserHave() asks about the directory and the one above it, so
		// both have to be open to a user who owns neither.
		$harness->directory('open', 0711);
		$temp = $harness->directory('open/temp', 0777);
		$output = $harness->run(array('RU_TEMP_DIRECTORY' => $temp));
		$this->assertTrue(!GetpluginsHarness::reported($output, 'badTempPath2'),
			'a temp directory open to the rTorrent user is not reported as badTempPath2');
	}
}
