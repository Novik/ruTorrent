<?php
/**
 * ruTorrent prerequisites & install checker.
 *
 * A standalone diagnostic that verifies your environment meets ruTorrent's
 * requirements and flags common install/config mistakes. It is NOT part of
 * ruTorrent's runtime and is never invoked automatically.
 *
 * It runs from the command line only, and refuses to run under any web SAPI
 * (so it can never leak configuration/paths over HTTP even if left in a
 * web-served directory):
 *
 *   php env_check.php
 *
 * It exits 0 when every REQUIRED check passes and 1 otherwise. REQUIRED
 * failures mean ruTorrent will not work; RECOMMENDED failures only limit
 * specific features or plugins.
 *
 * Everything here is self-contained (the only ruTorrent file it reads is
 * conf/config.php, to locate rtorrent and validate settings), so the checker
 * still runs when the rest of ruTorrent is broken by a missing prerequisite.
 */

/**
 * Pure decision logic, separated so it can be unit-tested without touching the
 * live environment (see tests/php/RequirementsTest.php). Nothing here reads the
 * filesystem, the network, or PHP's loaded extensions.
 */
class Requirements
{
	const MIN_PHP = '7.4.0'; // arrow functions (fn() =>) are used in the codebase

	/** rtorrent support policy: 0.9.8 baseline + 0.16.x series; anything else may or may not work. */
	public static function rtorrentSupport($ver)
	{
		$ver = (string)$ver;
		if (preg_match('/^0\.9\.8(\D|$)/', $ver)) return array(true, 'supported (0.9.8 baseline)');
		if (preg_match('/^0\.16\./', $ver))       return array(true, 'supported (0.16.x series)');
		return array(false, 'not an explicitly supported version (0.9.8 or 0.16.x) -- may or may not work');
	}

	public static function phpMeetsMinimum($current, $min = self::MIN_PHP)
	{
		return version_compare((string)$current, $min, '>=');
	}

	public static function isUnixSocket($host)
	{
		return strncmp((string)$host, 'unix://', 7) === 0;
	}

	public static function unixSocketPath($host)
	{
		return self::isUnixSocket($host) ? substr((string)$host, 7) : null;
	}

	public static function scgiLabel($host, $port)
	{
		return self::isUnixSocket($host) ? (string)$host : $host . ':' . $port;
	}

	/** Is the SCGI target configured coherently (a socket path, or host + a valid TCP port)? */
	public static function scgiConfigured($host, $port)
	{
		if ($host === null || $host === '') return false;
		if (self::isUnixSocket($host)) return strlen(self::unixSocketPath($host)) > 0;
		return ((int)$port > 0) && ((int)$port <= 65535);
	}

	/** The config file itself warns this must never be empty/commented. */
	public static function xmlrpcMountPointValid($mp)
	{
		return is_string($mp) && strlen($mp) > 0 && $mp[0] === '/';
	}

	public static function looksAbsolute($path)
	{
		if (!is_string($path) || $path === '') return false;
		return $path[0] === '/' || (bool)preg_match('#^[A-Za-z]:[\\\\/]#', $path);
	}
}

// When included by a test we only want the class above; skip the live probing.
if (defined('RUTORRENT_REQUIREMENTS_LIB')) return;

// Command line only. PHP_SAPI is fixed by how PHP was invoked (the cli binary
// vs a web module) and cannot be influenced by an HTTP request, so this can
// never run -- and never leak anything -- over the web, including the built-in
// `php -S` server (which reports "cli-server", not "cli").
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("env_check.php can only be run from the command line: php env_check.php\n");
}

// -------------------------------------------------------------------------
// Live environment probing + rendering (not unit-tested; exercises the host).
// -------------------------------------------------------------------------

$results = array(); // each: [section, ok|null, label, detail]  (ok===null => informational)
function check($section, $ok, $label, $detail = '') {
	global $results;
	$results[] = array($section, $ok, $label, $detail);
}
$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
function fn_available($name, $disabled) {
	return function_exists($name) && !in_array($name, $disabled, true);
}

