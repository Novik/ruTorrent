<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * plugins/seedingtime/init.php registers its event handlers and then asks the
 * daemon to fill a missing seedingtime on downloads that are already
 * complete. Whether that second request is sent, and what it carries, is only
 * visible from the other end of the socket: BackfillCommandTest pins the
 * command the plugin builds, this drives the real script against a daemon and
 * reads the requests that arrived.
 *
 * The driver sets iVersion without going through version detection, so no
 * alias map is loaded and command names reach the wire unmapped. The mapping
 * per rTorrent version is BackfillCommandTest's subject; what matters here is
 * that one expression serves both the handler and the pass, whatever its
 * names, so the expression is read off the wire rather than written out.
 */
class BackfillRequestTest extends TestCase
{
	private $base;
	private $tree;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-backfill-' . getmypid();
		$this->removeTree($this->base);
		@mkdir($this->base . '/profile/settings', 0700, true);
		$this->tree = $this->base . '/tree';
		$root = realpath(__DIR__ . '/../../..');
		foreach (array('php', 'conf', 'plugins/seedingtime') as $dir) {
			$this->copyTree($root . '/' . $dir, $this->tree . '/' . $dir);
		}
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

	private function copyTree($from, $to)
	{
		@mkdir($to, 0700, true);
		foreach (scandir($from) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			if (is_dir($from . '/' . $entry)) {
				$this->copyTree($from . '/' . $entry, $to . '/' . $entry);
				continue;
			}
			copy($from . '/' . $entry, $to . '/' . $entry);
		}
	}

	private function removeTree($path)
	{
		if (!is_dir($path)) {
			@unlink($path);
			return;
		}
		foreach (scandir($path) as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				$this->removeTree($path . '/' . $entry);
			}
		}
		@rmdir($path);
	}

	/** Runs init.php against a daemon giving $replies, one per request. */
	private function runInit($replies)
	{
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
		$driver = $this->base . '/drive-seedingtime.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($this->tree . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. 'chdir(' . var_export($this->tree . '/plugins/seedingtime', true) . ");\n"
			. 'require_once(' . var_export($this->tree . '/php/util.php', true) . ");\n"
			. 'require_once(' . var_export($this->tree . '/php/settings.php', true) . ");\n"
			. '$theSettings = rTorrentSettings::get();' . "\n"
			. '$theSettings->linkExist = true;' . "\n"
			. '$theSettings->iVersion = 0x1018;' . "\n"
			. '$theSettings->version = $theSettings->libVersion = \'0.16.24\';' . "\n"
			. '$theSettings->server = \'fake\';' . "\n"
			. '$plugin = array("name" => "seedingtime");' . "\n"
			. '$pInfo = array("perms" => array());' . "\n"
			. '$jResult = "";' . "\n"
			. 'require(' . var_export($this->tree . '/plugins/seedingtime/init.php', true) . ");\n"
			. 'echo "JRESULT:" . $jResult;' . "\n");
		return (string)shell_exec(
			escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver));
	}

	/** The first request body whose outer method is $method. */
	private function bodyOf($method)
	{
		foreach ($this->daemon->bodies() as $body) {
			if (strpos($body, '<methodName>' . $method . '</methodName>') !== false) {
				return $body;
			}
		}
		return '';
	}

	/** The last string parameter of the pass, which is its command. */
	private function passExpression($body)
	{
		preg_match_all('|<value><string>(.*)</string></value>|Us', $body, $values);
		return empty($values[1]) ? '' : end($values[1]);
	}

	public function testThePassIsSentAfterTheHandlersAreRegistered()
	{
		$output = $this->runInit(array(array('0', '0', '0'), array('')));
		$calls = $this->daemon->calls();
		$this->assertTrue($this->daemon->received('d.multicall'),
			'the pass reaches the daemon: ' . json_encode($calls) . ' ' . substr($output, 0, 200));
		$this->assertEquals('d.multicall', end($calls),
			'and it is sent after the handlers, not before: ' . json_encode($calls));
		$this->assertTrue(strpos($output, 'plugin.disable()') === false,
			'the plugin stays enabled: ' . substr($output, 0, 200));
	}

	public function testThePassCarriesTheMainViewAndTheHandlerExpression()
	{
		$this->runInit(array(array('0', '0', '0'), array('')));
		$pass = $this->bodyOf('d.multicall');
		$this->assertTrue(strpos($pass, '<value><string>main</string></value>') !== false,
			'the pass runs over every download: ' . substr($pass, 0, 400));

		$expression = $this->passExpression($pass);
		// Guards the comparison below: an empty or truncated match would
		// otherwise be found in any body at all.
		$this->assertTrue(strpos($expression, 'seedingtime') !== false
			&& strpos($expression, 'addtime') !== false
			&& substr_count($expression, 'branch=') === 2,
			'the pass writes a missing seedingtime from addtime: ' . $expression);

		// The handler covers downloads completing from now on and the pass
		// covers those already complete. One expression, or one of the two
		// populations is left without a seedingtime.
		$handlers = $this->bodyOf('system.multicall');
		$this->assertTrue(strpos($handlers, $expression) !== false,
			'the hash_done handler stores the same expression: ' . substr($handlers, 0, 800));
	}

	public function testNoPassWhenTheHandlersCouldNotBeRegistered()
	{
		// The handlers are refused, so the plugin disables itself; sending the
		// pass as well would only wait on the same daemon again.
		$output = $this->runInit(array(FakeRtorrentDaemon::fault('no such command'), array('')));
		$this->assertTrue(strpos($output, 'plugin.disable()') !== false,
			'the plugin reports it cannot start: ' . substr($output, 0, 200));
		$this->assertTrue(!$this->daemon->received('d.multicall'),
			'and no pass is sent: ' . json_encode($this->daemon->calls()));
	}
}
