<?php

require_once(dirname(__FILE__).'/telegram.php');

$telegram = rTelegram::load();
if($telegram->setHandlers())
{
	$jResult .= $telegram->get();
	$theSettings->registerPlugin($plugin['name'], $pInfo['perms']);
}
else
{
	$jResult .= "plugin.disable(); noty('telegram: '+theUILang.pluginCantStart,'error');";
}
