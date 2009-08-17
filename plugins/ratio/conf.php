<?php
// configuration parameters

define('MAX_RATIO', 8);

$isAutoStart = true;	// if false, then you need start plugin mannualy.
			// You must add to rtorrent conf file lines
			// group.insert_persistent_view = rat_0
			// group.rat_0.ratio.enable=
			// group.rat_0.ratio.min.set=[min_limit_for_ratio0]
			// group.rat_0.ratio.max.set=[max_limit_for_ratio0]
			// group.rat_0.ratio.upload.set=[upload_limit_for_ratio0]
 			// system.method.set = group.rat_0.ratio.command, d.close=
			// ...
			// group.insert_persistent_view = rat_MAX_RATIO
			// group.rat_0.ratio.enable=
			// group.rat_0.ratio.min.set=[min_limit_for_ratioMAX_RATIO]
			// group.rat_0.ratio.max.set=[max_limit_for_ratioMAX_RATIO]
			// group.rat_0.ratio.upload.set=[upload_limit_for_ratioMAX_RATIO]
 			// system.method.set = group.rat_MAX_RATIO.ratio.command, d.close=
?>
