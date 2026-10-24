<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/TestHttpServer.php');
require_once(__DIR__.'/TestTree.php');
require_once(__DIR__.'/FakeRtorrentDaemon.php');
require_once(__DIR__.'/HttprpcListFixture.php');

/** Real XML-RPC shapes, including binary legacy file paths. */
class DataDownloadPathSequence
{
    private $values;
    public function __construct($values) { $this->values = $values; }
    public function next() { return count($this->values) > 1 ? array_shift($this->values) : $this->values[0]; }
}

class DataDownloadNamesReplies
{
	private $optionalFailure;
	private $values;
	public function __construct($values, $optionalFailure = false)
	{
		$this->values = $values;
		$this->optionalFailure = $optionalFailure;
	}

	public function __invoke($request, $methods)
	{
		$values = array();
        foreach($methods as $method) {
            $value = $this->values[$method] ?? array(0);
            if($value instanceof DataDownloadPathSequence) $value = $value->next();
            $values = array_merge($values, $value);
        }
		$multiple = strpos($request, '<methodName>system.multicall</methodName>') !== false;
		$results = $multiple ? array_map(function($value) { return array($value); }, $values) : ($values[0] ?? '');
		if($multiple && $this->optionalFailure)
			foreach($methods as $index=>$method)
				if(($this->optionalFailure === true && ($method === 'session.path' || $method === 'get_session'))
					|| ($this->optionalFailure === 'close' && $method === 'd.close'))
					$results[$index] = array('faultCode'=>'-503', 'faultString'=>'optional metadata unavailable');
		$body = HttprpcListFixture::body($results);
		return HttprpcListFixture::raw($body);
	}
}

/** The data plugin serves raw paths, while Content-Disposition carries a Unicode label. */
class DataDownloadNamesTest extends TestCase
{
	private function assertSame($expected, $actual, $message)
	{
		$this->assertTrue($expected === $actual, $message);
	}

	private $base;
	private $daemon;
	private $server;

	public function setUp()
	{
		$this->base = sys_get_temp_dir().'/data-download-'.getmypid().'-'.bin2hex(random_bytes(5));
		mkdir($this->base.'/www', 0700, true);
		mkdir($this->base.'/session', 0700);
		foreach(array('', '/www', '/session') as $directory)
			chmod($this->base.$directory, 0700);
	}

	public function tearDown()
	{
		if($this->server !== null) $this->server->close();
		if($this->daemon !== null) $this->daemon->stop();
		$this->server = $this->daemon = null;
		TestTree::remove($this->base);
	}

	private static function string($text) { return strlen($text).':'.$text; }

