<?php
require_once( '../plugins/ratio/ratio.php');

$rat = rRatio::load();
if(($theSettings->iVersion<0x804) || !$rat->obtain())
	$jResult.="plugin.disable();";
else
	$theSettings->registerPlugin("ratio");
$jResult.=$rat->get();
?>
