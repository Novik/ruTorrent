<?php
require_once( dirname(__FILE__)."/../../php/settings.php" );

$ret = 0;
$port = rTorrentSettings::get()->port;
$bind = rTorrentSettings::get()->bind;
$url = 'http://www.canyouseeme.org';
$fields = array(
    'port' => $port,
    'submit' => 'Check+Your+Port',
);
$fields_string = '';
foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
rtrim($fields_string, '&');
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_INTERFACE, $bind);
curl_setopt($ch, CURLOPT_POST, count($fields));
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

if ($result)
{
	if(strpos($result,">Error:<")!==false)
		$ret = 1;
	else
	if(strpos($result,">Success:<")!==false)
		$ret = 2;
}

cachedEcho('{ "port": '.$port.', "status": '.$ret.' }',"application/json");
