<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * utf8_encode(), utf8_decode(), strptime(), strftime(), gmstrftime(),
 * xml_set_object(), libxml_disable_entity_loader() and, from PHP 8.5,
 * SplObjectStorage::contains() and attach() are deprecated in the PHP
 * versions ruTorrent runs on, and each call writes a deprecation to the
 * log. Each code path that used one runs here in its own PHP process with
 * every error reported, so that a deprecation shows up in its output, and
 * the same process computes what the deprecated function would have
 * returned, silenced, for comparison.
 */
class DeprecatedFunctionReplacementTest extends TestCase
{
	private $root = null;

	public function setUp()
	{
		$this->root = realpath(__DIR__ . '/../..');
	}

	/**
	 * Run $code after the given sources are loaded, with every error shown,
	 * and return [what the code returned, everything PHP reported]. $options
	 * go on the php command line, before the script.
	 */
	private function run_isolated($code, $requires, $options = '')
	{
		$prelude = "<?php\nerror_reporting(E_ALL);\n"
			. "chdir(" . var_export($this->root . '/php', true) . ");\n";
		foreach($requires as $file)
			$prelude .= 'require_once(' . var_export($this->root . '/' . $file, true) . ");\n";
		$script = tempnam(sys_get_temp_dir(), 'deprecated-');
		file_put_contents($script, $prelude . "\$result = json_encode(call_user_func(function() {\n"
			. $code . "\n}));\nfwrite(STDOUT, \$result);\n");
		$command = escapeshellarg(PHP_BINARY) . ' ' . $options
			. ' -d error_reporting=-1 -d display_errors=stderr -d log_errors=0 ' . escapeshellarg($script)
			. ' > ' . escapeshellarg($script . '.out') . ' 2> ' . escapeshellarg($script . '.err');
		shell_exec($command);
		$result = json_decode((string)@file_get_contents($script . '.out'), true);
		$other = (string)@file_get_contents($script . '.err');
		foreach(array($script, $script . '.out', $script . '.err') as $file)
			@unlink($file);
		return array($result, $other);
	}

	private function brief($output)
	{
		$output = trim($output);
		return (strlen($output) > 400) ? substr($output, 0, 400) . '...' : $output;
	}

	private function assertQuiet($other, $what)
	{
		$this->assertEquals('', trim($other), $what . ' runs without any notice, warning or deprecation: '
			. $this->brief($other));
	}

	// ---- extsearch: utf8_encode() / utf8_decode() --------------------------

