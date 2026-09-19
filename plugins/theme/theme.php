<?php

require_once( dirname(__FILE__)."/../../php/cache.php" );
require_once( dirname(__FILE__)."/../../php/utility/json.php" );
eval( FileUtil::getPluginConf( 'theme' ) );

class rTheme
{
	public $hash = "theme.dat";
	public $modified = false;
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
		return( is_string($this->current) && ($this->current!='') &&
			($this->current[0]!='.') && (basename($this->current)===$this->current) &&
			is_dir( dirname(__FILE__).'/themes/'.$this->current ) );
	}

	public function get()
	{
		return( "theWebUI.theme = ".JSON::jsValue(is_string($this->current) ? $this->current : '').";" );
	}

	public function set()
	{
		if(isset($_REQUEST['theme']))
		{
			$previous = $this->current;
			$this->current = is_string($_REQUEST['theme']) ? $_REQUEST['theme'] : '';
			if(($this->current!='') && !$this->isValid())
			{
				$this->current = $previous;
				return;
			}
			$this->store();
		}
	}
}
