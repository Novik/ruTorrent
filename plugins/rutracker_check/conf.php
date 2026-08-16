<?php

$updateInterval 	= 60;	// in minutes, zero for disable
$ignoreLabels 	= ['tv-sonarr', 'radarr'];	// list of labels to ignore
// ??=, not =: every evaluator of this file -- the CLI entry points
// (update.php, batch_check.php) and the web path (php/getplugins.php) --
// runs it in global scope after conf/config.php has already been loaded,
// so a plain assignment silently discards a value the administrator set
// there. The default applies only when nothing else has set the variable.
$rutrackerCheckDebug ??= false;	// write diagnostic messages to the configured ruTorrent log
