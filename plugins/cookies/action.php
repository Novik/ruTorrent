<?php
require_once( 'cookies.php' );

$cmd = '';
if(isset($_REQUEST['mode']))
	$cmd = $_REQUEST['mode'];

switch($cmd)
{
	case 'info':
	{
		$cookies = rCookies::load();
		if(isset($_REQUEST['host']))
			cachedEcho(safe_json_encode($cookies->getCookiesForHost($_REQUEST['host'])),"application/json");
		else
			cachedEcho(safe_json_encode($cookies->getInfo()),"application/json");
	}
	case 'add':
	{
		$cookies = rCookies::load();
		if(isset($_REQUEST['host']))
			$cookies->add($_REQUEST['host'],rawurldecode($_REQUEST['cookies']));
        	cachedEcho(safe_json_encode($cookies->getInfo()),"application/json");
	}
	default:
	{
		$cookies = new rCookies();
		$cookies->set();
		cachedEcho($cookies->get(),"application/javascript");
	}
}
