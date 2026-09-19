<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');

/**
 * A command's name is written into the <methodName> element of the XMLRPC
 * payload by plain string interpolation, in both shapes rXMLRPCRequest can
 * emit: the single call and the system.multicall struct. Nothing escapes it,
 * so a name carrying "</methodName>" closes that element early and the rest of
 * the name is parsed as XML -- a second <methodCall> among other things, which
 * the daemon executes on the same connection as the first.
 *
 * Names are not all internal constants. plugins/httprpc/action.php builds them
 * from request input by concatenation ('set_'.substr($s,1) and "d.set_".$s for
 * the setsettings and setprops modes) and hands its cmd= parameter straight to
 * rXMLRPCCommand as a name in its glbl, ttl and prp modes.
 *
 * A method name in rtorrent is [A-Za-z0-9_.]+ and nothing else: of the 1001
 * names system.listMethods reports on 0.9.8, none carries another character.
 * So the boundary these tests fix is that shape -- a name matching it is built
 * and sent unchanged, a name that does not match is refused, and a refused name
 * never reaches a payload.
 */
/**
 * run() clears the payload on its way out, so a test that reads $content
 * afterwards sees an empty string whatever happened. This keeps a copy of every
 * payload run() built, which is every payload it tried to send: send() is
 * called on nothing else.
 */
class RecordingXMLRPCRequest extends rXMLRPCRequest
{
	public $payloads = array();

	protected function makeNextCall()
	{
		$more = parent::makeNextCall();
		if ($more) {
			$this->payloads[] = $this->content;
		}
		return $more;
	}
}

class XMLRPCCommandNameTest extends TestCase
{
	/**
	 * Closes <methodName>, closes the call, and opens a second one. What lands
	 * in the second call is whatever the daemon will run; execute.throw is the
	 * one that makes the point.
	 */
	const INJECTED = 'd.name</methodName><params></params></methodCall>'
		. '<methodCall><methodName>execute.throw</methodName><params>'
		. '<param><value><string>/bin/sh</string></value></param></params></methodCall>'
		. '<methodCall><methodName>d.name';

	/** Names a real daemon answers to, one per family that exists. */
	const REAL_NAMES = array(
		'd.multicall', 'd.multicall2', 'f.multicall', 't.multicall', 'p.multicall',
		'd.name', 'd.get_name', 'd.custom1.set', 'd.set_custom1', 'd.set_custom5',
		'd.connection_seed.set', 'd.views.push_back_unique', 'd.is_multi_file',
		'system.api_version', 'system.client_version', 'system.listMethods',
		'system.methodExist', 'system.methodSignature', 'system.getCapabilities',
		'system.sockets.files.max_alloc.limit', 'system.sockets.http.min_alloc.limit',
		'network.listen.port.set', 'network.http.current_open', 'network.open_sockets',
		'group.insert_persistent_view', 'group.rat_5.ratio.command',
		'view.set_visible', 'view_list', 'throttle_up', 'throttle_down',
		'load.raw_start', 'load_raw_start', 'schedule2', 'convert.kb',
		'p.banned.set', 'ratio.min.set', 'file.prioritize_toc', 'add_peer',
		'dht', 'dht_statistics', 'cat', 'branch', 'get_down_total',
		// Refusing these is the proxy's job, not this check's: they are
		// well-formed names and have to stay constructible.
		'execute', 'execute.throw', 'execute_nothrow', 'import', 'catch',
		'method.set_key', 'system.shutdown',
	);

	private $previousSettings;

	public function setUp()
	{
		// rTorrentSettings' constructor talks to a daemon. The three fields the
		// name path reads are set by hand on an uninitialised instance instead.
		$reflection = new ReflectionClass('rTorrentSettings');
		$settings = $reflection->newInstanceWithoutConstructor();
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();

		$property = $reflection->getProperty('theSettings');
		$property->setAccessible(true);
		$this->previousSettings = $property->getValue();
		$property->setValue(null, $settings);

		// Named so that a payload this test manages to send goes to a closed
		// port on the loopback rather than anywhere real.
		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['scgi_port'] = 1;
		$GLOBALS['rpcTimeOut'] = 1;
		$GLOBALS['rpcLogCalls'] = false;
	}

