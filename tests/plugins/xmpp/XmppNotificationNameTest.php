<?php

require_once(__DIR__.'/../../php/TestCase.php');
require_once(__DIR__.'/../../../plugins/xmpp/xmpp.php');
require_once(__DIR__.'/../../../php/rtorrent.php');
require_once(__DIR__.'/../../../plugins/xmpp/XMPPHP/XMPP.php');
require_once(__DIR__.'/../../php/TestTree.php');
require_once(__DIR__.'/../../php/PhpProcess.php');
require_once(__DIR__.'/../../php/SCGITransportFixture.php');

class XmppNameTorrent extends Torrent
{
	public static function bytes($data) { return parent::encode($data); }
}

class XmppNameWire extends XMPPHP_XMPP
{
	public $sent;
	public function __construct() { $this->fulljid = 'sender@example.test'; }
	public function send($message, $timeout = null) { $this->sent = $message; }
}

class XmppNotificationNameTest extends TestCase
{
	private function assertSame($expected, $actual, $message = null)
	{
		$this->assertTrue($expected === $actual, $message);
	}

	private $base;
	public function setUp()
	{
		// Fixture modes must not depend on the caller's umask.
		umask(0077);
		$this->base = sys_get_temp_dir().'/xmpp-name-'.getmypid();
		mkdir($this->base,0700,true);
		chmod($this->base,0700);
	}
	public function tearDown() { TestTree::remove($this->base); }

	private function source($name, $alias = null, $encoding = null)
	{
		$info = array('name'=>$name, 'length'=>1);
		if($alias !== null) $info['name.utf-8'] = $alias;
		$meta = array('info'=>$info);
		if($encoding !== null) $meta['encoding'] = $encoding;
		$hash = strtoupper(sha1(XmppNameTorrent::bytes($info)));
		file_put_contents($this->base.'/'.$hash.'.torrent', XmppNameTorrent::bytes($meta));
		return $hash;
	}

	private function body($name, $hash = '', $paths = false)
	{
		$config = new rXmpp();
		$config->message = 'Done: {TORRENT}';
		$wire = new XmppNameWire();
		$wire->message('recipient@example.test', $config->notificationMessage($name,$hash,$paths), 'chat', '');
		$xml = new DOMDocument();
		$this->assertTrue($xml->loadXML($wire->sent), 'notification stays well-formed XML');
		$this->assertSame(1, $xml->getElementsByTagName('body')->length, 'name cannot insert another body');
		return $xml->getElementsByTagName('body')->item(0)->textContent;
	}

	public function testMatchingAliasIsTextAndKeepsTheSource()
	{
		$hash = $this->source("raw\xFF", 'Название <&>');
		$path = $this->base.'/'.$hash.'.torrent';
		$before = file_get_contents($path);
		$this->assertSame('Done: Название <&>', $this->body("raw\xFF",$hash,array($this->base.'/', '')), 'alias is readable');
		$this->assertSame($before,file_get_contents($path),'notification does not modify metainfo');
	}

	public function testOldHooksAndUnavailableMetadataKeepDifferentBytes()
	{
		$this->assertSame('Done: raw\\xFE', $this->body("raw\xFE"), 'old hook arguments need no daemon lookup');
		$this->assertSame('Done: raw\\xFF', $this->body("raw\xFF",str_repeat('A',40),array('', '')), 'unavailable source has a readable fallback');
		$this->assertSame('Done: имя \\xFF', $this->body('имя \\xFF'), 'valid Unicode and literal escapes stay unchanged');
	}

	public function testMismatchedHashAndNameCannotSupplyAnAlias()
	{
		$hash = $this->source("raw\xFF", 'wrong alias');
		$path = $this->base.'/'.$hash.'.torrent';
		$this->assertSame('Done: raw\\xFF', $this->body("raw\xFF",str_repeat('A',40),array('', $path)), 'another torrent cannot supply its alias');
		$this->assertSame('Done: other\\xFF', $this->body("other\xFF",$hash,array('', $path)), 'changed live name cannot borrow the old alias');
	}

	public function testDeclaredEncodingIsUsedWithoutAnAlias()
	{
		$hash = $this->source("\xCF\xF0\xE8\xE2\xE5\xF2",null,'windows-1251');
		$this->assertSame('Done: Привет', $this->body("\xCF\xF0\xE8\xE2\xE5\xF2",$hash,array($this->base.'/', '')), 'explicit lossless encoding supplies text');
	}

