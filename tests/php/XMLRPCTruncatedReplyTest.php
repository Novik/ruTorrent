<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');

/**
 * rXMLRPCRequest::run() reads rtorrent's answer until the connection closes
 * and takes out every value in it. An answer that stops early -- rtorrent
 * stopped or restarted while writing it, a read that timed out while the
 * daemon was busy -- still holds every value written before the cut, and read
 * that way it is a complete, shorter result: three loaded torrents become two,
 * or none, and a caller asking whether a hash is loaded is told it is not.
 *
 * rtorrent ends every answer with </methodResponse>: built with xmlrpc-c (as
 * 0.9.8 is) it adds a CRLF, built with tinyxml2 (as 0.16.25 is) nothing. The
 * answers below were captured from both over SCGI, byte for byte with
 * rtorrent's own headers, and are cut here the way a connection is: anywhere,
 * including inside a value.
 */
class XMLRPCTruncatedReplyTest extends TestCase
{
	// d.multicall2 "" default d.hash= with three torrents loaded, from
	// rTorrent 0.16.25 (rakshasa/rtorrent master 3c7bb87f, tinyxml2).
	const HASHES_016 = "Status: 200 OK\r\n"
		. "Content-Type: text/xml\r\n"
		. "Content-Length: 474\r\n"
		. "\r\n"
		. "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data>"
		. "<value><array><data>"
		. "<value><string>E3072F6DBC6622E9C8E3D24549A70FDBB0AFB49C</string></value></data></array>"
		. "</value><value><array><data>"
		. "<value><string>22D221B40747A89068ACAC0743E921486BAACAFB</string></value></data></array>"
		. "</value><value><array><data>"
		. "<value><string>D0C9E257FEE2DB586F1D397FFE07FC19F864E021</string></value></data></array>"
		. "</value></data></array></value></param></params></methodResponse>";

	// system.multicall of system.client_version and network.xmlrpc.size_limit.
	const MULTICALL_016 = "Status: 200 OK\r\n"
		. "Content-Type: text/xml\r\n"
		. "Content-Length: 286\r\n"
		. "\r\n"
		. "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data>"
		. "<value><array><data>"
		. "<value><string>0.16.25</string></value></data></array></value>"
		. "<value><array><data>"
		. "<value><i8>16777216</i8></value></data></array></value></data></array></value></param>"
		. "</params></methodResponse>";

	// no.such.method.
	const FAULT_016 = "Status: 200 OK\r\n"
		. "Content-Type: text/xml\r\n"
		. "Content-Length: 276\r\n"
		. "\r\n"
		. "<?xml version=\"1.0\"?><methodResponse><fault>"
		. "<value><struct><member><name>faultCode</name>"
		. "<value><i8>-506</i8></value></member><member><name>faultString</name>"
		. "<value><string>method 'no.such.method' not defined</string></value></member></struct>"
		. "</value></fault></methodResponse>";

	// The same three calls on rTorrent 0.9.8 (xmlrpc-c).
	const HASHES_098 = "Status: 200 OK\r\n"
		. "Content-Type: text/xml\r\n"
		. "Content-Length: 523\r\n"
		. "\r\n"
		. "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
		. "<methodResponse>\r\n"
		. "<params>\r\n"
		. "<param><value><array><data>\r\n"
		. "<value><array><data>\r\n"
		. "<value><string>E3072F6DBC6622E9C8E3D24549A70FDBB0AFB49C</string></value>\r\n"
		. "</data></array></value>\r\n"
		. "<value><array><data>\r\n"
		. "<value><string>D0C9E257FEE2DB586F1D397FFE07FC19F864E021</string></value>\r\n"
		. "</data></array></value>\r\n"
		. "<value><array><data>\r\n"
		. "<value><string>22D221B40747A89068ACAC0743E921486BAACAFB</string></value>\r\n"
		. "</data></array></value>\r\n"
		. "</data></array></value></param>\r\n"
		. "</params>\r\n"
		. "</methodResponse>\r\n";

	const MULTICALL_098 = "Status: 200 OK\r\n"
		. "Content-Type: text/xml\r\n"
		. "Content-Length: 327\r\n"
		. "\r\n"
		. "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
		. "<methodResponse>\r\n"
		. "<params>\r\n"
		. "<param><value><array><data>\r\n"
		. "<value><array><data>\r\n"
		. "<value><string>0.9.8</string></value>\r\n"
		. "</data></array></value>\r\n"
		. "<value><array><data>\r\n"
		. "<value><i8>16777216</i8></value>\r\n"
		. "</data></array></value>\r\n"
		. "</data></array></value></param>\r\n"
		. "</params>\r\n"
		. "</methodResponse>\r\n";

