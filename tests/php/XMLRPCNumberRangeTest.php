<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');

/**
 * rXMLRPCParam writes an <i8> or <i4> with number_format(), which formats
 * whatever float it is handed. INF, -INF and NAN come out as "inf", "-inf"
 * and "nan", and a float beyond the 64-bit range comes out as a string of
 * digits no 64-bit integer holds. None of those is an XMLRPC integer, and the
 * daemon's parser refuses the whole document that carries one.
 *
 * They are not exotic. plugins/httprpc/action.php runs floatval() on every
 * n-prefixed setting it is sent, so "1e400" becomes INF on its way to
 * set_max_peers, and the ratio plugin multiplies a user's figure by 2^30.
 *
 * The boundary these tests fix: a number an XMLRPC integer can hold is sent
 * exactly as before, and one it cannot hold is refused before any payload is
 * built, with a fault the caller can show.
 */
class RecordingNumberRequest extends rXMLRPCRequest
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

class XMLRPCNumberRangeTest extends TestCase
{
	const REFUSAL = 'Refused: not a number an XMLRPC integer can hold.';

	private $previousSettings;
	private $base;
	private $daemon;

	public function setUp()
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		$settings = $reflection->newInstanceWithoutConstructor();
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();

		$property = $reflection->getProperty('theSettings');
		$property->setAccessible(true);
		$this->previousSettings = $property->getValue();
		$property->setValue(null, $settings);

		// A payload that does get sent goes to a closed port on the loopback.
		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['scgi_port'] = 1;
		$GLOBALS['rpcTimeOut'] = 1;
		$GLOBALS['rpcLogCalls'] = false;
		$GLOBALS['rpcLogFaults'] = false;

