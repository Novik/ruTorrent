<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/xmpp/XMPPHP/XMPP.php');

// Drives resource_bind_handler() without a socket. send() is the only thing the
// handler reaches for that needs one, so overriding it is enough to run the real
// bind path in-process.
class XmppBindProbe extends XMPPHP_XMPP
{
	public $sent = array();

	public function send($msg, $timeout = null)
	{
		$this->sent[] = $msg;
		return strlen($msg);
	}

	public function handleBind($xml)
	{
		return $this->resource_bind_handler($xml);
	}

	public function readJid()
	{
		return $this->jid;
	}

	public function readFullJid()
	{
		return $this->fulljid;
	}
}

// XMPPHP is vendored under plugins/xmpp/XMPPHP and maintained as part of
// ruTorrent. Two things it got wrong are load-bearing on modern PHP:
//
// 1. resource_bind_handler() assigns $this->jid on the standard RFC 6120 bind
//    path -- i.e. on every successful connect -- and the property was never
//    declared. That is a deprecation on PHP 8.2+ and an Error on PHP 9, so the
//    plugin would stop delivering notifications outright.
//
// 2. XMPPHP_BOSH::connect() made parameter #1 required where the parent's is
//    optional. PHP rejects that at class-declaration time, so BOSH.php was an
//    unconditional fatal error on PHP 8 -- the file could not be loaded at all.
class XmppLibraryTest extends TestCase
{
	private function bindResult($fulljid)
	{
		$jid = new XMPPHP_XMLObj('jid', '', array(), $fulljid);
		$bind = new XMPPHP_XMLObj('bind', 'urn:ietf:params:xml:ns:xmpp-bind');
		$bind->subs[] = $jid;
		$iq = new XMPPHP_XMLObj('iq', 'jabber:client', array('type' => 'result', 'id' => '1'));
		$iq->subs[] = $bind;

		return $iq;
	}

	private function newProbe()
	{
		// Suppressed diagnostic: XMLStream::setupParser() drives the XML parser
		// through xml_set_object() and string method names, which PHP 8.4
		// deprecates. That is a pre-existing XMPPHP issue and not what these
		// tests are about; without the @ every construction below buries the
		// suite log in the same three notices.
		return @new XmppBindProbe('example.org', 5222, 'user', 'secret', 'rutorrent');
	}

	public function testJidIsADeclaredProperty()
	{
		$declared = array();
		$reflection = new ReflectionClass('XMPPHP_XMPP');
		foreach ($reflection->getProperties() as $property) {
			$declared[] = $property->getName();
		}

		$this->assertTrue(in_array('jid', $declared, true),
			'XMPPHP_XMPP must declare $jid; resource_bind_handler() assigns it on every connect');
	}

	public function testBindResultPopulatesJid()
	{
		$probe = $this->newProbe();
		$probe->handleBind($this->bindResult('user@example.org/rutorrent'));

		$this->assertEquals('user@example.org/rutorrent', $probe->readFullJid(),
			'the full JID from the bind result must be stored');
		$this->assertEquals('user@example.org', $probe->readJid(),
			'the bare JID must be the full JID with the resource stripped');
	}

	public function testBindDoesNotCreateADynamicProperty()
	{
		$probe = $this->newProbe();
		$probe->handleBind($this->bindResult('user@example.org/rutorrent'));

		$property = new ReflectionProperty($probe, 'jid');
		$this->assertTrue($property->isDefault(),
			'$jid must resolve to the declared property, not a dynamic one');
	}

	public function testBindRaisesNoDeprecation()
	{
		// Only the bind call is instrumented. Constructing the probe sets up the
		// XML parser, which has diagnostics of its own that are not this test's
		// subject.
		$probe = $this->newProbe();
		$bind = $this->bindResult('user@example.org/rutorrent');

		$notices = array();
		set_error_handler(function ($errno, $errstr) use (&$notices) {
			$notices[] = $errstr;

			return true;
		}, E_ALL);

		try {
			$probe->handleBind($bind);
		} finally {
			restore_error_handler();
		}

		$this->assertTrue(count($notices) === 0,
			'binding a resource must not raise a diagnostic (a dynamic $jid raises one on PHP 8.2+), got: '
			. implode(' | ', $notices));
	}