// ---- PHP + required extensions -------------------------------------------
check('req', Requirements::phpMeetsMinimum(PHP_VERSION), "PHP >= " . Requirements::MIN_PHP, "found " . PHP_VERSION);
foreach (array(
	'json' => 'encodes every API response ruTorrent sends to the browser',
	'pcre' => 'regular expressions used throughout, incl. XMLRPC parsing',
) as $ext => $why) {
	check('req', extension_loaded($ext), "PHP extension: $ext", $why);
}
check('req', fn_available('fsockopen', $disabled), 'fsockopen() available', 'used to talk to rtorrent over SCGI');

// ---- Recommended extensions ----------------------------------------------
foreach (array(
	'simplexml' => 'XMLRPC proxy sanitisation (Sonarr/Radarr raw pass-through)',
	'curl'      => 'HTTP fetches used by several plugins',
	'mbstring'  => 'robust handling of non-UTF8 torrent and file names',
	'zlib'      => 'gzip compression of responses',
) as $ext => $why) {
	check('rec', extension_loaded($ext), "PHP extension: $ext", $why);
}

// ---- Recommended external programs ---------------------------------------
if (fn_available('exec', $disabled)) {
	foreach (array(
		'php'  => 'runs plugin helper scripts scheduled by rtorrent (_task, autotools, ...)',
		'curl' => 'used by some plugins',
		'gzip' => 'response compression',
	) as $prog => $why) {
		$path = @exec('command -v ' . escapeshellarg($prog) . ' 2>/dev/null');
		check('rec', !empty($path), "program: $prog", $path ? "found at $path" : $why);
	}
} else {
	check('rec', false, 'external programs', 'exec() is disabled -- cannot check for php/curl/gzip; some plugins may not work');
}

// ---- Load ruTorrent config (best effort) ---------------------------------
function load_ru_config() {
	$cfg = dirname(__FILE__) . '/conf/config.php';
	if (!is_file($cfg) || !is_readable($cfg)) return null;
	$load = function() use ($cfg) {
		// config.php only assigns config vars (and guards its config.local include).
		$scgi_host = null; $scgi_port = null; $XMLRPCMountPoint = null;
		$topDirectory = null; $log_file = null; $tempDirectory = null;
		include $cfg;
		return array(
			'scgi_host' => isset($scgi_host) ? $scgi_host : null,
			'scgi_port' => isset($scgi_port) ? $scgi_port : null,
			'mount'     => isset($XMLRPCMountPoint) ? $XMLRPCMountPoint : null,
			'topdir'    => isset($topDirectory) ? $topDirectory : null,
			'log'       => isset($log_file) ? $log_file : null,
			'tmp'       => isset($tempDirectory) ? $tempDirectory : null,
		);
	};
	return @$load();
}
$cfg = load_ru_config();

// ---- Config validators + writable paths ----------------------------------
if ($cfg === null) {
	check('config', null, 'ruTorrent config', 'conf/config.php not found -- run this from your ruTorrent directory to validate config');
} else {
	check('config', Requirements::scgiConfigured($cfg['scgi_host'], $cfg['scgi_port']),
		'$scgi_host / $scgi_port', 'rtorrent SCGI address: ' . Requirements::scgiLabel($cfg['scgi_host'], $cfg['scgi_port']));
	check('config', Requirements::xmlrpcMountPointValid($cfg['mount']),
		'$XMLRPCMountPoint', 'must be set (config warns not to delete it); found: ' . var_export($cfg['mount'], true));
	if ($cfg['topdir'] !== null) {
		$ok = Requirements::looksAbsolute($cfg['topdir']) && @is_dir($cfg['topdir']) && @is_readable($cfg['topdir']);
		check('config', $ok, '$topDirectory', ($ok ? 'ok: ' : 'not an existing readable directory: ') . $cfg['topdir']);
	}
	if (!empty($cfg['log'])) {
		$dir = dirname($cfg['log']);
		check('config', @is_dir($dir) && @is_writable($dir), '$log_file writable', "log dir: $dir");
	}
	if (!empty($cfg['tmp'])) {
		check('config', @is_dir($cfg['tmp']) && @is_writable($cfg['tmp']), '$tempDirectory writable', $cfg['tmp']);
	}
	// ruTorrent stores per-user settings under share/; it must be writable.
	$share = dirname(__FILE__) . '/share';
	if (is_dir($share)) {
		check('config', @is_writable($share), 'share/ writable', 'ruTorrent stores settings/uploads here');
	}
}

