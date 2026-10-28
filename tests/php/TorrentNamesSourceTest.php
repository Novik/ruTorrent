<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/GlobalSettingsFixture.php');
require_once(__DIR__.'/FakeRtorrentDaemon.php');
require_once(__DIR__.'/TestTree.php');
require_once(__DIR__.'/TorrentSequenceFixtures.php');
require_once(__DIR__.'/QuietCall.php');
require_once(__DIR__.'/../../php/rtorrent.php');
require_once(__DIR__.'/../../php/torrentfilenames.php');

// A source file that url_stat() calls readable and that cannot be opened.
class TorrentNamesUnreadableStream
{
	public $context;

	public function url_stat($path, $flags)
	{
		return array('mode'=>0100644, 'uid'=>getmyuid(), 'gid'=>getmygid(), 'size'=>1);
	}

	public function stream_open($path, $mode, $options, &$opened_path)
	{
		return false;
	}
}

/**
 * rTorrent::getSource(), rTorrent::getSourcePaths() and the TorrentFileNames
 * calls that use them, against a daemon that answers like rtorrent.
 */
class TorrentNamesSourceTest extends TestCase
{
	use TorrentSequenceFixtures;
	use QuietCall;

	// Spare replies: a request nobody expected is answered and recorded.
	const TRAP = array(array('', ''), array('', ''), array('', ''));

	private $previousSettings;
	private $previousGlobals;
	private $base;
	private $daemon;

	public function setUp()
	{
		$this->previousGlobals = GlobalSettingsFixture::scgi();
		$this->base = sys_get_temp_dir().'/rutorrent-torrent-names-'.uniqid('', true);
		mkdir($this->base, 0700);
		chmod($this->base, 0700);
		$this->base = realpath($this->base);
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
		$GLOBALS['log_file'] = $this->base.'/errors.log';
		$GLOBALS['tempDirectory'] = $this->base;
		$GLOBALS['tempDirectory_init_done'] = false;
	}

	public function tearDown()
	{
		try
		{
			self::swapSettings($this->previousSettings);
			$this->stopDaemon();
			TestTree::remove($this->base);
		}
		finally
		{
			$this->previousGlobals->restore();
		}
	}

	private static function swapSettings($settings)
	{
		return Closure::bind(function($settings) {
			$previous = rTorrentSettings::$theSettings;
			rTorrentSettings::$theSettings = $settings;
			return $previous;
		}, null, 'rTorrentSettings')->__invoke($settings);
	}

	private function stopDaemon()
	{
		if($this->daemon !== null)
			$this->daemon->stop();
		$this->daemon = null;
	}

	private function daemon($replies)
	{
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon(array_merge($replies, self::TRAP), $this->base.'/calls.log');
		$GLOBALS['scgi_port'] = $this->daemon->port();
	}

	// The method names the daemon was asked for; stops it, so every request is in.
	private function calls()
	{
		$calls = $this->daemon->calls();
		$this->stopDaemon();
		return $calls;
	}

	// A torrent file in a new directory; returns the directory with its slash.
	private function store($directory, $file, $info, $root = array())
	{
		$directory = $this->base.'/'.$directory;
		if(!is_dir($directory))
			mkdir($directory, 0700);
		chmod($directory, 0700);
		file_put_contents($directory.'/'.$file, $this->bdict($root + array('info'=>$info)));
		return $directory.'/';
	}

	private static function hash($info)
	{
		return strtoupper(sha1($info));
	}

	private function single($name, $alias = null)
	{
		$pairs = array('length'=>$this->bint(7), 'name'=>$this->bstr($name));
		if($alias !== null)
			$pairs['name.utf-8'] = $this->bstr($alias);
		return $this->bdict($pairs);
	}

	// rtorrent's answer to a system.multicall whose second call failed.
	private static function nestedFault($first)
	{
		$body = '<?xml version="1.0" encoding="UTF-8"?><methodResponse><params><param><value><array><data>'
			.'<value><array><data><value><string>'.$first.'</string></value></data></array></value>'
			.'<value><struct><member><name>faultCode</name><value><i4>-501</i4></value></member>'
			.'<member><name>faultString</name><value><string>Could not find info-hash.</string></value></member>'
			.'</struct></value></data></array></value></param></params></methodResponse>';
		return FakeRtorrentDaemon::raw("Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: "
			.strlen($body)."\r\n\r\n".$body);
	}

