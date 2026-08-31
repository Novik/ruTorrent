<?php
/**
 * A filtered XMLRPC endpoint for rtorrent.
 *
 * Point your web server's XMLRPC location at this file instead of passing the
 * request straight to rtorrent's SCGI socket. The usual recipe —
 *
 *     location /RPC2 { include scgi_params; scgi_pass 127.0.0.1:5000; }
 *
 * — publishes rtorrent's whole command surface, execute.capture included, to
 * anyone who gets past the web server's authentication. rtorrent runs commands
 * as its own user, so that is a shell.
 *
 * This applies the same filtering ruTorrent's httprpc proxy applies, from the
 * same policy in conf/xmlrpc_proxy.php, and forwards what survives.
 *
 *     location = /RPC2 {
 *         # authenticate the caller however you already do
 *         include fastcgi_params;
 *         fastcgi_param SCRIPT_FILENAME /path/to/rutorrent/rpc2.php;
 *         fastcgi_param RUTORRENT_XMLRPC_ENDPOINT on;
 *         fastcgi_pass unix:/run/php/php-fpm.sock;
 *     }
 *
 * RUTORRENT_XMLRPC_ENDPOINT is required, and without it this file does
 * nothing. It is what stops the endpoint also being reachable at its own URL
 * under the ruTorrent docroot, through whatever authentication the rest of
 * ruTorrent uses — which would make tightening the XMLRPC credential
 * pointless. Set it in the one location block you meant to expose, and the
 * endpoint has exactly one door.
 *
 * It does not authenticate. That is the web server's job here, exactly as it
 * is for ruTorrent itself.
 */

// Not reachable except from the location block the operator wrote for it.
if(!isset($_SERVER['RUTORRENT_XMLRPC_ENDPOINT']) ||
	($_SERVER['RUTORRENT_XMLRPC_ENDPOINT'] !== 'on'))
{
	header('HTTP/1.1 404 Not Found');
	exit;
}

require_once(dirname(__FILE__).'/conf/config.php');
require_once(dirname(__FILE__).'/php/xmlrpc_proxy.php');

$policyFile = dirname(__FILE__).'/conf/xmlrpc_proxy.php';
if(is_file($policyFile) && is_readable($policyFile))
	require_once($policyFile);

$mode = isset($XMLRPCProxy) ? $XMLRPCProxy : 'sanitize';
$logging = isset($XMLRPCProxyLog) ? $XMLRPCProxyLog : true;
$safeParams = isset($XMLRPCProxySafeParams) ? $XMLRPCProxySafeParams : array();
$allowLocalPaths = isset($XMLRPCProxyAllowLocalPaths) ? $XMLRPCProxyAllowLocalPaths : false;
$allowRootDirectory = isset($XMLRPCProxyAllowRootDirectory) ? $XMLRPCProxyAllowRootDirectory : false;

/**
 * Resolve as much of a path as exists, so a symlink cannot be the difference
 * between where a caller says a download goes and where it lands. The path
 * usually does not exist yet, so realpath() alone answers nothing: walk up to
 * the deepest ancestor that does exist, resolve that, and re-attach the rest.
 */
function rpc2_resolve_path($path)
{
	$real = @realpath($path);
	if($real !== false)
		return $real;

	$parts = explode('/', trim($path, '/'));
	$tail = array();
	while(count($parts) > 0)
	{
		array_unshift($tail, array_pop($parts));
		$base = '/'.implode('/', $parts);
		$real = @realpath(($base === '') ? '/' : $base);
		if($real !== false)
			return rtrim($real, '/').'/'.implode('/', $tail);
	}
	return '';
}

/**
 * Written here rather than through FileUtil so that this file needs nothing
 * but the proxy and the configuration — the point of the endpoint is that it
 * does one thing.
 */
function rpc2_log($message)
{
	global $logging, $log_file;
	if(!$logging)
		return;
	$line = date('d.m.Y H:i:s').' rpc2: '.str_replace(array("\r", "\n"), ' ', $message)."\n";
	if(!empty($log_file) && (@file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX) !== false))
		return;
	error_log('rpc2: '.$line);
}

