<?php

require_once( 'cache.php' );

class WebUISettings
{
	public $hash = "WebUISettings.dat";	
	private $jsonString = "{}";

	public static function load()
	{
		$cache = new rCache();
		$settings = new WebUISettings();
		$cache->get($settings);
		return $settings;
	}

	public function store()
	{
		$cache = new rCache();
		$cache->set($this);
	}

	public function get()
	{
		return $this->jsonString;
	}

	public function set($json)
	{
		if ($this->jsonString !== $json)
		{
			$this->jsonString = $json;
			$this->store();
		}
	}
}
