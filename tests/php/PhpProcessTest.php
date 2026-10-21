<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/TestTree.php');

class PhpProcessTest extends TestCase
{
	public function testBothStreamsAreCollectedWithoutBlocking()
	{
		// Each stream far exceeds a pipe buffer: reading one to its end first
		// would leave the child blocked on the other.
		$run = PhpProcess::run(array('-n', '-r',
			'fwrite(STDERR, str_repeat("e", 300000)); echo str_repeat("o", 300000), getenv("PHP_PROCESS_PROBE"); exit(3);'),
			array('env' => array('PHP_PROCESS_PROBE' => 'added'), 'timeout' => 30));
		$this->assertTrue(!$run['timedOut'] && $run['code'] === 3, 'the child exits on its own with its exit code');
		$this->assertTrue($run['out'] === str_repeat('o', 300000).'added', 'stdout is complete and the added variable is seen');
		$this->assertTrue($run['err'] === str_repeat('e', 300000), 'stderr is complete');
	}

	public function testAddedVariablesKeepTheInheritedEnvironment()
	{
		// putenv() reaches getenv() but not $_ENV, which may also be empty.
		putenv('PHP_PROCESS_INHERITED=kept');
		try
		{
			$run = PhpProcess::run(array('-n', '-r', 'echo getenv("PHP_PROCESS_INHERITED");'),
				array('env' => array('PHP_PROCESS_PROBE' => '1')));
		}
		finally { putenv('PHP_PROCESS_INHERITED'); }
		$this->assertTrue($run['out'] === 'kept', 'the process environment is inherited when variables are added');
	}

	public function testAChildPastItsTimeoutIsStopped()
	{
		$started = microtime(true);
		$run = PhpProcess::run(array('-n', '-r', 'sleep(30);'), array('timeout' => 0.5));
		$this->assertTrue($run['timedOut'], 'the run reports the timeout');
		$this->assertTrue(microtime(true) - $started < 5, 'the stopped child does not hold the test for its own sleep');
	}

	public function testAChildThatClosesItsOutputKeepsItsTimeoutAndExitCode()
	{
		$started = microtime(true);
		$run = PhpProcess::run(array('-n', '-r', 'fclose(STDOUT); fclose(STDERR); sleep(30);'), array('timeout' => 0.5));
		$this->assertTrue($run['timedOut'] && (microtime(true) - $started < 5),
			'a child that closed its output is still stopped at the timeout');
		$run = PhpProcess::run(array('-n', '-r', 'fclose(STDOUT); fclose(STDERR); usleep(200000); exit(4);'));
		$this->assertTrue(!$run['timedOut'] && ($run['code'] === 4), 'its later exit code is reported: '.json_encode($run));
	}

	public function testTreeRemovalDoesNotFollowLinks()
	{
		$base = sys_get_temp_dir().'/php-process-tree-'.uniqid('', true);
		mkdir($base.'/tree/nested', 0700, true);
		mkdir($base.'/outside', 0700);
		file_put_contents($base.'/outside/kept', 'kept');
		file_put_contents($base.'/tree/nested/file', 'x');
		symlink($base.'/outside', $base.'/tree/link');
		try
		{
			TestTree::remove($base.'/tree');
			$this->assertTrue(!file_exists($base.'/tree'), 'the tree is removed');
			$this->assertTrue(is_file($base.'/outside/kept'), 'a linked directory outside the tree is left alone');
		}
		finally
		{
			@unlink($base.'/tree/link');
			TestTree::remove($base);
		}
	}
}
