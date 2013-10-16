<?php
	$path = dirname(realpath($argv[0]));
	if(chdir($path))
	{
		if( count( $argv ) > 12 )
			$_SERVER['REMOTE_USER'] = $argv[12];
		require_once( './history.php' );
		$hst = rHistoryData::load();
		$mgr = rHistory::load();
		$tracker = '';
		$pos = strpos( $argv[10], '#' );
		if($pos!==false)
			$tracker = substr( $argv[10], 0, $pos );
		$hst->add( array(
			"action"=>intval($argv[1]),
			"name"=>$argv[2], "size"=>floatval($argv[3]), "downloaded"=>floatval($argv[4]),
			"uploaded"=>floatval($argv[5]), "ratio"=>floatval($argv[6]), "creation"=>intval($argv[7]),
			"added"=>intval($argv[8]), "finished"=>intval($argv[9]), "tracker"=>$tracker,
			"label"=>rawurldecode($argv[11]),
			), $mgr->log["limit"] );
	}
