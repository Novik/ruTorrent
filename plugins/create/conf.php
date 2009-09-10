<?php
	// configuration parameters

	$useExternal = false;			// Valid choices:
						// false - use internal realization (may be too slow for large files)
						// "transmissioncli" - use program transmissioncli (see http://www.transmissionbt.com/)
						// "createtorrent" - use program createtorrent (see http://www.createtorrent.com)
	$pathToCreatetorrent = '';		// Something like /bin/createtorrent, or /bin/transmissioncli. If empty, program will be founded in PATH.

?>
