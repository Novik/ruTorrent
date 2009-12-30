<?php
	// configuration parameters

	define('HTTP_USER_AGENT', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/3.0');
	define('HTTP_TIME_OUT', 30);		// in seconds
	define('HTTP_USE_GZIP', true);

	define('RPC_TIME_OUT', 15);		// in seconds

	$do_diagnostic = true;
	$log_file = '/tmp/errors.log';		// path to log file (comment or make empty to disable logging)
	define('LOG_RPC_CALLS', false);
	define('LOG_RPC_FAULTS', true);

	$uploads = './share/torrents';		// temp directory for uploaded torrents, without tail slash
	$saveUploadedTorrents = true;
	$settings = './share/settings';		// settings directory, without tail slash

	$scgi_port = 5000;
	$scgi_host = "127.0.0.1";

	$pathToPHP = '';			// Something like /bin/php. If empty, will be founded in PATH.
	$pathToCurl = '';			// Something like /bin/curl. If empty, will be founded in PATH.

	// For web->rtorrent link through unix domain socket 
	// (scgi_local in rtorrent conf file), change variables 
	// above to something like this:
	//
	// $scgi_port = 0;
	// $scgi_host = "unix:///tmp/rpc.socket";

	$rootPath = realpath(dirname(__FILE__)."/..");	// don't touch this line!
?>
