<?php
	// configuration parameters

	@define('SCH_DEF_UL', 327625);	// default UL (KB/s). 
	@define('SCH_DEF_DL', 327625);	// default DL (KB/s)
	// Can't be greater then 327625 due to limitation in libtorrent ResourceManager::set_max_upload_unchoked function.

	$updateInterval = 60;	// in minutes, 1-6,10,12,15,20,30 or 60