		$this->base = sys_get_temp_dir() . '/rutorrent-number-range-' . getmypid();
		$this->removeTree($this->base);
		@mkdir($this->base . '/profile/settings', 0700, true);
	}

	public function tearDown()
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		$property = $reflection->getProperty('theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $this->previousSettings);
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

	private function readable($value)
	{
		return is_float($value) ? var_export($value, true) : json_encode($value);
	}

	/**
	 * Run a request of these commands and report what it did. A value that
	 * makes the build throw is reported as such rather than ending the file.
	 */
	private function runCommands($commands)
	{
		$request = new RecordingNumberRequest();
		foreach ($commands as $command) {
			$request->addCommand($command);
		}
		try {
			$ran = $request->run();
		} catch (Throwable $e) {
			return array('threw' => get_class($e), 'ran' => null, 'fault' => null,
				'faultString' => null, 'payloads' => $request->payloads);
		}
		return array('threw' => null, 'ran' => $ran, 'fault' => $request->fault,
			'faultString' => $request->faultString, 'payloads' => $request->payloads);
	}

	/** A set command carrying $value, typed the way addParameter() types it. */
	private function setCommand($value, $type = null)
	{
		try {
			$command = new rXMLRPCCommand('throttle.max_peers.normal.set', '');
			$command->addParameter($value, $type);
		} catch (Throwable $e) {
			return get_class($e);
		}
		return $command;
	}

	private function assertRefused($value, $type, $label)
	{
		$command = $this->setCommand($value, $type);
		if (is_string($command)) {
			$this->assertTrue(false, $label . ': building the command threw ' . $command);
			return;
		}
		$result = $this->runCommands(array($command));
		$this->assertTrue($result['threw'] === null,
			$label . ': the run does not throw' . ($result['threw'] ? ' (threw ' . $result['threw'] . ')' : ''));
		$this->assertTrue(count($result['payloads']) === 0,
			$label . ': no payload is built, got ' . json_encode($result['payloads']));
		$this->assertTrue(($result['ran'] === false) && ($result['fault'] === true),
			$label . ': the request is refused and marked faulted');
		$this->assertEquals(self::REFUSAL, $result['faultString'],
			$label . ': the fault says why, got ' . json_encode($result['faultString']));
	}

	/** The one <param> the set command's number went out as. */
	private function sentAs($value, $type = null)
	{
		$command = $this->setCommand($value, $type);
		if (is_string($command)) {
			return 'threw ' . $command;
		}
		$result = $this->runCommands(array($command));
		if (($result['threw'] !== null) || (count($result['payloads']) !== 1)) {
			return 'not sent';
		}
		if (!preg_match_all('|<param><value>(.*?)</value></param>|', $result['payloads'][0], $params)) {
			return 'no params';
		}
		return end($params[1]);
	}

	// ---- what is refused ------------------------------------------------

	public function testNonFiniteNumbersAreRefused()
	{
		foreach (array(INF, -INF, NAN) as $value) {
			$this->assertRefused($value, null, 'float ' . $this->readable($value));
		}
	}

	/** The value httprpc's setsettings makes out of v=1e400. */
	public function testTheSetsettingsCastOfAnOverlongExponentIsRefused()
	{
		$this->assertRefused(floatval('1e400'), null, "floatval('1e400')");
		$this->assertRefused(floatval('-1e400'), null, "floatval('-1e400')");
	}

	public function testFloatsBeyondTheI8RangeAreRefused()
	{
		foreach (array(9223372036854775808.0, 1e19, 1e300,
			-9223372036854777856.0, -1e19, -1e300) as $value) {
			$this->assertRefused($value, null, 'float ' . $this->readable($value));
		}
	}

	public function testAnExplicitI4OutsideItsRangeIsRefused()
	{
		$this->assertRefused(2147483648, 'i4', 'i4 2147483648');
		$this->assertRefused(-2147483649, 'i4', 'i4 -2147483649');
	}

	public function testAnExplicitIntegerTypeOnSomethingElseIsRefused()
	{
		foreach (array('abc', '', '1e400', 'inf', null, array()) as $value) {
			$this->assertRefused($value, 'i8', 'i8 ' . json_encode($value));
		}
	}

	/**
	 * Two commands take the system.multicall branch. One bad number there
	 * stops the batch whole: the good member is not applied on its own.
	 */
	public function testOneBadNumberStopsTheWholeBatch()
	{
		$good = new rXMLRPCCommand('throttle.max_uploads.set', array('', 50));
		$bad = new rXMLRPCCommand('throttle.max_peers.normal.set', array('', INF));
		$result = $this->runCommands(array($good, $bad));
		$this->assertTrue($result['threw'] === null, 'the batch does not throw');
		$this->assertTrue(count($result['payloads']) === 0,
			'nothing of the batch is built, got ' . json_encode($result['payloads']));
		$this->assertEquals(self::REFUSAL, $result['faultString'],
			'the batch carries the refusal');
	}

	// ---- what still has to work -----------------------------------------

	public function testOrdinaryNumbersAreSentAsBefore()
	{
		$cases = array(
			array(100, null, '<i4>100</i4>'),
			array(-1, null, '<i4>-1</i4>'),
			array(0, null, '<i4>0</i4>'),
			array(2147483647, null, '<i4>2147483647</i4>'),
			// An int beyond i4 has always been sent as a string; not this change.
			array(3000000000, null, '<string>3000000000</string>'),
			array(100.0, null, '<i8>100</i8>'),
			array(1024.0 * 1024 * 1024 * 1024, null, '<i8>1099511627776</i8>'),
			array(1.5, null, '<i8>2</i8>'),
			array(-2.5, null, '<i8>-3</i8>'),
			array(-0.0, null, '<i8>0</i8>'),
			array(floatval('100'), null, '<i8>100</i8>'),
			array(floatval(''), null, '<i8>0</i8>'),
			array('1024', 'i8', '<i8>1024</i8>'),
			array(7, 'i8', '<i8>7</i8>'),
			array(PHP_INT_MAX, 'i8', '<i8>' . PHP_INT_MAX . '</i8>'),
			array(PHP_INT_MIN, 'i8', '<i8>' . PHP_INT_MIN . '</i8>'),
			array(9223372036854774784.0, null, '<i8>9223372036854774784</i8>'),
			array(-9223372036854775808.0, null, '<i8>-9223372036854775808</i8>'),
			array('abc', null, '<string>abc</string>'),
			array('inf', null, '<string>inf</string>'),
		);
		foreach ($cases as $case) {
			list($value, $type, $expected) = $case;
			$this->assertEquals($expected, $this->sentAs($value, $type),
				$this->readable($value) . ($type ? ' as ' . $type : '') . ' is sent as ' . $expected
					. ', got ' . $this->sentAs($value, $type));
		}
	}

	// ---- through the plugin that makes the float ------------------------

	/**
	 * Post $query to plugins/httprpc/action.php against a daemon that answers
	 * every read with 5, and return the script's output.
	 */
	private function postToHttprpc($query)
	{
		$root = realpath(__DIR__ . '/../..');
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon(array(
			array(5), array(5), array(5), array(5),
		), $this->base . '/calls.log');
		$driver = $this->base . '/drive-httprpc.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 5; $rpcLogCalls = false; $rpcLogFaults = false;' . "\n"
			. 'chdir(' . var_export($root . '/php', true) . "); require_once('settings.php');\n"
			. '$__r = new ReflectionClass("rTorrentSettings");'
			. ' $__s = $__r->newInstanceWithoutConstructor();'
			. ' $__s->iVersion = 0x904; $__s->aliases = array(); $__s->plugins = array();'
			. ' $__s->directory = "/tmp"; $__s->linkExist = true;'
			. ' $__p = $__r->getProperty("theSettings"); $__p->setAccessible(true);'
			. ' $__p->setValue(null, $__s);' . "\n"
			. '$HTTP_RAW_POST_DATA = ' . var_export($query, true) . ";\n"
			. 'chdir(' . var_export($root . '/plugins/httprpc', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/httprpc/action.php', true) . ");\n");
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver) . ' 2>&1', $output);
		$this->daemon->stop();
		return implode("\n", $output);
	}

	private function setCallBodies()
	{
		$daemon = $this->daemon;
		$this->daemon = null;
		return array_values(array_filter($daemon->bodies(), function ($body) {
			return strpos($body, 'set_max_peers') !== false;
		}));
	}

	public function testHttprpcRefusesAnInfiniteSettingAndSaysSo()
	{
		$output = $this->postToHttprpc('mode=setsettings&s=nmax_peers&v=1e400');
		$sets = $this->setCallBodies();
		$this->assertEquals(0, count($sets),
			'the daemon is sent no set_max_peers call, got ' . json_encode($sets));
		$this->assertTrue(strpos($output, self::REFUSAL) !== false,
			'the answer carries the refusal, got ' . json_encode($output));
	}

	public function testHttprpcStillSendsAnOrdinarySetting()
	{
		$output = $this->postToHttprpc('mode=setsettings&s=nmax_peers&v=100');
		$sets = $this->setCallBodies();
		$this->assertEquals(1, count($sets), 'one set_max_peers call reaches the daemon');
		$this->assertTrue((count($sets) === 1) && (strpos($sets[0], '<i8>100</i8>') !== false),
			'carrying <i8>100</i8>, got ' . json_encode($sets));
		$this->assertTrue(strpos($output, self::REFUSAL) === false,
			'and nothing is refused, got ' . json_encode($output));
	}
}
