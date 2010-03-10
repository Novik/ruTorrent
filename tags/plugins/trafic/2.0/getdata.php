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
				@unlink("stats/".$tracker);
				$mode='day';
				$tracker = "global.csv";
			}
			$st = new rStat("stats/".$tracker);
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
	$content = '<?xml version="1.0" encoding="UTF-8"?><data>'.$val.'</data>';
	header("Content-Length: ".strlen($content));
	header("Content-Type: text/xml; charset=UTF-8");
	echo $content;
?>