function rpc2_fault($status, $message)
{
	header('HTTP/1.1 '.$status);
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"
		.'<methodResponse><fault><value><struct>'
		.'<member><name>faultCode</name><value><i4>-501</i4></value></member>'
		.'<member><name>faultString</name><value><string>'
		.htmlspecialchars($message, ENT_NOQUOTES, 'UTF-8')
		.'</string></value></member>'
		.'</struct></value></fault></methodResponse>';
	exit;
}

/**
 * One SCGI request to rtorrent, with the trust the policy decided on. Same
 * wire format ruTorrent's rXMLRPCRequest::send() uses; kept here so the
 * endpoint does not need ruTorrent's settings bootstrap to make a call.
 */
function rpc2_send($payload, $trusted)
{
	global $scgi_host, $scgi_port, $rpcTimeOut;

	$timeout = empty($rpcTimeOut) ? 30 : $rpcTimeOut;
	$socket = @fsockopen($scgi_host, $scgi_port, $errno, $errstr, $timeout);
	if(!$socket)
	{
		rpc2_log('cannot reach rtorrent at '.$scgi_host.': '.$errstr);
		return null;
	}

	$header = "CONTENT_LENGTH\x00".strlen($payload)."\x00CONTENT_TYPE\x00text/xml\x00"
		."SCGI\x001\x00UNTRUSTED_CONNECTION\x00".($trusted ? '0' : '1')."\x00";
	$request = strlen($header).':'.$header.','.$payload;

	stream_set_timeout($socket, $timeout);
	@fwrite($socket, $request, strlen($request));

	$response = '';
	while(!feof($socket))
	{
		$chunk = fread($socket, 65536);
		if($chunk === false)
			break;
		$response .= $chunk;
	}
	fclose($socket);

	// rtorrent answers with its own headers; the body is what the client asked for.
	$split = strpos($response, "\r\n\r\n");
	return ($split === false) ? $response : substr($response, $split + 4);
}

if(!isset($_SERVER['REQUEST_METHOD']) || ($_SERVER['REQUEST_METHOD'] !== 'POST'))
{
	header('HTTP/1.1 405 Method Not Allowed');
	header('Allow: POST');
	exit;
}

// A caller may name the directory a download is written into, so the endpoint
// has to know what is out of bounds before it answers anything. $topDirectory
// is ruTorrent's own answer and correctDirectory() already holds the panel to
// it; stock ruTorrent ships it as "/", which is not a boundary. Rather than
// apply a check that confines nothing, refuse to serve until somebody has said
// which it is.
$topDirectory = isset($topDirectory) ? trim($topDirectory) : '';
if((($topDirectory === '') || ($topDirectory === '/')) && !$allowRootDirectory)
{
	rpc2_log('refusing to serve: $topDirectory is "'.$topDirectory.'"'
		.' and $XMLRPCProxyAllowRootDirectory is false');
	rpc2_fault('503 Service Unavailable',
		'This XMLRPC endpoint is not configured: set $topDirectory in conf/config.php '
		.'to the directory downloads may be written under, or set '
		.'$XMLRPCProxyAllowRootDirectory = true in conf/xmlrpc_proxy.php to allow any path.');
}

$raw = file_get_contents('php://input');
if($raw === false)
{
	rpc2_log('could not read request body');
	rpc2_fault('400 Bad Request', 'Could not read XMLRPC request.');
}
if($raw === '')
{
	rpc2_log('empty request body');
	rpc2_fault('400 Bad Request', 'Empty XMLRPC request.');
}

$decision = XMLRPCProxy::decide($raw, $mode, $safeParams, $allowLocalPaths, array(
	'directory' => array(
		'root'    => ($topDirectory === '') ? '/' : $topDirectory,
		'resolve' => 'rpc2_resolve_path',
	),
));
foreach($decision['log'] as $line)
	rpc2_log($line);

if($decision['action'] !== 'send')
	rpc2_fault('403 Forbidden', XMLRPCProxy::rejectionMessage($decision['method'] ?? null));

$result = rpc2_send($decision['payload'], $decision['trusted']);
if($result === null)
	rpc2_fault('502 Bad Gateway', 'Could not reach rTorrent over XMLRPC. Is rTorrent running?');

header('Content-Type: text/xml');
header('Content-Length: '.strlen($result));
echo $result;