	public function testBindContinuesTheSessionHandshake()
	{
		$probe = $this->newProbe();
		$probe->handleBind($this->bindResult('user@example.org/rutorrent'));

		$this->assertEquals(1, count($probe->sent),
			'the bind handler must send exactly one follow-up stanza');
		$this->assertTrue(strpos($probe->sent[0], 'urn:ietf:params:xml:ns:xmpp-session') !== false,
			'the follow-up stanza must be the session request');
	}

	public function testNonResultBindIsIgnored()
	{
		$iq = new XMPPHP_XMLObj('iq', 'jabber:client', array('type' => 'error', 'id' => '1'));
		$probe = $this->newProbe();
		$probe->handleBind($iq);

		$this->assertEquals(null, $probe->readJid(),
			'a bind error must leave the JID unset');
	}

	// The BOSH override signatures are a class-declaration-time contract: PHP
	// refuses to load the class if they are wrong, and that fatal cannot be
	// caught from inside the process that hits it. A subprocess is the only way
	// to turn it into a pass/fail.
	public function testBoshClassCanBeLoaded()
	{
		$path = __DIR__ . '/../../../plugins/xmpp/XMPPHP/BOSH.php';
		$command = escapeshellarg(PHP_BINARY)
			. ' -d display_errors=1 -d error_reporting=-1 -r '
			. escapeshellarg('require ' . var_export($path, true) . '; echo "BOSH_LOADED";')
			. ' 2>&1';
		$output = shell_exec($command);

		$this->assertTrue(strpos((string) $output, 'BOSH_LOADED') !== false,
			'BOSH.php must load; its overrides have to stay signature-compatible with XMPPHP_XMLStream. Got: '
			. trim((string) $output));
	}

	public function testBoshOverridesAcceptEverythingTheParentAccepts()
	{
		require_once(__DIR__ . '/../../../plugins/xmpp/XMPPHP/BOSH.php');

		$child = new ReflectionClass('XMPPHP_BOSH');
		foreach ($child->getMethods() as $method) {
			if ($method->getDeclaringClass()->getName() !== 'XMPPHP_BOSH') {
				continue;
			}

			$parentClass = $child->getParentClass();
			if (!$parentClass->hasMethod($method->getName())) {
				continue;
			}
			$parent = $parentClass->getMethod($method->getName());
			if ($parent->isPrivate()) {
				// A private parent method is not inherited, so a same-named
				// child method is a separate method rather than an override
				// (XMLStream::__process() vs BOSH::__process()).
				continue;
			}

			$this->assertTrue(
				$method->getNumberOfParameters() >= $parent->getNumberOfParameters(),
				"XMPPHP_BOSH::{$method->getName()}() must accept at least as many parameters as the method it overrides");
			$this->assertTrue(
				$method->getNumberOfRequiredParameters() <= $parent->getNumberOfRequiredParameters(),
				"XMPPHP_BOSH::{$method->getName()}() must not make a parameter required that the method it overrides leaves optional");
		}
	}

	public function testBoshConnectRejectsAMissingEndpoint()
	{
		require_once(__DIR__ . '/../../../plugins/xmpp/XMPPHP/BOSH.php');

		// @ for the same setupParser() deprecations described in newProbe().
		$bosh = @new XMPPHP_BOSH('example.org', 5222, 'user', 'secret', 'rutorrent');
		$message = null;
		try {
			$bosh->connect();
		} catch (XMPPHP_Exception $e) {
			$message = $e->getMessage();
		}

		$this->assertTrue($message !== null,
			'connect() without an endpoint must raise XMPPHP_Exception rather than proceeding with a null URL');
	}
}
