<?php
require_once( 'stat.php' );
eval(getPluginConf('trafic'));

$val = "";
$storage = "global.csv";

if(isset($_REQUEST['tracker']))
{
	if($_REQUEST['tracker']=="none")
	{
		if(isset($_REQUEST['hash']))
			$storage = "torrents/".$_REQUEST['hash'].".csv";		
	}		
	else
		if($_REQUEST['tracker']!="global")
			$storage = "trackers/".$_REQUEST['tracker'].".csv";
}

if(isset($_REQUEST['mode']))
{
	$mode = $_REQUEST['mode'];
	if($mode=='clear') 
	{
		if(!$disableClearButton)
			@unlink(getSettingsPath().'/trafic/'.$storage);
		if($_REQUEST['tracker']!="none")
		{
			$mode='day';
			$storage = "global.csv";
		}
	}
	$st = new rStat($storage);
	if($mode=='day')
		$val = $st->getDay();
	else
	if($mode=='month')
		$val = $st->getMonth();
	else
	if($mode=='year')
		$val = $st->getYear();
}

cachedEcho($val,"application/json");
