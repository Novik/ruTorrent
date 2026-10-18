<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/RtorrentProbeFixture.php');

/**
 * What rTorrentSettings::get() hands back when share/settings/rtorrent.dat is
 * not there to read.
 *
 * Only obtain() learns the daemon's version and its command aliases, and only
 * php/getplugins.php and php/initplugins.php call it. Every other entry point
 * -- a plugin action, an httprpc mode, a client posting straight to the
 * plugin -- reads the cache and takes it at its word. Before the first
 * getplugins.php of a profile's life, and on a profile the web server cannot
 * write, there is nothing to read: iVersion is null and the alias map is
 * empty, so getCommand() answers with the pre-0.9 spelling of every renamed
 * command and patchDeprecatedCommand() leaves out the target argument that
 * d.multicall has taken since 0.16.16.
 *
 * The singleton is per process, so each case runs in one of its own -- and in
 * a profile of its own, because a probe caches what it learned and setUp() runs
 * once for the file rather than once per case.
 */
class SettingsColdCacheTest extends TestCase
{
	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-cold-cache-' . getmypid();
		$this->removeTree($this->base);
	}

	public function tearDown()
	{
		$this->stopDaemon();
		$this->removeTree($this->base);
	}

	private function stopDaemon()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
	}

	private function removeTree($path)
	{
		if (!is_dir($path)) {
			return;
		}
		foreach (glob($path . '/*') as $entry) {
			is_dir($entry) ? $this->removeTree($entry) : @unlink($entry);
		}
		@rmdir($path);
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../..');
	}

	/**
	 * Runs $code in a process of its own, with a fresh profile and a daemon
	 * answering as rtorrent 0.16.20. Returns the process output; what the
	 * daemon was asked is in $this->daemon.
	 */
	private function drive($code, $profile)
	{
		$this->stopDaemon();
		$root = $this->repoRoot();
		@mkdir($profile . '/settings', 0700, true);
		$this->daemon = new FakeRtorrentDaemon(RtorrentProbeFixture::responder(),
			$this->base . '/calls.log');

		$driver = $this->base . '/drive.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($profile, true) . ";\n"
			. '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
			. '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string)$this->daemon->port(), true) . ";\n"
			. 'require_once(' . var_export($root . '/php/xmlrpc.php', true) . ");\n"
			. $code . "\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	/** How many times the daemon was asked for its version. */
	private function probes()
	{
		return count(array_keys($this->daemon->calls(), 'system.client_version', true));
	}

	public function testAColdCacheIsProbedRatherThanBelieved()
	{
		$out = $this->drive(
			'echo rTorrentSettings::get()->iVersion, "\n";'
			. 'echo rTorrentSettings::get()->version, "\n";',
			$this->base . '/version');

		$this->assertEquals("4116\n0.16.20", $out,
			'with no cache file, get() reads the version off the daemon: got ' . json_encode($out));
		$this->assertEquals(1, $this->probes(),
			'and it asked for it: ' . json_encode($this->daemon->calls()));
	}

	public function testAColdCacheStillResolvesRenamedCommands()
	{
		$out = $this->drive('echo getCmd("set_download_rate");', $this->base . '/aliases');

		$this->assertEquals('throttle.global_down.max_rate.set', $out,
			'the alias map is available to the first caller, not only to the second: '
				. json_encode($out));
	}

	public function testAColdCacheStillCarriesTheMulticallTarget()
	{
		// The defect as the httprpc list mode meets it: d.multicall takes
		// (target, view, command...) from 0.16.16, and the empty target is
		// prepended from the alias map. Without the map "main" lands in the
		// target and rtorrent refuses the call.
		$out = $this->drive(
			'$cmd = new rXMLRPCCommand("d.multicall", "main");'
			. 'echo json_encode(array_map(function($p){return $p->value;}, $cmd->params));',
			$this->base . '/target');

		$this->assertEquals('["","main"]', $out,
			'the target argument is prepended on a cold cache too: ' . json_encode($out));
	}

	public function testTheProbeHappensOnceAcrossAWholeRequest()
	{
		// get() is consulted by every command a script builds -- the list mode
		// alone builds 36 of them. The cold-cache probe has to be the
		// singleton's, not each caller's.
		$out = $this->drive(
			'for($i = 0; $i<20; $i++) new rXMLRPCCommand("d.multicall", "main");'
			. 'echo rTorrentSettings::get()->version;',
			$this->base . '/once');

		$this->assertEquals(RtorrentProbeFixture::VERSION, $out,
			'the settings are available throughout: ' . json_encode($out));
		$this->assertEquals(1, $this->probes(),
			'the daemon was asked for its version once, not once per caller: '
				. json_encode($this->probes()));
	}

	public function testAWarmCacheIsNotProbed()
	{
		// The cost of the probe is paid where there is nothing to read. An
		// install that has a cache file keeps reading it.
		$profile = $this->base . '/warm';
		$this->drive('echo rTorrentSettings::get()->version;', $profile);
		$this->assertTrue(is_file($profile . '/settings/rtorrent.dat'),
			'the probe stored what it learned');

		$out = $this->drive('echo rTorrentSettings::get()->version;', $profile);
		$this->assertEquals(RtorrentProbeFixture::VERSION, $out,
			'the second process reads the cache: ' . json_encode($out));
		$this->assertEquals(0, $this->probes(),
			'and asks the daemon nothing: ' . json_encode($this->daemon->calls()));
	}

	public function testACacheFileThatRecordsNoVersionIsProbedToo()
	{
		// Not only a missing file. store() has not always refused to cache a
		// probe that failed, so a profile carried over from an older install
		// can hold an object reporting version 0 and no aliases -- which is
		// the same nothing, written down.
		$profile = $this->base . '/hollow';
		$this->drive(
			'$s = (new ReflectionClass("rTorrentSettings"))->newInstanceWithoutConstructor();'
			. '$s->iVersion = 0; $s->linkExist = false; $s->aliases = array();'
			. 'echo (int)(new rCache())->set($s);',
			$profile);
		$this->assertTrue(is_file($profile . '/settings/rtorrent.dat'),
			'the hollow cache file is in place');

		$out = $this->drive('echo rTorrentSettings::get()->version;', $profile);
		$this->assertEquals(RtorrentProbeFixture::VERSION, $out,
			'a cache recording no version is replaced by a probe: ' . json_encode($out));
		$this->assertEquals(1, $this->probes(),
			'which took one probe: ' . json_encode($this->daemon->calls()));
	}

	public function testADaemonThatAnswersNothingIsAskedOnceNotOncePerCommand()
	{
		// The probe has to be the singleton's. A probe that learns nothing
		// leaves iVersion unset, so a check made per call rather than per
		// process would dial the daemon again for every command the script
		// builds -- 36 of them in the httprpc list mode alone, each waiting
		// on the socket timeout.
		$this->stopDaemon();
		$root = $this->repoRoot();
		$profile = $this->base . '/silent';
		@mkdir($profile . '/settings', 0700, true);
		// A daemon that accepts the connection and says nothing back.
		$this->daemon = new FakeRtorrentDaemon(function () { return array(); },
			$this->base . '/calls.log');

		$driver = $this->base . '/drive-silent.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($profile, true) . ";\n"
			. '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
			. '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string)$this->daemon->port(), true) . ";\n"
			. 'require_once(' . var_export($root . '/php/xmlrpc.php', true) . ");\n"
			. 'for($i = 0; $i<20; $i++) new rXMLRPCCommand("d.multicall", "main");' . "\n"
			. 'echo (int)is_null(rTorrentSettings::get()->iVersion);' . "\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);

		$this->assertEquals('1', implode("\n", $output),
			'nothing was learned: ' . json_encode(implode("\n", $output)));
		$this->assertEquals(1, count($this->daemon->bodies()),
			'and the daemon was asked once for the 20 commands, not once each: '
				. json_encode(count($this->daemon->bodies())));
		$this->assertTrue(!is_file($profile . '/settings/rtorrent.dat'),
			'nothing was cached for the next request to believe');
	}

	public function testAnUnreachableDaemonLeavesTheCallerWorkingAndTheCacheEmpty()
	{
		// There is nothing to learn from a daemon that is not there, so the
		// command keeps the shape it had before -- the caller must still get
		// an object back, and nothing may be written for the next request to
		// believe.
		$this->stopDaemon();
		$root = $this->repoRoot();
		$profile = $this->base . '/unreachable';
		@mkdir($profile . '/settings', 0700, true);
		// A port nothing listens on: taken, then released.
		$probe = stream_socket_server('tcp://127.0.0.1:0');
		$name = stream_socket_get_name($probe, false);
		$dead = (int)substr($name, strrpos($name, ':') + 1);
		fclose($probe);

		$driver = $this->base . '/drive-dead.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($profile, true) . ";\n"
			. '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
			. '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string)$dead, true) . ";\n"
			. 'require_once(' . var_export($root . '/php/xmlrpc.php', true) . ");\n"
			. '$cmd = new rXMLRPCCommand("d.multicall", "main");' . "\n"
			. 'echo (int)is_null(rTorrentSettings::get()->iVersion), " ", count($cmd->params);' . "\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);

		$this->assertEquals('1 1', implode("\n", $output),
			'an unreachable daemon leaves the version unknown and the command buildable: '
				. json_encode(implode("\n", $output)));
		$this->assertTrue(!is_file($profile . '/settings/rtorrent.dat'),
			'a probe that learned nothing is not cached');
	}
}
