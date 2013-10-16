<?php

require_once( dirname(__FILE__)."/../../php/cache.php" );
eval(getPluginConf('cpu'));

class rCPU
{
	public $hash = "cpu.dat";
	public $count = 1;

	static public function load()
	{
		global $processorsCount;		
                if(is_null($processorsCount))
		{
			$cache = new rCache();
			$cpu = new rCPU();
			if(!$cache->get($cpu))
				$cpu->obtain();
		}
		else
			$cpu->count = $processorsCount;
		return($cpu);
	}

	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}

	public function obtain()
	{
		$this->count = max(intval(shell_exec('grep -c processor /proc/cpuinfo')),1);
		$this->store();
	}

	public function get()
	{
		if(!function_exists('sys_getloadavg'))
		{
			function sys_getloadavg()
			{
				$loadavg_file = '/proc/loadavg';
				if(file_exists($loadavg_file))
					return(explode(chr(32),file_get_contents($loadavg_file)));
				else
					return(array_map("trim",explode(",",substr(strrchr(shell_exec("uptime"),":"),1))));
				return array(0,0,0);
			}
		}
		$arr = sys_getloadavg();
		return( round(min($arr[0]*100/$this->count,100)) );
	}
}
