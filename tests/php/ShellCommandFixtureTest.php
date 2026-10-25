<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/ShellCommandFixture.php');
require_once(__DIR__.'/TestTree.php');

class ShellCommandFixtureTest extends TestCase
{
	public function testShellInputRetainsNonUtf8Bytes()
	{
		$value = "raw\xFF '$(printf INJECTED)' серия";
		$this->assertTrue(ShellCommandFixture::arguments('printf '.Utility::escapeShellArg($value)) === array('printf', $value),
			'the supervisor leaves raw shell input bytes intact');
	}

	public function testStatementsRunInTheCLocaleOnly()
	{
		$previous = setlocale(LC_CTYPE, 0);
		try
		{
			$this->assertTrue(setlocale(LC_CTYPE, 'C.UTF-8') === 'C.UTF-8', 'the caller starts in a UTF-8 locale');
			$this->assertTrue(ShellCommandFixture::evaluate('$commands[] = setlocale(LC_CTYPE, 0);', array()) === array('C'),
				'an evaluated statement runs in the C locale');
			$this->assertTrue(setlocale(LC_CTYPE, 0) === 'C.UTF-8', 'the caller\'s locale returns afterwards');
		}
		finally { setlocale(LC_CTYPE, $previous); }
	}

	public function testTimedOutExpansionLeavesNoShellOrSleeper()
	{
		if(!is_dir('/proc/self')) throw new RuntimeException('The process cleanup regression requires /proc');
		$directory = sys_get_temp_dir().'/shell-timeout-'.bin2hex(random_bytes(6));
		mkdir($directory, 0700);
		chmod($directory, 0700);
		$pidFiles = array($directory.'/shell.pid', $directory.'/sleeper.pid');
		$sleeper = Utility::escapeShellArg(PHP_BINARY).' -n -r '
			.Utility::escapeShellArg('file_put_contents($argv[1], (string)getmypid()); sleep(60);').' -- '.Utility::escapeShellArg($pidFiles[1]);
		$command = 'dummy $(echo $$ > '.Utility::escapeShellArg($pidFiles[0]).'; '.$sleeper.')';
		$started = microtime(true);
		$refused = false;
		try
		{
			try { ShellCommandFixture::arguments($command); }
			catch(RuntimeException $error) { $refused = true; }
			$this->assertTrue($refused, 'the fixture reports the timed out shell expansion');
			$this->assertTrue(microtime(true)-$started < 15, 'the inner bound ends before the outer PHP process timeout');
			foreach($pidFiles as $file)
			{
				$pid = is_file($file) ? (int)file_get_contents($file) : 0;
				$this->assertTrue($pid > 1, 'the fixture started the child named by '.basename($file));
				$this->assertTrue($pid > 1 && (string)@file_get_contents('/proc/'.$pid.'/cmdline') === '',
					'the timed out child no longer executes: '.basename($file));
			}
		}
		finally
		{
			// Keep even a failed regression run from leaving its own sleeper behind.
			foreach($pidFiles as $file)
			{
				$pid = is_file($file) ? (int)file_get_contents($file) : 0;
				$commandLine = $pid > 1 ? (string)@file_get_contents('/proc/'.$pid.'/cmdline') : '';
				if($commandLine !== '' && strpos($commandLine, $directory) !== false)
					exec('kill -9 '.(string)$pid);
			}
			TestTree::remove($directory);
		}
	}

	public function testMissingTimeoutUtilityIsAnExplicitFailure()
	{
		$directory = sys_get_temp_dir().'/shell-no-timeout-'.bin2hex(random_bytes(6));
		mkdir($directory, 0700);
		try
		{
			$code = 'require '.var_export(__DIR__.'/ShellCommandFixture.php', true).';'
				.'try { ShellCommandFixture::arguments("printf x"); echo "unexpected success"; }'
				.'catch(RuntimeException $error) { echo "refused"; }';
			$result = PhpProcess::run(array('-n', '-r', $code), array('env'=>array('PATH'=>$directory)));
			$this->assertTrue($result['code'] === 0 && $result['out'] === 'refused' && $result['err'] === '',
				'a missing supervisor fails visibly instead of skipping shell validation');
		}
		finally { TestTree::remove($directory); }
	}
}
