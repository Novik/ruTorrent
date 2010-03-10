<?php

require_once( "xmlrpc.php" );
require_once( 'settings.php' );

$theSettings = rTorrentSettings::load();
$jResult = "";

if(!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
if(isset($HTTP_RAW_POST_DATA))
{
	$vars = split('&', $HTTP_RAW_POST_DATA);
	foreach($vars as $var)
	{
		$parts = split("=",$var);
		if($parts[0]=="plg")
		{
			$php = "../plugins/".$parts[1]."/done.php";
			if(is_file($php) && is_readable($php))
				require_once($php);
			$jResult.="thePlugins.get('".$parts[1]."').remove();";
		}
	}
}

echo $jResult;

?>