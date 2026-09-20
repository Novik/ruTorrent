<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/RtorrentCommandParser.php');

/**
 * plugins/edit/action.php and plugins/retrackers/update.php erase a download
 * and load it again. Between the two calls the torrent exists nowhere, so
 * every value they carry into the reload has to survive the trip: a reload
 * that is refused loses the download, and a reload that arrives altered
 * reattaches it to a directory it was never in.
 *
 * The values are not typed parameters. They are appended to the load call as
 * command strings and rtorrent parses them itself, so what matters is what its
 * parser makes of them -- RtorrentCommandParser answers that. Each case here
 * feeds one value to the daemon, runs the real script against it, and reads
 * the load call back out of the request body the daemon received.
 *
 * The table is the point. A value reaches the reload from three places -- the
 * download's directory, its connection type and its throttle -- and the same
 * shapes have to hold at all three, so adding a shape covers every one of them
 * without a new case being written.
 */
class EraseAndReloadShapeTest extends TestCase
{
	use TorrentSequenceFixtures;

	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

	private $base;
	private $daemon;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-reload-shape-' . getmypid();
		$this->removeTree($this->base);
		@mkdir($this->base . '/session', 0700, true);
		@mkdir($this->base . '/profile/settings', 0700, true);
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
		return realpath(__DIR__ . '/../..');
	}

	/**
	 * The values a download can carry back from the daemon. Each is a byte
	 * sequence a name, a path or a label really can hold; rtorrent answers
	 * with whatever the filesystem gave it, and a value that arrived over the
	 * XMLRPC door was only ever checked for the command it names.
	 */
	private function shapes()
	{
		return array(
			'plain'          => 'ab',
			'double quote'   => 'a"b',
			'single quote'   => "a'b",
			'backslash'      => 'a\\b',
			'backslash then quote' => 'a\\"b',
			'comma'          => 'a,b',
			'escaped comma'  => 'a\\,b',
			'newline'        => "a\nb",
			'semicolon'      => 'a;b',
			'space'          => 'a b',
			'leading dollar' => '$execute=/bin/true',
			'inner dollar'   => 'a$b',
			'parentheses'    => 'a(cat,b)c',
			'braces'         => 'a{b}c',
			'utf8'           => "a\xC3\xA9\xF0\x9F\x8E\x89e\xCC\x81b",
			'long'           => 'a' . str_repeat('x', 4998) . 'b',
			'empty'          => '',
			'whitespace'     => '   ',
		);
	}

	/**
	 * A value that is not valid UTF-8 -- a Windows-1251 path, which is why
	 * UTF::win2utf() exists -- is dropped whole by rXMLRPCParam, which encodes
	 * with htmlspecialchars(..., ENT_NOQUOTES, 'UTF-8') and gets an empty
	 * string back for an invalid sequence. The command around it goes out
	 * empty, so the reload silently loses the directory instead of carrying a
	 * wrong one. That is a separate defect from the ones below and it cannot
	 * be fixed by quoting: the bytes have no XMLRPC representation short of
	 * base64. It is named here so the table does not pretend to cover it.
	 */
	private function shapesNotRepresentableInXml()
	{
		return array(
			'windows-1251' => "a\xEF\xF0\xE8\xE2\xE5\xF2b",
			// A carriage return is not carried by an XMLRPC string at all:
			// every conforming XML parser turns one in element content into a
			// line feed before the value is read (XML 1.0, section 2.11), and
			// the daemon's parser is one. The value that arrives is a real
			// value, just not the one that was sent, and no amount of quoting
			// changes that -- it would take base64 for every command string.
			'carriage return' => "a\rb",
		);
	}

	private function settingsStub($iVersion, $plugins)
	{
		return 'chdir(' . var_export($this->repoRoot() . '/php', true) . "); require_once('settings.php');\n"
			. '$__r = new ReflectionClass("rTorrentSettings");'
			. ' $__s = $__r->newInstanceWithoutConstructor();'
			. ' $__s->iVersion = ' . $iVersion . ';'
			. ' $__s->aliases = array(); $__s->plugins = ' . var_export($plugins, true) . ';'
			. ' $__s->directory = "/tmp"; $__s->linkExist = true;'
			. ' $__p = $__r->getProperty("theSettings"); $__p->setAccessible(true);'
			. ' $__p->setValue(null, $__s);' . "\n";
	}

	private function startDaemon($values)
	{
		$this->stopDaemon();
		file_put_contents($this->base . '/session/' . self::HASH . '.torrent',
			$this->announceOnlyTorrent());
		// One reply per request, plus spares: the opening multicall, d.erase,
		// the load, and whatever a failing run does instead.
		$this->daemon = new FakeRtorrentDaemon(array(
			$values, array(0), array(0), array(0), array(0),
		), $this->base . '/calls.log');
	}

	private function run_($driver)
	{
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	/** plugins/edit/action.php, saving a comment edit. */
	private function saveAnEdit($directory, $connectionSeed, $throttle)
	{
		$root = $this->repoRoot();
		$values = array($this->base . '/session/', 1, 1, 1, '', 'lbl',
			$directory, $connectionSeed, 0);
		$plugins = array();
		if ($throttle !== null) {
			$values[] = $throttle;
			$plugins = array('throttle' => true);
		}
		$this->startDaemon($values);
		$driver = $this->base . '/drive-edit.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10; $rpcLogCalls = false; $saveUploadedTorrents = false;' . "\n"
			. $this->settingsStub(0x904, $plugins)
			. '$HTTP_RAW_POST_DATA = ' . var_export('hash=' . self::HASH
				. '&set_comment=1&comment=c', true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/edit', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/edit/action.php', true) . ");\n");
		return $this->run_($driver);
	}

	/** plugins/retrackers/update.php, adding a tracker to a torrent. */
	private function updateRetrackers($directory)
	{
		$root = $this->repoRoot();
		$this->startDaemon(array($this->base . '/session/', 1, '', 'lbl',
			$directory, 0, 'a-name'));
		$driver = $this->base . '/drive-retrackers.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10; $rpcLogCalls = false;' . "\n"
			. $this->settingsStub(0x904, array())
			. 'chdir(' . var_export($root . '/plugins/retrackers', true) . ");\n"
			. 'require_once(' . var_export($root . '/plugins/retrackers/retrackers.php', true) . ");\n"
			. '$rt = new rRetrackers(); $rt->list = array(array("http://added.test/announce"));'
			. ' $rt->dontAddPrivate = 0; $rt->store();' . "\n"
			. '$argv = array("update.php", ' . var_export(self::HASH, true) . '); $argc = 2;' . "\n"
			. 'require(' . var_export($root . '/plugins/retrackers/update.php', true) . ");\n");
		return $this->run_($driver);
	}

	private function erasedAndReloaded()
	{
		$erase = -1;
		$load = -1;
		foreach ($this->daemon->calls() as $index => $call) {
			if (($call === 'd.erase') && ($erase < 0)) {
				$erase = $index;
			}
			if ((strpos($call, 'load') === 0) && ($load < 0)) {
				$load = $index;
			}
		}
		return array($erase, $load);
	}

	/** The command strings the load call carried, in order. */
	private function reloadCommandStrings()
	{
		foreach ($this->daemon->bodies() as $body) {
			$xml = @simplexml_load_string($body);
			if ($xml === false) {
				continue;
			}
			$calls = array();
			if ((string)$xml->methodName === 'system.multicall') {
				foreach ($xml->params->param->value->array->data->value as $entry) {
					$calls[] = array((string)$entry->struct->member[0]->value->string,
						$entry->struct->member[1]->value->array->data->value);
				}
			} else {
				$params = array();
				foreach ($xml->params->param as $param) {
					$params[] = $param->value;
				}
				$calls[] = array((string)$xml->methodName, $params);
			}
			foreach ($calls as $call) {
				if (strpos($call[0], 'load') !== 0) {
					continue;
				}
				$strings = array();
				foreach ($call[1] as $index => $value) {
					// Parameter 0 is the torrent itself, as base64 or a path.
					if (($index === 0) || isset($value->base64)) {
						continue;
					}
					$strings[] = (string)$value->string;
				}
				return $strings;
			}
		}
		return null;
	}

	/**
	 * The single argument rtorrent would end up passing to $command, or a
	 * string starting with '!' saying why it would not.
	 */
	private function argumentReaching($command)
	{
		$strings = $this->reloadCommandStrings();
		if ($strings === null) {
			return '!no load call was sent';
		}
		foreach ($strings as $parameter) {
			if (strpos($parameter, $command . '=') !== 0) {
				continue;
			}
			try {
				$commands = RtorrentCommandParser::commands($parameter);
			} catch (RtorrentInputError $e) {
				return '!rtorrent refuses the whole load command: ' . $e->getMessage();
			}
			if (count($commands) !== 1) {
				return '!the parameter holds ' . count($commands) . ' commands, not one';
			}
			$substituted = RtorrentCommandParser::substitutedArguments($commands[0]['args']);
			if (count($substituted)) {
				return '!rtorrent would run the value as a command: ' . $substituted[0];
			}
			if (count($commands[0]['args']) !== 1) {
				return '!the value arrives as ' . count($commands[0]['args']) . ' arguments';
			}
			return $commands[0]['args'][0];
		}
		return '!no ' . $command . ' command was sent';
	}

	private function readable($value)
	{
		$shown = addcslashes($value, "\0..\37\177");
		return (strlen($shown) > 60) ? (substr($shown, 0, 55) . '…') : $shown;
	}

	/**
	 * The erase and the reload happen together or not at all. A run that
	 * erases and does not load has lost the download, whatever the value was.
	 */
	public function testEveryShapeEitherErasesAndReloadsOrDoesNeither()
	{
		$all = array_merge($this->shapes(), $this->shapesNotRepresentableInXml());
		foreach ($all as $name => $value) {
			foreach (array('directory', 'connection seed', 'throttle') as $where) {
				$this->saveAnEdit(
					($where === 'directory') ? '/tmp/' . $value : '/tmp/d',
					($where === 'connection seed') ? $value : 'seed',
					($where === 'throttle') ? $value : null);
				list($erase, $load) = $this->erasedAndReloaded();
				$this->assertTrue(($erase >= 0) === ($load >= 0),
					'edit, ' . $where . ' holding ' . $name . ': erased=' . ($erase >= 0 ? 'yes' : 'no')
					. ' reloaded=' . ($load >= 0 ? 'yes' : 'no'));
			}
			$this->updateRetrackers('/tmp/' . $value);
			list($erase, $load) = $this->erasedAndReloaded();
			$this->assertTrue(($erase >= 0) === ($load >= 0),
				'retrackers, directory holding ' . $name . ': erased=' . ($erase >= 0 ? 'yes' : 'no')
				. ' reloaded=' . ($load >= 0 ? 'yes' : 'no'));
		}
	}

	/**
	 * The directory the download had is the directory it gets back. The value
	 * arrives from the daemon already carrying the escaping
	 * rXMLRPCRequest::run() puts on every string it reads, so quoting it a
	 * second time hands rtorrent a path with a backslash in it that the
	 * download never had -- and the erase has already happened.
	 */
	public function testTheReloadRestoresTheDirectoryTheDownloadHad()
	{
		foreach ($this->shapes() as $name => $value) {
			$directory = '/tmp/' . $value;
			$this->saveAnEdit($directory, 'seed', null);
			// parseDirectory() substitutes the default for an empty one.
			$expected = (trim($value) === '') ? rtrim('/tmp/' . $value, '/') : $directory;
			$this->assertEquals($expected, $this->argumentReaching('d.set_directory_base'),
				'edit, directory holding ' . $name . ' (' . $this->readable($directory) . ')');

			$this->updateRetrackers($directory);
			$this->assertEquals($expected, $this->argumentReaching('d.set_directory_base'),
				'retrackers, directory holding ' . $name . ' (' . $this->readable($directory) . ')');
		}
	}

	/**
	 * The addition carries the download's connection type and throttle back
	 * unchanged. Both are appended to the load call as bare text, so a value
	 * holding a comma splits into two arguments, one holding a space or a
	 * semicolon ends the command early, and one starting with '$' is run.
	 * rTorrent::areValidAdditions() checks the name in front of the '=' and
	 * nothing after it, so none of that is refused.
	 */
	public function testTheReloadCarriesTheAdditionValuesUnchanged()
	{
		foreach ($this->shapes() as $name => $value) {
			// A value rtorrent would run rather than read cannot be sent at
			// all, so the run is expected to stop before it erases anything.
			$expected = $this->wouldBeRunAsACommand($value)
				? '!no load call was sent' : $value;

			$this->saveAnEdit('/tmp/d', $value, null);
			$this->assertEquals($expected, $this->argumentReaching('d.set_connection_seed'),
				'edit, connection seed holding ' . $name . ' (' . $this->readable($value) . ')');

			$this->saveAnEdit('/tmp/d', 'seed', $value);
			$this->assertEquals($expected, $this->argumentReaching('d.set_throttle_name'),
				'edit, throttle name holding ' . $name . ' (' . $this->readable($value) . ')');
		}
	}

	/**
	 * rtorrent replaces a value whose first character is '$' with the result of
	 * running it, after the quotes come off -- so such a value is refused here
	 * rather than sent, and the refusal has to come before the erase.
	 */
	private function wouldBeRunAsACommand($value)
	{
		return isset($value[0]) && ($value[0] === '$');
	}
}
