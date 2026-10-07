<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/SeedingtimeCommandFixtures.php');

class BackfillCommandTest extends TestCase
{
	use SeedingtimeCommandFixtures;

	// The pass over downloads that are already complete, as built by init.php.
	private function backfill($iVersion)
	{
		$settings = $this->initPlugin($iVersion);
		$label = sprintf('rTorrent iVersion 0x%x', $iVersion);
		$found = array();
		foreach($settings->built as $command)
			if(strpos($command->command, 'd.multicall') === 0)
				$found[] = $command;
		$this->assertEquals(1, count($found), 'one pass over the downloads; ' . $label);
		if(count($found) !== 1)
			return(null);
		// Not among the commands of the request that carries the event
		// handlers: a daemon refusing the pass must not keep the handlers
		// from being registered.
		$this->assertTrue(!in_array($found[0], $settings->requested, true),
			'the pass travels in a request of its own; ' . $label);
		return($found[0]);
	}

	public function testBackfillAsksTheDaemonForItsMainView()
	{
		// d.multicall2 was removed in 0.16.16, where d.multicall took over the
		// same signature; php/methods-*.php maps the name per version, and the
		// empty leading target comes from patchDeprecatedCommand.
		$methods = array(0x908 => 'd.multicall2', 0x1000 => 'd.multicall2',
			0x1010 => 'd.multicall', 0x1017 => 'd.multicall');
		foreach($methods as $iVersion => $method)
		{
			$command = $this->backfill($iVersion);
			$label = sprintf('rTorrent iVersion 0x%x', $iVersion);
			if($command === null)
				continue;
			$this->assertEquals($method, $command->command, 'the multicall name for ' . $label);
			$values = $this->parameterValues($command);
			$this->assertEquals(3, count($values), 'target, view and one command; ' . $label);
			$this->assertEquals('', $values[0], 'no download target for a multicall; ' . $label);
			$this->assertEquals('main', $values[1], 'every download, not a filtered view; ' . $label);
		}
	}

	public function testBackfillRunsTheSameExpressionAsTheHandler()
	{
		// Downloads completing from now on are reached by the hash_done key;
		// those already complete are only ever reached by this pass. A
		// divergence between the two expressions would leave one population
		// without a seedingtime.
		foreach(array(0x908, 0x1017) as $iVersion)
		{
			$command = $this->backfill($iVersion);
			if($command === null)
				continue;
			$values = $this->parameterValues($command);
			$this->assertEquals(
				$this->eventCommand($iVersion, 'event.download.hash_done'),
				end($values),
				sprintf('pass and handler run one expression; rTorrent iVersion 0x%x', $iVersion));
		}
	}

	public function testBackfillOnlyWritesWhereTheValueIsMissing()
	{
		// The inner branch is the safety property: an existing seedingtime is
		// never overwritten and an incomplete download never gets one. Pinned
		// here rather than left to the comparison above.
		$command = $this->backfill(0x1017);
		if($command === null)
			return;
		$values = $this->parameterValues($command);
		$this->assertEquals(
			'branch=d.complete=,"branch=d.custom=seedingtime,,\"d.custom.set=seedingtime,$d.custom=addtime\""',
			end($values),
			'complete, and only when seedingtime is empty');
	}
}
