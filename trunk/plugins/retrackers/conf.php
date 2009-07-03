<?php
	// configuration parameters

	$isAutoStart = true;	// if false, then you need start plugin mannualy.
				// For do this, add to rtorrent conf file something like this:
				// system.method.set_key = event.download.inserted_new,add_trackers,"branch=$not=$d.get_custom3=,\"execute={'.$theSettings->path.'plugins/retrackers/run.sh'.','.$pathToPHP.',$d.get_hash=}\" ; d.set_custom3="

?>
