<?php

require_once( "xmlrpc.php" );
require_once( 'settings.php' );

$theSettings = rTorrentSettings::get();
$jResult = "";

if(!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
if(isset($HTTP_RAW_POST_DATA))
{
	$vars = explode('&', $HTTP_RAW_POST_DATA);
	foreach($vars as $var)
	{
		$parts = explode("=",$var);
		if($parts[0]=="plg")
		{
			$php = "../plugins/".$parts[1]."/done.php";
			if(is_file($php) && is_readable($php))
				require_once($php);
			$theSettings->unregisterPlugin($parts[1]);
			$jResult.="thePlugins.get('".$parts[1]."').remove();";
		}
	}
	$theSettings->store();
}

echo $jResult;

?>