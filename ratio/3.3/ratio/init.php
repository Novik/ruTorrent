<?php
require_once( '../plugins/ratio/ratio.php');

$rat = rRatio::load();
if(!$rat->obtain())
	$jResult.="plugin.disable(); log('ratio: '+theUILang.pluginCantStart);";
else
	$theSettings->registerPlugin("ratio");
$jResult.=$rat->get();
?>