	public function testLatin1HelpersMatchUtf8EncodeAndDecode()
	{
		// commonEngine falls back to these when neither iconv nor mbstring is
		// loaded. They are compared directly, because whether either
		// extension can be left out depends on how PHP was built.
		list($result, $other) = $this->run_isolated('
			$samples = array("", "plain", "caf\xE9", "\xFF\x80\x00\xA0", "caf\xC3\xA9", "\xE2\x82\xAC 5",
				"\xF0\x9F\x98\x80", "\xC3", "\xE2\x82", "\xED\xA0\x80", "\xC0\xAF", "&amp; <b>", "\xC2\xA9\xC3\xBF\xC4\x80");
			mt_srand(3);
			for($i = 0; $i < 3000; $i++)
			{
				$s = "";
				for($j = mt_rand(0, 10); $j > 0; $j--)
					$s .= chr(mt_rand(0, 255));
				$samples[] = $s;
			}
			$same = 0;
			$differ = array();
			foreach($samples as $s)
			{
				$ok = (UTF::latin1ToUtf8($s) === @utf8_encode($s)) &&
					(UTF::utf8ToLatin1($s) === @utf8_decode($s));
				if($ok)
					$same++;
				else
					$differ[] = bin2hex($s);
			}
			return array("same" => $same, "differ" => array_slice($differ, 0, 5));
		', array('php/utility/utf.php'));
		$this->assertTrue(is_array($result), 'the ISO-8859-1 helpers ran: ' . $this->brief($other));
		if(!is_array($result))
			return;
		$this->assertEquals(3013, $result['same'], 'latin1ToUtf8/utf8ToLatin1 give what utf8_encode/utf8_decode gave, for '
			. $result['same'] . ' of 3013 strings; first to differ: ' . implode(', ', $result['differ']));
		$this->assertQuiet($other, 'the ISO-8859-1 helpers');
	}

	// ---- extsearch: PirateBay's strptime() -------------------------------

	public function testPirateBayUploadedTimeMatchesStrptime()
	{
		list($result, $other) = $this->run_isolated('
			$old = function($tms)
			{
				if(strpos($tms,":")!==false)
				{
					$tm = @strptime($tms,"%m-%d %H:%M");
					if($tm===false)
					{
						$tms = str_replace( "Y-day", "-1 day", $tms );
						$tm = strtotime($tms);
						if($tm!==false)
							$tm = localtime($tm,true);
					}
					else
						$tm["tm_year"] = date("Y")-1900;
				}
				else
					$tm = @strptime($tms,"%m-%d %Y");
				if($tm===false)
					return false;
				return mktime( $tm["tm_hour"], $tm["tm_min"], $tm["tm_sec"], $tm["tm_mon"]+1, $tm["tm_mday"], $tm["tm_year"]+1900 );
			};
			$samples = array("05-12 12:34", "12-31 23:59", "01-01 00:00", "5-3 7:05", "05-12  12:34", "05-12 \t12:34",
				"05-12 12:34 extra", "05-12 12:345", "05-12 12:65", "05-12 24:00", "13-01 10:00", "00-01 10:00",
				"02-30 10:00", "05- 12 10:00", " 05-12 10:00", "05-12 1:2", "051-2 10:00", "05-12 10 :00",
				"Today 12:34", "Y-day 08:15", "yesterday 01:00", "05-12 2021", "12-31 1999", "1-1 1", "01-01 99999",
				"05-12 20211", "05-12", "05-12 ", "garbage", "", "02-29 2024", "02-29 2023", "10-10 2010x");
			$differ = array();
			foreach($samples as $s)
				if(PirateBayEngine::uploadedTime($s) !== $old($s))
					$differ[] = $s . " => " . var_export(PirateBayEngine::uploadedTime($s), true) . " vs " . var_export($old($s), true);
			return array("count" => count($samples), "differ" => $differ);
		', array('plugins/extsearch/engines.php', 'plugins/extsearch/engines/PirateBay.php'));
		$this->assertTrue(is_array($result), 'the PirateBay date parser ran: ' . $this->brief($other));
		if(!is_array($result))
			return;
		$this->assertEquals(array(), $result['differ'], 'uploadedTime() reads each of ' . $result['count']
			. ' listing dates as strptime() did; differ: ' . implode('; ', $result['differ']));
		$this->assertQuiet($other, 'PirateBay date parsing');
	}

	// ---- strftime() / gmstrftime() ---------------------------------------

	public function testTimeFormatMatchesStrftime()
	{
		list($result, $other) = $this->run_isolated('
			$convs = str_split("aAbBcCdDeFgGhHIjklmMnpPrRsStTuUVwWxXyYzZ%qfiJ");
			$specs = array();
			foreach($convs as $c)
				foreach(array("", "-", "_", "0", "^", "#", "-^") as $f)
					foreach(array("", "1", "6") as $w)
						foreach(array("", "E", "O") as $m)
							$specs[] = "%" . $f . $w . $m . $c;
			$specs[] = "%";
			$specs[] = "50% off %";
			$times = array(0, 951782400, 1009843199, 1704067199, 1735689600, 1767225599, 1790000000);
			$zones = array("UTC", "Europe/Madrid", "America/New_York", "Asia/Kolkata", "Australia/Lord_Howe");
			$checked = 0;
			$differ = array();
			foreach($zones as $zone)
			{
				date_default_timezone_set($zone);
				foreach($times as $t)
					foreach($specs as $spec)
					{
						// glibc computes %s with mktime() of the broken-down time
						// in the process zone (TZ), not in the zone PHP formats in:
						// with TZ=Europe/Moscow both strftime("%s", 0) and
						// gmstrftime("%s", 0) answer -10800. TimeFormat renders the
						// timestamp itself, which is checked below for every zone.
						if(substr($spec, -1) == "s")
							continue;
						$fmt = "<" . $spec . ">";
						$pairs = array(array(@gmstrftime($fmt, $t), TimeFormat::gmstrftime($fmt, $t)),
							array(@strftime($fmt, $t), TimeFormat::strftime($fmt, $t)));
						foreach($pairs as $pair)
						{
							$checked++;
							if($pair[0] !== $pair[1])
								$differ[] = $zone . " " . $t . " " . $fmt . " " . json_encode($pair);
						}
					}
			}
			// %s is the timestamp whatever zone PHP formats in and whatever
			// zone the process runs in.
			$tz = getenv("TZ");
			foreach(array("UTC", "Europe/Moscow", "America/New_York") as $process)
			{
				putenv("TZ=" . $process);
				foreach($zones as $zone)
				{
					date_default_timezone_set($zone);
					foreach($times as $t)
						foreach(array("<%s>", "<%Es>", "<%Os>") as $fmt)
						{
							$pair = array(TimeFormat::strftime($fmt, $t), TimeFormat::gmstrftime($fmt, $t));
							$checked++;
							if($pair !== array("<" . $t . ">", "<" . $t . ">"))
								$differ[] = "TZ=" . $process . " " . $zone . " " . $t . " " . $fmt . " " . json_encode($pair);
						}
				}
			}
			putenv(($tz === false) ? "TZ" : "TZ=" . $tz);
			// Flags and widths on %s as glibc 2.43 renders them under TZ=UTC,
			// the one process zone where its %s is the timestamp (captured
			// 2026-10-17): a space is the default pad, "0" pads with zeros,
			// "-" keeps an explicit width, and E and O are ignored.
			$padded = array(
				0 => array("%6s" => "     0", "%06s" => "000000", "%-6s" => "     0", "%_6s" => "     0",
					"%^6s" => "     0", "%#6s" => "     0", "%-^6s" => "     0", "%6Es" => "     0", "%06Os" => "000000"),
				1790000000 => array("%s" => "1790000000", "%1s" => "1790000000", "%6s" => "1790000000",
					"%12s" => "  1790000000", "%012s" => "001790000000", "%-12s" => "  1790000000"));
			foreach($padded as $t => $expected)
				foreach($expected as $spec => $text)
				{
					$pair = array(TimeFormat::strftime($spec, $t), TimeFormat::gmstrftime($spec, $t));
					$checked++;
					if($pair !== array($text, $text))
						$differ[] = $t . " " . $spec . " " . json_encode($pair) . " is not " . json_encode($text);
				}
			date_default_timezone_set("Europe/Madrid");
			$now = time();
			$current = TimeFormat::strftime("%Y-%m-%d %H:%M");
			if(($current !== @strftime("%Y-%m-%d %H:%M", $now)) && ($current !== @strftime("%Y-%m-%d %H:%M")))
				$differ[] = "no timestamp means now";
			return array("checked" => $checked, "differ" => array_slice($differ, 0, 10));
		', array('php/util.php'));
		$this->assertTrue(is_array($result), 'TimeFormat ran: ' . $this->brief($other));
		if(!is_array($result))
			return;
		$this->assertEquals(array(), $result['differ'], 'TimeFormat renders ' . $result['checked']
			. ' formats as strftime/gmstrftime did and %s as the timestamp; differ: ' . implode('; ', $result['differ']));
		$this->assertQuiet($other, 'TimeFormat');
	}

	public function testFeedsPubDateFormatIsRfc822()
	{
		list($result, $other) = $this->run_isolated('
			date_default_timezone_set("America/New_York");
			return TimeFormat::gmstrftime("%a, %d %b %Y %T %Z", 1767225599);
		', array('php/util.php'));
		$this->assertEquals('Wed, 31 Dec 2025 23:59:59 GMT', $result, 'the feeds pubDate is RFC 822 in GMT');
		$this->assertQuiet($other, 'the feeds pubDate format');
	}

	// ---- xmpp: xml_set_object() ------------------------------------------

	public function testXmppStreamParsesWithoutXmlSetObject()
	{
		list($result, $other) = $this->run_isolated('
			$probe = new class extends XMPPHP_XMLStream
			{
				public function feed($xml)
				{
					xml_parse($this->parser, $xml, false);
					$names = array();
					foreach($this->xmlobj as $depth => $obj)
						$names[$depth] = $obj->name . "@" . $obj->ns;
					return array("depth" => $this->xml_depth, "names" => $names);
				}
			};
			return $probe->feed("<stream:stream xmlns=\"jabber:client\" xmlns:stream=\"http://etherx.jabber.org/streams\">"
				. "<message to=\"a@b\"><body>hi");
		', array('plugins/xmpp/XMPPHP/XMLStream.php'));
		$this->assertEquals(array('depth' => 3, 'names' => array(1 => 'stream@http://etherx.jabber.org/streams',
			2 => 'message@jabber:client', 3 => 'body@jabber:client')), $result, 'the stream parser still reaches its own handlers: '
			. json_encode($result) . ' ' . $this->brief($other));
		$this->assertTrue(strpos($other, 'Deprecated') === false,
			'setting up the stream parser raises no deprecation: ' . $this->brief($other));
	}

	// ---- cache: SplObjectStorage::contains() / attach() -------------------

	public function testCacheWalkOverObjectsRaisesNoDeprecation()
	{
		// rCache::get() walks every value it unserializes and remembers the
		// objects it has met, so an object met twice is the path that both
		// asks whether it was seen and records that it was. PHP 8.5
		// deprecates SplObjectStorage::contains() and attach() in favour of
		// offsetExists() and offsetSet(), which exist since PHP 5.3.
		list($result, $other) = $this->run_isolated('
			// Bound to rCache rather than a subclass of it, because
			// constructing an rCache creates the settings directory.
			$walk = Closure::bind(function($value)
			{
				return rCache::holdsRefusedClass($value, 0, $reason);
			}, null, "rCache");
			$first = new stdClass();
			$second = new stdClass();
			$first->peer = $second;
			$second->peer = $first;
			return $walk(array($first, $second));
		', array('php/cache.php'));
		$this->assertTrue($result === false, 'a cycle between two objects is walked to the end, not refused: '
			. json_encode($result) . ' ' . $this->brief($other));
		$this->assertQuiet($other, 'walking objects for a refused class');
	}

	// ---- extsearch: libxml_disable_entity_loader() -----------------------

	public function testHtmlEnginesDoNotCallTheEntityLoaderSwitch()
	{
		foreach(array('mTeam', 'IPTorrents') as $engine)
		{
			list($result, $other) = $this->run_isolated('
				$method = new ReflectionMethod("' . $engine . 'Engine", "disableEntityLoader");
				if(PHP_VERSION_ID < 80100)
					$method->setAccessible(true);
				$method->invoke(null);
				return true;
			', array('plugins/extsearch/engines.php', 'plugins/extsearch/engines/' . $engine . '.php'));
			$this->assertTrue($result === true, $engine . ' ran: ' . $this->brief($other));
			$this->assertQuiet($other, $engine . '::disableEntityLoader()');
		}
	}

	// ---- nothing else in the tree calls them -----------------------------

	public function testNoShippedCodeCallsTheDeprecatedFunctions()
	{
		$deprecated = array('utf8_encode', 'utf8_decode', 'strptime', 'strftime', 'gmstrftime', 'xml_set_object');
		$found = array();
		foreach(array('/php', '/plugins', '/conf') as $sub)
		{
			$walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
				$this->root . $sub, FilesystemIterator::SKIP_DOTS));
			foreach($walk as $file)
			{
				if(substr($file->getFilename(), -4) !== '.php')
					continue;
				$tokens = token_get_all(file_get_contents($file->getPathname()));
				foreach($tokens as $i => $token)
				{
					if(!is_array($token) || ($token[0] !== T_STRING) ||
						!in_array(strtolower($token[1]), $deprecated))
						continue;
					$prev = $i - 1;
					while(($prev >= 0) && is_array($tokens[$prev]) && ($tokens[$prev][0] === T_WHITESPACE))
						$prev--;
					$next = $i + 1;
					while(isset($tokens[$next]) && is_array($tokens[$next]) && ($tokens[$next][0] === T_WHITESPACE))
						$next++;
					// A call, not a method or a declaration of the same name.
					$method = ($prev >= 0) && is_array($tokens[$prev]) &&
						in_array($tokens[$prev][0], array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION));
					if(!$method && isset($tokens[$next]) && ($tokens[$next] === '('))
						$found[] = substr($file->getPathname(), strlen($this->root) + 1) . ':' . $token[2]
							. ' ' . $token[1];
				}
			}
		}
		$this->assertEquals(array(), $found, 'no shipped code calls a deprecated date, encoding or XML function: '
			. implode(', ', $found));
	}
}
