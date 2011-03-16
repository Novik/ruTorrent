<?php
	// configuration parameters

	@define('MAX_CONSOLE_SIZE',25,true);

	$useExternal = false;			// Valid choices:
						// false - use internal realization (may be too slow for large files)
						// "transmissioncli" - use program transmissioncli (see http://www.transmissionbt.com/)
						// "createtorrent" - use program createtorrent (see http://www.createtorrent.com)
						// "mktorrent" - use program createtorrent (see http://mktorrent.sourceforge.net)
						// "buildtorrent" - use program buildtorrent (see http://claudiusmaximus.goto10.org/cm/torrent.html)
	$pathToCreatetorrent = '';		// Something like /bin/createtorrent, or /bin/transmissioncli. If empty, program will be founded in PATH.
	$pathToExternals["pgrep"] = '';		// Something like /usr/bin/pgrep. If empty, will be founded in PATH.

?>