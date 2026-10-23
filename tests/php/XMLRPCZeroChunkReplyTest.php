<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');

/**
 * A single-byte socket read containing "0" is data, although PHP considers
 * that string false. Dropping it truncates the SCGI frame and must fail the
 * request rather than publish a partial answer. The peer pauses between
 * chunks so the zero reaches the reader on its own.
 */
class XMLRPCZeroChunkReplyTest extends TestCase
{
	const HASHES = array(
		'E3072F6DBC6622E9C8E3D24549A70FDBB0AFB49C',
		'22D221B40747A89068ACAC0743E921486BAACAFB',
		'D0C9E257FEE2DB586F1D397FFE07FC19F864E021',
	);

	private $previousSettings;
	private $previousGlobals;
	private $base;
	private $daemon;

	public function setUp()
	{
		$this->previousGlobals = GlobalSettingsFixture::scgi();
		// run() asks the settings to adapt old command names and to size a
		// multicall; a fixed version keeps it from asking a daemon first.
		$settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
		$settings->iVersion = 0x904;
		$settings->apiVersion = 0;
		$settings->aliases = array();
		$this->previousSettings = self::swapSettings($settings);

		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['rpcTimeOut'] = 5;
		$GLOBALS['rpcTransferTimeOut'] = 5;
		unset($GLOBALS['rpcMaxResponseBytes']);
		$GLOBALS['rpcLogCalls'] = false;
		$GLOBALS['rpcLogFaults'] = false;

		$this->base = sys_get_temp_dir() . '/rutorrent-zero-chunk-' . getmypid();
		@mkdir($this->base, 0700, true);
		$GLOBALS['log_file'] = $this->base . '/errors.log';
		$GLOBALS['tempDirectory'] = $this->base;
		$GLOBALS['tempDirectory_init_done'] = false;
	}

	public function tearDown()
	{
		try
		{
			self::swapSettings($this->previousSettings);
			$this->stopDaemon();
			foreach(glob($this->base . '/*') as $file)
				@unlink($file);
			@rmdir($this->base);
		}
		finally
		{
			$this->previousGlobals->restore();
		}
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

	private function daemon($replies)
	{
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
		$GLOBALS['scgi_port'] = $this->daemon->port();
	}

	/** A d.multicall2 answer carrying the three hashes, SCGI headers and all. */
	private function answer()
	{
		$body = '<?xml version="1.0"?><methodResponse><params><param><value><array><data>';
		foreach (self::HASHES as $hash) {
			$body .= '<value><array><data><value><string>' . $hash
				. '</string></value></data></array></value>';
		}
		$body .= '</data></array></value></param></params></methodResponse>';
		return "Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: " . strlen($body)
			. "\r\n\r\n" . $body;
	}

	/** $answer in three pieces, the middle one the single byte at $at. */
	private function dividedAt($answer, $at, $expected)
	{
		$this->assertTrue(isset($answer[$at]) && ($answer[$at] === $expected),
			'the byte sent on its own at ' . $at . ' is ' . json_encode($expected));
		return array(substr($answer, 0, $at), $answer[$at], substr($answer, $at + 1));
	}

	private function hashRequest()
	{
		return new rXMLRPCRequest(new rXMLRPCCommand('d.multicall2', array('', 'default', 'd.hash=')));
	}

	/**
	 * Every value in the answer is read whatever division the reads fall in.
	 * The three positions are the status line, a hash in the middle of the
	 * document and the last zero in it, so a reply cut at its front, its
	 * middle or its end all have to come through whole.
	 */
	public function testAnAnswerDividedOnAZeroIsReadWhole()
	{
		$answer = $this->answer();
		$positions = array(
			'in the status line' => strpos($answer, '0'),
			'inside the second hash' => strpos($answer, '0', strpos($answer, self::HASHES[1])),
			'at the last zero in the document' => strrpos($answer, '0'),
		);
		foreach ($positions as $where => $at) {
			$this->daemon(array(FakeRtorrentDaemon::paced($this->dividedAt($answer, $at, '0'))));
			$request = $this->hashRequest();
			$this->assertTrue($request->success() === true,
				$where . ': a zero of its own is a byte of the answer, not the end of it');
			$this->assertEquals(self::HASHES, $request->val,
				$where . ': and all three hashes are read: ' . json_encode($request->val));
		}
	}

	/**
	 * The same division on a byte that is not '0'. It passes either way, which
	 * is the point: it shows the daemon really does put one byte in a read of
	 * its own, so the zero case fails for the zero and not for the pacing.
	 */
	public function testAnAnswerDividedOnAnotherByteIsReadWhole()
	{
		$answer = $this->answer();
		$at = strpos($answer, '2');
		$this->daemon(array(FakeRtorrentDaemon::paced($this->dividedAt($answer, $at, '2'))));
		$request = $this->hashRequest();
		$this->assertTrue($request->success() === true,
			'a single byte that is not zero is read as a byte');
		$this->assertEquals(self::HASHES, $request->val,
			'and all three hashes are read: ' . json_encode($request->val));
	}

	/** An answer that really did stop early still fails, as #3390 made it. */
	public function testAnAnswerThatEndsEarlyStillFails()
	{
		$answer = $this->answer();
		$cut = substr($answer, 0, strpos($answer, self::HASHES[2]) + 4);
		$this->daemon(array(FakeRtorrentDaemon::raw($cut)));
		$request = $this->hashRequest();
		$this->assertTrue($request->success() === false,
			'an answer cut inside its third hash fails the request');
		$this->assertTrue($request->val === array(),
			'and leaves no hashes behind: ' . json_encode($request->val));
	}
}
