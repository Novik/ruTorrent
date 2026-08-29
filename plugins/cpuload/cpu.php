<?php

require_once( dirname(__FILE__)."/../../php/cache.php" );
eval(FileUtil::getPluginConf('cpuload'));

class rCPU
{
	public $hash = "cpu.dat";
	public $modified = false;
	public $count = 1;

	static public function load()
	{
		global $processorsCount;
		$cpu = new rCPU();
                if(is_null($processorsCount))
		{
			$cache = new rCache();
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
		$arr = $this->loadavg();
		return( round(min($arr[0]*100/$this->count,100)) );
	}

	// sys_getloadavg() is not available on every platform the WebUI runs on --
	// Windows has no such function -- so /proc/loadavg, and then uptime(1),
	// stand in for it.
	protected function loadavg()
	{
		if(function_exists('sys_getloadavg'))
			return(sys_getloadavg());
		$loadavg_file = '/proc/loadavg';
		if(file_exists($loadavg_file))
			return(explode(chr(32),file_get_contents($loadavg_file)));
		return(array_map("trim",explode(",",substr(strrchr(shell_exec("uptime"),":"),1))));
	}
}
