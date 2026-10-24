<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/../../php/utility/utility.php');

class ShellArgumentBytesTest extends TestCase
{
	private function assertSame($expected, $actual, $message)
	{
		$this->assertTrue($expected === $actual, $message);
	}

	public function testAsciiQuotingKeepsTheExistingShellContract()
	{
		foreach(array('', '0', 'simple/path.ext', 'a b', "single'quote", '"double"', '\\path\\',
			'$(printf INJECTED)', '`printf INJECTED`', 'a; printf INJECTED', "line\nbreak", '%2F+%25') as $value)
			$this->assertSame(escapeshellarg($value), Utility::escapeShellArg($value), 'ASCII shell quoting remains byte-identical');
	}

	public function testRealShellPreservesLegacyBytesAndUnicodeInBothLocales()
	{
		$values = array('', "raw\xFF\xF1\xE5 name", 'серия 中文 🙂', "a'\"\\$;`printf INJECTED`\n%2F+%25", "\x01\x7F\x80\xFE");
		foreach(array('C', 'C.UTF-8') as $locale)
			foreach($values as $value)
			{
				$code = 'require '.var_export(__DIR__.'/../../php/utility/utility.php', true).';'
					.'if (setlocale(LC_CTYPE, '.var_export($locale, true).') === false) exit(90);'
					.'$command = Utility::escapeShellArg(PHP_BINARY)." -r ".'
					.'Utility::escapeShellArg(\'echo base64_encode($argv[1]);\')." -- ".'
					.'Utility::escapeShellArg('.var_export($value, true).');'
					.'passthru($command, $status); exit($status);';
				$result = PhpProcess::run(array('-n', '-r', $code));
				$this->assertSame(0, $result['code'], 'the actual shell command exits successfully in '.$locale);
				$this->assertSame(base64_encode($value), $result['out'], 'the invoked process receives the exact original argument in '.$locale);
				$this->assertSame('', $result['err'], 'quoting produces no diagnostic or injected command output');
			}
	}

	public function testNulIsRefusedBeforeACommandCanBeConstructed()
	{
		$refused = false;
		try { Utility::escapeShellArg("prefix\0suffix"); }
		catch(InvalidArgumentException $error) { $refused = true; }
		$this->assertSame(true, $refused, 'NUL cannot truncate the argument or become an empty success');
	}

	public function testQuotingDoesNotDependOnLocaleSensitiveBuiltins()
	{
		$code = 'require '.var_export(__DIR__.'/../../php/utility/utility.php', true).';'
			.'if (Utility::escapeShellArg("raw\\xFF") !== "\'raw\\xFF\'") exit(1);'
			.'try { Utility::escapeShellArg("a\\0b"); exit(2); } catch (InvalidArgumentException $error) {}';
		$result = PhpProcess::run(array('-n', '-d', 'disable_functions=escapeshellarg,str_replace,strpos,strlen,substr,setlocale', '-r', $code));
		$this->assertSame(0, $result['code'], 'raw-byte quoting and NUL refusal work without string/locale primitives');
		$this->assertSame('', $result['out'].$result['err'], 'disabled primitive paths are quiet');
	}
}
