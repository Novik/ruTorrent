<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/_task/task.php');

// A payload class for the params test: unserialize() must not construct it, so
// its __wakeup() must never run.
class TaskPayload
{
	public static $woken = false;
	public function __wakeup()
	{
		self::$woken = true;
	}
}

class TaskTest extends TestCase
{
	protected $tasks = null;
	protected $marker = null;
	protected $outside = null;

	public function setUp()
	{
		$this->tasks = FileUtil::getSettingsPath().'/tasks';
		$this->marker = FileUtil::getSettingsPath().'/task-test-marker';
		$this->outside = FileUtil::getSettingsPath().'/task-test-outside';
		@mkdir($this->tasks, 0777, true);
		@unlink($this->marker);
		TaskPayload::$woken = false;
	}

	public function tearDown()
	{
		@unlink($this->marker);
		$this->removeDir($this->outside);
	}

	protected function removeDir($dir)
	{
		foreach (array('pid', 'status', 'flags', 'params') as $file) {
			@unlink($dir.'/'.$file);
		}
		@rmdir($dir);
	}

	// A task the plugin itself could have produced: a real id, and the flags
	// value that makes rTask::run() use the local shell rather than rtorrent.
	protected function makeTask($pid, $params = null)
	{
		$id = uniqid(time(), true);
		$dir = rTask::formatPath($id);
		mkdir($dir, 0777, true);
		file_put_contents($dir.'/pid', $pid);
		file_put_contents($dir.'/flags', rTask::FLG_RUN_AS_WEB);
		if (!is_null($params)) {
			file_put_contents($dir.'/params', $params);
		}
		return array($id, $dir);
	}

	public function testKillRunsNothingFromANonNumericPidFile()
	{
		list($id, $dir) = $this->makeTask('1$(touch '.$this->marker.')');
		rTask::kill($id);
		sleep(1);
		$this->assertEquals(false, file_exists($this->marker),
			'The contents of a pid file cannot reach the shell');
		$this->assertEquals(false, is_dir($dir), 'The task is still cleaned up');
	}

	public function testKillStopsTheProcessThePidFileNames()
	{
		$pid = intval(exec('sh -c \'sleep 30 >/dev/null 2>&1 & echo $!\''));
		$this->assertEquals(true, $pid > 0, 'A process to kill was started');
		list($id, $dir) = $this->makeTask($pid);
		rTask::kill($id);
		sleep(1);
		$this->assertEquals(false, posix_kill($pid, 0), 'The named process is gone');
	}

	public function testCheckDoesNotConstructClassesNamedByTheParamsFile()
	{
		list($id, $dir) = $this->makeTask('999999', serialize(new TaskPayload()));
		$ret = rTask::check($id);
		$this->assertEquals(false, TaskPayload::$woken,
			'No class from the params file is woken');
		$this->assertEquals(true, $ret['params'] instanceof __PHP_Incomplete_Class,
			'The object arrives inert');
		$this->removeDir($dir);
	}

	public function testCheckOfAPathOutsideTheTasksDirectoryFindsNoTask()
	{
		mkdir($this->outside, 0777, true);
		file_put_contents($this->outside.'/pid', '424242');
		file_put_contents($this->outside.'/params', serialize(array('leaked' => 'data')));
		$ret = rTask::check('../'.basename($this->outside));
		$this->assertEquals(0, $ret['pid'], 'No pid is read from outside the tasks directory');
		$this->assertEquals(array(), $ret['params'], 'No params are read from there either');
		$this->assertEquals(true, is_file($this->outside.'/pid'), 'And nothing there was touched');
	}
}