// ---- rtorrent version (needs config + a running rtorrent) ----------------
function scgi_call($host, $port, $methodName) {
	$body = '<?xml version="1.0"?><methodCall><methodName>' . $methodName . '</methodName><params></params></methodCall>';
	$hdr  = "CONTENT_LENGTH\x00" . strlen($body) . "\x00SCGI\x001\x00";
	$packet = strlen($hdr) . ':' . $hdr . ',' . $body;
	$fp = Requirements::isUnixSocket($host) ? @fsockopen($host, -1, $en, $es, 5) : @fsockopen($host, (int)$port, $en, $es, 5);
	if (!$fp) return null;
	@fwrite($fp, $packet);
	$resp = '';
	while (!feof($fp)) { $chunk = @fread($fp, 8192); if ($chunk === false) break; $resp .= $chunk; }
	@fclose($fp);
	if (preg_match('#<value>\s*<string>(.*?)</string>#s', $resp, $m)) return $m[1];
	if (preg_match('#<string>(.*?)</string>#s', $resp, $m)) return $m[1];
	return null;
}
if ($cfg && Requirements::scgiConfigured($cfg['scgi_host'], $cfg['scgi_port'])) {
	$ver = scgi_call($cfg['scgi_host'], $cfg['scgi_port'], 'system.client_version');
	$where = Requirements::scgiLabel($cfg['scgi_host'], $cfg['scgi_port']);
	if ($ver === null) {
		check('rtorrent', null, 'rtorrent reachable', "could not reach rtorrent at $where -- is it running and is the SCGI address correct?");
	} else {
		list($ok, $note) = Requirements::rtorrentSupport($ver);
		check('rtorrent', $ok, 'rtorrent version', "$ver -- $note");
	}
} else {
	check('rtorrent', null, 'rtorrent version', 'no usable SCGI address in config -- cannot check rtorrent');
}

// ---- Render --------------------------------------------------------------
$req_fail = 0; $warn = 0;
foreach ($results as $r) { if ($r[1] === false) { $r[0] === 'req' ? $req_fail++ : $warn++; } }
$mark = function($ok, $section) {
	if ($ok === null) return 'INFO';
	if ($ok) return 'OK  ';
	return $section === 'req' ? 'FAIL' : 'WARN';
};
$sections = array('req' => 'Required', 'rec' => 'Recommended', 'config' => 'Configuration', 'rtorrent' => 'rtorrent');
$lines = array();
$lines[] = "ruTorrent prerequisites & install check  (PHP " . PHP_VERSION . ")";
$lines[] = str_repeat('-', 74);
foreach ($sections as $sec => $title) {
	$has = false; foreach ($results as $r) if ($r[0] === $sec) { $has = true; break; }
	if (!$has) continue;
	$lines[] = "$title:";
	foreach ($results as $r) if ($r[0] === $sec)
		$lines[] = sprintf("  [%s] %-24s %s", $mark($r[1], $r[0]), $r[2], $r[3]);
	$lines[] = "";
}
$lines[] = str_repeat('-', 74);
$lines[] = ($req_fail === 0)
	? "RESULT: all required checks passed." . ($warn ? " ($warn warning(s) -- some features may be limited)" : "")
	: "RESULT: $req_fail required check(s) FAILED -- ruTorrent will not work until fixed.";
$report = implode("\n", $lines) . "\n";

fwrite(STDOUT, $report);
exit($req_fail === 0 ? 0 : 1);
