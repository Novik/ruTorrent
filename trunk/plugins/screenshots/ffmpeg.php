<?php

require_once( dirname(__FILE__)."/../../php/settings.php" );

class ffmpegSettings
{
	public $hash = "ffmpeg.dat";
	public $data = array(
		'exusewidth'=>0,
		'exfrmwidth'=>320,
		'exfrmcount'=>3,
		'exfrmoffs'=>3,
		'exfrminterval'=>5,
		'explayinterval'=>3,
		'exformat'=>0,
		);
	static public function load()
	{
		$cache = new rCache();
		$rt = new ffmpegSettings();
		if($cache->get($rt))
		{
			if(!array_key_exists('exusewidth',$rt->data))
				$rt->data['exusewidth']=0;
			if(!array_key_exists('exformat',$rt->data))
				$rt->data['exformat']=0;
		}
		return($rt);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	public function get()
	{
		return($this->data);
	}
	public function set()
	{
		foreach( $this->data as $name=>&$val )
		{
			$val = isset($_REQUEST[$name]) ? intval($_REQUEST[$name]) : 0;
		}
		if($this->data['exfrminterval']<=0)
			$this->data['exfrminterval'] = 5;
		if($this->data['exfrmcount']<=0)
			$this->data['exfrmcount'] = 3;
		if($this->data['exfrmwidth']<=32)
			$this->data['exfrmwidth'] = 128;
                $this->store();
		return($this->get());
	}
}
