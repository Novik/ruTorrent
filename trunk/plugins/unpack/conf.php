<?php

@define('USE_UNZIP', true, true);
@define('USE_UNRAR', true, true);

$pathToExternals['unzip'] = '';		// Something like /usr/bin/unzip. If empty, will be found in PATH.
$pathToExternals['unrar'] = '/usr/local/src/rar/unrar';		// Something like /usr/bin/unrar. If empty, will be found in PATH.

$cleanupAutoTasks = false;		// Remove autounpack tasks parameters after finish, otherwise will be shown in the 'Tasks' tab
