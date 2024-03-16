<?php
// configuration parameters

$useExternal = false;		// Valid choices:
				// false - use internal realization (may be too slow for large files)
				// "transmissioncli" - use program transmissioncli (see http://www.transmissionbt.com/)
				// "transmissioncreate" - use program transmissioncreate (see http://www.transmissionbt.com/)
				// "createtorrent" - use program createtorrent (see http://www.createtorrent.com)
				// "mktorrent" - use program mktorrent (see http://mktorrent.sourceforge.net)
				// "buildtorrent" - use program buildtorrent (see http://claudiusmaximus.goto10.org/cm/torrent.html)
				// "torrenttools" - use program torrenttools (see http://github.com/fbdtemme/torrenttools)
$pathToCreatetorrent = '';	// Something like /bin/createtorrent, or /bin/transmissioncli. If empty, program will be found in PATH.

$recentTrackersMaxCount = 15;

// Sets wether to use internal realization, when the external program doesn't support hybrid torrents.
// This option is slower, but adds compatibility for hybrid torrents, where the external program lacks support. 
// It's recommended to enable with option and use an external program such as "mktorrent" to accelerate torrent creation.
$useInternalHybrid = true;
