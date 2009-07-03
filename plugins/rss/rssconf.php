<?php

define('HISTORY_MAX_COUNT', 100);
define('HISTORY_MAX_TRY', 3);

$isAutoStart = true;	// if false, then you need start plugin mannualy.
			// For do this, add to rtorrent conf file something like this:
			// schedule = rss,0,00:30:00,"execute={sh,-c,php full_path_to_update.php& exit 0}"

$updateInterval = 30;	// in minutes

?>