	const FAULT_098 = "Status: 200 OK\r\n"
		. "Content-Type: text/xml\r\n"
		. "Content-Length: 315\r\n"
		. "\r\n"
		. "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
		. "<methodResponse>\r\n"
		. "<fault>\r\n"
		. "<value><struct>\r\n"
		. "<member><name>faultCode</name>\r\n"
		. "<value><i4>-506</i4></value></member>\r\n"
		. "<member><name>faultString</name>\r\n"
		. "<value><string>Method 'no.such.method' not defined</string></value></member>\r\n"
		. "</struct></value>\r\n"
		. "</fault>\r\n"
		. "</methodResponse>\r\n";

	const HASHES = array(
		'016' => array('E3072F6DBC6622E9C8E3D24549A70FDBB0AFB49C', '22D221B40747A89068ACAC0743E921486BAACAFB',
			'D0C9E257FEE2DB586F1D397FFE07FC19F864E021'),
		'098' => array('E3072F6DBC6622E9C8E3D24549A70FDBB0AFB49C', 'D0C9E257FEE2DB586F1D397FFE07FC19F864E021',
			'22D221B40747A89068ACAC0743E921486BAACAFB'),
	);

	private $previousSettings;
	private $base;
	private $daemon;

	public function setUp()
	{
		// run() asks the settings to adapt old command names and to size a
		// multicall; a fixed version keeps it from asking a daemon first.
		$settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();
		$this->previousSettings = self::swapSettings($settings);

		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['rpcTimeOut'] = 5;
		$GLOBALS['rpcLogCalls'] = false;
		$GLOBALS['rpcLogFaults'] = false;

		$this->base = sys_get_temp_dir() . '/rutorrent-truncated-reply-' . getmypid();
		@mkdir($this->base, 0700, true);
	}

	public function tearDown()
	{
		self::swapSettings($this->previousSettings);
		$this->stopDaemon();
		foreach (glob($this->base . '/*') as $file) {
			@unlink($file);
		}
		@rmdir($this->base);
	}

	/** Install $settings as rTorrentSettings::get()'s answer, returning the previous one. */
	private static function swapSettings($settings)
	{
		return Closure::bind(function ($settings) {
			$previous = rTorrentSettings::$theSettings;
			rTorrentSettings::$theSettings = $settings;
			return $previous;
		}, null, 'rTorrentSettings')->__invoke($settings);
	}

