<?php
require_once( 'unpack.php' );

ignore_user_abort(true);
set_time_limit(0);
$ret = array();

if(isset($_REQUEST['cmd']))
{
	$cmd = $_REQUEST['cmd'];
	switch($cmd)
	{
		case "set":
		{
			$up = rUnpack::load();
			$up->set();
			CachedEcho::send($up->get(),"application/javascript");
			break;
		}
		case "unpack":
		{
			$up = rUnpack::load();
			$directory = isset($_REQUEST['dir']) ? FileUtil::requestPath($_REQUEST['dir']) : null;
			if(($directory !== null) || !isset($_REQUEST['dir']))
				$ret = $up->startTask( $_REQUEST['hash'], $directory, $_REQUEST['mode'], $_REQUEST['no'] );
			else
				$ret = array('no' => -1, 'pid' => 0, 'status' => 255, 'log' => array(), 'errors' => array('Invalid directory.'));
			break;
		}
	}
}

CachedEcho::send(JSON::safeEncode($ret),"application/json");
