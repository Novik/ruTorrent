<?php
	$path = dirname(realpath($argv[0]));
	if(chdir($path))
	{
		if( count( $argv ) > 1 )
			$_SERVER['REMOTE_USER'] = $argv[1];
		require_once( './accounts.php' );

		$acc = accountManager::load();
		$acc->checkAuto();
	}
