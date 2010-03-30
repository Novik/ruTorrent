<?php
	// configuration parameters

	// for snoopy client
	@define('HTTP_USER_AGENT', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/3.0', true);
	@define('HTTP_TIME_OUT', 30, true);	// in seconds
	@define('HTTP_USE_GZIP', true, true);

	@define('RPC_TIME_OUT', 5, true);	// in seconds

	@define('LOG_RPC_CALLS', false, true);
	@define('LOG_RPC_FAULTS', true, true);

	// for php	
	@define('PHP_USE_GZIP', true, true);
	@define('PHP_GZIP_LEVEL', 2, true);

	$do_diagnostic = true;
	$log_file = '/tmp/errors.log';		// path to log file (comment or make empty to disable logging)
	$saveUploadedTorrents = true;

	$topDirectory = '/';			// Upper available directory. Absolute path with trail slash.
	$forbidUserSettings = false;

	$scgi_port = 5000;
	$scgi_host = "127.0.0.1";
	$XMLRPCMountPoint = "/RPC2";

	$pathToExternals = array(
		"php" 	=> '',			// Something like /usr/bin/php. If empty, will be founded in PATH.
		"curl"	=> '',			// Something like /usr/bin/curl. If empty, will be founded in PATH.
		"gzip"	=> '',			// Something like /usr/bin/gzip. If empty, will be founded in PATH.
		"id"	=> '',			// Something like /usr/bin/id. If empty, will be founded in PATH.
	);

	// For web->rtorrent link through unix domain socket 
	// (scgi_local in rtorrent conf file), change variables 
	// above to something like this:
	//
	// $scgi_port = 0;
	// $scgi_host = "unix:///tmp/rpc.socket";

?>
