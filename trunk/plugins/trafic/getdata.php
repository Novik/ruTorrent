<?php
	require_once( 'stat.php' );
	$val = "";
	if(isset($_REQUEST['tracker']))
	{
		if($_REQUEST['tracker']!="global")
			$tracker = "trackers/".$_REQUEST['tracker'].".csv";
		else
	                $tracker = "global.csv";
		if(isset($_REQUEST['mode']))
		{
			$mode = $_REQUEST['mode'];
                        if($mode=='clear')
			{
				@unlink(getSettingsPath().'/trafic/'.$tracker);
				$mode='day';
				$tracker = "global.csv";
			}
			$st = new rStat($tracker);
			if($mode=='day')
				$val = $st->getDay();
			else
			if($mode=='month')
				$val = $st->getMonth();
			else
			if($mode=='year')
				$val = $st->getYear();
		}
	}
	if(!ini_get("zlib.output_compression"))
		header("Content-Length: ".strlen($val));
	header("Content-Type: application/json; charset=UTF-8");
	echo $val;
?>
