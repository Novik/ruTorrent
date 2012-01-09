<?php

	// umask for Webserver user (for files & dirs creation mode)
	// ( don't forget to set correct umask in rtorrent.rc file )
	$datadir_umask = 0022;

	// set run mode: "webserver", "rtorrent"
	$datadir_runmode="rtorrent";

	// set "true" to enable debug output
	$datadir_debug_enabled = false;

?>