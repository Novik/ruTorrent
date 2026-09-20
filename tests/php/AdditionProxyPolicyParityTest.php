<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/AdditionCallerFixtures.php');
require_once(__DIR__ . '/TorrentAdditionSourceScanner.php');
require_once(__DIR__ . '/../../php/rtorrent.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * The two allowlists that govern an addition name the same commands.
 *
 * An addition is an rtorrent command attached to a load call, and two separate
 * lists say which ones may be attached. rTorrent::ADDITION_COMMANDS answers for
 * the shipped plugins, which build theirs in php and reach rtorrent directly.
 * $XMLRPCProxySafeParams in conf/xmlrpc_proxy.php answers for a client that
 * sends its own load call through the proxy. The operation is the same one --
 * add this torrent, and put it in this view, this throttle group, this custom
 * field -- so a command one list holds and the other does not means the panel
 * can do something a client cannot, and the client is not told why.
 *
 * Two costs, and the second is the expensive one:
 *
 *   - Sent on its own, a command the proxy does not rebuild is stripped out of
 *     the call. The torrent is added without it: no view, no throttle group,
 *     no custom field, and no error anywhere.
 *   - Batched in a system.multicall, nothing is stripped, so the parameter goes
 *     to refusedCommandName() instead -- which splits the whole parameter,
 *     argument text included, and judges every word that follows. An ordinary
 *     two-word value whose second word starts with a refused prefix then reads
 *     as a call to it, and the batch is refused whole: a -501 naming a word out
 *     of somebody's throttle group, and every add in the batch lost. A command
 *     the proxy does rebuild never reaches that scan.
 *
 * The addition set is read out of the tree, and the alias tables out of
 * php/settings.php, so a plugin that builds a new addition and a daemon gate
 * that produces a new table are both covered here without this file changing.
 */
class AdditionProxyPolicyParityTest extends TestCase
{
	use AdditionCallerFixtures;

	/**
	 * An ordinary two-word value. Somebody's throttle group, ratio view or
	 * custom field: nothing in it is a command, and the second word begins
	 * with a refused prefix, which is all refusedCommandName() looks at.
	 */
	const ORDINARY_VALUE = 'evening catch-up';

	private $previousSettings;

	public function setUp()
	{
		$this->previousSettings = $this->currentSettings();
	}

	public function tearDown()
	{
		$this->restoreSettings($this->previousSettings);
	}

	/** $XMLRPCProxySafeParams exactly as conf/xmlrpc_proxy.php ships it. */
	private function shippedSafeParams()
	{
		$read = function ($file) {
			$XMLRPCProxySafeParams = null;
			require($file);
			return $XMLRPCProxySafeParams;
		};
		return (array)$read($this->repoRoot() . '/conf/xmlrpc_proxy.php');
	}

	/**
	 * Every addition the tree builds under one alias table, as
	 * array('name' => wire spelling, 'where' => file:line).
	 */
	private function additionsUnder($iVersion)
	{
		$this->installSettingsFor($iVersion);
		$scanner = new TorrentAdditionSourceScanner($this->repoRoot());
		$out = array();
		foreach ($scanner->callSites() as $site) {
			foreach ($site['elements'] as $element) {
				$name = TorrentAdditionSourceScanner::commandName($element);
				if ($name === null) {
					// Unreadable elements are TorrentAdditionCoverageTest's
					// failure, not this one's.
					continue;
				}
				$out[$name] = array('name' => $name,
					'where' => $site['file'] . ':' . $site['line']);
			}
		}
		ksort($out);
		return $out;
	}

	private function torrent()
	{
		return 'd8:announce20:http://tr.invalid/a4:infod6:lengthi12e4:name8:file.txt'
			. '12:piece lengthi16384e6:pieces20:' . str_repeat("\x01", 20) . 'ee';
	}

	private function value($text, $type = 'string')
	{
		return '<value><' . $type . '>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8')
			. '</' . $type . '></value>';
	}

	/** One load.raw_start carrying $parameters. */
	private function single($parameters)
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<methodCall><methodName>load.raw_start</methodName><params>'
			. '<param>' . $this->value('') . '</param>'
			. '<param>' . $this->value(base64_encode($this->torrent()), 'base64') . '</param>';
		foreach ($parameters as $parameter) {
			$xml .= '<param>' . $this->value($parameter) . '</param>';
		}
		return $xml . '</params></methodCall>';
	}

	/** One load.raw_start member per parameter, all in one system.multicall. */
	private function batched($parameters)
	{
		$members = '';
		foreach ($parameters as $parameter) {
			$members .= '<value><struct>'
				. '<member><name>methodName</name>'
				. '<value><string>load.raw_start</string></value></member>'
				. '<member><name>params</name><value><array><data>'
				. $this->value('')
				. $this->value(base64_encode($this->torrent()), 'base64')
				. $this->value($parameter)
				. '</data></array></value></member></struct></value>';
		}
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>' . $members
			. '</data></array></value></param></params></methodCall>';
	}

	private function decide($xml)
	{
		return XMLRPCProxy::decide($xml, 'sanitize', $this->shippedSafeParams(), false,
			array('directory' => array('root' => '/', 'resolve' => null)));
	}

	// ---- the fixtures are honest -----------------------------------------

	/**
	 * A scan that found nothing, or a policy file that defined nothing, would
	 * pass everything below. These are the controls that say both are read.
	 */
	public function testTheAdditionSetAndThePolicyAreBothRead()
	{
		$tables = $this->supportedVersions();
		$this->assertTrue(count($tables) >= 6,
			'php/settings.php produces several distinct alias tables; found ' . count($tables));

		$safe = $this->shippedSafeParams();
		$this->assertTrue(count($safe) > 0,
			'conf/xmlrpc_proxy.php defines $XMLRPCProxySafeParams');

		foreach ($tables as $iVersion => $version) {
			$additions = $this->additionsUnder($iVersion);
			$this->assertTrue(count($additions) >= 4,
				'the tree builds additions on rtorrent ' . $version . '; found '
					. count($additions));
		}
	}

	/**
	 * And the probe value really does trip the name scan, so a green below is
	 * the proxy rebuilding the command rather than the value being harmless.
	 * d.set_directory is on neither list, which is what makes it the control.
	 */
	public function testTheOrdinaryValueIsRefusedOnACommandTheProxyDoesNotRebuild()
	{
		$this->assertTrue(!in_array('d.set_directory', $this->shippedSafeParams(), true),
			'the control command is one the proxy does not rebuild');
		$decision = $this->decide($this->batched(array('d.set_directory=' . self::ORDINARY_VALUE)));
		$this->assertTrue($decision['action'] === 'reject',
			'batched, an ordinary value on a command the proxy does not rebuild is refused: '
				. implode(' ', $decision['log']));
	}

	// ---- the end condition -----------------------------------------------

	/**
	 * Every addition the shipped plugins build is a command the proxy rebuilds,
	 * on every alias table php/settings.php can produce.
	 */
	public function testEveryAdditionTheTreeBuildsIsOnTheProxyPolicy()
	{
		$safe = $this->shippedSafeParams();
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$absent = array();
			foreach ($this->additionsUnder($iVersion) as $addition) {
				if (!in_array($addition['name'], $safe, true)) {
					$absent[] = $addition['name'] . ' (' . $addition['where'] . ')';
				}
			}
			$this->assertTrue($absent === array(),
				'on rtorrent ' . $version . ' every addition the tree builds is a command name '
				. '$XMLRPCProxySafeParams allows'
				. (count($absent) ? '; absent: ' . implode(', ', $absent) : ''));
		}
	}

	/**
	 * Sent on its own, the command reaches rtorrent rather than being stripped
	 * out of the call.
	 */
	public function testNoAdditionIsStrippedOutOfASingleAdd()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			foreach ($this->additionsUnder($iVersion) as $addition) {
				$parameter = $addition['name'] . '=' . self::ORDINARY_VALUE;
				$decision = $this->decide($this->single(array($parameter)));
				$this->assertTrue($decision['action'] === 'send',
					'on rtorrent ' . $version . ' an add carrying ' . $addition['name']
						. ' is not refused');
				$this->assertTrue(strpos($decision['payload'], $addition['name']) !== false,
					'on rtorrent ' . $version . ' ' . $addition['name'] . ' from '
						. $addition['where'] . ' survives into the call sent to rtorrent: '
						. implode(' ', $decision['log']));
			}
		}
	}

	/**
	 * Batched, one ordinary value does not cost the whole batch. This is the
	 * shape a client sends when it adds several torrents at once.
	 */
	public function testABatchOfAddsIsNotRefusedForTheValuesTheAdditionsCarry()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$additions = $this->additionsUnder($iVersion);

			foreach ($additions as $addition) {
				$parameter = $addition['name'] . '=' . self::ORDINARY_VALUE;
				$decision = $this->decide($this->batched(array($parameter)));
				$this->assertTrue($decision['action'] === 'send',
					'on rtorrent ' . $version . ' a batched add carrying ' . $addition['name']
						. ' from ' . $addition['where'] . ' is not refused: '
						. implode(' ', $decision['log']));
			}

			// And all of them together, which is the whole batch a client loses.
			$parameters = array();
			foreach ($additions as $addition) {
				$parameters[] = $addition['name'] . '=' . self::ORDINARY_VALUE;
			}
			$decision = $this->decide($this->batched($parameters));
			$this->assertTrue($decision['action'] === 'send',
				'on rtorrent ' . $version . ' a batch of ' . count($parameters)
					. ' adds, one addition each, is not refused: '
					. implode(' ', $decision['log']));
		}
	}

	// ---- and the refusals still refuse -----------------------------------

	/**
	 * Widening what the proxy rebuilds does not widen what it allows. The
	 * execution primitives stay refused, batched alongside an addition and on
	 * their own.
	 */
	public function testTheRefusedCommandsStayRefusedAlongsideAnAddition()
	{
		$refused = array('execute=/bin/id', 'execute2=/bin/id', 'import=/etc/passwd',
			'try_import=/etc/passwd', 'method.insert=x,simple,"d.name="',
			'schedule2=x,0,0,"d.name="', 'catch=/bin/id', 'log.open_file=x,/tmp/x',
			'network.scgi.open_port=0.0.0.0:5000', 'session.path.set=/tmp',
			'directory.default.set=/', 'system.env=PATH');

		foreach ($this->supportedVersions() as $iVersion => $version) {
			$additions = $this->additionsUnder($iVersion);
			$first = reset($additions);
			foreach ($refused as $parameter) {
				$decision = $this->decide($this->batched(
					array($first['name'] . '=' . self::ORDINARY_VALUE, $parameter)));
				$this->assertTrue($decision['action'] === 'reject',
					'on rtorrent ' . $version . ' a batch carrying ' . $parameter
						. ' is refused even beside an addition');
			}
		}
	}

	/**
	 * An addition whose argument would be called rather than stored is still
	 * refused: quoting cannot make a '$'-introduced argument safe, and
	 * rebuildSafeLoadParam drops the parameter, which puts it back in front of
	 * the name scan.
	 */
	public function testAnAdditionCarryingACalledArgumentIsStillRefused()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			foreach ($this->additionsUnder($iVersion) as $addition) {
				$decision = $this->decide($this->batched(
					array($addition['name'] . '=$execute=/bin/id')));
				$this->assertTrue($decision['action'] === 'reject',
					'on rtorrent ' . $version . ' ' . $addition['name']
						. ' carrying a called argument is refused');
			}
		}
	}
}
