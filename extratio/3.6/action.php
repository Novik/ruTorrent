<?php
require_once('rules.php');

$cmd = '';
if(isset($_REQUEST['mode']))
	$cmd = $_REQUEST['mode'];
$mngr = rRatioRulesList::load();
$val = null;

switch($cmd)
{
	case "setrules":
	{
		$mngr->set();
		break;
	}
	case "checklabels":
	{
		$hash = array();
		if (!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = explode('&', $HTTP_RAW_POST_DATA);
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				switch($parts[0])
				{
					case "hash":
					{
						$hash[] = $parts[1];
						break;
					}
				}
			}
		}
		$mngr->checkLabels($hash);
		$val = array();
		break;
	}
}

if(is_null($val))
	$val = $mngr->getContents();

cachedEcho(json_encode($val),"application/json",true);