	private function download($raw, $alias, $failure = null, $closed = false)
	{
		if($this->server !== null) { $this->server->close(); $this->server = null; }
		if($this->daemon !== null) { $this->daemon->stop(); $this->daemon = null; }
		$root = realpath(__DIR__.'/../..');
		$payload = "payload\0exact\xFF\r\n";
		$frozenRaw = $failure === 'frozenleaf' ? "other\xFE.bin" : $raw;
		$path = $this->base.'/'.$frozenRaw;
		file_put_contents($path, $payload);
		$info = 'd6:lengthi'.strlen($payload).'e4:name'.self::string($raw)
			.'10:name.utf-8'.self::string($alias).'12:piece lengthi16384e6:pieces20:'.str_repeat('x',20).'e';
		$hash = strtoupper(sha1($info));
		if($failure !== 'missing')
			file_put_contents($this->base.'/session/'.$hash.'.torrent', 'd4:info'
				.($failure === 'hash' ? str_replace('20:'.str_repeat('x',20), '20:'.str_repeat('y',20), $info) : $info).'e');
		$values = array();
		foreach(array('f.get_frozen_path','f.frozen_path') as $method)
			$values[$method] = $closed ? new DataDownloadPathSequence(array(array(''),array($path))) : array($path);
		foreach(array('f.get_path','f.path') as $method) $values[$method] = array($failure === 'path' ? 'different' : $raw);
		foreach(array('f.get_size_bytes','f.size_bytes') as $method) $values[$method] = array(strlen($payload)+($failure === 'size' ? 1 : 0));
		foreach(array('d.get_size_files','d.size_files') as $method) $values[$method] = array($failure === 'count' ? 2 : 1);
		foreach(array('get_session','session.path') as $method) $values[$method] = array($this->base.'/session/');
		foreach(array('d.get_tied_to_file','d.tied_to_file') as $method) $values[$method] = array('');
		$fault = $failure === 'close-fault' ? 'close' : ($failure === 'rpc');
		$this->daemon = new FakeRtorrentDaemon(new DataDownloadNamesReplies($values, $fault), $this->base.'/rpc.log', false, array(__DIR__.'/DataDownloadNamesTest.php'));
		$router = $this->base.'/www/router.php';
		file_put_contents($router, '<?php $_ENV["RU_PROFILE_PATH"]='.var_export($this->base.'/profile',true).';'
			.' require '.var_export($root.'/php/rtorrent.php',true).';'
			.' $r=new ReflectionClass("rTorrentSettings");$s=$r->newInstanceWithoutConstructor();'
			.' $s->iVersion=0x101a;$s->apiVersion=11;$s->aliases=array();'
			.' $p=$r->getProperty("theSettings");if(PHP_VERSION_ID<80100)$p->setAccessible(true);$p->setValue(null,$s);'
			.' $scgi_host="127.0.0.1";$scgi_port='.$this->daemon->port().';$rpcTimeOut=3;$rpcLogCalls=false;'
			.' $log_file='.var_export($this->base.'/app.log',true).';'
			.' chdir('.var_export($root.'/plugins/data',true).');require '.var_export($root.'/plugins/data/action.php',true).';');
		$this->server = TestHttpServer::start($this->base.'/www', array(), array(), $router);
		$response = TestHttpServer::rawGet($this->server->port(), '/?hash='.$hash.'&no='.($failure === 'index' ? '-1' : '0'));
		if($failure === 'close-fault') {
			$this->assertSame('HTTP/1.0 302 Moved Temporarily', $response['status'], 'a failed required close retains the existing data-download refusal');
			$this->assertSame('', $response['body'], 'a required lifecycle fault cannot be relabeled as an optional metadata failure');
			return null;
		}
		$this->assertSame('HTTP/1.0 200 OK', $response['status'], 'the data endpoint still serves the selected raw file');
		$this->assertSame($payload, $response['body'], 'a Unicode download label does not change any payload byte');
		$header = $response['headers']['content-disposition'] ?? '';
		$this->assertSame(1, preg_match("/; filename\\*=UTF-8''([^;]*)/", $header, $match), 'the real HTTP response offers a Unicode filename');
		return isset($match[1]) ? rawurldecode($match[1]) : null;
	}

	public function testAttestedAliasNamesTheDownloadWithoutRenamingItsRawFile()
	{
		$raw = "cp \xF1\xE5\xF0\xE8\xFF %41.bin";
		$alias = 'серия %41.bin';
		$this->assertSame($alias, $this->download($raw, $alias), 'metadata supplies the basename with literal percent text');
	}

	public function testSourceFailuresKeepBytesVisibleAndPreserveThePayload()
	{
		foreach(array('missing','hash','path','size','rpc','index','count','frozenleaf') as $failure) {
			$expected = $failure === 'frozenleaf' ? 'other\\xFE.bin' : 'cp\\xFF.bin';
			$this->assertSame($expected, $this->download("cp\xFF.bin", 'alias.bin', $failure), 'missing or mismatched source evidence uses the actual frozen basename fallback');
			$this->server->close(); $this->server = null;
			$this->daemon->stop(); $this->daemon = null;
		}
	}

	public function testValidUnicodeAndClosedDownloadsRetainTheirFileIdentity()
	{
		$this->assertSame('中文 🙂 %41.bin', $this->download('中文 🙂 %41.bin', 'different.bin'), 'valid Unicode download basenames are unchanged');
		$this->server->close(); $this->server = null;
		$this->daemon->stop(); $this->daemon = null;
		$this->assertSame('серия.bin', $this->download("cp\xFF.bin", 'серия.bin', null, true), 'temporarily opening a closed torrent retains its attested Unicode label');
		$this->assertTrue(in_array('d.close', $this->daemon->calls(), true), 'a temporarily opened torrent is closed by the shared owner');
	}

	public function testARequiredCloseFaultCannotBeIgnoredAsDisplayMetadataFailure()
	{
		$this->download("cp\xFF.bin", 'alias.bin', 'close-fault', true);
	}

}
