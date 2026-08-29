<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/UnpackQuotaFixture.php');
require_once(__DIR__ . '/../../../plugins/unpack/unpack.php');

/**
 * An install that really has quotaspace. unpack.php loads rQuota out of
 * plugins/quotaspace/rquota.php and takes its answer, and that path is written
 * into the production code, so the only way to exercise it is to put a plugin
 * there for the duration of the run: the directory is created only if it is
 * absent, and is removed again on the way out, including on an abnormal exit.
 *
 * This is the case that already worked. The guard that stops a missing
 * rquota.php from being fatal -- see UnpackQuotaMissingTest.php -- must not stop
 * a present one from being obeyed.
 */
class UnpackQuotaTest extends TestCase
{
	use UnpackQuotaProbe;

	private $created = false;

	public function setUp()
	{
		$this->beginProbe();

		$file = $this->rquotaPath();
		$dir = dirname($file);
		if (!file_exists($dir) && !file_exists($file)) {
			mkdir($dir, 0777, true);
			file_put_contents($file, $this->pluginSource());
			$this->created = true;
			// The runner does not reach tearDown() if the process dies outright,
			// and a plugin directory left behind in the tree would change what
			// every later run means.
			register_shutdown_function(array($this, 'removePlugin'));
		}
	}

	public function tearDown()
	{
		$this->quotaspaceRegistered(false);
		$this->endProbe();
		$this->removePlugin();
	}

	public function removePlugin()
	{
		if (!$this->created) {
			return;
		}
		$this->created = false;
		@unlink($this->rquotaPath());
		@rmdir(dirname($this->rquotaPath()));
	}

	// The stand-in reads its answer at call time, so one loaded copy serves both
	// the satisfied and the exceeded case.
	private function pluginSource()
	{
		return "<?php\n\n" .
			"// Written by tests/plugins/unpack/UnpackQuotaTest.php and removed again\n" .
			"// when it finishes. It stands in for the third-party quotaspace plugin,\n" .
			"// which ruTorrent does not ship.\n" .
			"class rQuota\n" .
			"{\n" .
			"\tpublic static \$loads = 0;\n" .
			"\tpublic static function load()\n" .
			"\t{\n" .
			"\t\tself::\$loads++;\n" .
			"\t\treturn new rQuota();\n" .
			"\t}\n" .
			"\tpublic function check()\n" .
			"\t{\n" .
			"\t\treturn !empty(\$GLOBALS['rutorrent_test_quota_has_room']);\n" .
			"\t}\n" .
			"}\n";
	}

	// The stand-in only exists once ruTorrent has loaded it, and a case that
	// asks how often it was loaded must survive its never having been.
	private function timesLoaded()
	{
		return class_exists('rQuota', false) ? rQuota::$loads : 0;
	}

	private function quota($hasRoom)
	{
		$this->quotaspaceRegistered(true);
		$GLOBALS['rutorrent_test_quota_has_room'] = $hasRoom;
	}

	// Precondition. Every case below is about a quotaspace that is installed.
	public function testThePluginWasPutInPlace()
	{
		$this->assertTrue($this->created, 'A stand-in quotaspace plugin was installed for this run');
		$this->assertTrue(is_readable($this->rquotaPath()), 'plugins/quotaspace/rquota.php is readable');
	}

	// Room left: the unpack is none of the quota's business.
	public function testASatisfiedQuotaLetsTheSilentTaskRun()
	{
		$this->quota(true);
		$this->assertTrue($this->silentTaskWasAllowed(), 'A satisfied quota lets the silent unpack proceed');
		$this->assertTrue(class_exists('rQuota', false), 'rQuota was loaded from plugins/quotaspace/rquota.php');
		$this->assertTrue($this->timesLoaded() > 0, 'The quota was actually consulted');
	}

	// Out of room: the autounpack returns before it starts a task.
	public function testAnExceededQuotaStopsTheSilentTask()
	{
		$this->quota(false);
		$this->assertTrue(!$this->silentTaskWasAllowed(), 'An exceeded quota stops the silent unpack');
	}

	public function testASatisfiedQuotaLetsTheManualTaskRun()
	{
		$this->quota(true);
		$this->assertTrue(
			$this->startTaskErrors() != $this->quotaRefusal(),
			'A satisfied quota is not reported as a refusal'
		);
	}

	// The refusal the user sees, unchanged.
	public function testAnExceededQuotaIsReportedToTheManualTask()
	{
		$this->quota(false);
		$this->assertEquals(
			$this->quotaRefusal(),
			$this->startTaskErrors(),
			'An exceeded quota is reported as "Quota limitation was reached. Unpack failed."'
		);
	}

	// The registration is what makes the quota authoritative. An operator who
	// turned quotaspace off must stop being governed by it even though its files
	// are still on disk.
	public function testAnUnregisteredQuotaspaceIsNotConsulted()
	{
		$this->quotaspaceRegistered(false);
		$GLOBALS['rutorrent_test_quota_has_room'] = false;
		$before = $this->timesLoaded();
		$this->assertTrue($this->silentTaskWasAllowed(), 'An unregistered quotaspace does not stop the silent unpack');
		$this->assertEquals($before, $this->timesLoaded(), 'An unregistered quotaspace is never asked');
	}
}
