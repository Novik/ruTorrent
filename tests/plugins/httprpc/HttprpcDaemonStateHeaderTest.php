<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TestHttpServer.php');
require_once(__DIR__ . '/../../php/TestTree.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * What tells a client that rtorrent is the thing that is not there.
 *
 * Every answer this door sends with no result is an HTTP 500, and the only
 * thing that separated "the daemon is not reachable" from a refused command
 * name or a fault the daemon itself raised was the English sentence in the
 * body. A client keying off that sentence breaks when it is reworded, when it
 * is translated, or when an intermediary wraps the body in a page of its own.
 *
 * X-rTorrent-State carries that one answer out of band. These drive the real
 * door over HTTP -- the only way the headers it sets are observable -- and
 * read the header off the response: present with its value on the answer that
 * establishes the daemon is unreachable, absent on every other.
 *
 * The body is asserted byte for byte as well. Nothing is added to it, so a
 * client that reads only the body reads today's sentence and displays it.
 */
class HttprpcDaemonStateHeaderTest extends TestCase
{
	const HASH = 'C0FFEE0123456789C0FFEE0123456789C0FFEE01';
	const OUTAGE = 'Could not reach rTorrent over XMLRPC. Is rTorrent running?';
	const HEADER = 'x-rtorrent-state';

	private $trees = array();
	private $daemon;

	public function tearDown()
	{
		$this->stopDaemon();
		foreach($this->trees as $tree)
			TestTree::remove($tree);
		$this->trees = array();
	}

	private function stopDaemon()
	{
		if($this->daemon !== null)
		{
			$this->daemon->stop();
			$this->daemon = null;
		}
	}

	private function repoRoot()
	{
		$root = realpath(__DIR__ . '/../../..');
		if($root === false)
			throw new Exception('could not locate the production source root');
		return $root;
	}

	/** A port nothing is listening on: reserved to learn a free number, then released. */
	private function closedPort()
	{
		$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		if($listener === false)
			throw new Exception('could not reserve a port: ' . $error);
		$address = stream_socket_get_name($listener, false);
		fclose($listener);
		return (int)substr($address, strrpos($address, ':') + 1);
	}

	/**
	 * Runs plugins/httprpc/action.php over HTTP against $scgiPort and returns
	 * the status line, the headers and the body the client received.
	 */
	private function post($body, $scgiPort)
	{
		$root = $this->repoRoot();
		$tree = sys_get_temp_dir() . '/rutorrent-daemon-state-' . uniqid('', true);
		if(!mkdir($tree, 0700, true) && !is_dir($tree))
			throw new Exception('could not create the fixture tree');
		$this->trees[] = $tree;
		$profile = $tree . '/profile';
		if(!mkdir($profile . '/settings', 0700, true))
			throw new Exception('could not create the fixture profile');

		$router = $tree . '/router.php';
		file_put_contents($router, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($profile, true) . ";\n"
			. '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
			. '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string)$scgiPort, true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$rpcTimeOut = 5;' . "\n"
			. '$rpcLogCalls = false;' . "\n"
			. 'chdir(' . var_export($root . '/plugins/httprpc', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/httprpc/action.php', true) . ");\n");

		$server = null;
		try
		{
			$server = TestHttpServer::start($tree, array(),
				array('-d', 'display_errors=0'), $router);
			return TestHttpServer::rawPost($server->port(), '/action.php', $body,
				'application/x-www-form-urlencoded');
		}
		finally
		{
			if($server !== null)
				$server->close();
		}
	}

	public function testTheUnreachableAnswerNamesItselfInAHeader()
	{
		$result = $this->post('mode=list&cid=0', $this->closedPort());

		$this->assertTrue(strpos($result['status'], '500') !== false,
			'the answer is a 500: ' . $result['status']);
		$this->assertEquals('unreachable',
			isset($result['headers'][self::HEADER]) ? $result['headers'][self::HEADER] : null,
			'and it names the daemon state: ' . json_encode($result['headers']));
	}

	public function testTheSentenceInTheBodyIsUntouched()
	{
		$result = $this->post('mode=list&cid=0', $this->closedPort());

		// A client that reads only the body is unaffected: it reads what it
		// read before, with no marker to display to a person.
		$this->assertEquals(self::OUTAGE, $result['body'],
			'the body is the sentence and nothing else: ' . $result['body']);
	}

	public function testARefusedCommandNameSaysNothingAboutTheDaemon()
	{
		$result = $this->post('mode=glbl&cmd=execute.capture%3D%2Fbin%2Fid',
			$this->closedPort());

		$this->assertTrue(strpos($result['status'], '403') !== false,
			'a refused command parameter is a 403: ' . $result['status']);
		$this->assertTrue(!isset($result['headers'][self::HEADER]),
			'and carries no daemon state: ' . json_encode($result['headers']));
	}

	public function testTheOtherTwoAnswersWithNoResultSayNothingAboutTheDaemon()
	{
		// Both of these are the same 500 with no result, reached through the
		// same block. Neither establishes that rtorrent is unreachable: the
		// name never left this server, and the fault came back from a daemon
		// that answered.
		$refused = $this->post('mode=ttl&cmd=not%20a%20command%20name',
			$this->closedPort());
		$this->assertEquals('Refused: not an rtorrent command name.', $refused['body'],
			'a name this server will not send is refused: ' . $refused['body']);
		$this->assertTrue(!isset($refused['headers'][self::HEADER]),
			'and carries no daemon state: ' . json_encode($refused['headers']));

		$log = sys_get_temp_dir() . '/rutorrent-daemon-state-fault-' . getmypid() . '.log';
		$this->daemon = new FakeRtorrentDaemon(function($body)
		{
			$names = RtorrentProbeFixture::methodNames($body);
			if((count($names) === 1) && ($names[0] === 'd.multicall'))
				return FakeRtorrentDaemon::fault('The daemon raised this fault.');
			$responder = RtorrentProbeFixture::responder();
			return $responder($body);
		}, $log);
		try
		{
			$fault = $this->post('mode=list&cid=0', $this->daemon->port());
		}
		finally
		{
			$this->stopDaemon();
			@unlink($log);
			@unlink($log . '.bodies');
		}
		$this->assertEquals('The daemon raised this fault.', $fault['body'],
			'a fault is relayed as the daemon worded it: ' . $fault['body']);
		$this->assertTrue(!isset($fault['headers'][self::HEADER]),
			'and carries no daemon state: ' . json_encode($fault['headers']));
	}

	public function testAnAnswerFromARunningDaemonSaysNothingAboutTheDaemon()
	{
		$log = sys_get_temp_dir() . '/rutorrent-daemon-state-calls-' . getmypid() . '.log';
		$this->daemon = new FakeRtorrentDaemon(RtorrentProbeFixture::responder(self::HASH),
			$log, false, RtorrentProbeFixture::replyClassFiles());
		try
		{
			$result = $this->post('mode=list&cid=0', $this->daemon->port());
		}
		finally
		{
			$this->stopDaemon();
			@unlink($log);
		}

		$this->assertTrue(strpos($result['body'], self::HASH) !== false,
			'the torrent list came back: ' . substr($result['body'], 0, 200));
		$this->assertTrue(!isset($result['headers'][self::HEADER]),
			'and a daemon that answered is not reported unreachable: '
				. json_encode($result['headers']));
	}
}
