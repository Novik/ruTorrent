<?php

require_once(__DIR__.'/../../php/TestCase.php');
require_once(__DIR__.'/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__.'/../../php/HttprpcListFixture.php');
require_once(__DIR__.'/../../php/TestHttpServer.php');

/** Byte-oriented daemon seam; the HTTP controller and XMLRPC serializer are real. */
class SettingsPathReplies
{
	private $directory;
	private $version;
	public function __construct($directory, $version = null) { $this->directory = $directory; $this->version = $version; }
	public function __invoke($request, $methods)
	{
		if(in_array('set_directory', $methods, true) || in_array('directory.default.set', $methods, true))
		{
			preg_match_all('~<(string|base64)>(.*?)</\1>~s', $request, $matches, PREG_SET_ORDER);
			$value = end($matches);
			$this->directory = $value[1] === 'base64' ? base64_decode($value[2])
				: html_entity_decode($value[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
		}
		$values = array();
		foreach($methods as $method)
			if($method === 'dht_statistics' || $method === 'dht.statistics')
				$values = array_merge($values, array('0', 'auto', '0'));
			else if($method === 'system.client_version') $values[] = $this->version;
			else if($method === 'execute.capture') $values[] = "1000\n1000\n/tmp\n";
			else $values[] = in_array($method, array('get_directory', 'directory.default'), true) ? $this->directory : '0';
		return HttprpcListFixture::raw(HttprpcListFixture::body($values));
	}
}

class SettingsPathTest extends TestCase
{
    private function assertSame($expected, $actual, $message) { $this->assertTrue($expected === $actual, $message); }

	private $base;
	private $daemon;
	private $server;

	public function setUp()
	{
		$this->base = sys_get_temp_dir().'/rt-stg-path-'.getmypid().'-'.bin2hex(random_bytes(4));
		mkdir($this->base, 0700);
		chmod($this->base, 0700);
	}
	public function tearDown()
	{
		if($this->server !== null) $this->server->close();
		if($this->daemon !== null) $this->daemon->stop();
		TestTree::remove($this->base);
	}
	private function start($directory, $version = null)
	{
		if($this->server !== null) { $this->server->close(); $this->server = null; }
		if($this->daemon !== null) { $this->daemon->stop(); $this->daemon = null; }
		$root = realpath(__DIR__.'/../../..');
		$this->daemon = new FakeRtorrentDaemon(new SettingsPathReplies($directory, $version),
			$this->base.'/calls.log', false, array(__DIR__.'/SettingsPathTest.php'));
		$router = $this->base.'/router.php';
		file_put_contents($router, '<?php $_ENV["RU_PROFILE_PATH"]='.var_export($this->base.'/profile', true).';'
			.'$_ENV["RU_LOG_FILE"]='.var_export($this->base.'/app.log', true).';'
			.'require '.var_export($root.'/php/settings.php', true).';'
			.'$r = new ReflectionClass("rTorrentSettings"); $s = $r->newInstanceWithoutConstructor();'
			.'$s->iVersion=0x101a; $s->aliases=array(); $s->directory="/"; $s->linkExist=true;'
			.'$p=$r->getProperty("theSettings"); if(PHP_VERSION_ID<80100) $p->setAccessible(true); $p->setValue(null,$s);'
			.'$topDirectory="/"; $rpcLogCalls=false; $scgi_host="127.0.0.1"; $scgi_port='.$this->daemon->port().';'
			.($version !== null ? '$s->obtain();' : '')
			.'chdir('.var_export($root.'/plugins/httprpc', true).'); require "action.php";');
		$this->server = TestHttpServer::start($this->base,
			array('RU_PROFILE_PATH'=>$this->base.'/profile', 'RU_LOG_FILE'=>$this->base.'/app.log'), array(), $router);
	}
	private function post($body)
	{
		$response = TestHttpServer::rawPost($this->server->port(), '/', $body, 'application/x-www-form-urlencoded');
		$this->assertTrue(strpos($response['status'], '200') !== false, 'settings HTTP request succeeds: '.$response['body']);
		$value = json_decode($response['body'], true);
		$this->assertTrue(is_array($value), 'settings response is a JSON array');
		return $value;
	}
	public function testReadPreservesRawDirectoryBesideDisplayText()
	{
		$this->start("/data/\xCF\xF0");
		$values = $this->post('mode=stg');
		$this->assertSame(array('name'=>'/data/\\xCF\\xF0', 'pathKey'=>'%2Fdata%2F%CF%F0'),
			$values[4], 'legacy directory bytes survive the HTTP JSON boundary');
	}
	public function testApplyAndReadBackKeepSelectedBytesAndLiteralPercentPaths()
	{
		$this->start('/data/old');
		foreach(array("/data/\xCF\xF0", '/data/literal%CF%F0', '/data/映画', '/data/a&b=c+d') as $path)
		{
			$this->post('mode=setsettings&s=sdirectory&v='.rawurlencode($path));
			$values = $this->post('mode=stg');
			$actual = is_array($values[4]) ? rawurldecode($values[4]['pathKey']) : $values[4];
			$this->assertSame(bin2hex($path), bin2hex($actual), 'Apply and refresh name the identical directory bytes');
		}
	}
	private function capturedCommands($version)
	{
		$this->start('/data/valid', $version);
		$this->post('mode=stg');
		$this->assertTrue(is_file($this->base.'/profile/settings/rtorrent.dat'),
			'HTTP bootstrap writes settings only to the fixture-owned profile');
		$calls = $this->daemon->calls();
		$start = array_search('dht.statistics', $calls, true);
		$this->assertTrue($start !== false, 'the settings request begins with DHT statistics');
		return array_slice($calls, $start, 49);
	}
	public function testModernReadsRetainCapturedOrderAndEffectiveSocketAllocation()
	{
		$commands = $this->capturedCommands('0.16.24');
		$capture = file_get_contents(__DIR__.'/../../php/fixtures/settings-read-rtorrent-0.16.24.xml');
		preg_match_all('~<name>methodName</name><value><string>([^<]+)</string>~', $capture, $names);
		$this->assertSame($names[1], $commands, 'server settings read preserves the captured command order');
		$this->assertTrue(in_array('system.sockets.http.max_size', $commands, true), 'read-back reports the effective HTTP allocation');
		$this->assertTrue(!in_array('system.sockets.http.max_alloc', $commands, true), 'the ceiling is not reported as the current allocation');
	}
	public function testLegacyReadsRetainCapturedOrder()
	{
		$commands = $this->capturedCommands('0.9.8');
		$capture = file_get_contents(__DIR__.'/../../php/fixtures/settings-read-rtorrent-0.9.8.xml');
		preg_match_all('~<name>methodName</name><value><string>([^<]+)</string>~', $capture, $names);
		$this->assertSame($names[1], $commands, 'legacy server settings read preserves the captured command order');
	}
}
