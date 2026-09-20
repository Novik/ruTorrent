<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * Three shapes that carry a command past the name this side classified, and
 * what each of them puts on the wire.
 *
 * The question every case here asks is the payload, not the verdict. "It was
 * forwarded untrusted" is only an answer on a daemon that acts on
 * UNTRUSTED_CONNECTION, which is 0.16.10 and later; below that the header is
 * read and ignored, and what the payload says is what runs. So each case
 * states the bytes: the ones that must not be in them, the ones that must, and
 * the trust the connection carrying them is given.
 *
 *  - a system.multicall member's command parameter, which was judged by
 *    rebuilding it and then sent in the caller's own spelling;
 *  - a directory setter called as a call rather than written as a command
 *    string, under either of the spellings a daemon registers it under;
 *  - an evaluator, whose ordinary arguments rtorrent runs as commands.
 */
class XMLRPCProxyCommandCarrierTest extends TestCase
{
	private $root = '/torrents1';

	/** The command names conf/xmlrpc_proxy.php ships, near enough. */
	private function safeParams()
	{
		return array(
			'd.custom1.set', 'd.custom.set', 'd.directory.set',
			'd.directory_base.set', 'd.priority.set', 'd.open', 'd.close',
			'd.start', 'd.stop', 'd.set_custom', 'd.set_connection_seed',
		);
	}

	private function decide($xml, $confined = true)
	{
		$options = $confined
			? array('directory' => array('root' => $this->root, 'resolve' => null))
			: array();
		return XMLRPCProxy::decide($xml, 'sanitize', $this->safeParams(), false, $options);
	}

