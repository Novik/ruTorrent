<?php

require_once( dirname(__FILE__)."/../../php/cache.php" );
eval( FileUtil::getPluginConf( 'theme' ) );

class rTheme
{
	public $hash = "theme.dat";
	public $current = "";

	static public function load()
	{
		global $defaultTheme;
		$cache = new rCache();
		$theme = new rTheme();
		$theme->current = $defaultTheme;
		if(!$cache->get($theme))
			$theme->current = $defaultTheme;
		return($theme);
	}

	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}

	public function isValid()
	{
		return( ($this->current!='') && is_dir( dirname(__FILE__).'/themes/'.$this->current ) );
	}

	public function get()
	{
		return( "theWebUI.theme = '".$this->current."';" );
	}

	public function set()
	{
		if(isset($_REQUEST['theme']))
		{
			$this->current = $_REQUEST['theme'];
			$this->store();
		}
	}
}
