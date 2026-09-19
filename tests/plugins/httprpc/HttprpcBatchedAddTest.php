<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * The batched shape reaches rtorrent, at the door that answers for it.
 *
 * plugins/httprpc/action.php is where a raw XMLRPC body meets the policy: its
 * default branch loads conf/xmlrpc_proxy.php, calls XMLRPCProxy::decide() and
 * answers a refusal with 403 and a -501 fault, without opening a connection to
 * rtorrent at all. So whether a call was refused is visible from two sides
 * here -- what the script printed, and whether the daemon was ever asked
 * anything -- and neither can be read off decide() alone.
 *
 * The call is three adds in one system.multicall, the shape a client sends
 * when it adds several torrents at once, with the middle one labelled
 * "catch-up tv". Refusing a batch refuses all of it, so the other two are the
 * measure of the cost.
 */
class HttprpcBatchedAddTest extends TestCase
{
	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-httprpc-batch-' . getmypid();
		@mkdir($this->base . '/profile/settings', 0700, true);
	}

	public function tearDown()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
		$this->removeTree($this->base);
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
		return realpath(__DIR__ . '/../../..');
	}

	private function torrent()
	{
		return 'd8:announce20:http://tr.invalid/a4:infod6:lengthi12e4:name8:file.txt'
			. '12:piece lengthi16384e6:pieces20:' . str_repeat("\x01", 20) . 'ee';
	}

	/** Three load.raw_start members, one label each. */
	private function batchedAdd($labels)
	{
		$members = '';
		foreach ($labels as $label) {
			$members .= '<value><struct>'
				. '<member><name>methodName</name>'
				. '<value><string>load.raw_start</string></value></member>'
				. '<member><name>params</name><value><array><data>'
				. '<value><string></string></value>'
				. '<value><base64>' . base64_encode($this->torrent()) . '</base64></value>'
				. '<value><string>d.custom1.set=&quot;' . $label . '&quot;</string></value>'
				. '</data></array></value></member></struct></value>';
		}
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>' . $members
			. '</data></array></value></param></params></methodCall>';
	}

	/** Runs plugins/httprpc/action.php against the daemon; returns its output. */
	private function post($body)
	{
		$root = $this->repoRoot();
		if ($this->daemon !== null) {
			$this->daemon->stop();
		}
		$this->daemon = new FakeRtorrentDaemon(array(array(0, 0, 0)),
			$this->base . '/calls.log');

		$driver = $this->base . '/drive-httprpc.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. '$topDirectory = "/";' . "\n"
			. '$HTTP_RAW_POST_DATA = ' . var_export($body, true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/httprpc', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/httprpc/action.php', true) . ");\n");

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	public function testABatchOfAddsWithAnOrdinaryLabelReachesRtorrent()
	{
		$out = $this->post($this->batchedAdd(array('tv', 'catch-up tv', 'films')));

		$this->assertTrue(strpos($out, 'rejected by this server') === false,
			'the door did not refuse the batch: ' . substr($out, 0, 200));
		$this->assertTrue($this->daemon->received('load.raw_start'),
			'rtorrent was asked to load the batch; it was asked for '
				. json_encode($this->daemon->calls()));
		$this->assertTrue(count($this->daemon->calls()) === 3,
			'all three adds reached rtorrent, not some of them: '
				. json_encode($this->daemon->calls()));
	}

	public function testABatchCarryingExecuteIsStillRefusedAtTheDoor()
	{
		// The other half: the door still refuses, and refuses without opening a
		// connection, so a refusal cannot be mistaken for rtorrent being down.
		$body = str_replace('d.custom1.set=&quot;catch-up tv&quot;', 'execute=/bin/id',
			$this->batchedAdd(array('tv', 'catch-up tv', 'films')));
		$out = $this->post($body);

		$this->assertTrue(strpos($out, "The command 'execute' was rejected by this server") !== false,
			'the door refuses execute and names it: ' . substr($out, 0, 200));
		$this->assertTrue($this->daemon->calls() === array(),
			'rtorrent was never asked: ' . json_encode($this->daemon->calls()));
	}

	public function testTheLabelSurvivesToRtorrentUnchanged()
	{
		// Refusing was one failure; quietly dropping the label would be the
		// other. The payload forwarded is the caller's own bytes.
		$out = $this->post($this->batchedAdd(array('catch-up tv')));

		$this->assertTrue(strpos($out, 'rejected by this server') === false,
			'a single-member batch with that label is not refused');
		$this->assertTrue($this->daemon->calls() === array('load.raw_start'),
			'the add reached rtorrent: ' . json_encode($this->daemon->calls()));
	}
}
