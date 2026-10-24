<?php
	$path = dirname(realpath($argv[0]));
	if(chdir($path))
	{
		// New hooks retain the user slot even when empty, then append source paths.
		if(count($argv) > 14)
		{
			if(strncmp((string)$argv[13], 'u:', 2) !== 0) return;
			$_SERVER['REMOTE_USER'] = substr($argv[13], 2);
		}
		elseif(count($argv) > 13)
			$_SERVER['REMOTE_USER'] = $argv[13];
		require_once( './history.php' );
		$hst = rHistoryData::load();
		$mgr = rHistory::load();
		$action = intval($argv[1]);
		$actions = array
		(
			1 => 'addition',
			2 => 'finish',
			3 => 'deletion',
		);
		$tracker = '';
		$pos = strpos( $argv[10], '#' );
		if($pos!==false)
			$tracker = substr( $argv[10], 0, $pos );
		$data = array
		(
			"action"=>$action,
			"name"=>$argv[2],
			"size"=>floatval($argv[3]),
			"downloaded"=>floatval($argv[4]),
			"uploaded"=>floatval($argv[5]),
			"ratio"=>floatval($argv[6]),
			"creation"=>intval($argv[7]),
			"added"=>intval($argv[8]),
			"finished"=>intval($argv[9]),
			"tracker"=>$tracker,
			"label"=>rawurldecode($argv[11]),
		);
		// A magnet placeholder is not the user's own event: neither record it
		// nor notify about it. The notification is skipped for the same reason
		// the row is -- a push about a torrent named after its own hash tells
		// the user nothing they did.
		if(!rHistoryData::isMagnetPlaceholder($data["name"]))
		{
			if(isset($argv[14]))
				$data = rHistoryData::captureTorrentName($data, $argv[14],
					isset($argv[15], $argv[16]) ? array($argv[15], $argv[16]) : false);
			if($mgr->log[$actions[$action]])
			{
				$hst->add( $data, $mgr->log["limit"] );
			}
			if($mgr->log['pushbullet_enabled'] && $mgr->log['pushbullet_'.$actions[$action]] && !$argv[12])
			{
				$mgr->pushBulletNotify( $data );
			}
		}
	}
