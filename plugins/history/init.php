<?php

require_once( 'history.php' );

$mngr = rHistory::load();
if($mngr->setHandlers())
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	$jResult .= $mngr->get();
}
else
	$jResult .= "plugin.disable(); noty('history: '+theUILang.pluginCantStart,'error');";
