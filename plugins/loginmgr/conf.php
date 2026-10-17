<?php

// YggTorrent: the origin the account logs in at and downloads from, as
// "https://host" with an optional port and nothing else -- the address the
// tracker is opened at in a browser, e.g. 'https://www3.yggtorrent.example'.
// The tracker moves between domains, so none is trusted by default: until
// this is set, the account claims no URL, posts no credentials, and says so
// once in the log. Set it in plugins/loginmgr/conf.local.php or in
// conf/users/<user>/plugins/loginmgr/conf.php, which are read after this file.
$yggTorrentOrigin = '';