	private function stopDaemon()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
	}

	/** A daemon that answers the next requests with $replies, in order. */
	private function daemon($replies)
	{
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
		$GLOBALS['scgi_port'] = $this->daemon->port();
	}

	private function hashRequest()
	{
		return new rXMLRPCRequest(new rXMLRPCCommand('d.multicall2', array('', 'default', 'd.hash=')));
	}

	private function versionAndLimitRequest()
	{
		$request = new rXMLRPCRequest(array(
			new rXMLRPCCommand('system.client_version'),
			new rXMLRPCCommand('network.xmlrpc.size_limit'),
		));
		$request->setParseByTypes();
		return $request;
	}

	/** $answer up to and including the first $keep bytes of $text in it. */
	private function cutInside($answer, $text, $keep)
	{
		$at = strpos($answer, $text);
		$this->assertTrue($at !== false, 'the captured answer holds ' . $text);
		return substr($answer, 0, $at + $keep);
	}

	public function testCapturedAnswersAreReadWhole()
	{
		foreach (array('016' => array(self::HASHES_016, self::MULTICALL_016, '0.16.25'),
			'098' => array(self::HASHES_098, self::MULTICALL_098, '0.9.8')) as $version => $case) {
			list($hashes, $multicall, $client) = $case;
			foreach (array($hashes, $multicall) as $answer) {
				list($headers, $body) = explode("\r\n\r\n", $answer, 2);
				$this->assertTrue(strpos($headers . "\r\n", 'Content-Length: ' . strlen($body) . "\r\n") !== false,
					$version . ': the captured body is as long as its own Content-Length says');
			}

			$this->daemon(array(FakeRtorrentDaemon::raw($hashes)));
			$request = $this->hashRequest();
			$this->assertTrue($request->success() === true, $version . ': a complete list of hashes is read');
			$this->assertEquals(self::HASHES[$version], $request->val,
				$version . ': and all three hashes are in it: ' . json_encode($request->val));

			$this->daemon(array(FakeRtorrentDaemon::raw($multicall)));
			$request = $this->versionAndLimitRequest();
			$this->assertTrue($request->success() === true, $version . ': a complete multicall is read by types');
			$this->assertTrue(($request->strings === array($client)) && ($request->i8s === array('16777216')),
				$version . ': with its string and its integer: '
				. json_encode(array($request->strings, $request->i8s)));
		}
	}

	public function testCapturedFaultStillCarriesItsReason()
	{
		foreach (array('016' => array(self::FAULT_016, "method 'no.such.method' not defined"),
			'098' => array(self::FAULT_098, "Method 'no.such.method' not defined")) as $version => $case) {
			list($answer, $reason) = $case;
			$this->daemon(array(FakeRtorrentDaemon::raw($answer)));
			$request = new rXMLRPCRequest(new rXMLRPCCommand('no.such.method'));
			$this->assertTrue($request->run() === true, $version . ': a fault is an answer');
			$this->assertTrue(($request->fault === true) && ($request->faultString === $reason),
				$version . ': and it is reported as one, with its reason: ' . $request->faultString);
		}
	}

	public function testAListOfHashesCutShortIsNotAnAnswer()
	{
		foreach (array('016' => self::HASHES_016, '098' => self::HASHES_098) as $version => $answer) {
			$hashes = self::HASHES[$version];
			// Inside the third hash, so two came through whole; inside the
			// first, so none did and the list reads as empty.
			foreach (array(2 => $hashes[2], 0 => $hashes[0]) as $whole => $cutIn) {
				$this->daemon(array(FakeRtorrentDaemon::raw($this->cutInside($answer, $cutIn, 20))));
				$request = $this->hashRequest();
				$ok = $request->success();
				$this->assertTrue($ok === false,
					$version . ': an answer cut after ' . $whole . ' of 3 hashes fails the request, not '
					. json_encode($request->val));
				$this->assertTrue($request->val === array(),
					$version . ': and leaves no hashes behind: ' . json_encode($request->val));
			}
		}
	}

	public function testAnAnswerReadByTypesCutShortIsNotAnAnswer()
	{
		foreach (array('016' => self::MULTICALL_016, '098' => self::MULTICALL_098) as $version => $answer) {
			$this->daemon(array(FakeRtorrentDaemon::raw($this->cutInside($answer, '16777216', 5))));
			$request = $this->versionAndLimitRequest();
			$this->assertTrue($request->success() === false,
				$version . ': a multicall cut inside its second result fails the request');
			$this->assertTrue(($request->strings === array()) && ($request->i8s === array()),
				$version . ': and leaves no values behind: ' . json_encode(array($request->strings, $request->i8s)));
		}
	}

	/** Two commands too large to share one call, so run() sends them as two. */
	private function splitRequest()
	{
		$padding = str_repeat('x', 1500000);
		return new rXMLRPCRequest(array(
			new rXMLRPCCommand('d.custom.set', array('', 'first', $padding)),
			new rXMLRPCCommand('d.custom.set', array('', 'second', $padding)),
		));
	}

	/** A complete single-value answer, and the same answer cut inside its value. */
	private function laterReplies()
	{
		$complete = '<?xml version="1.0"?><methodResponse><params><param><value><string>second</string>'
			. '</value></param></params></methodResponse>';
		return array('cut inside its value' => substr($complete, 0, strpos($complete, 'second') + 3),
			'closed without a byte' => '');
	}

	public function testALaterBatchCutShortFailsTheWholeRequest()
	{
		// The values of the first call must not stand for the answer to both.
		foreach ($this->laterReplies() as $how => $reply) {
			$this->daemon(array(array('first'), FakeRtorrentDaemon::raw($reply)));
			$request = $this->splitRequest();
			$ok = $request->success();
			$this->assertTrue(count($this->daemon->bodies()) === 2,
				$how . ': the two commands went out as two calls');
			$this->assertTrue($ok === false,
				$how . ': a second call answered that way fails the request, not ' . json_encode($request->val));
			$this->assertTrue($request->val === array(),
				$how . ': and the first call\'s values are not left behind: ' . json_encode($request->val));
		}
	}

	public function testALaterBatchCutShortLeavesNothingWhenReadByTypes()
	{
		foreach ($this->laterReplies() as $how => $reply) {
			$this->daemon(array(array('first', 7), FakeRtorrentDaemon::raw($reply)));
			$request = $this->splitRequest();
			$request->setParseByTypes();
			$ok = $request->success();
			$this->assertTrue(count($this->daemon->bodies()) === 2,
				$how . ', by types: the two commands went out as two calls');
			$this->assertTrue($ok === false,
				$how . ', by types: a second call answered that way fails the request');
			$this->assertTrue(($request->strings === array()) && ($request->i8s === array()),
				$how . ', by types: and the first call\'s string and integer are not left behind: '
				. json_encode(array($request->strings, $request->i8s)));
		}
	}
}
