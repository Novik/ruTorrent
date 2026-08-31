<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/settings.php');

/**
 * The settings page offers the user a maximum for open files and for HTTP
 * connections, and states the budget the two share. rTorrent reports that
 * budget only from 0.16.21 -- system.sockets.available_alloc and the
 * per-category limits do not exist before it -- so the probe is version-gated
 * and the fields stay at zero, which the page reads as "unknown".
 *
 * Nothing covered the gate: a settings page that offered limits an older
 * daemon will reject, or a six-command multicall sent to a daemon that cannot
 * answer it, would both have passed CI.
 */
class SocketAllocLimitsTest extends TestCase
{
	private function settings()
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		return $reflection->newInstanceWithoutConstructor();
	}

	public function testTheProbeIsSentOnlyFromTheReleaseThatAnswersIt()
	{
		$this->assertTrue(rTorrentSettings::socketAllocSupported(0x1015),
			'0.16.21 reports its socket allocation limits');
		$this->assertTrue(rTorrentSettings::socketAllocSupported(0x1016),
			'and so does anything after it');
		$this->assertTrue(!rTorrentSettings::socketAllocSupported(0x1014),
			'0.16.20 does not, and must not be asked');
		$this->assertTrue(!rTorrentSettings::socketAllocSupported(0x908),
			'nor does the 0.9.8 baseline');
	}

	public function testAnUnknownDaemonIsNotAsked()
	{
		// php/getplugins.php emits 0 while the daemon is unreachable, and
		// iVersion is null before the version has been read at all.
		$this->assertTrue(!rTorrentSettings::socketAllocSupported(0),
			'the unreachable-daemon sentinel is not a version that answers');
		$this->assertTrue(!rTorrentSettings::socketAllocSupported(null),
			'and neither is not having asked yet');
	}

	public function testTheBudgetIsWhatTheOfferedCategoriesShare()
	{
		$settings = $this->settings();
		// available, internal, rpc, http max, files max, files min
		$this->assertTrue($settings->applySocketAllocLimits(array(65536, 1024, 1448, 4096, 65536, 4)),
			'a well-formed answer is taken');
		$this->assertEquals(63064, $settings->socketAllocBudget,
			'the budget is the total less the categories the page does not offer');
		$this->assertEquals(4096, $settings->socketHttpAllocMax, 'the HTTP maximum is carried through');
		$this->assertEquals(65536, $settings->socketFilesAllocMax, 'and the files maximum');
		$this->assertEquals(4, $settings->socketFilesAllocMin, 'and the files minimum');
	}

	public function testABudgetCannotBeNegative()
	{
		$settings = $this->settings();
		$settings->applySocketAllocLimits(array(1024, 2048, 512, 4096, 65536, 4));
		$this->assertEquals(0, $settings->socketAllocBudget,
			'a total already spent leaves nothing to share rather than a negative budget');
	}

	public function testAnAnswerOfTheWrongShapeLeavesTheDefaults()
	{
		$settings = $this->settings();
		$this->assertTrue(!$settings->applySocketAllocLimits(array(65536, 1024, 1448)),
			'a short answer is refused');
		$this->assertEquals(0, $settings->socketAllocBudget, 'and the budget stays unknown');
		$this->assertEquals(0, $settings->socketFilesAllocMax, 'and so do the limits');
	}

	public function testAnAnswerThatIsNotNumbersLeavesTheDefaults()
	{
		$settings = $this->settings();
		$this->assertTrue(!$settings->applySocketAllocLimits(array('n/a', 1024, 1448, 4096, 65536, 4)),
			'an answer that is not numbers is refused');
		$this->assertEquals(0, $settings->socketAllocBudget,
			'rather than offering the user a limit computed from it');
	}
}
