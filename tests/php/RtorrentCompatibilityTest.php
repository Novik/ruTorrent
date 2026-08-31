<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/settings.php');

class RtorrentCompatibilityTest extends TestCase
{
	/**
	 * settings.php loads the per-version method alias files from obtain(),
	 * which interrogates a live daemon over XMLRPC before it reaches the
	 * require_once ladder, so the ladder cannot be executed directly here.
	 * Instead, parse the version => file gate table out of the source and
	 * replay it, so the constants under test are the real ones and a wrong
	 * gate in settings.php fails these tests.
	 */
	private function methodFileGates()
	{
		// This fork carries one downlevel gate (methods-pre-0.9.0.php loads
		// under iVersion < 0x900), so both comparison directions are parsed.
		$source = file_get_contents(__DIR__ . '/../../php/settings.php');
		preg_match_all(
			'/if\s*\(\s*\$this->iVersion\s*(>=|<)\s*(0x[0-9A-Fa-f]+)\s*\)\s*\{\s*require_once\(\s*\'(methods-[^\']+\.php)\'\s*\);/',
			$source,
			$matches,
			PREG_SET_ORDER
		);
		if (count($matches) < 4) {
			throw new Exception('Could not parse the method alias gates out of php/settings.php');
		}
		$gates = array();
		foreach ($matches as $match) {
			$gates[] = array('op' => $match[1], 'version' => intval($match[2], 16), 'file' => $match[3]);
		}
		return $gates;
	}

	private function makeSettings($version)
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		$settings = $reflection->newInstanceWithoutConstructor();
		$settings->iVersion = $version;
		$settings->aliases = [];

		foreach ($this->methodFileGates() as $gate) {
			$applies = ($gate['op'] === '>=')
				? ($version >= $gate['version'])
				: ($version < $gate['version']);
			if ($applies) {
				$this->loadMethodAliases($settings, $gate['file']);
			}
		}

