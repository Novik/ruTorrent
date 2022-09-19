<?php

@define('HISTORY_MAX_COUNT', 100);
@define('HISTORY_MAX_TRY', 3);
@define('WAIT_AFTER_LOADING', 0);

$minInterval = 2;	// in minutes

$feedsWithIncorrectTimes = array
(
	"iptorrents.",	// substring of hostname
	"torrentday.",
);

$rss_debug_enabled = false;		// true, false or 'dry-run'