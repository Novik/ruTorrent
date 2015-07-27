<?php
if( !chdir( dirname( __FILE__) ) )
	exit();

if( count( $argv ) > 2 )
	$_SERVER['REMOTE_USER'] = $argv[2];

require_once( dirname(__FILE__)."/../../php/settings.php");
require_once( "./XMPPHP/XMPP.php" );
require_once( "xmpp.php" );

$name = $argv[1];
$at = rXmpp::load();
if ( $at->message !== '' && isset($at->jabberServer) && isset($at->jabberLogin) && isset($at->jabberPasswd) && isset($at->jabberFor))
{
    $useEncryption = true;
    $jabberHost = $at->jabberServer;
    $jabberPort = 5222;
    if ($at->advancedSettings)
    {
	$useEncryption = $at->useEncryption;
	if ($at->jabberHost)
	{
	    $jabberHost = $at->jabberHost;
	}

	if ($at->jabberPort)
	{
	    $jabberPort = $at->jabberPort;
	}
    }
    $conn = new XMPPHP_XMPP($jabberHost, $jabberPort ? $jabberPort : 5222, $at->jabberLogin, $at->jabberPasswd, 'xmpphp', $at->jabberServer);
    if ($useEncryption)
    {
	$conn->useEncryption(true);
	$opts = array(
	    'ssl' => array(
	    'verify_peer' => true,
	    'allow_self_signed' => true
	));
	if ($at->jabberHost != $at->jabberServer)
	{
	    $opts['ssl']['CN_match'] = $at->jabberServer;
	}
	$conn->set_context($opts);
    }
    else
    {
	$conn->useEncryption(false);
    }
    try
    {
	$message = str_replace( "{TORRENT}", $name, $at->message );
	$conn->connect();
	$conn->processUntil('session_start');
	$conn->presence();
	$conn->message($at->jabberFor, $message);
	$conn->disconnect();
    }
    catch(XMPPHP_Exception $e)
    {
	die($e->getMessage());
    }
}
