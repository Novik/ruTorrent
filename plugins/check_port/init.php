<?php

// Load the plugin's configuration settings from conf.php
eval(FileUtil::getPluginConf($plugin["name"]));
require_once(dirname(__FILE__) . "/providers.php");

// A service is considered valid if configured with a provider
// that supports the requested IP version.
$isIPv4Valid = isset($checkPortProviders[$useWebsiteIPv4]) &&
        !empty($checkPortProviders[$useWebsiteIPv4]["ipv4"]);

$isIPv6Valid = isset($checkPortProviders[$useWebsiteIPv6]) &&
        !empty($checkPortProviders[$useWebsiteIPv6]["ipv6"]);

if ($isIPv4Valid && isset($failoverProvidersIPv4)) {
        foreach ($failoverProvidersIPv4 as $provider) {
                if (
                        !isset($checkPortProviders[$provider]) ||
                        empty($checkPortProviders[$provider]["ipv4"])
                ) {
                        $isIPv4Valid = false;
                        break;
                }
        }
}

if ($isIPv6Valid && isset($failoverProvidersIPv6)) {
        foreach ($failoverProvidersIPv6 as $provider) {
                if (
                        !isset($checkPortProviders[$provider]) ||
                        empty($checkPortProviders[$provider]["ipv6"])
                ) {
                        $isIPv6Valid = false;
                        break;
                }
        }
}

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
