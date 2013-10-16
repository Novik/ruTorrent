<?php

require_once( "task.php" );

$ret = array();

switch($_REQUEST['cmd'])
{
	case "kill":
	{
	        $ret = rTask::kill($_REQUEST['no']);
		break;
	}
	case "check":
	{
	        $ret = rTask::check($_REQUEST['no']);
		break;
	}
}
cachedEcho(json_encode($ret),"application/json");
