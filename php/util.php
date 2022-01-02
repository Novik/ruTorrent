<?php

// Include our base configuration file
$rootPath = realpath(dirname(__FILE__)."/..");
require_once( $rootPath.'/conf/config.php' );

// Automatically include only the used utility classes
spl_autoload_register(function ($class) {
    require_once 'utility/'. strtolower($class). '.php';
});

Utility::fix_magic_quotes_gpc();

@ini_set('precision',16);
@define('XMLRPC_MAX_I4', 2147483647);
@define('XMLRPC_MIN_I4', ~XMLRPC_MAX_I4);
@define('XMLRPC_MIN_I8', -9.999999999999999E+15);
@define('XMLRPC_MAX_I8', 9.999999999999999E+15);

if(function_exists('ini_set'))
{
	ini_set('display_errors',false);
	ini_set('log_errors',true);
}

if(!isset($_SERVER['REMOTE_USER']))
{
	if(isset($_SERVER['PHP_AUTH_USER']))
		$_SERVER['REMOTE_USER'] = $_SERVER['PHP_AUTH_USER'];
	else
	if(isset($_SERVER['REDIRECT_REMOTE_USER']))
		$_SERVER['REMOTE_USER'] = $_SERVER['REDIRECT_REMOTE_USER'];
}

FileUtil::getProfilePath();	// for creation profile, if it is absent
$conf = FileUtil::getConfFile('config.php');
if($conf)
	require_once($conf);

if(!isset($profileMask))
	$profileMask = 0777;
if(!isset($locale))	
	$locale = "UTF8";
setlocale(LC_CTYPE, $locale, "UTF-8", "en_US.UTF-8", "en_US.UTF8");
setlocale(LC_COLLATE, $locale, "UTF-8", "en_US.UTF-8", "en_US.UTF8");

Utility::disableUnsupportedMethods();
Utility::makeCSRFCheck();