	public function testNotifyEntryReadsAppendedEvidenceAndOldArguments()
	{
		$root = dirname(__DIR__,3);
		$tree = $this->base.'/entry';
		mkdir($tree.'/php',0700,true);
		mkdir($tree.'/plugins/xmpp/XMPPHP',0700,true);
		copy($root.'/plugins/xmpp/notify.php',$tree.'/plugins/xmpp/notify.php');
		file_put_contents($tree.'/php/settings.php','<?php require_once '.var_export($root.'/php/settings.php',true).';');
		file_put_contents($tree.'/plugins/xmpp/xmpp.php','<?php require_once '.var_export($root.'/plugins/xmpp/xmpp.php',true).';');
		// Only the network owner is replaced. The CLI argument mapping, cached
		// configuration and name/source resolution are the production code.
		file_put_contents($tree.'/plugins/xmpp/XMPPHP/XMPP.php', <<<'PHP'
<?php
class XMPPHP_XMPP {
    public function __construct(...$args) {}
    public function useEncryption($value) {}
    public function set_context($value) {}
    public function connect() {}
    public function processUntil($event) {}
    public function presence() {}
    public function message($to,$message) { echo json_encode(array('to'=>$to,'message'=>$message)); }
    public function disconnect() {}
}
PHP
		);
		$hash = $this->source("raw\xFF",'Название');
		$driver = '$_ENV["RU_PROFILE_PATH"]='.var_export($this->base.'/profile',true).';'
			.'require '.var_export($root.'/plugins/xmpp/xmpp.php',true).';'
			.'$config=new rXmpp(); $config->message="Done: {TORRENT}";'
			.'$config->jabberFor="recipient@example.test"; $config->store();'
			.'require '.var_export($tree.'/plugins/xmpp/notify.php',true).';';
		foreach(array(array(array("raw\xFF",'', $hash,$this->base.'/', ''),'Done: Название'),
			array(array("raw\xFF",''),'Done: raw\\xFF')) as $case)
		{
			$result = PhpProcess::run(array_merge(array('-d','display_errors=stderr','-r',$driver,'--'),$case[0]));
			$this->assertSame(0,$result['code'],'notification child exits');
			$this->assertSame('',$result['err'],'notification child has no diagnostics');
			$this->assertSame(array('to'=>'recipient@example.test','message'=>$case[1]),json_decode($result['out'],true),'real entry maps hook arguments into display evidence');
		}
	}

	public function testFinishedHookCarriesDisplayEvidenceAfterLegacyArguments()
	{
		global $scgi_host, $scgi_port;
		$oldAddress = array($scgi_host, $scgi_port);
		$reflection = new ReflectionClass('rTorrentSettings');
		$property = $reflection->getProperty('theSettings');
		if(PHP_VERSION_ID < 80100) $property->setAccessible(true);
		$oldSettings = $property->getValue();
		$peer = null;
		try
		{
			$settings = $reflection->newInstanceWithoutConstructor();
			$settings->iVersion = 0x101A;
			$settings->linkExist = true;
			(function() { require __DIR__.'/../../../php/methods-0.9.4.php'; })->call($settings);
			$property->setValue(null,$settings);
			$xml = '<methodResponse><params><param><value><i4>0</i4></value></param></params></methodResponse>';
			$peer = SCGITransportFixture::start("Content-Length: ".strlen($xml)."\r\n\r\n".$xml);
			$scgi_host = '127.0.0.1'; $scgi_port = $peer->port();
			$config = new rXmpp(); $config->message = 'Done: {TORRENT}';
			$this->assertTrue($config->setHandlers(),'hook registration succeeds');
			$request = $peer->request();
			$document = new DOMDocument(); $document->loadXML($request['payload']);
			$xpath = new DOMXPath($document);
			$params = $xpath->query('/methodCall/params/param/value/string');
			$command = $params->item($params->length-1)->textContent;
			$this->assertTrue(strpos($command,',"$d.name=",'.rTorrent::quoteCommandArg(User::getUser()).',"$d.hash=","$session.path=","$d.tied_to_file="}') !== false,
				'raw name and user retain their positions before hash, session and tied path');
		}
		finally
		{
			if($peer !== null) $peer->close();
			$property->setValue(null,$oldSettings);
			list($scgi_host,$scgi_port) = $oldAddress;
		}
	}
}
