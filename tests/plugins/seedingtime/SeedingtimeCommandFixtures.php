<?php

// Captures what plugins/seedingtime/init.php builds, with the alias maps the
// given rTorrent would have loaded. Deliberately not named *Test.php:
// php-test.sh collects test files by that suffix.
//
// Two capture points, both reached before any socket work:
//   patchDeprecatedCommand  -- every command, from the rXMLRPCCommand constructor
//   patchDeprecatedRequest  -- the commands of a request, from run()

require_once(__DIR__ . '/../../../php/settings.php');

class SeedingtimeCommandsCaptured extends Exception {}

class SeedingtimeTestSettings extends rTorrentSettings
{
	public $built = array();	// every command constructed, in order
	public $requested = array();	// the commands of the first request to run

	public function patchDeprecatedCommand($cmd, $name)
	{
		$this->built[] = $cmd;
		parent::patchDeprecatedCommand($cmd, $name);
	}

	// Stops init.php at its first request, so no daemon is needed. Commands
	// built before that point stay readable through $built.
	public function patchDeprecatedRequest($commands)
	{
		$this->requested = $commands;
		throw new SeedingtimeCommandsCaptured();
	}
}

trait SeedingtimeCommandFixtures
{
	// The version thresholds php/settings.php applies. iVersion packs one
	// component per byte, so 0.10.2 is 0x0a02 and 0.16.23 is 0x1017.
	private function aliasMapsFor($iVersion)
	{
		$maps = array(
			0x904  => 'methods-0.9.4.php',
			0x0a02 => 'methods-0.10.2.php',
			0x1000 => 'methods-0.16.0.php',
			0x1010 => 'methods-0.16.16.php',
			0x1012 => 'methods-0.16.18.php',
		);
		$wanted = array();
		foreach($maps as $from => $file)
			if($iVersion >= $from)
				$wanted[] = __DIR__ . '/../../../php/' . $file;
		return($wanted);
	}

	// Runs the plugin's init.php against a given rTorrent version and returns
	// the settings double holding what it built.
	protected function initPlugin($iVersion)
	{
		$settings = (new ReflectionClass('SeedingtimeTestSettings'))->newInstanceWithoutConstructor();
		$settings->iVersion = $iVersion;
		$settings->aliases = array();

		// Each map does $this->aliases = array_merge($this->aliases, ...),
		// so the requires run bound to the settings object.
		$maps = $this->aliasMapsFor($iVersion);
		$loadAliases = function () use ($maps) {
			foreach($maps as $map)
				require $map;
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
			return($settings);
		}
		finally
		{
			$property->setValue(null, $previous);
		}
	}

	protected function parameterValues($command)
	{
		$values = array();
		foreach($command->params as $parameter)
			$values[] = $parameter->value;
		return($values);
	}

	// The command stored for one event key, e.g. event.download.hash_done.
	protected function eventCommand($iVersion, $event)
	{
		foreach($this->initPlugin($iVersion)->requested as $command)
			foreach($command->params as $parameter)
				if($parameter->value === $event)
					return(end($command->params)->value);
		throw new Exception('Plugin did not register a handler for ' . $event);
	}
}
