<?php
/* We use cache to store and retrieve variables for us */
require_once( dirname(__FILE__)."/../../php/cache.php" );

class rUploadeta
{
	public $hash = "uploadeta.dat"; /* The file where our value will be stored */
	public $uploadtarget = 200; /* Just a default value */

	static public function load()
	{
		$cache = new rCache();
		$inst = new rUploadeta();
		$cache->get($inst); /* Get our cached or stored value */
		return($inst);
	}

	public function store()
	{ /* Store a new value */
		$cache = new rCache();
		return($cache->set($this));
	}

	public function get()
	{ /* Get our value */
		return( "theWebUI.uploadtarget = '".$this->uploadtarget."';" );
	}

	public function set()
	{ /* Set our value */
		if(isset($_REQUEST['uploadtarget']))
		{
			$this->uploadtarget = $_REQUEST['uploadtarget'];
			$this->store();
		}
	}
}
