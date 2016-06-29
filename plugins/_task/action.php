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
	case "list":
	{
		set_time_limit(0);
		$ret = rTaskManager::obtain();
		break;		
	}
	case "remove":
	{
		$list = array();
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");	
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = explode('&', $HTTP_RAW_POST_DATA);
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				if($parts[0]=="no")
				{
					$value = trim(rawurldecode($parts[1]));
					if(strlen($value) && intval($value))
						$list[] = $value;
				}
			}
		}			
		$ret = rTaskManager::remove($list);
		break;		
	}	
}
cachedEcho(safe_json_encode($ret),"application/json");