	private static function loaded($torrent)
	{
		return ($torrent instanceof Torrent) ? $torrent->info['name'] : false;
	}

	public function testKnownPathsUseTheSessionThenTheTiedFileWithoutARequest()
	{
		$info = $this->single('session');
		$hash = self::hash($info);
		$session = $this->store('session', $hash.'.torrent', $info);
		$tied = $this->store('watch', 'tied.torrent', $this->single('tied')).'tied.torrent';
		$this->daemon(array());
		$this->assertTrue(self::loaded(rTorrent::getSource($hash, array($session, $tied))) === 'session',
			'a readable session file keeps precedence');
		foreach(array(false, array(), array($session), array($session, null), array(null, $tied), 'invalid') as $paths)
			$this->assertTrue(rTorrent::getSource($hash, $paths) === false,
				'an unavailable or incomplete path pair gives no source, even beside a readable session file');
		unlink($session.$hash.'.torrent');
		$this->assertTrue(self::loaded(rTorrent::getSource($hash, array($session, $tied))) === 'tied',
			'a missing session file falls back to the tied file');
		$legacy = $this->single("raw\xFF", 'Серия');
		$this->store('session', self::hash($legacy).'.torrent', $legacy);
		$this->assertTrue(TorrentFileNames::displayTitle("raw\xFF", self::hash($legacy), array($session, '')) === 'Серия',
			'a title gets its alias from the paths it is given');
		$this->assertTrue($this->calls() === array(), 'known paths never open a daemon request');
	}

	public function testTheBatchReadsEveryPathInOneRequest()
	{
		$info = $this->single('session');
		$hash = self::hash($info);
		$session = $this->store('session', $hash.'.torrent', $info);
		$other = str_repeat('B', 40);
		$this->daemon(array(array($session, $hash, '/watch/a.torrent', $other, '')));
		$this->assertTrue(rTorrent::getSourcePaths() === array($hash=>array($session, '/watch/a.torrent'), $other=>array($session, '')),
			'each torrent is keyed by its hash with the session directory and its tied file');
		$body = $this->daemon->bodies()[0];
		$this->assertTrue($this->calls() === array('get_session', 'd.multicall')
			&& (strpos($body, '<string>main</string>') !== false) && (strpos($body, 'd.get_hash=') !== false)
			&& (strpos($body, 'd.get_tied_to_file=') > strpos($body, 'd.get_hash=')),
			'one request asks the main view for the hash, then the tied file');
	}

	public function testTheBatchUnescapesEachPathOnce()
	{
		$info = $this->single('session');
		$hash = self::hash($info);
		// The reply reader escapes a backslash and a quote; a backslash
		// before a quote also shows a second decoding.
		foreach(array('plain', "x\\\"y") as $name)
		{
			$session = $this->store($name, $hash.'.torrent', $info);
			$this->daemon(array(array($session, $hash, '')));
			$this->assertTrue(self::loaded(rTorrent::getSource($hash, rTorrent::getSourcePaths()[$hash] ?? false)) === 'session',
				'the batch finds the session file: '.$name);
			$tied = $this->store($name, 'tied.torrent', $info).'tied.torrent';
			unlink($session.$hash.'.torrent');
			$this->daemon(array(array($this->base.'/none/', $hash, $tied)));
			$this->assertTrue(self::loaded(rTorrent::getSource($hash, rTorrent::getSourcePaths()[$hash] ?? false)) === 'session',
				'the batch finds the tied file: '.$name);
		}
	}

	public function testItsOwnRequestUnescapesEachPathOnce()
	{
		$info = $this->single('session');
		$hash = self::hash($info);
		// A backslash before a quote also shows a second decoding.
		foreach(array('plain', "x\\\"y") as $name)
		{
			$session = $this->store($name, $hash.'.torrent', $info);
			$this->daemon(array(array($session, '')));
			$this->assertTrue(self::loaded(rTorrent::getSource($hash)) === 'session', 'its own request finds the session file: '.$name);
			$tied = $this->store($name, 'tied.torrent', $info).'tied.torrent';
			unlink($session.$hash.'.torrent');
			$this->daemon(array(array($this->base.'/none/', $tied)));
			$this->assertTrue(self::loaded(rTorrent::getSource($hash)) === 'session', 'its own request finds the tied file: '.$name);
		}
	}

