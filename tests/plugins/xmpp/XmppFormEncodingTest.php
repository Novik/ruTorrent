<?php

// The XMPP settings form posts an application/x-www-form-urlencoded body that
// rXmpp::set() reads by splitting on '&' and then on '='. Every value in it
// therefore has to arrive escaped, and be unescaped here.
//
// tests/fixtures/xmpp-form-values.json holds one value per row with the form
// it travels as. tests/plugins/xmpp/form-encoding.spec.js asserts that
// plugins/xmpp/init.js writes that form; this asserts that set() reads the
// same form back as the value. Neither side can be changed alone.

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-xmpp-encoding-test-' . getmypid();

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/xmpp/xmpp.php');

// set() re-registers the on-finished command and writes the cache file.
// Neither is what is under test.
class XmppFormEncodingProbe extends rXmpp
{
	public function store() { return true; }
	public function setHandlers() { return true; }
}

class XmppFormEncodingTest extends TestCase
{
	const STORED = 'correct-horse-battery-staple';
	const MARKER = 'formEncoding=percent-v1';

	private $values;

	public function setUp()
	{
		$this->values = json_decode(
			file_get_contents(__DIR__ . '/../../fixtures/xmpp-form-values.json'), true);
		$this->assertTrue(is_array($this->values) && count($this->values) > 0,
			'read a table with values in it');
	}

	private function configured()
	{
		$xmpp = new XmppFormEncodingProbe();
		$xmpp->jabberLogin = 'someone';
		$xmpp->jabberServer = 'jabber.example';
		$xmpp->jabberFor = 'someone-else@jabber.example';
		$xmpp->jabberPasswd = self::STORED;
		return $xmpp;
	}

	// What the settings page sends: the word that says its values are
	// escaped, and then one field each.
	private function body($field, $encoded)
	{
		$fields = array(
			'advancedSettings' => '0',
			'useEncryption' => '1',
			'jabberHost' => '',
			'jabberPort' => '5222',
			'jabberJid' => 'someone%40jabber.example',
			'jabberFor' => 'nobody%40jabber.example',
			'message' => 'done',
		);
		$fields[$field] = $encoded;
		$pairs = array(self::MARKER);
		foreach($fields as $name => $value)
			$pairs[] = $name . '=' . $value;
		return implode('&', $pairs);
	}

	// What a settings page from before that word sends: no word, and
	// whatever was typed put on the wire as it stands.
	private function legacyBody($field, $raw)
	{
		$fields = array(
			'advancedSettings' => '0',
			'useEncryption' => '1',
			'jabberHost' => '',
			'jabberPort' => '5222',
			'jabberJid' => 'someone@jabber.example',
			'jabberFor' => 'nobody@jabber.example',
			'message' => 'done',
		);
		$fields[$field] = $raw;
		$pairs = array();
		foreach($fields as $name => $value)
			$pairs[] = $name . '=' . $value;
		return implode('&', $pairs);
	}

	public function testATypedPasswordArrivesAsItWasTyped()
	{
		foreach($this->values as $row)
		{
			$xmpp = $this->configured();
			$xmpp->set($this->body('jabberPasswd', $row['encoded']));
			$this->assertTrue($xmpp->jabberPasswd === $row['value'],
				'password ' . json_encode($row['value']) . ' (' . $row['why'] . '): '
				. var_export($xmpp->jabberPasswd, true));
		}
	}

	public function testTheRecipientAndTheMessageArriveAsTheyWereTyped()
	{
		foreach($this->values as $row)
		{
			$xmpp = $this->configured();
			$xmpp->set($this->body('jabberFor', $row['encoded']));
			$this->assertTrue($xmpp->jabberFor === $row['value'],
				'recipient ' . json_encode($row['value']) . ': '
				. var_export($xmpp->jabberFor, true));

			$xmpp = $this->configured();
			$xmpp->set($this->body('message', $row['encoded']));
			$this->assertTrue($xmpp->message === $row['value'],
				'message ' . json_encode($row['value']) . ': '
				. var_export($xmpp->message, true));
		}
	}

	// The jid is one field until it gets here, so it is unescaped before it is
	// split and the halves keep whatever they carried.
	public function testTheJidIsSplitAfterItIsUnescaped()
	{
		$xmpp = $this->configured();
		$xmpp->set($this->body('jabberJid', 'some%26one%40jabber.example'));
		$this->assertTrue($xmpp->jabberLogin === 'some&one',
			'the login: ' . var_export($xmpp->jabberLogin, true));
		$this->assertTrue($xmpp->jabberServer === 'jabber.example',
			'the server: ' . var_export($xmpp->jabberServer, true));
	}

	// encodeURIComponent() writes a space as "%20" and a plus as "%2B", so
	// nothing it escapes comes out as a bare plus. One in the body is data,
	// and a reader that took the body for an HTML form would eat it.
	public function testAPlusInAMarkedBodyIsAPlus()
	{
		$xmpp = $this->configured();
		$xmpp->set($this->body('jabberPasswd', 'p+ssword'));
		$this->assertTrue($xmpp->jabberPasswd === 'p+ssword',
			'a plus stays a plus: ' . var_export($xmpp->jabberPasswd, true));
	}

