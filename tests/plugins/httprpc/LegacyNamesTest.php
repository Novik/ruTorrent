<?php

require_once(__DIR__.'/../../php/TestCase.php');
require_once(__DIR__.'/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__.'/../../php/HttprpcListFixture.php');
require_once(__DIR__.'/../../php/TestHttpServer.php');

function legacyNamesBencode($value)
{
	if(is_int($value))
		return 'i'.$value.'e';
	if(is_string($value))
		return strlen($value).':'.$value;
	if(array_keys($value) === range(0, count($value) - 1))
		return 'l'.implode('', array_map('legacyNamesBencode', $value)).'e';
	ksort($value, SORT_STRING);
	$out = 'd';
	foreach($value as $key=>$item)
		$out .= legacyNamesBencode((string)$key).legacyNamesBencode($item);
	return $out.'e';
}

/** Answers like rTorrent: names that are not UTF-8 stay raw bytes inside <string>. */
class LegacyNamesReplies
{
	private $session;
	private $phases;
	private $torrents;
	private $lists = 0;
	private $brokenPaths;
	/** Each list request answers with the next phase; the last one repeats. */
	public function __construct($session, $phases, $brokenPaths = false)
	{
		$this->session = $session;
		$this->phases = $phases;
		$this->torrents = $phases[0];
		$this->brokenPaths = $brokenPaths;
	}
	public function __invoke($request, $methods)
	{
		$session = count(array_intersect(array('get_session', 'session.path'), $methods)) > 0;
		if($methods === array('d.multicall'))
		{
			$this->torrents = $this->phases[min($this->lists++, count($this->phases) - 1)];
			$rows = array();
			foreach($this->torrents as $hash=>$torrent)
			{
				$row = array_fill(0, 36, '0');
				$row[0] = $hash;
				$row[5] = $torrent['name'];
				$row[15] = $torrent['label'];
				$row[26] = $torrent['base'];
				$row[12] = $torrent['rate'];
				$rows[] = $row;
			}
			return HttprpcListFixture::raw(HttprpcListFixture::body($rows));
		}
		if($session && in_array('d.multicall', $methods, true))
		{
			$paths = array();
			foreach($this->torrents as $hash=>$torrent)
				$paths[] = array($hash, $torrent['tied'] ?? '');
			if($this->brokenPaths)
				$paths[] = array('unpaired');
			return HttprpcListFixture::raw(HttprpcListFixture::body(array(array($this->session), array($paths))));
		}
		if($session)
			return HttprpcListFixture::raw(HttprpcListFixture::body(array(array($this->session), array(''))));
		if($methods === array('f.multicall'))
		{
			$torrent = reset($this->torrents);
			$rows = array();
			foreach($torrent['files'] as $file)
				$rows[] = array($file[0], '1', '1', (string)$file[1], '1');
			return HttprpcListFixture::raw(HttprpcListFixture::body($rows));
		}
		if(in_array('d.open', $methods, true))
		{
			$torrent = reset($this->torrents);
			return HttprpcListFixture::raw(HttprpcListFixture::body(array(array('0'), array($torrent['base']),
				array('0'), array($torrent['name']))));
		}
		return FakeRtorrentDaemon::fault('unexpected request: '.implode(',', $methods));
	}
}

class LegacyNamesTest extends TestCase
{
	private function assertSame($expected, $actual, $message) { $this->assertTrue($expected === $actual, $message.': '.json_encode($actual, JSON_INVALID_UTF8_SUBSTITUTE)); }

	private $base;
	private $daemon;
	private $server;

	public function setUp()
	{
		$this->base = sys_get_temp_dir().'/rt-legacy-names-'.getmypid().'-'.bin2hex(random_bytes(4));
		mkdir($this->base, 0700);
		chmod($this->base, 0700);
		mkdir($this->base.'/session', 0700);
		chmod($this->base.'/session', 0700);
	}
	public function tearDown()
	{
		if($this->server !== null) $this->server->close();
		if($this->daemon !== null) $this->daemon->stop();
		TestTree::remove($this->base);
	}

	/** A metainfo file in the session directory; returns its info hash. */
	private function torrent($info)
	{
		$info['piece length'] = 16384;
		$info['pieces'] = str_repeat("\0", 20);
		$hash = strtoupper(sha1(legacyNamesBencode($info)));
		file_put_contents($this->base.'/session/'.$hash.'.torrent', legacyNamesBencode(array('info'=>$info)));
		return $hash;
	}

	private function start($torrents, $brokenPaths = false)
	{
		$root = realpath(__DIR__.'/../../..');
		$this->daemon = new FakeRtorrentDaemon(new LegacyNamesReplies($this->base.'/session/',
			isset($torrents[0]) ? $torrents : array($torrents), $brokenPaths),
			$this->base.'/calls.log', false, array(__DIR__.'/LegacyNamesTest.php'));
		$router = $this->base.'/router.php';
		file_put_contents($router, '<?php $_ENV["RU_PROFILE_PATH"]='.var_export($this->base.'/profile', true).';'
			.'$_ENV["RU_LOG_FILE"]='.var_export($this->base.'/app.log', true).';'
			.'require '.var_export($root.'/php/settings.php', true).';'
			.'$r = new ReflectionClass("rTorrentSettings"); $s = $r->newInstanceWithoutConstructor();'
			.'$s->iVersion=0x101a; $s->aliases=array(); $s->directory="/"; $s->linkExist=true;'
			.'$p=$r->getProperty("theSettings"); if(PHP_VERSION_ID<80100) $p->setAccessible(true); $p->setValue(null,$s);'
			.'$topDirectory="/"; $rpcLogCalls=false; $scgi_host="127.0.0.1"; $scgi_port='.$this->daemon->port().';'
			.'chdir('.var_export($root.'/plugins/httprpc', true).'); require "action.php";');
		$this->server = TestHttpServer::start($this->base,
			array('RU_PROFILE_PATH'=>$this->base.'/profile', 'RU_LOG_FILE'=>$this->base.'/app.log'), array(), $router);
	}

	private function post($body)
	{
		$response = TestHttpServer::rawPost($this->server->port(), '/', $body, 'application/x-www-form-urlencoded');
		$this->assertTrue(strpos($response['status'], '200') !== false, 'httprpc answers 200: '.$response['body']);
		$value = json_decode($response['body'], true);
		$this->assertTrue(is_array($value), 'httprpc answers a JSON value');
		return $value;
	}

	private function legacy($label = 'lab%FF', $name = "Serie \xF1\xE5\xF0\xE8\xFF", $alias = 'Серия')
	{
		$hash = $this->torrent(array('name'=>$name, 'name.utf-8'=>$alias, 'length'=>100));
		return array($hash, array('name'=>$name, 'label'=>$label, 'base'=>'/data/'.$name, 'rate'=>'0',
			'files'=>array(array($name, 100))));
	}

	public function testListAddsVerifiedTitlesLabelKeysAndPathKeysForLegacyBytesOnly()
	{
		list($hash, $legacy) = $this->legacy();
		$plain = str_repeat('B', 40);
		$this->start(array($hash=>$legacy,
			$plain=>array('name'=>'映画', 'label'=>'Movies', 'base'=>'/data/映画', 'rate'=>'0', 'files'=>array())));
		$list = $this->post('mode=list');
		$this->assertSame(array($hash=>'Серия'), $list['n'], 'the title comes from the verified name.utf-8 of the same torrent');
		$this->assertSame(array($hash=>array('name'=>'lab\\xFF', 'labelKey'=>'lab%FF')), $list['l'],
			'a label that is not UTF-8 keeps its byte key beside visible escapes');
		$this->assertSame(array($hash=>array(
			'base'=>array('name'=>'/data/Serie \\xF1\\xE5\\xF0\\xE8\\xFF', 'pathKey'=>rawurlencode($legacy['base'])),
			'save'=>'/data')), $list['p'], 'the base path keeps its bytes; the save path is cut from the raw name');
		$this->assertSame('映画', $list['t'][$plain][4], 'a UTF-8 name is sent as it was');
		$this->assertSame('Movies', $list['t'][$plain][14], 'a UTF-8 label is sent as it was');
		$this->assertSame(array('d.multicall', 'get_session', 'd.multicall'), $this->daemon->calls(),
			'source paths for every title are read in one extra request');
	}

	public function testUtf8NamesAddNoMapsAndNoRequests()
	{
		$this->start(array(str_repeat('C', 40)=>array('name'=>'Plain', 'label'=>'', 'base'=>'/data/Plain', 'rate'=>'0', 'files'=>array())));
		$list = $this->post('mode=list');
		$this->assertSame(false, isset($list['n']) || isset($list['l']) || isset($list['p']), 'UTF-8 values need no side maps');
		$this->assertSame(array('d.multicall'), $this->daemon->calls(), 'the list stays one daemon request');
	}

	public function testUnreadableSourceKeepsVisibleEscapes()
	{
		list($hash, $legacy) = $this->legacy();
		unlink($this->base.'/session/'.$hash.'.torrent');
		$this->start(array($hash=>$legacy));
		$list = $this->post('mode=list');
		$this->assertSame(array($hash=>'Serie \\xF1\\xE5\\xF0\\xE8\\xFF'), $list['n'], 'without metadata the name shows its bytes');
		$this->assertSame(1, count($list['t']), 'missing metadata never drops the torrent');
	}

	public function testDeltaWithoutNameChangesReadsNoSource()
	{
		list($hash, $legacy) = $this->legacy();
		$this->start(array($hash=>$legacy));
		$first = $this->post('mode=list');
		$second = $this->post('mode=list&cid='.$first['cid']);
		$this->assertSame(false, isset($second['n']) || isset($second['p']) || isset($second['l']),
			'an unchanged torrent repeats no side map');
		$this->assertSame(array('d.multicall', 'get_session', 'd.multicall', 'd.multicall'), $this->daemon->calls(),
			'an unchanged name reads no source again');
	}

	public function testFileListAndSavePathKeepByteKeys()
	{
		$name = "Dir\xFF";
		$hash = $this->torrent(array('name'=>$name, 'name.utf-8'=>'Папка', 'files'=>array(
			array('path'=>array("a\xFF.mkv"), 'path.utf-8'=>array('Файл.mkv'), 'length'=>7),
			array('path'=>array('b.txt'), 'length'=>3))));
		$this->start(array($hash=>array('name'=>$name, 'label'=>'', 'base'=>'/data/'.$name, 'rate'=>'0',
			'files'=>array(array("a\xFF.mkv", 7), array('b.txt', 3)))));
		$files = $this->post('mode=fls&hash='.$hash);
		$this->assertSame(array('name'=>'Файл.mkv', 'pathKey'=>'a%FF.mkv'), $files[0][0], 'a verified file alias keeps its byte key');
		$this->assertSame('b.txt', $files[1][0], 'a UTF-8 file name is sent as it was');
		$path = $this->post('mode=getsavepath&hash='.$hash);
		$this->assertSame(array('0', '/data/'.$name, '0'), array($path[0], rawurldecode($path[3]['base']['pathKey']), $path[2]),
			'the save path answer keeps its three upstream cells and adds the byte key');
		$this->assertSame('/data', $path[3]['save'], 'the save path is cut from the raw torrent name');
	}

	public function testOneSourceRequestServesEveryLegacyTitle()
	{
		list($first, $one) = $this->legacy();
		list($second, $two) = $this->legacy('', "Folge \xFE", 'Folge');
		$this->start(array($first=>$one, $second=>$two));
		$list = $this->post('mode=list');
		$this->assertSame(array($first=>'Серия', $second=>'Folge'), $list['n'], 'each legacy torrent gets its own verified title');
		$this->assertSame(array('d.multicall', 'get_session', 'd.multicall'), $this->daemon->calls(),
			'all titles share one source path request');
	}

	public function testTiedFileIsTheFallbackSource()
	{
		list($hash, $legacy) = $this->legacy();
		rename($this->base.'/session/'.$hash.'.torrent', $this->base.'/tied.torrent');
		$legacy['tied'] = $this->base.'/tied.torrent';
		$this->start(array($hash=>$legacy));
		$this->assertSame(array($hash=>'Серия'), $this->post('mode=list')['n'], 'without a session copy the tied file supplies the title');
	}

	public function testMalformedSourcePathsKeepVisibleBytes()
	{
		list($hash, $legacy) = $this->legacy();
		$this->start(array($hash=>$legacy), true);
		$list = $this->post('mode=list');
		$this->assertSame(array($hash=>'Serie \\xF1\\xE5\\xF0\\xE8\\xFF'), $list['n'], 'an unpaired source path reply is not trusted');
		$this->assertSame(1, count($list['t']), 'and the list itself is unaffected');
	}

	public function testQuotesAndBackslashesInLegacyBytesKeepTheTitleAndKey()
	{
		$name = "Serie \xFF \"q\" \\b";
		list($hash, $legacy) = $this->legacy('', $name, 'Серия "q" \\b');
		$this->start(array($hash=>$legacy));
		$list = $this->post('mode=list');
		$this->assertSame(array($hash=>'Серия "q" \\b'), $list['n'], 'the reader escaping does not hide the verified title');
		$this->assertSame(rawurlencode('/data/'.$name), $list['p'][$hash]['base']['pathKey'], 'the key names the daemon bytes');
		$this->assertSame('/data', $list['p'][$hash]['save'], 'the save path is cut from the real name');
	}

	public function testNameOrBasePathChangesRefreshTheSavePathKeys()
	{
		list($hash, $legacy) = $this->legacy();
		$renamed = $legacy;
		$renamed['name'] = "Other \xFF";
		$moved = $renamed;
		$moved['base'] = "/new/Other \xFF";
		$this->start(array(array($hash=>$legacy), array($hash=>$renamed), array($hash=>$moved)));
		$first = $this->post('mode=list');
		$second = $this->post('mode=list&cid='.$first['cid']);
		$this->assertSame(array(4), array_keys($second['t'][$hash]), 'only the name changed');
		$this->assertSame(array('name'=>'/data/Serie \\xF1\\xE5\\xF0\\xE8\\xFF', 'pathKey'=>rawurlencode($legacy['base'])),
			$second['p'][$hash]['save'] ?? null, 'a new name alone refreshes the save path, which is no longer cut');
		$third = $this->post('mode=list&cid='.$second['cid']);
		$this->assertSame(array(25), array_keys($third['t'][$hash]), 'only the base path changed');
		$this->assertSame('/new', $third['p'][$hash]['save'] ?? null, 'a moved path is cut by the name in the full row');
	}
}
