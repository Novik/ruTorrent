<?php

require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/../../php/utility/utility.php');

/** Exercise a production command expression without starting its daemon or UI. */
class ShellCommandFixture
{
	/** Read a complete statement: quoted semicolons do not terminate PHP tokens. */
	public static function statement($file, $marker, $occurrence = 0)
	{
		$source = file_get_contents($file);
		$offset = -1;
		for($index = 0; $index <= $occurrence; $index++)
		{
			$offset = strpos($source, $marker, $offset + 1);
			if($offset === false)
				throw new RuntimeException('Production command marker not found: '.$marker);
		}
		$statement = '';
		foreach(token_get_all('<?php '.substr($source, $offset)) as $token)
		{
			if(is_array($token) && $token[0] === T_OPEN_TAG) continue;
			$statement .= is_array($token) ? $token[1] : $token;
			if($token === ';') return $statement;
		}
		throw new RuntimeException('Production command statement is incomplete');
	}

	/** Evaluate the statement in the C locale, where escapeshellarg() drops every non-ASCII byte. */
	public static function evaluate($statement, $variables, $result = 'commands')
	{
		$locale = setlocale(LC_CTYPE, 0);
		setlocale(LC_CTYPE, 'C');
		try
		{
			// The closure keeps $locale out of the statement's scope.
			return (function() use ($statement, $variables, $result)
			{
				extract($variables, EXTR_SKIP);
				$commands = array();
				eval($statement);
				return $$result;
			})();
		}
		finally { setlocale(LC_CTYPE, $locale); }
	}

	/** Parse exactly the command words with the shell the production tasks use. */
	public static function arguments($command)
	{
		$script = 'set -- '.$command."\n".'exec '.Utility::escapeShellArg(PHP_BINARY).' -r '
			.Utility::escapeShellArg('echo serialize(array_slice($argv, 1));').' -- "$@"';
		// A bounded process group also stops children from a broken shell expansion.
		$code = '$process=proc_open('.var_export(array('timeout', '-k', '1', '5', '/bin/sh'), true).', '
			.'array(0=>array("pipe","r"), 1=>STDOUT, 2=>STDERR), $pipes);'
			.'if(!is_resource($process)) exit(91);'
			.'fwrite($pipes[0], '.var_export($script, true).'); fclose($pipes[0]); exit(proc_close($process));';
		$result = PhpProcess::run(array('-n', '-r', $code));
		if($result['code'] !== 0 || $result['err'] !== '' || $result['timedOut'])
			throw new RuntimeException('Shell argument capture failed: '.$result['err']);
		$arguments = unserialize($result['out'], array('allowed_classes'=>false));
		if(!is_array($arguments))
			throw new RuntimeException('Shell did not return an argument list');
		return $arguments;
	}
}