	public function testFailedOrPartialRepliesGiveNoPaths()
	{
		$info = $this->single('session');
		$hash = self::hash($info);
		$session = $this->store('session', $hash.'.torrent', $info);
		$this->daemon(array(FakeRtorrentDaemon::fault('boom'), self::nestedFault($session), array($session, $hash), null));
		for($reply = 0; $reply < 4; $reply++)
			$this->assertTrue(rTorrent::getSourcePaths() === array(),
				'a fault, a failed call inside the batch, a partial row or no reply gives no paths');
		$this->daemon(array(self::nestedFault($session)));
		$this->assertTrue(rTorrent::getSource($hash) === false,
			'a failed tied-file call gives no source, although the session file is readable');
	}

	public function testTitlesAskOneBatchAndNoMore()
	{
		$info = $this->single("first\xFF", 'Первый');
		$first = self::hash($info);
		$session = $this->store('session', $first.'.torrent', $info);
		$second = str_repeat('B', 40);
		$names = array($first=>"first\xFF", $second=>"second\xFF", str_repeat('C', 40)=>'中文');
		$this->daemon(array(FakeRtorrentDaemon::fault('boom')));
		$this->assertTrue(TorrentFileNames::titles($names) === array($first=>'first\\xFF', $second=>'second\\xFF', str_repeat('C', 40)=>'中文'),
			'after a failed batch every legacy title shows its bytes');
		$this->assertTrue($this->calls() === array('get_session', 'd.multicall'), 'a failed batch is not followed by a request per title');
		$this->daemon(array(array($session, $first, '')));
		$this->assertTrue(TorrentFileNames::titles($names) === array($first=>'Первый', $second=>'second\\xFF', str_repeat('C', 40)=>'中文'),
			'a title found in the batch gets its alias; one missing from it shows its bytes');
		$this->assertTrue($this->calls() === array('get_session', 'd.multicall'), 'a torrent missing from the batch is not asked about again');
		$this->daemon(array(array($session, $first, '')));
		$this->assertTrue(TorrentFileNames::titles(array(strtolower($first)=>"first\xFF")) === array(strtolower($first)=>'Первый'),
			'the batch matches a hash in either case and keeps the caller\'s key');
		$this->daemon(array(array($session, '')));
		$this->assertTrue(TorrentFileNames::displayTitle("first\xFF", strtolower($first), null) === 'Первый', 'one title matches a hash in either case');
		$this->daemon(array());
		$this->assertTrue(TorrentFileNames::titles(array($second=>'中文')) === array($second=>'中文'), 'valid titles need no request');
		$this->assertTrue(TorrentFileNames::displayTitle('中文', $second, null) === '中文', 'a valid title is its own label');
		$this->assertTrue($this->calls() === array(), 'valid titles need no request');
	}

	public function testSourceReadWarningsStayOutOfTheLabel()
	{
		if(!in_array('unreadable', stream_get_wrappers(), true))
			stream_wrapper_register('unreadable', 'TorrentNamesUnreadableStream');
		$paths = array('unreadable://session/', '');
		$hash = str_repeat('A', 40);
		$this->assertTrue(self::quietly(function() use ($hash, $paths) { return TorrentFileNames::displayTitle("raw\xFF", $hash, $paths); })
			=== array('raw\\xFF', array()), 'an unreadable source leaves the bytes and no diagnostic');
		list($source, $errors) = self::quietly(function() use ($hash, $paths) { return rTorrent::getSource($hash, $paths); });
		$this->assertTrue(($source === false) && (count($errors) === 1) && (strpos($errors[0], 'file_get_contents') !== false),
			'the fixture is a real read failure, and getSource() itself still reports it');
	}

	public function testDownloadNameUnescapesTheLivePathOnce()
	{
		// The reply reader escapes a backslash and a quote in f.get_path.
		foreach(array("a\"b\xFF.mkv", "a\\b\xFF.mkv", "a\\\"b\xFF.mkv") as $number=>$leaf)
		{
			$file = $this->bdict(array('length'=>$this->bint(7), 'path'=>$this->blist(array($this->bstr("dir\xFF"), $this->bstr($leaf))),
				'path.utf-8'=>$this->blist(array($this->bstr('Сезон'), $this->bstr('Файл.mkv')))));
			$info = $this->bdict(array('files'=>$this->blist(array($file)), 'name'=>$this->bstr('Root')));
			$hash = self::hash($info);
			$session = $this->store('escaped'.$number, $hash.'.torrent', $info);
			$this->daemon(array(array("dir\xFF/".$leaf, 7, '1'), array($session, '')));
			$this->assertTrue(TorrentFileNames::downloadName($hash, 0, "/data/Root/dir\xFF/".$leaf) === 'Файл.mkv',
				'a raw file name with a backslash or a quote gets its alias');
		}
	}

