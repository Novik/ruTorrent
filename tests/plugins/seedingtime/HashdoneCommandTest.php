<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../php/settings.php');

class SeedingtimeCommandsCaptured extends Exception {}

class SeedingtimeTestSettings extends rTorrentSettings
{
	public $captured = array();

	public function patchDeprecatedRequest($commands)
	{
		$this->captured = $commands;
		throw new SeedingtimeCommandsCaptured();
	}
}

class HashdoneCommandTest extends TestCase
{
	private function hashdoneCommand($modern)
	{
		$settings = (new ReflectionClass('SeedingtimeTestSettings'))->newInstanceWithoutConstructor();
		$settings->iVersion = $modern ? 0x1017 : 0x908;
		$settings->aliases = array();
		$loadAliases = function () use ($modern) {
			require __DIR__ . '/../../../php/methods-0.9.4.php';
			if($modern)
			{
				require __DIR__ . '/../../../php/methods-0.10.2.php';
				require __DIR__ . '/../../../php/methods-0.16.0.php';
			}
		};
		$loadAliases->call($settings);
		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		if(PHP_VERSION_ID < 80100)
			$property->setAccessible(true);
		$previous = $property->getValue();
		$property->setValue(null, $settings);
		try
		{
			$theSettings = $settings;
			try
			{
				require __DIR__ . '/../../../plugins/seedingtime/init.php';
			}
			catch(SeedingtimeCommandsCaptured $captured) {}
			foreach($settings->captured as $command)
				foreach($command->params as $parameter)
					if($parameter->value === 'event.download.hash_done')
						return end($command->params)->value;
			throw new Exception('Plugin did not register its hash-done handler');
		}
		finally
		{
			$property->setValue(null, $previous);
		}
	}

	public function testHashdoneEmitsNestedConditions()
	{
		// rTorrent 0.16 evaluates one condition/then/else per branch. A
		// five-argument chain only reads seedingtime instead of setting it.
		// Capture the emitted command, including PHP's nested quote escaping.
		foreach(array(false, true) as $modern)
			$this->assertEquals(
				'branch=d.complete=,"branch=d.custom=seedingtime,,\"d.custom.set=seedingtime,$d.custom=addtime\""',
				$this->hashdoneCommand($modern),
				'complete data sets a missing time through a quoted inner branch; rTorrent ' . ($modern ? '0.16' : '0.9'));
	}
}
