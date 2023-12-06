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
			CachedEcho::send(JSON::safeEncode($cookies->getCookiesForHost($_REQUEST['host'])),"application/json");
		else
			CachedEcho::send(JSON::safeEncode($cookies->getInfo()),"application/json");
	}
	case 'add':
	{
		$cookies = rCookies::load();
		if(isset($_REQUEST['host']))
			$cookies->add($_REQUEST['host'],rawurldecode($_REQUEST['cookies']));
        	CachedEcho::send(JSON::safeEncode($cookies->getInfo()),"application/json");
	}
	default:
	{
		$cookies = new rCookies();
		$cookies->set();
		CachedEcho::send($cookies->get(),"application/javascript");
	}
}
