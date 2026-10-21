<?php

/**
 * Runs PHP_BINARY with an argv array, so no shell sits between the test and
 * the child, and collects both output streams without blocking on either.
 */
class PhpProcess
{
	/**
	 * Options: 'cwd'; 'env', variables added to the inherited environment;
	 * 'timeout', seconds before the child is stopped (default 60). Standard
	 * input is closed at once.
	 */
	public static function run($arguments, $options = array())
	{
		$environment = isset($options['env']) ? array_merge(getenv(), $options['env']) : null;
		$process = proc_open(array_merge(array(PHP_BINARY), $arguments), array(
			0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'),
		), $pipes, $options['cwd'] ?? null, $environment);
		if(!is_resource($process))
			throw new RuntimeException('could not start PHP subprocess');
		fclose($pipes[0]);
		$output = array(1 => '', 2 => '');
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$deadline = microtime(true) + ($options['timeout'] ?? 60);
		$timedOut = false;
		while(!feof($pipes[1]) || !feof($pipes[2]))
		{
			$left = $deadline - microtime(true);
			if($left <= 0)
			{
				$timedOut = true;
				break;
			}
			$read = array();
			foreach(array(1, 2) as $index)
				if(!feof($pipes[$index]))
					$read[] = $pipes[$index];
			$write = $except = null;
			if(stream_select($read, $write, $except, 0, (int)min(100000, $left * 1000000)) === false)
				break;
			foreach($read as $pipe)
				$output[($pipe === $pipes[1]) ? 1 : 2] .= (string)stream_get_contents($pipe);
		}
		fclose($pipes[1]);
		fclose($pipes[2]);
		// A child may close its output and keep running: wait within the same
		// deadline. Before PHP 8.3 only the first status that reports the exit
		// carries its code; proc_close() then returns -1.
		$code = null;
		while(!$timedOut)
		{
			$status = proc_get_status($process);
			if(empty($status['running']))
			{
				$code = $status['exitcode'];
				break;
			}
			if(microtime(true) >= $deadline)
				$timedOut = true;
			else
				usleep(10000);
		}
		if($timedOut)
			self::stop($process);
		$closed = proc_close($process);
		return array('code' => ($code === null) ? $closed : $code, 'out' => $output[1], 'err' => $output[2],
			'timedOut' => $timedOut);
	}

	/**
	 * Arguments that give a child this process's configuration. -n would also
	 * drop the extensions a distribution loads from conf.d, such as json on
	 * PHP 7.4 packages.
	 */
	public static function sameIni()
	{
		$ini = php_ini_loaded_file();
		return ($ini === false) ? array() : array('-c', $ini);
	}

	/** SIGTERM, then SIGKILL if the child is still running two seconds later. */
	public static function stop($process)
	{
		proc_terminate($process);
		for($i = 0; $i < 40; $i++)
		{
			$status = proc_get_status($process);
			if(empty($status['running']))
				return;
			usleep(50000);
		}
		proc_terminate($process, 9);
	}
}
