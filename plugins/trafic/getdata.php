<?php
require_once( 'stat.php' );
eval(getPluginConf('trafic'));

$ret = null;
$storages = array( "global.csv" );

if(isset($_REQUEST['tracker']))
{
	if($_REQUEST['tracker']=="none")
	{
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		$tstorages = array();
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = explode('&', $HTTP_RAW_POST_DATA);
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				if($parts[0]=="hash")
					$tstorages[] = 'torrents/'.$parts[1].".csv";
			}
		}
		if( count($tstorages) )
			$storages = $tstorages;
	}
	else
		if($_REQUEST['tracker']!="global")
			$storages = array( "trackers/".$_REQUEST['tracker'].".csv" );
}

function sum($e1, $e2)
{
	return($e1+$e2);
}

if(isset($_REQUEST['mode']))
{
	$mode = $_REQUEST['mode'];
	if($mode=='clear')
	{
		if(!$disableClearButton)
			foreach( $storages as $storage )
				@unlink(getSettingsPath().'/trafic/'.$storage);
		if($_REQUEST['tracker']!="none")
			$storages = array( "global.csv" );
		$mode = 'day';
	}
	$ret = array();
	foreach( $storages as $storage )
	{
		$st = new rStat($storage);
		if($mode=='day')
			$val = $st->getDay();
		else
		if($mode=='month')
			$val = $st->getMonth();
		else
		if($mode=='year')
			$val = $st->getYear();
		if(empty($ret))
			$ret = $val;
		else
		{
			$ret["up"] = array_map("sum", $val["up"], $ret["up"]);
			$ret["down"] = array_map("sum", $val["down"], $ret["down"]);
			$ret["labels"] = array_map("max", $val["labels"], $ret["labels"]);
		}
	}
}

cachedEcho(safe_json_encode($ret),"application/json");