	public function tearDown()
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		$property = $reflection->getProperty('theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $this->previousSettings);
	}

	/**
	 * A command whose name was never checked, for the tests that ask what the
	 * request does with one. Constructing it the ordinary way is refused once
	 * the fix is in, which is a different claim and has its own tests.
	 */
	private function unvalidatedCommand($name, $params = array())
	{
		$command = (new ReflectionClass('rXMLRPCCommand'))->newInstanceWithoutConstructor();
		$command->command = $name;
		$command->params = array();
		foreach ($params as $value) {
			$command->params[] = new rXMLRPCParam('string', $value);
		}
		return $command;
	}

	/** The payload rXMLRPCRequest built, or '' if it built none. */
	private function payloadOf($request)
	{
		$property = new ReflectionProperty('rXMLRPCRequest', 'content');
		$property->setAccessible(true);
		return (string)$property->getValue($request);
	}

	/** Build the payload for these commands without sending it. */
	private function buildPayload($commands)
	{
		$request = new rXMLRPCRequest($commands);
		$method = new ReflectionMethod('rXMLRPCRequest', 'makeNextCall');
		$method->setAccessible(true);
		$method->invoke($request);
		return $this->payloadOf($request);
	}

	private function constructionRefused($name)
	{
		try {
			new rXMLRPCCommand($name);
		} catch (rXMLRPCInvalidCommandName $refused) {
			return true;
		}
		return false;
	}

	// ---- the injection --------------------------------------------------

	public function testAnInjectedNameIsRefusedAtConstruction()
	{
		$this->assertTrue($this->constructionRefused(self::INJECTED),
			'a name carrying </methodName> cannot be made into a command');
	}

	/**
	 * The shape plugins/httprpc/action.php builds for setprops: "d.set_" glued
	 * to an unfiltered request field.
	 */
	public function testTheSetpropsShapeIsRefused()
	{
		$this->assertTrue($this->constructionRefused('d.set_' . self::INJECTED),
			'the name setprops concatenates is refused with the payload in it');
	}

	/**
	 * And the shape setsettings builds: 'set_' glued to the field with its
	 * type prefix dropped.
	 */
	public function testTheSetsettingsShapeIsRefused()
	{
		$this->assertTrue($this->constructionRefused('set_' . self::INJECTED),
			'the name setsettings concatenates is refused with the payload in it');
	}

	public function testAnEmptyNameIsRefused()
	{
		$this->assertTrue($this->constructionRefused(''),
			'an empty command name is refused rather than sent');
	}

	/**
	 * The name is public and the check in the constructor is not the last word:
	 * the request refuses to build anything at all when it is handed a command
	 * whose name was never checked, so nothing partial goes out either.
	 */
	public function testARequestBuildsNoPayloadForAnUncheckedName()
	{
		$request = new RecordingXMLRPCRequest($this->unvalidatedCommand(self::INJECTED));
		$ran = $request->run();

		$this->assertTrue($ran === false, 'the run is refused');
		$this->assertTrue($request->fault === true, 'the request is marked faulted');
		$this->assertTrue(
			strpos(implode('', $request->payloads), '<methodName>execute.throw</methodName>') === false,
			'no payload carries the injected second method call');
		$this->assertEquals(0, count($request->payloads),
			'no payload was built at all, so none was sent');
	}

	/**
	 * Two commands take the system.multicall branch, where the name goes into a
	 * <string> instead. One bad name there has to stop the whole batch, not
	 * just its own entry, or the good half goes out with the injection in it.
	 */
	public function testAMulticallBatchIsRefusedWholeForOneUncheckedName()
	{
		$request = new RecordingXMLRPCRequest(array(
			$this->unvalidatedCommand('d.name', array('abc')),
			$this->unvalidatedCommand(self::INJECTED),
		));
		$ran = $request->run();

		$this->assertTrue($ran === false, 'the batch is refused');
		$this->assertTrue(
			strpos(implode('', $request->payloads), 'execute.throw') === false,
			'no payload carries the injection the second command held');
		$this->assertEquals(0, count($request->payloads),
			'the good command was not sent either');
	}

