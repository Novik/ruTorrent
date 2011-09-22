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
	@define('PHP_USE_GZIP', false, true);
	@define('PHP_GZIP_LEVEL', 2, true);

	$do_diagnostic = true;
	$log_file = '/tmp/errors.log';		// path to log file (comment or make empty to disable logging)

	$saveUploadedTorrents = true;		// Save uploaded torrents into profile/torrents directory or not
	$overwriteUploadedTorrents = false;     // Overwrite existing uploaded torrents into profile/torrents directory or make unique name

	$topDirectory = '/';			// Upper available directory. Absolute path with trail slash.
	$forbidUserSettings = false;

	$scgi_port = 5000;
	$scgi_host = "127.0.0.1";

	// For web->rtorrent link through unix domain socket 
	// (scgi_local in rtorrent conf file), change variables 
	// above to something like this:
	//
	// $scgi_port = 0;
	// $scgi_host = "unix:///tmp/rpc.socket";

	$XMLRPCMountPoint = "/RPC2";		// DO NOT DELETE THIS LINE!!! DO NOT COMMENT THIS LINE!!!

	$pathToExternals = array(
		"php" 	=> '',			// Something like /usr/bin/php. If empty, will be found in PATH.
		"curl"	=> '',			// Something like /usr/bin/curl. If empty, will be found in PATH.
		"gzip"	=> '',			// Something like /usr/bin/gzip. If empty, will be found in PATH.
		"id"	=> '',			// Something like /usr/bin/id. If empty, will be found in PATH.
		"stat"	=> '',			// Something like /usr/bin/stat. If empty, will be found in PATH.
	);

	$localhosts = array( 			// list of local interfaces
		"127.0.0.1", 
		"localhost",
	);

	$profilePath = '../share';		// Path to users profiles
	$profileMask = 0777;			// Mask for files and directory creation in users profiles. 
						// Both Webserver and rtorrent users must have read-write access to its.
						// For example, if Webserver and rtorrent users are in some group then value may be 0770

?>