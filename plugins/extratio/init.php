<?php

require_once( $rootPath.'/plugins/extratio/rules.php' );

$mngr = rRatioRulesList::load();
if($mngr->setHandlers())
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
else
	$jResult .= "plugin.disable(); log('retrackers: '+theUILang.pluginCantStart);";
?>