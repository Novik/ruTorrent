<?php

// Resolve one flag for one plugin out of conf/plugins.ini: the plugin's own
// section wins, a [default] section answers for the rest, and a flag nobody
// set is true. That last rule is what keeps an upgrade quiet -- a plugins.ini
// written before a flag existed must behave as it did before, which is why
// enabledByDefault leaves a plugin enabled when it is absent.
//
// Shared because getplugins.php and initplugins.php both need it and neither
// can include the other: each does work at the top level.
function getFlag($permissions,$pname,$fname)
{
	$ret = true;
	if(array_key_exists($pname,$permissions) &&
		array_key_exists($fname,$permissions[$pname]))
		$ret = $permissions[$pname][$fname];
	else
	if(array_key_exists("default",$permissions) &&
		array_key_exists($fname,$permissions["default"]))
		$ret = $permissions["default"][$fname];
	return($ret);
}