	// ---- what still has to work -----------------------------------------

	public function testEveryRealMethodNameIsAccepted()
	{
		$rejected = array();
		foreach (self::REAL_NAMES as $name) {
			if ($this->constructionRefused($name)) {
				$rejected[] = $name;
			}
		}
		$this->assertEquals(array(), $rejected,
			'no real rtorrent method name is refused, rejected: '
				. implode(', ', $rejected));
	}

	public function testTheSingleCallPayloadIsUnchanged()
	{
		$this->assertEquals(
			'<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>'
				. "d.multicall</methodName><params>\r\n"
				. "<param><value><string>main</string></value></param>\r\n"
				. "<param><value><string>d.get_hash=</string></value></param>\r\n"
				. '</params></methodCall>',
			$this->buildPayload(new rXMLRPCCommand('d.multicall',
				array('main', 'd.get_hash='))),
			'd.multicall still emits exactly the payload it emitted before');
	}

	public function testTheMulticallPayloadIsUnchanged()
	{
		$this->assertEquals(
			'<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>'
				. 'system.multicall</methodName><params><param><value><array><data>'
				. "\r\n<value><struct><member><name>methodName</name><value><string>"
				. 'd.set_priority</string></value></member><member><name>params</name>'
				. '<value><array><data>'
				. "\r\n<value><string>abc</string></value>"
				. "\r\n</data></array></value></member></struct></value>"
				. "\r\n<value><struct><member><name>methodName</name><value><string>"
				. 'd.set_custom1</string></value></member><member><name>params</name>'
				. '<value><array><data>'
				. "\r\n<value><string>abc</string></value>"
				. "\r\n</data></array></value></member></struct></value>"
				. "\r\n</data></array></value></param></params></methodCall>",
			$this->buildPayload(array(
				new rXMLRPCCommand('d.set_priority', 'abc'),
				new rXMLRPCCommand('d.set_custom1', 'abc'),
			)),
			'a two-command batch still emits exactly the payload it emitted before');
	}

	/**
	 * Only names are constrained. Parameters carry command strings of their own
	 * -- "cat=$d.get_name=", "d.get_hash=" -- and those characters have to keep
	 * travelling, or every multicall and every branch call breaks.
	 */
	public function testParametersStillCarryCommandPunctuation()
	{
		$payload = $this->buildPayload(new rXMLRPCCommand('branch', array(
			'0123456789abcdef0123456789abcdef01234567',
			'd.is_active=',
			'cat=$d.stop=,$d.close=,$d.set_connection_seed=seed',
		)));
		$this->assertTrue(
			strpos($payload, '<string>d.is_active=</string>') !== false,
			'a command string parameter is still sent verbatim');
		$this->assertTrue(
			strpos($payload, '<string>cat=$d.stop=,$d.close=,$d.set_connection_seed=seed</string>') !== false,
			'a parameter full of = , and $ is still sent verbatim');
	}

	/**
	 * The alias tables in php/methods-*.php rewrite a name on the way in, and
	 * the check runs on what comes out of that rewrite, not on what went in.
	 */
	public function testAnAliasedNameIsCheckedAfterTheRewriteAndAccepted()
	{
		$settings = rTorrentSettings::get();
		$settings->aliases = array(
			'd.set_peer_exchange' => array('name' => 'd.peer_exchange.set', 'prm' => 0),
		);
		$command = new rXMLRPCCommand('d.set_peer_exchange');
		$settings->aliases = array();
		$this->assertEquals('d.peer_exchange.set', $command->command,
			'the alias still resolves and the resolved name is accepted');
	}

	/**
	 * getCmd() keeps a trailing '=' on the names it is asked to resolve,
	 * because a command string parameter needs one. As a command name it is not
	 * a name at all, and no shipped caller passes one, so it is refused.
	 */
	public function testANameWithATrailingEqualsIsRefused()
	{
		$this->assertTrue($this->constructionRefused('d.get_hash='),
			'a command string is not a command name');
	}
}
