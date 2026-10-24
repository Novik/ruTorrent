<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TestHttpServer.php');
require_once(__DIR__ . '/../../php/TestTree.php');
require_once(__DIR__ . '/../../../plugins/unpack/unpack.php');

class UnpackDiagnosticProbe extends rUnpack
{
	public static function process($task, $commands)
	{
		return parent::processTask($task, $commands);
	}
}

class UnpackDiagnosticTest extends TestCase
{
	private function assertSame($expected, $actual, $message = null)
	{
		$this->assertTrue($expected === $actual, $message);
	}

	private $log;
	private $oldLog;
	private $oldDebug;

	public function setUp()
	{
		$this->log = tempnam(sys_get_temp_dir(), 'unpack-display-');
		$this->oldLog = $GLOBALS['log_file'];
		$this->oldDebug = $GLOBALS['unpack_debug_enabled'];
		$GLOBALS['log_file'] = $this->log;
		$GLOBALS['unpack_debug_enabled'] = true;
	}

	public function tearDown()
	{
		$GLOBALS['log_file'] = $this->oldLog;
		$GLOBALS['unpack_debug_enabled'] = $this->oldDebug;
		unlink($this->log);
	}

	public function testRawCommandsStayExecutableAndTheirDebugArgumentsStayVisible()
	{
		$commands = array("unzip '/data/raw\xFF.zip'", "echo '映画 %FF'", "echo 'literal\\xFF'");
		$task = new class {
			public $id = 'display-probe';
			public $started = null;
			public function start($commands, $flags)
			{
				$this->started = array($commands, $flags);
				return array('status' => 0);
			}
		};
		$this->assertSame(array('status' => 0), UnpackDiagnosticProbe::process($task, $commands),
			'the diagnostic does not prevent the task owner from starting');
		$this->assertSame(array($commands, 0), $task->started,
			'the task receives the original byte strings without display escaping');
		$log = file_get_contents($this->log);
		$marker = ' with arguments ';
		$offset = strpos($log, $marker);
		$this->assertTrue($offset !== false, 'the operation logs its argument list');
		$arguments = $offset === false ? null : json_decode(substr($log, $offset + strlen($marker)), true);
		$this->assertSame(array("unzip '/data/raw\\xFF.zip'", $commands[1], $commands[2]), $arguments,
			'the JSON diagnostic retains every command and represents invalid bytes visibly');
	}
	public function testSavedSelectedDirectoryPreservesItsBytes()
	{
		$root = realpath(__DIR__.'/../../..');
		$tree = sys_get_temp_dir().'/unpack-save-'.bin2hex(random_bytes(6));
		mkdir($tree, 0700);
		chmod($tree, 0700);
		$server = null;
		try
		{
			$router = $tree.'/router.php';
			file_put_contents($router, '<?php $_ENV["RU_PROFILE_PATH"]='.var_export($tree.'/profile', true).';'
				.'$_ENV["RU_LOG_FILE"]='.var_export($tree.'/app.log', true).';'
				.'require '.var_export($root.'/plugins/unpack/unpack.php', true).';'
				.'class UnpackPathSaveProbe extends rUnpack { public function setHandlers() { return true; } }'
				.'$r=new ReflectionClass("rTorrentSettings"); $settings=$r->newInstanceWithoutConstructor();'
				.'$settings->directory="/"; $settings->linkExist=true;'
				.'$p=$r->getProperty("theSettings"); if(PHP_VERSION_ID<80100) $p->setAccessible(true); $p->setValue(null,$settings);'
				.'$topDirectory="/"; $up=new UnpackPathSaveProbe(); $up->set();'
				.'$saved=new UnpackPathSaveProbe(); $cache=new rCache(); $cache->get($saved); echo json_encode(array(base64_encode($saved->path),$saved->get()));');
			$server = TestHttpServer::start($tree, array('RU_PROFILE_PATH'=>$tree.'/profile', 'RU_LOG_FILE'=>$tree.'/app.log'), array(), $router);
			foreach(array("/data/raw\xFF ", '/data/映画 ', '/data/literal%FF ', "/data/with\r\nline ") as $path)
			{
				$response = TestHttpServer::rawPost($server->port(), '/', 'unpack_enabled=1&unpack_path='.rawurlencode($path), 'application/x-www-form-urlencoded');
				$value = json_decode($response['body'], true);
				$this->assertTrue(is_file($tree.'/profile/settings/unpack.dat'),
					'HTTP save remains inside the fixture-owned profile');
				$this->assertTrue(is_array($value), 'saved path produces a valid response: '.$response['body']);
				$this->assertSame(base64_encode(trim($path)), $value[0] ?? null, 'the persistent cache retains the selected bytes, trimmed like typed text');
				$contents = isset($value[1]) ? substr($value[1], strlen('theWebUI.unpackData = '), -2) : '';
				$settings = json_decode($contents, true);
				$display = $settings['path'] ?? null;
				$this->assertSame(FileUtil::displayPath(trim($path)), $display, 'reopening the plugin retains display text and the original path key');
			}
		}
		finally
		{
			if($server !== null) $server->close();
			TestTree::remove($tree);
		}
	}

}
