<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/SeedingtimeCommandFixtures.php');

class HashdoneCommandTest extends TestCase
{
	use SeedingtimeCommandFixtures;

	public function testHashdoneEmitsNestedConditions()
	{
		// rTorrent 0.16 evaluates one condition/then/else per branch. A
		// five-argument chain only reads seedingtime instead of setting it.
		// Capture the emitted command, including PHP's nested quote escaping.
		foreach(array(0x908, 0x1017) as $iVersion)
			$this->assertEquals(
				'branch=d.complete=,"branch=d.custom=seedingtime,,\"d.custom.set=seedingtime,$d.custom=addtime\""',
				$this->eventCommand($iVersion, 'event.download.hash_done'),
				sprintf('complete data sets a missing time through a quoted inner branch; rTorrent iVersion 0x%x', $iVersion));
	}
}
