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
			$up = new rUnpack();
			$up->set();
			cachedEcho($up->get(),"application/javascript");
			break;
		}
		case "start":
		{
			if(isset($_REQUEST['hash']) && isset($_REQUEST['dir']))
			{
		        	$up = rUnpack::load();
				$ret = $up->startTask( $_REQUEST['hash'], rawurldecode($_REQUEST['dir']), 
				        isset($_REQUEST['mode']) ? $_REQUEST['mode'] : null, 
					isset($_REQUEST['no']) ? $_REQUEST['no'] : null,
					isset($_REQUEST['all']) );
			}
			if(empty($ret))
				$ret = array( "no"=>-1 );
  	                break;

		}
		case "check":
		{
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
						$chk = rUnpack::checkTask( trim($parts[1]) );
						if($chk)
							$ret[] = $chk;
					}
				}
			}
			break;
		}
	}
}

cachedEcho(json_encode($ret),"application/json");