	private function call($method, $params)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . $method
			. '</methodName><params>';
		foreach($params as $p)
			$xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
				. '</string></value></param>';
		return $xml . '</params></methodCall>';
	}

	private function systemMulticall($members)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall'
			. '</methodName><params><param><value><array><data>';
		foreach($members as $member)
		{
			$xml .= '<value><struct><member><name>methodName</name><value><string>'
				. htmlspecialchars($member[0], ENT_NOQUOTES)
				. '</string></value></member>'
				. '<member><name>params</name><value><array><data>';
			foreach($member[1] as $p)
				$xml .= '<value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
					. '</string></value>';
			$xml .= '</data></array></value></member></struct></value>';
		}
		return $xml . '</data></array></value></param></params></methodCall>';
	}

	/**
	 * Refused here, not handed to rtorrent with a header to sort out: nothing
	 * is sent, nothing is trusted, and the command named in the refusal is the
	 * one the caller wrote.
	 */
	private function assertRefused($decision, $method, $what)
	{
		$this->assertTrue($decision['action'] === 'reject',
			$what . ' is refused (action=' . $decision['action'] . ')');
		$this->assertTrue($decision['payload'] === '',
			$what . ' sends no payload');
		$this->assertTrue($decision['trusted'] === false,
			$what . ' is not trusted');
		$this->assertTrue($decision['method'] === $method,
			$what . ' names ' . $method . ' (named ' . var_export($decision['method'], true) . ')');
	}

	/**
	 * Forwarded, with the bytes stated rather than assumed.
	 */
	private function assertForwarded($decision, $absent, $present, $what)
	{
		$this->assertTrue($decision['action'] === 'send',
			$what . ' is forwarded (action=' . $decision['action'] . ')');
		$this->assertTrue($decision['trusted'] === false,
			$what . ' is forwarded untrusted');
		foreach((array)$absent as $bytes)
			$this->assertTrue(strpos($decision['payload'], $bytes) === false,
				$what . ' does not put ' . var_export($bytes, true) . ' on the wire');
		foreach((array)$present as $bytes)
			$this->assertTrue(strpos($decision['payload'], $bytes) !== false,
				$what . ' puts ' . var_export($bytes, true) . ' on the wire');
	}

	// ------------------------------------------------------------------
	// A system.multicall member's command parameters.
	// ------------------------------------------------------------------

	/**
	 * Rebuilding quotes each argument, so a ';' inside one stops ending the
	 * command. That is true of the rebuilt string and of nothing else: the
	 * original is two commands, and it was the original that was sent.
	 */
	public function testASystemMulticallSendsTheParameterItValidated()
	{
		$decision = $this->decide($this->systemMulticall(array(
			array('d.multicall', array('main', '', 'd.custom1.set=x;execute=cat,/etc/passwd')))));

		$this->assertForwarded($decision,
			'd.custom1.set=x;execute=cat,/etc/passwd',
			'd.custom1.set="x;execute=cat","/etc/passwd"',
			'a label carrying a second command');
	}

	/**
	 * And the same for the setter the boundary is about: the first path is
	 * inside the root, which is what made the rebuild accept the parameter,
	 * and the second is the one that was going to be run.
	 */
	public function testASystemMulticallCannotSmuggleASecondDirectorySetter()
	{
		$decision = $this->decide($this->systemMulticall(array(
			array('d.multicall', array('main', '',
				'd.directory.set=/torrents1/a;d.directory.set=/var/www/html')))));

		$this->assertForwarded($decision,
			'd.directory.set=/torrents1/a;d.directory.set=/var/www/html',
			'd.directory.set="/torrents1/a;d.directory.set=/var/www/html"',
			'a directory carrying a second directory setter');
	}

	/**
	 * What is sent is what this side would accept if it arrived: deciding on
	 * the payload again changes nothing, so the rebuild is the fixed point and
	 * not one more representation.
	 */
	public function testTheRebuiltPayloadIsWhatThisSideWouldAccept()
	{
		$first = $this->decide($this->systemMulticall(array(
			array('d.multicall', array('main', '', 'd.custom1.set=x;execute=cat,/etc/passwd')))));
		$second = $this->decide($first['payload']);

		$this->assertTrue($second['action'] === 'send',
			'the rebuilt payload is accepted on its own terms');
		$this->assertTrue($second['payload'] === $first['payload'],
			'the rebuilt payload is left alone the second time');
	}

	/**
	 * A parameter no rebuild accepts is still the caller's own bytes, and the
	 * types around it are still the caller's own types — a base64 torrent does
	 * not become a string on the way through.
	 */
	public function testWhatIsNotRebuiltIsCopied()
	{
		$torrent = base64_encode("d8:announce4:teste");
		$request = '<?xml version="1.0"?><methodCall><methodName>system.multicall'
			. '</methodName><params><param><value><array><data>'
			. '<value><struct><member><name>methodName</name><value><string>d.multicall'
			. '</string></value></member><member><name>params</name><value><array><data>'
			. '<value><string>main</string></value>'
			. '<value><base64>' . $torrent . '</base64></value>'
			. '<value><string>d.custom1.set=keep</string></value>'
			. '<value><string>d.name=</string></value>'
			. '</data></array></value></member></struct></value>'
			. '<value><struct><member><name>methodName</name><value><string>system.listMethods'
			. '</string></value></member><member><name>params</name><value><array><data>'
			. '<value><string>x</string></value>'
			. '</data></array></value></member></struct></value>'
			. '</data></array></value></param></params></methodCall>';

		$decision = $this->decide($request);

		$this->assertForwarded($decision, array(), array(
			'<base64>' . $torrent . '</base64>',   // the torrent keeps its type
			'<string>d.name=</string>',            // a read command is not rebuilt away
			'system.listMethods',                  // a member this does not classify is kept
			'd.custom1.set="keep"',                // and the one it does rebuild is rebuilt
		), 'a mixed system.multicall');
	}

	/**
	 * A call carrying no command parameter has nothing to rebuild, so it goes
	 * on as it arrived rather than being re-emitted for no reason.
	 */
	public function testASystemMulticallWithNothingToRebuildIsForwardedVerbatim()
	{
		$request = $this->systemMulticall(array(
			array('d.name', array(str_repeat('A', 40)))));
		$decision = $this->decide($request);

		$this->assertTrue($decision['action'] === 'send', 'a read multicall is forwarded');
		$this->assertTrue($decision['payload'] === $request,
			'a read multicall is forwarded byte for byte');
		$this->assertTrue($decision['trusted'] === false, 'and untrusted');
	}

	// ------------------------------------------------------------------
	// Directory setters called as calls.
	// ------------------------------------------------------------------

	/**
	 * Both setters, under the name a 0.16.x daemon registers and the name a
	 * 0.9.x one does. php/methods-0.9.4.php is what says they are the same
	 * command.
	 */
	private function directorySpellings()
	{
		return array('d.directory.set', 'd.set_directory',
			'd.directory_base.set', 'd.set_directory_base');
	}

	public function testATopLevelDirectorySetterOutsideTheRootIsRefused()
	{
		foreach($this->directorySpellings() as $name)
		{
			$decision = $this->decide($this->call($name,
				array(str_repeat('A', 40), '/var/www/html')));
			$this->assertRefused($decision, $name, 'a top-level ' . $name . ' to /var/www/html');
			$this->assertTrue(strpos($decision['payload'], '/var/www/html') === false,
				$name . ' does not put /var/www/html on the wire');
		}
	}

	public function testATopLevelDirectorySetterInsideTheRootIsForwarded()
	{
		foreach($this->directorySpellings() as $name)
		{
			$request = $this->call($name, array(str_repeat('A', 40), '/torrents1/ok'));
			$decision = $this->decide($request);
			$this->assertTrue($decision['action'] === 'send',
				'a top-level ' . $name . ' inside the root is forwarded');
			$this->assertTrue($decision['payload'] === $request,
				'a top-level ' . $name . ' inside the root is forwarded byte for byte');
			$this->assertTrue($decision['trusted'] === false,
				'a top-level ' . $name . ' inside the root is not trusted');
		}
	}

	public function testASystemMulticallMemberDirectorySetterIsConfined()
	{
		foreach($this->directorySpellings() as $name)
		{
			$decision = $this->decide($this->systemMulticall(array(
				array($name, array(str_repeat('A', 40), '/var/www/html')))));
			$this->assertRefused($decision, $name,
				'a system.multicall member ' . $name . ' to /var/www/html');
		}
	}

	public function testACommandStringDirectorySetterIsConfinedUnderEverySpelling()
	{
		foreach($this->directorySpellings() as $name)
		{
			$decision = $this->decide($this->call('d.multicall',
				array('main', '', $name . '=/var/www/html')));
			$this->assertRefused($decision, $name,
				'a d.multicall carrying ' . $name . '=/var/www/html');

			$decision = $this->decide($this->systemMulticall(array(
				array('d.multicall', array('main', '', $name . '=/var/www/html')))));
			$this->assertRefused($decision, $name,
				'a system.multicall carrying ' . $name . '=/var/www/html');
		}
	}

	/**
	 * A caller that states no boundary is not policed, which is what every
	 * caller had before the confinement existed.
	 */
	public function testNoStatedBoundaryIsNotPoliced()
	{
		$decision = $this->decide($this->call('d.directory.set',
			array(str_repeat('A', 40), '/var/www/html')), false);
		$this->assertTrue($decision['action'] === 'send',
			'with no boundary stated a directory setter is forwarded');
		$this->assertTrue($decision['trusted'] === false, 'and untrusted');
	}

	/**
	 * The global setter is a different command with a different answer: it is
	 * refused outright, under both spellings, rather than confined.
	 */
	public function testTheGlobalDirectorySetterIsRefusedOutright()
	{
		foreach(array('directory.default.set', 'set_directory') as $name)
			$this->assertRefused($this->decide($this->call($name, array('/torrents1/ok'))),
				$name, 'the global setter ' . $name);
	}

	// ------------------------------------------------------------------
	// Evaluators.
	// ------------------------------------------------------------------

	/**
	 * Every name in $evaluatorMethods, enumerated from the property rather
	 * than written out here, so one added later is covered by this file
	 * without it being edited.
	 */
	private function evaluators()
	{
		$property = new ReflectionProperty('XMLRPCProxy', 'evaluatorMethods');
		$property->setAccessible(true);
		return $property->getValue();
	}

	public function testTheEvaluatorsAreEnumerated()
	{
		$evaluators = $this->evaluators();
		$this->assertTrue(count($evaluators) > 0, 'the evaluator list was read');
		$this->assertTrue(in_array('branch', $evaluators, true),
			'branch is one of them');
	}

	public function testATopLevelEvaluatorCannotCarryARefusedCommand()
	{
		foreach($this->evaluators() as $name)
		{
			$decision = $this->decide($this->call($name,
				array(str_repeat('A', 40), 'd.is_active=', 'execute=cat,/etc/passwd')));
			$this->assertRefused($decision, 'execute',
				'a top-level ' . $name . ' carrying execute');
		}
	}

	public function testASystemMulticallMemberEvaluatorCannotCarryARefusedCommand()
	{
		foreach($this->evaluators() as $name)
		{
			$decision = $this->decide($this->systemMulticall(array(
				array($name, array(str_repeat('A', 40), 'd.is_active=', 'import=/tmp/evil.rc')))));
			$this->assertRefused($decision, 'import',
				'a system.multicall member ' . $name . ' carrying import');
		}
	}

	/**
	 * The first argument counts too: branch runs its conditional, so a command
	 * there is as good as one in a branch.
	 */
	public function testAnEvaluatorIsReadFromItsFirstArgument()
	{
		$this->assertRefused($this->decide($this->call('branch',
			array('execute=cat,/etc/passwd', 'd.name=', 'd.name='))),
			'execute', 'a branch whose conditional is execute');
	}

	public function testAnEvaluatorCannotCarryADirectoryOutsideTheRoot()
	{
		$this->assertRefused($this->decide($this->call('branch',
			array(str_repeat('A', 40), 'd.is_active=', 'd.directory.set=/var/www/html'))),
			'd.directory.set', 'a branch carrying a directory outside the root');
	}

	/**
	 * And the call ruTorrent itself makes through this door still goes
	 * through: js/rtorrent.js builds this for the superseed toggle, and with
	 * the httprpc plugin loaded theURLs.XMLRPCMountPoint is this proxy. A
	 * refusal of the evaluators by name would have stopped it.
	 */
	public function testRuTorrentsOwnSuperseedBranchIsStillForwarded()
	{
		$request = $this->call('branch', array(
			str_repeat('A', 40),
			'd.is_active=',
			'cat=$d.stop=,$d.close=,$d.set_connection_seed=seed,$d.open=,$d.start=',
			'd.set_connection_seed=seed'));
		$decision = $this->decide($request);

		$this->assertTrue($decision['action'] === 'send',
			'the superseed branch is forwarded');
		$this->assertTrue($decision['payload'] === $request,
			'the superseed branch is forwarded byte for byte');
		$this->assertTrue($decision['trusted'] === false,
			'the superseed branch is forwarded untrusted');
	}

	/**
	 * One command ends and the next begins at ';' as well as at a newline, so
	 * a name after one is a name this has to read.
	 */
	public function testACommandStringEndsAtASemicolon()
	{
		$this->assertTrue(XMLRPCProxy::refusedCommandName('d.name=;execute=id') === 'execute',
			"a ';' starts a new command and the name after it is judged");
		$this->assertTrue(XMLRPCProxy::refusedCommandName('d.custom1.set=season 1') === null,
			'and ordinary argument text is still not judged');
	}
}
