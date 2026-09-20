<?php

// rXmpp::get() is appended to the javascript of every page load by
// plugins/xmpp/init.php and is the whole answer of plugins/xmpp/action.php.
// It used to carry the stored XMPP account password, so the password was in
// the page for anything that could read the page to take. What the settings
// form needs is only whether a password is stored; the value goes the other
// way, and only when somebody types one.

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-xmpp-password-test-' . getmypid();

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/xmpp/xmpp.php');

// set() re-registers the on-finished command and writes the cache file. Both
// want a daemon and a settings directory, and neither is what is under test.
class XmppPasswordProbe extends rXmpp
{
	public $stored = 0;
	public function store() { $this->stored++; return true; }
	public function setHandlers() { return true; }
}

class XmppPasswordTest extends TestCase
{
	const STORED = 'correct-horse-battery-staple';

	// The right-hand side of "theWebUI.xmpp = <literal>;", decoded.
	private function settings($fragment)
	{
		$fragment = trim($fragment);
		$prefix = 'theWebUI.xmpp = ';
		$this->assertTrue(substr($fragment, 0, strlen($prefix)) === $prefix
			&& substr($fragment, -1) === ';',
			'The answer is that one assignment: ' . $fragment);
		return json_decode(substr($fragment, strlen($prefix), -1), true);
	}

	private function configured()
	{
		$xmpp = new XmppPasswordProbe();
		$xmpp->jabberLogin = 'someone';
		$xmpp->jabberServer = 'jabber.example';
		$xmpp->jabberFor = 'someone-else@jabber.example';
		$xmpp->jabberPasswd = self::STORED;
		return $xmpp;
	}

	public function testThePageNeverCarriesTheStoredPassword()
	{
		$fragment = $this->configured()->get();
		$this->assertTrue(strpos($fragment, self::STORED) === false,
			'The stored password was written into the page javascript: ' . $fragment);
		$settings = $this->settings($fragment);
		$this->assertTrue(!array_key_exists('JabberPasswd', $settings),
			'No password field is sent at all: ' . json_encode($settings));
		$this->assertTrue($settings['JabberPasswd_set'] === 1,
			'The page is still told that a password is stored: ' . json_encode($settings));
	}

	public function testAnAccountWithNoPasswordSaysSo()
	{
		$xmpp = $this->configured();
		$xmpp->jabberPasswd = '';
		$this->assertTrue($this->settings($xmpp->get())['JabberPasswd_set'] === 0,
			'An empty password reads as unset');
	}

	// What the settings form still needs is all there.
	public function testTheRestOfTheSettingsStillReachThePage()
	{
		$xmpp = $this->configured();
		$settings = $this->settings($xmpp->get());
		$this->assertTrue($settings['JabberJID'] === 'someone@jabber.example', 'the jid');
		$this->assertTrue($settings['JabberFor'] === 'someone-else@jabber.example', 'the recipient');
		$this->assertTrue($settings['JabberPort'] === 5222, 'the port');
	}

	// The form has nothing to send back, so a save that did not touch the
	// password field must not clear the stored one.
	public function testASaveThatCarriesNoPasswordKeepsTheStoredOne()
	{
		$xmpp = $this->configured();
		$xmpp->set('advancedSettings=0&useEncryption=1&jabberHost=&jabberPort=5222'
			. '&jabberJid=someone@jabber.example&jabberFor=nobody@jabber.example'
			. '&message=done');
		$this->assertTrue($xmpp->jabberPasswd === self::STORED,
			'The stored password survives a save that leaves it alone: '
			. var_export($xmpp->jabberPasswd, true));
		$this->assertTrue($xmpp->jabberFor === 'nobody@jabber.example',
			'and the rest of the form is still written');
		$this->assertTrue($xmpp->stored === 1, 'and the write was stored');
	}

	public function testASaveThatCarriesAPasswordReplacesTheStoredOne()
	{
		$xmpp = $this->configured();
		$xmpp->set('advancedSettings=0&useEncryption=1&jabberHost=&jabberPort=5222'
			. '&jabberJid=someone@jabber.example&jabberFor=nobody@jabber.example'
			. '&message=done&jabberPasswd=a-new-one');
		$this->assertTrue($xmpp->jabberPasswd === 'a-new-one',
			'A typed password replaces the stored one: ' . var_export($xmpp->jabberPasswd, true));
	}

	// Typed, then emptied: the field is sent empty, and that clears it.
	public function testAnEmptiedPasswordFieldClearsTheStoredOne()
	{
		$xmpp = $this->configured();
		$xmpp->set('advancedSettings=0&useEncryption=1&jabberHost=&jabberPort=5222'
			. '&jabberJid=someone@jabber.example&jabberFor=nobody@jabber.example'
			. '&message=done&jabberPasswd=');
		$this->assertTrue($xmpp->jabberPasswd === '',
			'An emptied field clears it: ' . var_export($xmpp->jabberPasswd, true));
		$this->assertTrue($this->settings($xmpp->get())['JabberPasswd_set'] === 0,
			'and the page is told there is none');
	}

	// notify.php logs in with this, so what set() keeps has to still be there
	// for it to read.
	public function testTheStoredPasswordIsStillWhatTheNotifierReads()
	{
		$xmpp = $this->configured();
		$xmpp->get();
		$this->assertTrue($xmpp->jabberPasswd === self::STORED,
			'Emitting the page does not disturb the stored password');
	}
}
