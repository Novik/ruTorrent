<?php

// Include our base configuration file
$rootPath = realpath(dirname(__FILE__)."/..");
require_once( $rootPath.'/conf/config.php' );

// Automatically include only the used utility classes
spl_autoload_register(function ($class) 
{
	// Remove namespaces from the classname string
	// Important for compatibility with 3rd party plugins
	$arr = explode('\\',$class);
	$class = end($arr);
	
	// Suppress include warnings if the user disables do_diagnostic
	// For compatibility with 3rd party plugins which use autoloaders
	global $do_diagnostic;
	if($do_diagnostic)
		include_once 'utility/'. strtolower($class). '.php';
	else
		@include_once 'utility/'. strtolower($class). '.php';
});

// Fixes quotations if php verison is less than 5.4
// Only include these methods if applicable
if(version_compare(phpversion(), '5.4', '<'))
	require_once ( 'utility/phpversionfix.php');

// Only allow "POST" or "GET" request methods
// Exit script and send 405 if anther method is tried
Requests::disableUnsupportedMethods();

// For "Cross-Site Request Forgery" checks if enabled
Requests::makeCSRFCheck();

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
