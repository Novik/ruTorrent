<?php

// Load the plugin's configuration settings from conf.php
eval(FileUtil::getPluginConf($plugin["name"]));

// A service is considered validly configured if set to a known provider
$isIPv4Valid = in_array($useWebsiteIPv4, ["yougetsignal", "portchecker"]);
$isIPv6Valid = in_array($useWebsiteIPv6, ["portchecker"]);

// The plugin should be active if at least one service is validly configured
if ($isIPv4Valid || $isIPv6Valid) {
	$theSettings->registerPlugin($plugin["name"], $pInfo["perms"]);
} else {
	// If neither is validly configured, disable the plugin
	// Show an error message only if the configuration is not explicitly set to 'false' for both
	// This distinguishes between "disabled" and "misconfigured"
	if ($useWebsiteIPv4 !== false || $useWebsiteIPv6 !== false) {
		$jResult .= "plugin.disable(); plugin.showError(theUILang.checkWebsiteNotFound);";
	} else {
		$jResult .= "plugin.disable();";
	}
}
