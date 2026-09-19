<?php

// Every plugin here hands the browser a fragment of JavaScript built by string
// concatenation, and each of the values below reaches that fragment from a
// request parameter. The contract this file pins is the same one for all of
// them: whatever the stored value is, the fragment stays a single assignment
// whose right-hand side is one well-formed literal that decodes back to the
// value. A fragment that fails to decode is a fragment the value escaped from.

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-emitted-script-test-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/cache.php');
require_once(__DIR__ . '/../../php/utility/json.php');
require_once(__DIR__ . '/../../plugins/theme/theme.php');
require_once(__DIR__ . '/../../plugins/uploadeta/uploadeta.php');
require_once(__DIR__ . '/../../plugins/scheduler/scheduler.php');
require_once(__DIR__ . '/../../plugins/xmpp/xmpp.php');

class EmittedScriptValueTest extends TestCase
{
	// Payloads that end the emitted statement and start another one, in each of
	// the two quotings the tree emits, plus the one that ends the <script>.
	private static function payloads()
	{
		return array(
			"apostrophe" => "';alert(1);//",
			"quote" => '";alert(1);//',
			"script end" => '</script><script>alert(1)</script>',
			"line break" => "a\nalert(1)//",
			"backslash" => 'a\\',
		);
	}

	// The right-hand side of "<lvalue> = <literal>;", decoded. Returns the
	// marker object when the fragment is not that shape or the literal is not
	// one JSON value, which is what a value escaping its quotes looks like.
	private function assigned($fragment, $lvalue)
	{
		$fragment = trim($fragment);
		$prefix = $lvalue . ' = ';
		if (substr($fragment, 0, strlen($prefix)) !== $prefix || substr($fragment, -1) !== ';') {
			return 'NOT-AN-ASSIGNMENT';
		}
		$literal = substr($fragment, strlen($prefix), -1);
		$value = json_decode($literal, true);
		if ($value === null && trim($literal) !== 'null') {
			return 'NOT-ONE-LITERAL';
		}
		return $value;
	}

	private function assertRoundTrips($fragment, $lvalue, $expected, $what)
	{
		$this->assertTrue($this->assigned($fragment, $lvalue) === $expected,
			$what . ' survives as data: ' . json_encode($fragment));
	}

	public function testJsValueNeverEmitsCharactersThatCanCloseItsContext()
	{
		foreach (self::payloads() as $name => $payload) {
			$literal = JSON::jsValue($payload);
			$this->assertTrue(strpbrk($literal, '<>&') === false,
				'jsValue leaves no markup delimiter for '.$name.': '.$literal);
			// The delimiters json_encode puts round the string are the only
			// quotes allowed; the content carries none of its own.
			$this->assertTrue(strpbrk(substr($literal, 1, -1), '\'"') === false,
				'jsValue leaves no bare quote inside for '.$name.': '.$literal);
			$this->assertTrue(json_decode($literal, true) === $payload,
				'jsValue round-trips '.$name);
		}
	}

	public function testThemeNameCannotEscapeTheAssignment()
	{
		foreach (self::payloads() as $name => $payload) {
			$theme = new rTheme();
			$theme->current = $payload;
			$this->assertRoundTrips($theme->get(), 'theWebUI.theme', $payload, 'theme '.$name);
		}
	}

	public function testThemeRefusesToStoreANameThatIsNotAThemeDirectory()
	{
		foreach (array_merge(array_values(self::payloads()),
			array('../../../..', 'themes/../../conf', '.', '..', 'NoSuchTheme')) as $payload) {
			$theme = new rTheme();
			$theme->current = 'Blue';
			$_REQUEST['theme'] = $payload;
			$theme->set();
			$this->assertTrue($theme->current === 'Blue',
				'refused theme name '.json_encode($payload).', kept '.json_encode($theme->current));
		}
		unset($_REQUEST['theme']);
	}

	public function testThemeStillAcceptsARealThemeAndTheEmptyName()
	{
		$real = array_map('basename', glob(__DIR__ . '/../../plugins/theme/themes/*', GLOB_ONLYDIR));
		$this->assertTrue(count($real) > 0, 'the tree ships at least one theme to test with');
		foreach (array_merge($real, array('')) as $payload) {
			$theme = new rTheme();
			$theme->current = 'nonsense';
			$_REQUEST['theme'] = $payload;
			$theme->set();
			$this->assertTrue($theme->current === $payload,
				'accepted theme name '.json_encode($payload));
		}
		unset($_REQUEST['theme']);
	}

	public function testUploadTargetCannotEscapeTheAssignment()
	{
		foreach (self::payloads() as $name => $payload) {
			$eta = new rUploadeta();
			$eta->uploadtarget = $payload;
			$this->assertTrue(is_int($this->assigned($eta->get(), 'theWebUI.uploadtarget')),
				'upload target stays a number for '.$name.': '.json_encode($eta->get()));
		}
		$eta = new rUploadeta();
		$eta->uploadtarget = 250;
		$this->assertRoundTrips($eta->get(), 'theWebUI.uploadtarget', 250, 'upload target');
	}

	public function testScheduleTableCarriesOnlyNumbers()
	{
		foreach (self::payloads() as $name => $payload) {
			$sch = new rScheduler();
			$sch->fillWeek();
			$sch->week[3][7] = $payload;
			$sch->UL[1] = $payload;
			$sch->enabled = $payload;
			$table = $this->assigned($sch->get(), 'theWebUI.scheduleTable');
			$this->assertTrue(is_array($table), 'schedule table decodes for '.$name.': '.json_encode($sch->get()));
			if (!is_array($table)) {
				continue;
			}
			$this->assertTrue($table['week'][3][7] === 0 && $table['UL'][1] === 0 && $table['enabled'] === 0,
				'schedule table holds numbers for '.$name.': '.json_encode($table));
			$this->assertTrue(count($table['week']) === 7 && count($table['week'][0]) === 24,
				'schedule table keeps its shape for '.$name);
		}
	}

	public function testXmppSettingsCannotEscapeTheAssignment()
	{
		foreach (self::payloads() as $name => $payload) {
			$xmpp = new rXmpp();
			$xmpp->jabberHost = $payload;
			$xmpp->jabberPasswd = $payload;
			$xmpp->jabberFor = $payload;
			$xmpp->message = $payload;
			$xmpp->jabberPort = $payload;
			$xmpp->useEncryption = $payload;
			$xmpp->advancedSettings = $payload;
			$settings = $this->assigned($xmpp->get(), 'theWebUI.xmpp');
			$this->assertTrue(is_array($settings), 'xmpp settings decode for '.$name.': '.json_encode($xmpp->get()));
			if (!is_array($settings)) {
				continue;
			}
			$this->assertTrue($settings['JabberHost'] === $payload && $settings['JabberPasswd'] === $payload &&
				$settings['JabberFor'] === $payload && $settings['Message'] === $payload,
				'xmpp strings survive as data for '.$name);
			$this->assertTrue($settings['JabberPort'] === 0 && $settings['UseEncryption'] === 0 &&
				$settings['AdvancedSettings'] === 0,
				'xmpp numeric fields stay numbers for '.$name.': '.json_encode($settings));
		}
	}
}
