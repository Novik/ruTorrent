<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/UnpackQuotaFixture.php');
require_once(__DIR__ . '/../../../plugins/unpack/unpack.php');

/**
 * quotaspace is a third-party plugin: nothing in this repository ships
 * plugins/quotaspace/rquota.php, so a registration is not a promise that the
 * file is there. unpack.php used to require_once that path outright whenever
 * the plugin was registered, and a registration without the file killed the
 * request -- both the manual unpack and the autounpack that runs unattended on
 * every completed download.
 *
 * A quota that cannot be consulted is not a quota that was exceeded, so the
 * unpack proceeds, exactly as it does where quotaspace was never installed.
 *
 * This file must run in a process where the plugin is absent, which is why it is
 * separate from UnpackQuotaTest.php -- that one puts a stand-in plugin in place.
 */
class UnpackQuotaMissingTest extends TestCase
{
	use UnpackQuotaProbe;

	public function setUp()
	{
		$this->beginProbe();
	}

	public function tearDown()
	{
		$this->quotaspaceRegistered(false);
		$this->endProbe();
	}

	// Preconditions. If either of these fails the rest of the file is testing
	// something other than what it claims to.
	public function testQuotaspaceIsNotVendoredInThisTree()
	{
		$this->assertTrue(
			!file_exists($this->rquotaPath()),
			'plugins/quotaspace/rquota.php is not shipped with ruTorrent'
		);
	}

	public function testRQuotaIsNotDeclaredInThisProcess()
	{
		$this->assertTrue(
			!class_exists('rQuota', false),
			'No rQuota class exists in the process running this file'
		);
	}

	// The autounpack path: it runs unattended on download completion, so a fatal
	// here is a silently broken feature rather than a visible error.
	public function testTheSilentTaskRunsWhenTheQuotaCannotBeConsulted()
	{
		$this->quotaspaceRegistered(true);
		$this->assertTrue(
			$this->silentTaskWasAllowed(),
			'A registered quotaspace with no rquota.php lets the silent unpack proceed'
		);
	}

	// The manual "Unpack" menu entry.
	public function testTheManualTaskRunsWhenTheQuotaCannotBeConsulted()
	{
		$this->quotaspaceRegistered(true);
		$this->assertTrue(
			$this->startTaskErrors() != $this->quotaRefusal(),
			'A registered quotaspace with no rquota.php is not reported as a quota refusal'
		);
	}

	// Looking for a quota that is not installed must not send the core
	// autoloader hunting for php/utility/rquota.php, which is not where a plugin
	// class would ever live and which warns on every miss when al_diagnostic is
	// on.
	public function testTheCoreAutoloaderIsNotAskedForRQuota()
	{
		$this->quotaspaceRegistered(true);
		$asked = array();
		spl_autoload_register(function ($class) use (&$asked) {
			$asked[] = $class;
		});
		$this->silentTaskWasAllowed();
		$this->assertEquals(array(), $asked, 'No class was autoloaded while looking for the quota');
	}

	// The overwhelmingly common install: no quotaspace at all. Nothing about the
	// quota is looked at, so nothing about it can fail.
	public function testAnUnregisteredQuotaspaceIsNeverConsulted()
	{
		$this->quotaspaceRegistered(false);
		$this->assertTrue(
			$this->silentTaskWasAllowed(),
			'Without quotaspace the silent unpack proceeds'
		);
		$this->assertTrue(
			$this->startTaskErrors() != $this->quotaRefusal(),
			'Without quotaspace the manual unpack is not refused'
		);
	}
}
