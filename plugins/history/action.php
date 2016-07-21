<?php
require_once( 'history.php' );

if(isset($_REQUEST['cmd']))
{
	$cmd = $_REQUEST['cmd'];
	switch($cmd)
	{
		case "set":
		{
			$up = rHistory::load();
			$up->set();
			cachedEcho($up->get(),"application/javascript");
			break;
		}
		case "get":
		{
			$up = rHistoryData::load();
			cachedEcho(safe_json_encode($up->get($_REQUEST['mark'])),"application/json");
  	                break;
		}
		case "delete":
		{
			$up = rHistoryData::load();
			$hashes = array();
			if(!isset($HTTP_RAW_POST_DATA))
				$HTTP_RAW_POST_DATA = file_get_contents("php://input");
			if(isset($HTTP_RAW_POST_DATA))
			{
				$vars = explode('&', $HTTP_RAW_POST_DATA);
				foreach($vars as $var)
				{
					$parts = explode("=",$var);
					$hashes[] = $parts[1];
  	                	}
				$up->delete( $hashes );
			}
			cachedEcho(safe_json_encode($up->get(0)),"application/json");
  	                break;
		}
	}
}
