<?php
// configuration parameters

define('MAX_THROTTLE', 10);

$isAutoStart = true;	// if false, then you need start plugin mannualy.
			// You must add to rtorrent conf file lines
			// throttle_up = thr_0,[up_limit_for_channel0]
			// throttle_down = thr_0,[down_limit_for_channel0]
			// ...
			// throttle_up = thr_[MAX_THROTTLE],up_limit_for_channelMAX_THROTTLE
			// throttle_down = thr_[MAX_THROTTLE],down_limit_for_channelMAX_THROTTLE

?>