	// A settings page from before the word escaped nothing, so nothing it
	// sends is unescaped: what arrived is what somebody typed.
	public function testAnUnmarkedBodyIsNotUnescaped()
	{
		foreach(array('p+ssword', '100%25', 'p%2Bssword', 'p%26ssword',
			'p%3Dssword', 'p%40ssword', 'p%20ssword') as $typed)
		{
			$xmpp = $this->configured();
			$xmpp->set($this->legacyBody('jabberPasswd', $typed));
			$this->assertTrue($xmpp->jabberPasswd === $typed,
				'unmarked ' . json_encode($typed) . ' stays itself: '
				. var_export($xmpp->jabberPasswd, true));
		}
	}

	// The new settings page puts the representation word first. An old page
	// escaped nothing, so an ampersand in any value can make the text after it
	// look like another field. A later marker-shaped segment is therefore data
	// that already escaped its old field, not authority to reinterpret every
	// percent sequence in the body.
	public function testAMarkerThatDoesNotOpenTheBodyDoesNotDecodeLegacyValues()
	{
		$xmpp = $this->configured();
		$xmpp->set($this->legacyBody('jabberPasswd', 'p%26ssword')
			. '&formEncoding=percent-v1');
		$this->assertTrue($xmpp->jabberPasswd === 'p%26ssword',
			'a later marker leaves legacy text as it arrived: '
			. var_export($xmpp->jabberPasswd, true));
	}

	// The same bytes either way, and the word is the whole difference: one
	// page escaped an ampersand, the other was typed those ten characters.
	public function testTheWordDecidesWhatTheSameBytesMean()
	{
		$marked = $this->configured();
		$marked->set($this->body('jabberPasswd', 'p%26ssword'));
		$this->assertTrue($marked->jabberPasswd === 'p&ssword',
			'marked: ' . var_export($marked->jabberPasswd, true));

		$legacy = $this->configured();
		$legacy->set($this->legacyBody('jabberPasswd', 'p%26ssword'));
		$this->assertTrue($legacy->jabberPasswd === 'p%26ssword',
			'unmarked: ' . var_export($legacy->jabberPasswd, true));
	}

	// Once, and once only. Somebody who types the five characters "p%26s"
	// has them escaped to "p%2526s" and gets those five back.
	public function testAMarkedValueIsUnescapedExactlyOnce()
	{
		$xmpp = $this->configured();
		$xmpp->set($this->body('jabberPasswd', 'p%2526ssword'));
		$this->assertTrue($xmpp->jabberPasswd === 'p%26ssword',
			'unescaped once: ' . var_export($xmpp->jabberPasswd, true));
	}

	// The localpart of a jid cannot hold an "@", so the first one separates
	// the two halves. A later one belongs to the server half; dropping it
	// would leave a login pointed at a server nobody asked for.
	public function testTheJidIsSplitAtTheFirstAt()
	{
		$xmpp = $this->configured();
		$xmpp->set($this->body('jabberJid', 'a%40b%40c.example'));
		$this->assertTrue($xmpp->jabberLogin === 'a',
			'the login: ' . var_export($xmpp->jabberLogin, true));
		$this->assertTrue($xmpp->jabberServer === 'b@c.example',
			'the server: ' . var_export($xmpp->jabberServer, true));
	}

	// An escaped value has to stay one field, whatever it holds. The count is
	// what a value that got loose would change.
	public function testAnEscapedValueIsStillOneField()
	{
		foreach($this->values as $row)
		{
			$this->assertTrue(count(explode('&', $this->body('jabberPasswd', $row['encoded']))) === 9,
				'the body still has nine fields with ' . json_encode($row['value']));
		}
	}

	// #3309: a save that leaves the password field alone sends no jabberPasswd
	// at all, and the stored one has to survive that.
	public function testASaveThatCarriesNoPasswordStillKeepsTheStoredOne()
	{
		$marked = $this->configured();
		$marked->set(self::MARKER . '&advancedSettings=0&useEncryption=1&jabberHost=&jabberPort=5222'
			. '&jabberJid=someone%40jabber.example&jabberFor=nobody%40jabber.example&message=done');
		$this->assertTrue($marked->jabberPasswd === self::STORED,
			'marked, the stored password survives: ' . var_export($marked->jabberPasswd, true));

		$legacy = $this->configured();
		$legacy->set('advancedSettings=0&useEncryption=1&jabberHost=&jabberPort=5222'
			. '&jabberJid=someone@jabber.example&jabberFor=nobody@jabber.example&message=done');
		$this->assertTrue($legacy->jabberPasswd === self::STORED,
			'unmarked, the stored password survives: ' . var_export($legacy->jabberPasswd, true));
	}

	// And an emptied field still clears it, which an unescaping reader must not
	// turn into "no field sent".
	public function testAnEmptiedPasswordFieldStillClearsTheStoredOne()
	{
		$marked = $this->configured();
		$marked->set($this->body('jabberPasswd', ''));
		$this->assertTrue($marked->jabberPasswd === '',
			'marked, an emptied field clears it: ' . var_export($marked->jabberPasswd, true));

		$legacy = $this->configured();
		$legacy->set($this->legacyBody('jabberPasswd', ''));
		$this->assertTrue($legacy->jabberPasswd === '',
			'unmarked, an emptied field clears it: ' . var_export($legacy->jabberPasswd, true));
	}
}