	public function testDownloadNameComesFromTheSameLiveFile()
	{
		$file = $this->bdict(array('length'=>$this->bint(7), 'path'=>$this->blist(array($this->bstr("dir\xFF"), $this->bstr("a\xFF.mkv"))),
			'path.utf-8'=>$this->blist(array($this->bstr('Сезон'), $this->bstr('Файл.mkv')))));
		$info = $this->bdict(array('files'=>$this->blist(array($file)), 'name'=>$this->bstr('Root')));
		$hash = self::hash($info);
		$session = $this->store('session', $hash.'.torrent', $info);
		$path = "dir\xFF/a\xFF.mkv";
		$filename = '/data/Root/'.$path;
		$this->daemon(array(array($path, 7, '1'), array($session, '')));
		$this->assertTrue(TorrentFileNames::downloadName(strtolower($hash), 0, $filename) === 'Файл.mkv', 'the live file gets its alias');
		$this->assertTrue($this->calls() === array('f.get_path', 'f.get_size_bytes', 'd.get_size_files', 'get_session', 'd.get_tied_to_file'),
			'one request for the live file, one for its source');
		foreach(array(array(1, array($path, 7, '1')), array(0, array($path, 8, '1')), array(0, array($path, 7, '2'))) as $case)
		{
			$this->daemon(array($case[1], array($session, '')));
			$this->assertTrue(TorrentFileNames::downloadName($hash, $case[0], $filename) === 'a\\xFF.mkv',
				'an index, size or file count that differs from the metadata keeps the bytes');
		}
		$this->daemon(array(array($path, 7)));
		$this->assertTrue(self::quietly(function() use ($hash, $filename) { return TorrentFileNames::downloadName($hash, 0, $filename); })
			=== array('a\\xFF.mkv', array()), 'a partial reply keeps the bytes without a diagnostic');
		$this->daemon(array(FakeRtorrentDaemon::fault('boom'), array("dir\xFF/b\xFF.mkv", 7, '1')));
		$this->assertTrue(TorrentFileNames::downloadName($hash, 0, $filename) === 'a\\xFF.mkv', 'a failed request keeps the bytes');
		$this->assertTrue(TorrentFileNames::downloadName($hash, 0, $filename) === 'a\\xFF.mkv', 'another live file keeps the bytes');
		$this->assertTrue($this->calls() === array('f.get_path', 'f.get_size_bytes', 'd.get_size_files', 'f.get_path', 'f.get_size_bytes', 'd.get_size_files'),
			'no source is read for a file that is not the one asked for');
		if(extension_loaded('iconv') || extension_loaded('mbstring'))
		{
			$info = $this->single("\xF1\xE5\xF0\xE8\xFF.mkv");
			$hash = self::hash($info);
			$session = $this->store('encoded', $hash.'.torrent', $info, array('encoding'=>$this->bstr('Windows-1251')));
			$this->daemon(array(array("\xF1\xE5\xF0\xE8\xFF.mkv", 7, '1'), array($session, '')));
			$this->assertTrue(TorrentFileNames::downloadName($hash, 0, "/data/\xF1\xE5\xF0\xE8\xFF.mkv") === 'серия.mkv',
				'without an alias the file name converts from the declared encoding');
			$this->daemon(array(array("\xF1\xE5\xF0\xE8\xFF.mkv", 8, '1'), array($session, '')));
			$this->assertTrue(TorrentFileNames::downloadName($hash, 0, "/data/\xF1\xE5\xF0\xE8\xFF.mkv") === '\\xF1\\xE5\\xF0\\xE8\\xFF.mkv',
				'a file whose size differs from the metadata is not converted');
		}
		$this->daemon(array());
		$this->assertTrue(TorrentFileNames::downloadName($hash, 0, '/data/Файл.mkv') === null, 'a UTF-8 name keeps the default');
		$this->assertTrue($this->calls() === array(), 'a UTF-8 name needs no request');
	}
}