		return $settings;
	}

	private function loadMethodAliases($settings, $file)
	{
		$loader = function () use ($file) {
			require __DIR__ . '/../../php/' . $file;
		};
		$loader = $loader->bindTo($settings, get_class($settings));
		$loader();
	}

	private function useSettingsSingleton($settings)
	{
		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}
		$property->setValue(null, $settings);
	}

	public function testRtorrent0102MethodAliasGateUsesBytewiseVersion()
	{
		// iVersion packs one version component per byte, so 0.10.2 is 0x0a02
		// (0x1002 would be 0.16.2) and 0.10.1 is 0x0a01.
		foreach (array(0x908 => '0.9.8', 0x0a01 => '0.10.1') as $version => $label) {
			$settings = $this->makeSettings($version);
			$this->assertEquals('dht', $settings->getCommand('dht'), 'rTorrent '.$label.' predates the 0.10.2 aliases so dht stays unmapped');
			$this->assertEquals('connection_leech', $settings->getCommand('connection_leech'), 'rTorrent '.$label.' predates the 0.10.2 aliases so connection_leech stays unmapped');
		}

		$settings = $this->makeSettings(0x0a02);
		$this->assertEquals('dht.mode.set', $settings->getCommand('dht'), 'rTorrent 0.10.2 itself gets its dht alias');
		$this->assertEquals('group2.seeding.ratio.min.set', $settings->getCommand('ratio.min.set'), 'rTorrent 0.10.2 itself gets its ratio aliases');
	}

	public function testRtorrent016UsesNonDeprecatedRpcCommandNames()
	{
		$settings = $this->makeSettings(0x100e);
		$this->useSettingsSingleton($settings);

		$this->assertEquals('execute', $settings->getCommand('execute'), 'rTorrent 0.16 uses execute directly');
		$this->assertEquals('schedule', $settings->getCommand('schedule'), 'rTorrent 0.16 uses schedule directly');
		$this->assertEquals('schedule.remove', $settings->getCommand('schedule_remove'), 'rTorrent 0.16 uses schedule.remove directly');

		$command = new rXMLRPCCommand('schedule', array('test_schedule', '0', '60', 'system.method'));
		$this->assertEquals('schedule', $command->command, 'rTorrent 0.16 schedule command name stays direct');
		$this->assertEquals('', $command->params[0]->value, 'rTorrent 0.16 schedule keeps an empty target parameter');
		$this->assertEquals('test_schedule', $command->params[1]->value, 'rTorrent 0.16 schedule keeps requested parameters after the empty target');
	}

	public function testRtorrent0102AliasesAreLoadedFor0102AndInheritedBy016()
	{
		foreach (array(0x0a02 => '0.10.2', 0x1000 => '0.16.0') as $version => $label) {
			$settings = $this->makeSettings($version);

			$this->assertEquals('dht.mode.set', $settings->getCommand('dht'), 'rTorrent '.$label.' maps DHT mode command');
			$this->assertEquals('protocol.connection.leech.set', $settings->getCommand('connection_leech'), 'rTorrent '.$label.' maps leech connection command');
		}

		$settings = $this->makeSettings(0x1000);
		$this->assertEquals('group.seeding.ratio.min.set', $settings->getCommand('ratio.min.set'), 'rTorrent 0.16.0 overrides ratio aliases back to group commands');
	}

	public function testRtorrent016RatioSettersPrependAnEmptyTarget()
	{
		// rtorrent 0.16's XMLRPC dispatcher (src/rpc/xmlrpc_tinyxml2.cc)
		// unconditionally parses the FIRST param of any call as the target,
		// so group.NAME.ratio.*.set must be sent as ('', value); a bare
		// (value) faults with "invalid parameters: target must be a string".
		// getRatioGroupCommand in php/settings.php prepends '' for the same
		// reason. This pins the shape on both the alias path (prm=1 makes
		// rXMLRPCCommand prepend the empty target) and the group-command
		// path — this fork once got the alias shape wrong (prm=0), which
		// this test now guards against.
		$settings = $this->makeSettings(0x100e);
		$this->useSettingsSingleton($settings);

		$aliasCommand = new rXMLRPCCommand('ratio.min.set', 100);
		$this->assertEquals('group.seeding.ratio.min.set', $aliasCommand->command, 'rTorrent 0.16 maps bare ratio setters to group commands');
		$this->assertEquals(2, count($aliasCommand->params), 'rTorrent 0.16 ratio setters send target and value arguments');
		$this->assertEquals('', $aliasCommand->params[0]->value, 'rTorrent 0.16 ratio setters prepend the empty target the dispatcher requires');
		$this->assertEquals('100', $aliasCommand->params[1]->value, 'rTorrent 0.16 ratio setters keep the requested value after the target');

		$command = $settings->getRatioGroupCommand('rat_0', 'ratio.min.set', 100);

		$this->assertEquals('group.rat_0.ratio.min.set', $command->command, 'rTorrent 0.16 uses group ratio command names');
		$this->assertEquals(2, count($command->params), 'rTorrent 0.16 group ratio value setters send target and value arguments');
		$this->assertEquals('', $command->params[0]->value, 'rTorrent 0.16 group ratio commands send an empty target argument');
		$this->assertEquals('100', $command->params[1]->value, 'rTorrent 0.16 group ratio commands keep the requested value');
	}

	public function testRtorrent01616UsesCanonicalCommands()
	{
		$settings = $this->makeSettings(0x1010);
		$this->useSettingsSingleton($settings);

		$this->assertEquals('network.proxy.http', $settings->getCommand('get_http_proxy'), 'rTorrent 0.16.16 reads HTTP proxy through proxy manager');
		$this->assertEquals('network.proxy.http.set', $settings->getCommand('set_http_proxy'), 'rTorrent 0.16.16 writes HTTP proxy through proxy manager');
		$this->assertEquals('network.proxy.global', $settings->getCommand('get_proxy_address'), 'rTorrent 0.16.16 reads global proxy through proxy manager');
		$this->assertEquals('network.proxy.global.set', $settings->getCommand('set_proxy_address'), 'rTorrent 0.16.16 writes global proxy through proxy manager');
		$this->assertEquals('d.multicall', $settings->getCommand('d.multicall'), 'rTorrent 0.16.16 uses the canonical download multicall command');
		$this->assertEquals('d.multicall', $settings->getCommand('d.multicall2'), 'rTorrent 0.16.16 maps the legacy download multicall command to the canonical command');

		$command = new rXMLRPCCommand('d.multicall', array('main', 'd.hash='));
		$this->assertEquals('d.multicall', $command->command, 'rTorrent 0.16.16 sends canonical download multicalls');
		$this->assertEquals('', $command->params[0]->value, 'canonical download multicalls keep the required empty target argument');
	}

	public function testRtorrent01618AndLaterLoadCanonicalPortAliasesAtExactThreshold()
	{
		$legacyAliases = array(
			'get_port_range' => 'network.port_range',
			'set_port_range' => 'network.port_range.set',
			'get_port_random' => 'network.port_random',
			'set_port_random' => 'network.port_random.set',
			'get_port_open' => 'network.port_open',
			'set_port_open' => 'network.port_open.set',
			'port_open' => 'network.port_open',
		);
		$settings = $this->makeSettings(0x1011);
		foreach ($legacyAliases as $alias => $command) {
			$this->assertEquals($command, $settings->getCommand($alias), 'rTorrent 0.16.17 keeps '.$alias.' on its legacy command');
		}

		$canonicalAliases = array(
			'get_port_range' => 'network.listen.port.range',
			'set_port_range' => 'network.listen.port.range.set',
			'get_port_random' => 'network.listen.port.random',
			'set_port_random' => 'network.listen.port.random.set',
			'get_port_open' => 'cat',
			'set_port_open' => 'cat',
			'port_open' => 'cat',
		);
		foreach (array(0x1012 => '0.16.18', 0x1013 => '0.16.19', 0x1015 => '0.16.21') as $version => $label) {
			$settings = $this->makeSettings($version);
			foreach ($canonicalAliases as $alias => $command) {
				$this->assertEquals($command, $settings->getCommand($alias), 'rTorrent '.$label.' maps '.$alias.' to its canonical command');
			}
			$this->assertEquals('system.sockets.max_size', $settings->getCommand('get_max_open_sockets'), 'rTorrent '.$label.' reads the generic socket ceiling without removed allocation commands');
			$this->assertEquals('system.sockets.files.max_alloc.set', $settings->getCommand('set_max_open_files'), 'rTorrent '.$label.' keeps the file-category allocation setter');
		}
	}

	public function testPartiallyDoneCommandIsANoOpBelowRtorrent090()
	{
		$settings = $this->makeSettings(0x809);
		$this->assertEquals('cat', $settings->getCommand('d.is_partially_done'), 'rTorrent 0.8.9 answers the partially done question with the no-op cat');
		$this->assertEquals('cat=', $settings->getCommand('d.is_partially_done='), 'the no-op keeps the trailing = so the multicall field stays in place');

		foreach (array(0x900 => '0.9.0', 0x908 => '0.9.8', 0x1014 => '0.16.20', 0x1015 => '0.16.21') as $version => $label) {
			$settings = $this->makeSettings($version);
			$this->assertEquals('d.is_partially_done', $settings->getCommand('d.is_partially_done'), 'rTorrent '.$label.' asks the daemon directly');
		}
	}
}
