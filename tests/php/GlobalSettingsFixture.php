<?php

/** Restores test-owned globals, including whether a setting was absent. */
class GlobalSettingsFixture
{
	// Every global the SCGI transport, its two doors and their logging read.
	const SCGI = array('scgi_host', 'scgi_port', 'rpcTimeOut', 'rpcTransferTimeOut',
		'rpcMaxResponseBytes', 'rpcLogCalls', 'rpcLogFaults', 'log_file', 'profileMask',
		'tempDirectory', 'tempDirectory_init_done');

	private $settings = array();

	public static function scgi()
	{
		return new self(self::SCGI);
	}

	public function __construct($names)
	{
		foreach($names as $name)
			$this->settings[$name] = array(array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null);
	}

	public function restore()
	{
		foreach($this->settings as $name => $setting)
			if($setting[0])
				$GLOBALS[$name] = $setting[1];
			else
				unset($GLOBALS[$name]);
	}
}
