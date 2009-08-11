<?php
	// configuration parameters

	$isAutoStart = true;	// if false, then you need start plugin mannualy.
				// For do this, add to rtorrent conf file something like this:
				// schedule = trafic,10,00:15:00,"execute={sh,-c,php full_path_to_update.php& exit 0}"

	$updateInterval = 15;	// in minutes, 1-6,10,12,15,20,30 or 60

?>
