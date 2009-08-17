<?php
require_once( 'plugins/ratio/ratio.php');

$rat = rRatio::load();
if(($theSettings->iVersion<0x804) || !$rat->flush() || ($isAutoStart && !$rat->correct()))
	$jResult.="utWebUI.showRatioError('WUILang.ratioUnsupported'); utWebUI.ratioSupported = false; ";
else
{
	$jResult.="utWebUI.trtColumns.push({'text' : 'Ratio Group','width' : '60px','type' : TYPE_STRING}); ";
	$theSettings->registerPlugin("ratio");
}
$jResult.=$rat->get();

?>