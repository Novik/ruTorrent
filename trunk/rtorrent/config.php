<?php
    // configuration parameters

    define('HTTP_USER_AGENT', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/3.0');
    define('HTTP_TIME_OUT', 30);
    define('HTTP_USE_GZIP', true);

    define('RPC_TIME_OUT', 15);		// in seconds
    define('DO_DIAGNOSTIC', true);	

    $uploads = './torrents';		// temp directory for uploaded torrents, without tail slash
    $settings = './settings';		// settings directory, without tail slash

    $scgi_port = 1886;
    $scgi_host = "127.0.0.1";

    $pathToPHP = '';			// Something like /bin/php. If empty, will be founded in PATH.
    $pathToCurl = '';			// Something like /bin/curl. If empty, will be founded in PATH.

    // For web->rtorrent link through unix domain socket 
    // (scgi_local in rtorrent conf file), change variables 
    // above to something like this:
    //
    // $scgi_port = 0;
    // $scgi_host = "unix:///tmp/rpc.socket";
?>
