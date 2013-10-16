<?php
require_once( '../plugins/ratio/ratio.php');

$rat = rRatio::load();
if(!$rat->obtain())
	$jResult.="plugin.disable(); noty('ratio: '+theUILang.pluginCantStart,'error');";
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
$jResult.=$rat->get();
