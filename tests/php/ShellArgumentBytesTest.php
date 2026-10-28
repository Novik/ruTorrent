<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/../../php/utility/utility.php');

class ShellArgumentBytesTest extends TestCase
{
	public function testAsciiQuotingKeepsTheExistingShellContract()
	{
		$this->assertTrue(Utility::escapeShellArg(null) === "''", 'a null optional shell argument remains empty after string conversion');
		foreach(array('', '0', 'simple/path.ext', 'a b', "single'quote", '"double"', '\\path\\',
			'$(printf INJECTED)', '`printf INJECTED`', 'a; printf INJECTED', "line\nbreak", '%2F+%25') as $value)
			$this->assertTrue(Utility::escapeShellArg($value) === escapeshellarg($value), 'ASCII shell quoting remains byte-identical');
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
				$result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-r', $code)));
				$this->assertTrue($result['code'] === 0, 'the actual shell command exits successfully in '.$locale);
				$this->assertTrue($result['out'] === base64_encode($value), 'the invoked process receives the exact original argument in '.$locale);
				$this->assertTrue($result['err'] === '', 'quoting produces no diagnostic or injected command output');
			}
	}

	public function testNulIsRefusedBeforeACommandCanBeConstructed()
	{
		$refused = false;
		try { Utility::escapeShellArg("prefix\0suffix"); }
		catch(InvalidArgumentException $error) { $refused = true; }
		$this->assertTrue($refused, 'NUL cannot truncate the argument or become an empty success');
	}
}
