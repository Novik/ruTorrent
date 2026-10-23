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
 * It reads conf/config.php and uses the common SCGI transport, without loading
 * the application or plugins, so missing prerequisites can still be diagnosed.
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

	/**
	 * rtorrent registers these on event.download.inserted_new by itself; every
	 * other key on that event was installed by ruTorrent (from a plugin init.php).
	 */
	public static function rutorrentHandlers($keys)
	{
		$builtin = array('1_prepare', '~_save_full');
		return is_array($keys) ? array_values(array_diff($keys, $builtin)) : array();
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
		if ($path[0] === '/') return true;
		return DIRECTORY_SEPARATOR === '\\' &&
			((bool)preg_match('#^[A-Za-z]:[\\\\/]#', $path) || strncmp($path, '\\\\', 2) === 0);
	}

	/**
	 * conf/config.php reads RU_PROFILE_MASK as three or four octal digits and
	 * falls back to 0777 for anything else -- which is wider than any mask
	 * somebody sets one for, so a value it could not read is worth reporting.
	 * An absent or empty mask is not a mistake; it asks for the default.
	 */
	public static function profileMaskValid($value)
	{
		if ($value === null || $value === '') return true;
		return is_string($value) && (bool)preg_match('/^0?[0-7]{3}$/D', $value);
	}

	/**
	 * The PHP extensions a plugin says it needs, read from its plugin.info.
	 * Returns array('error' => array(...), 'warning' => array(...)) -- error
	 * means the plugin is disabled without it, warning that one of its
	 * features is.
	 */
	public static function pluginExtensions($info)
	{
		$ret = array('error' => array(), 'warning' => array());
		if (!is_string($info) || $info === '') return $ret;
		foreach (preg_split('/\r\n|\r|\n/', $info) as $line) {
			if (!preg_match('/^\s*php\.extensions\.(error|warning)\s*:\s*(.+)$/', $line, $m)) continue;
			foreach (explode(',', $m[2]) as $ext) {
				$ext = trim($ext);
				if ($ext !== '' && !in_array($ext, $ret[$m[1]], true)) $ret[$m[1]][] = $ext;
			}
		}
		return $ret;
	}

	/**
	 * Which of the extensions the plugins declare are not loaded, and what
	 * each absence costs. $byPlugin is plugin name => the array
	 * pluginExtensions() returned for it; $loaded is the extension names PHP
	 * has. Returns extension => array('disables' => array(...),
	 * 'limits' => array(...)), keyed in a stable order.
	 */
	public static function missingPluginExtensions($byPlugin, $loaded)
	{
		$wanted = array();
		foreach ($byPlugin as $plugin => $needs)
			foreach (array('error', 'warning') as $severity)
				foreach ((isset($needs[$severity]) ? $needs[$severity] : array()) as $ext) {
					if (!isset($wanted[$ext])) $wanted[$ext] = array();
					// A plugin that cannot run without it outranks one that
					// only loses a feature, so error wins for the same pair.
					if (!isset($wanted[$ext][$plugin]) || ($severity === 'error'))
						$wanted[$ext][$plugin] = $severity;
				}
		ksort($wanted);
		// get_loaded_extensions() answers with the name each extension
		// registered -- Phar, SimpleXML -- while plugin.info spells them
		// lower case, and extension_loaded() does not care either way.
		$have = array_map('strtolower', $loaded);
		$ret = array();
		foreach ($wanted as $ext => $plugins) {
			if (in_array(strtolower($ext), $have, true)) continue;
			ksort($plugins);
			$ret[$ext] = array(
				'disables' => array_keys(array_filter($plugins, function ($s) { return $s === 'error'; })),
				'limits'   => array_keys(array_filter($plugins, function ($s) { return $s === 'warning'; })),
			);
		}
		return $ret;
	}

	/**
	 * The bundled Public Suffix List is a dated snapshot, and an install cannot
	 * refresh it: php/publicsuffix.php pins its SHA-256, so a newer .dat makes
	 * every lookup throw. Upgrading ruTorrent is the only remedy, which is why
	 * the age is reported and not repaired.
	 *
	 * The bound sits above the age a correct install already shows. A snapshot
	 * reaches the tree weeks after it is published -- the shipped one was four
	 * weeks old on arrival -- and the last twelve releases are up to 24 days
	 * apart, so 52 days is the oldest a fully current install can be. One
	 * release cycle, 8.9 days on average across those twelve, would warn about
	 * a tree with nothing wrong with it.
	 */
	const MAX_PSL_AGE_DAYS = 56;

	/** The list's own '// VERSION: 2026-09-24_13-26-36_UTC' stamp, as a UTC timestamp. */
	public static function publicSuffixListDate($data)
	{
		if (!is_string($data) || !preg_match(
			'~^//[ \t]*VERSION:[ \t]*([0-9]{4})-([0-9]{2})-([0-9]{2})_([0-9]{2})-([0-9]{2})-([0-9]{2})_UTC[ \t\r]*$~m',
			$data, $matches)) return null;
		list(, $year, $month, $day, $hour, $minute, $second) = array_map('intval', $matches);
		if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 60) return null;
		return gmmktime($hour, $minute, $second, $month, $day, $year);
	}

	/**
	 * How old the bundled list reads, as array(ok, detail) -- the shape
	 * rtorrentSupport() answers in. Age is a warning and never a failure: a
	 * public suffix the snapshot does not know widens a cookie's accepted
	 * Domain scope back to what it was before any list was consulted, so it
	 * admits names it should refuse and refuses nothing it should admit.
	 */
	public static function publicSuffixListAge($data, $now)
	{
		$stamped = self::publicSuffixListDate($data);
		if ($stamped === null)
			return array(false, 'carries no "// VERSION:" stamp, so its age cannot be read');
		$days = intdiv(max(0, (int)$now - $stamped), 86400);
		$detail = 'published ' . gmdate('Y-m-d', $stamped) . ', ' . $days . ' day(s) ago';
		if ($days <= self::MAX_PSL_AGE_DAYS) return array(true, $detail);
		return array(false, $detail . ' -- over ' . self::MAX_PSL_AGE_DAYS .
			'; a public suffix delegated since then is unknown here, so a cookie Domain naming' .
			' one is accepted. Upgrade ruTorrent for a newer list.');
	}

	public static function logFileStreamScheme($path)
	{
		if (!is_string($path) ||
			!preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):\/\//', $path, $matches)) return null;
		return $matches[1];
	}

	public static function fileUriFilesystemPath($path)
	{
		$parts = parse_url($path);
		if (!is_array($parts) || !isset($parts['scheme']) ||
			strcasecmp($parts['scheme'], 'file') !== 0 || !isset($parts['path']) ||
			isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) ||
			isset($parts['query']) || isset($parts['fragment'])) return null;
		$host = isset($parts['host']) ? $parts['host'] : '';
		if ($host === '' || strcasecmp($host, 'localhost') === 0) return $parts['path'];
		return DIRECTORY_SEPARATOR === '\\'
			? '\\\\' . $host . str_replace('/', '\\', $parts['path']) : null;
	}

	public static function logFilePathValid($path)
	{
		$scheme = self::logFileStreamScheme($path);
		if ($scheme !== null && strcasecmp($scheme, 'file') === 0) {
			$filesystemPath = self::fileUriFilesystemPath($path);
			return $filesystemPath !== null && self::looksAbsolute($filesystemPath);
		}
		return self::looksAbsolute($path) || $scheme !== null;
	}

	public static function logFileStreamAvailable($path, $wrappers)
	{
		$scheme = self::logFileStreamScheme($path);
		if ($scheme === null || !is_array($wrappers)) return false;
		if (in_array($scheme, $wrappers, true)) return true;
		// PHP's built-in wrappers are case-insensitive; user-registered wrappers are not.
		$builtin = array('https', 'ftps', 'compress.zlib', 'php', 'file',
			'glob', 'data', 'http', 'ftp', 'phar');
		$lower = strtolower($scheme);
		return in_array($lower, $builtin, true) && in_array($lower, $wrappers, true);
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
$transportFile = __DIR__.'/php/scgitransport.php';
$transportAvailable = is_readable($transportFile);
check('req', $transportAvailable, 'SCGI transport', 'php/scgitransport.php must be readable');
if($transportAvailable) {
	require_once($transportFile);
	foreach(rSCGITransport::requiredFunctions() as $function) {
		$available = fn_available($function, $disabled);
		check('req', $available, $function.'() available', 'used to talk to rtorrent over SCGI');
		$transportAvailable = $transportAvailable && $available;
	}
	$clock = false;
	foreach(rSCGITransport::clockFunctions() as $function)
		$clock = $clock || fn_available($function, $disabled);
	check('req', $clock, 'hrtime() or microtime() available', 'times the SCGI transfer budget');
	$transportAvailable = $transportAvailable && $clock;
}

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

// ---- The bundled Public Suffix List --------------------------------------
// php/publicsuffix.php decides from this snapshot whether a cookie Domain
// names a public suffix. Nothing else reports that the snapshot is dated.
$pslData = @file_get_contents(__DIR__ . '/php/public_suffix_list.dat');
if (!is_string($pslData)) {
	check('rec', false, 'Public Suffix List',
		'php/public_suffix_list.dat is unreadable, so every cookie carrying a Domain attribute is refused');
} else {
	list($pslOk, $pslNote) = Requirements::publicSuffixListAge($pslData, time());
	check('rec', $pslOk, 'Public Suffix List', $pslNote);
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
			'max'       => isset($rpcMaxResponseBytes) ? $rpcMaxResponseBytes : null,
			'connect'   => isset($rpcTimeOut) ? $rpcTimeOut : null,
			'transfer'  => isset($rpcTransferTimeOut) ? $rpcTransferTimeOut : null,
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
	if(class_exists('rSCGITransport') && !rSCGITransport::isValidResponseLimit($cfg['max'])) {
		check('config', false, '$rpcMaxResponseBytes', 'invalid value '.var_export($cfg['max'], true)
			.'; using 104857600 bytes (100 MiB). Set an integer from 1 to 104857600.');
	}
	if(class_exists('rSCGITransport')) {
		list($connect, $transfer) = rSCGITransport::timeouts($cfg['connect'], $cfg['transfer']);
		if(!rSCGITransport::isValidTimeout($cfg['connect']))
			check('config', false, '$rpcTimeOut', 'invalid value '.var_export($cfg['connect'], true)
				.'; using 30 seconds. Set a positive number of seconds.');
		if(!rSCGITransport::isValidTimeout($cfg['transfer']))
			check('config', false, '$rpcTransferTimeOut', 'invalid value '.var_export($cfg['transfer'], true)
				.'; using '.$transfer.' seconds, as for null. Set null or a positive number of seconds.');
	}
	if ($cfg['topdir'] !== null) {
		$ok = Requirements::looksAbsolute($cfg['topdir']) && @is_dir($cfg['topdir']) && @is_readable($cfg['topdir']);
		check('config', $ok, '$topDirectory', ($ok ? 'ok: ' : 'not an existing readable directory: ') . $cfg['topdir']);
	}
	if (!empty($cfg['log'])) {
		$scheme = Requirements::logFileStreamScheme($cfg['log']);
		$isFileStream = $scheme !== null && strcasecmp($scheme, 'file') === 0;
		$available = $scheme === null || Requirements::logFileStreamAvailable($cfg['log'], stream_get_wrappers());
		if (!$available) {
			check('config', false, '$log_file stream', "stream wrapper is not available: $scheme");
		} elseif ($scheme !== null && !$isFileStream) {
			check('config', null, '$log_file stream',
				"registered $scheme wrapper; writability is checked on first write");
		} else {
			$path = $isFileStream ? Requirements::fileUriFilesystemPath($cfg['log']) : $cfg['log'];
			$validPath = Requirements::logFilePathValid($cfg['log']);
			$dir = $validPath ? dirname($path) : null;
			$ok = $validPath && @is_dir($dir) && @is_writable($dir);
			$detail = $validPath ? "log dir: $dir"
				: 'must be an absolute path or stream URI: ' . $cfg['log'];
			check('config', $ok, '$log_file writable', $detail);
		}
	}
	// Read the way conf/config.php reads it, so the checker sees what the
	// configuration sees rather than what the shell happens to export.
	$rawMask = isset($_ENV['RU_PROFILE_MASK']) ? $_ENV['RU_PROFILE_MASK'] : '';
	if ($rawMask !== '') {
		check('config', Requirements::profileMaskValid($rawMask), 'RU_PROFILE_MASK',
			Requirements::profileMaskValid($rawMask)
				? 'octal file mode: ' . $rawMask
				: 'not three or four octal digits, so the wider 0777 default is in use: ' . $rawMask);
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

// ---- what the bundled plugins declare they need ---------------------------
// Read from each plugin.info rather than a list kept here, so a plugin that
// declares a new dependency is checked without this file being touched. A
// missing extension disables one plugin, not ruTorrent, so these are warnings.
$pluginDir = __DIR__ . '/plugins';
if (@is_dir($pluginDir)) {
	$byPlugin = array();
	foreach (@scandir($pluginDir) ?: array() as $entry) {
		if (($entry === '.') || ($entry === '..')) continue;
		$info = @file_get_contents($pluginDir . '/' . $entry . '/plugin.info');
		if ($info === false) continue;
		$byPlugin[$entry] = Requirements::pluginExtensions($info);
	}
	$missing = Requirements::missingPluginExtensions($byPlugin, get_loaded_extensions());
	foreach ($missing as $ext => $cost) {
		$detail = array();
		if ($cost['disables']) $detail[] = 'disables ' . implode(', ', $cost['disables']);
		if ($cost['limits'])   $detail[] = 'limits ' . implode(', ', $cost['limits']);
		check('plugins', false, "PHP extension: $ext", implode('; ', $detail));
	}
	if (!$missing)
		check('plugins', true, 'plugin extensions',
			'every extension the bundled plugins declare is loaded');
}

// ---- rtorrent version (needs config + a running rtorrent) ----------------
function scgi_params($params) {
	$xml = '';
	foreach ($params as $p)
		$xml .= '<param><value><string>' . htmlspecialchars($p, ENT_QUOTES) . '</string></value></param>';
	return $xml;
}
// A check that prints nothing for a minute looks hung itself, so a probe
// connects and waits within the WebUI's budgets, but no longer than this.
if (!defined('ENV_CHECK_PROBE_SECONDS')) define('ENV_CHECK_PROBE_SECONDS', 10);
function probe_budgets() {
	global $cfg;
	list($connect, $transfer) = rSCGITransport::timeouts($cfg['connect'], $cfg['transfer']);
	return array(min($connect, (float)ENV_CHECK_PROBE_SECONDS), min($transfer, (float)ENV_CHECK_PROBE_SECONDS), $transfer, $connect);
}
function scgi_call($host, $port, $methodName, $params = array(), $asList = false, &$failure = null) {
	global $cfg;
	$body = '<?xml version="1.0"?><methodCall><methodName>' . $methodName . '</methodName><params>' . scgi_params($params) . '</params></methodCall>';
	list($connect, $probe) = probe_budgets();
	$resp = rSCGITransport::send($host, $port, $body, true, array('connectTimeout' => $connect,
		'transferTimeout' => $probe, 'maxResponseBytes' => $cfg['max'],
		'responseMode' => rSCGITransport::RESPONSE_BODY, 'temporaryDirectory' => $cfg['tmp']), $failure);
	if ($resp === null) return null;
	if (strpos($resp, '<fault>') !== false) {
		$failure = 'fault';
		return null;
	}
	if ($asList) {
		if (preg_match_all('#<string>(.*?)</string>#s', $resp, $m)) return $m[1];
		return array();
	}
	if (preg_match('#<value>\s*<string>(.*?)</string>#s', $resp, $m)) return $m[1];
	if (preg_match('#<string>(.*?)</string>#s', $resp, $m)) return $m[1];
	return null;
}
if ($transportAvailable && $cfg && Requirements::scgiConfigured($cfg['scgi_host'], $cfg['scgi_port'])) {
	$ver = scgi_call($cfg['scgi_host'], $cfg['scgi_port'], 'system.client_version', array(), false, $failure);
	$where = Requirements::scgiLabel($cfg['scgi_host'], $cfg['scgi_port']);
	if ($ver === null) {
		if ($failure === 'read-timeout' || $failure === 'write-timeout') {
			list(, $probe, $transfer) = probe_budgets();
			$why = ($probe < $transfer)
				? "rtorrent at $where did not answer within {$probe} seconds -- it may be busy; the WebUI waits up to {$transfer} seconds"
				: "rtorrent at $where did not answer within the {$transfer}-second transfer budget -- it may be busy; raise \$rpcTransferTimeOut if this persists";
		} elseif ($failure === 'connect-failed') {
			list($probe, , , $connect) = probe_budgets();
			$why = "could not reach rtorrent at $where -- is it running and is the SCGI address correct?"
				. (($probe < $connect) ? " (this check connects within {$probe} seconds; the WebUI waits up to {$connect})" : '');
		} elseif ($failure === 'fault') {
			$why = "rtorrent at $where answered system.client_version with a fault";
		} elseif ($failure !== null) {
			$why = "rtorrent at $where sent no usable SCGI reply ($failure)";
		} else {
			$why = "rtorrent at $where answered system.client_version without a version";
		}
		check('rtorrent', null, 'rtorrent reachable', $why);
	} else {
		list($ok, $note) = Requirements::rtorrentSupport($ver);
		check('rtorrent', $ok, 'rtorrent version', "$ver -- $note");

		// ruTorrent installs its event handlers into rtorrent at runtime, from every
		// plugin's init.php, whenever a page is loaded. rtorrent does not persist them,
		// so after it restarts with nobody opening the web UI there are none, and
		// torrents added by other clients silently get no ratio group, no added time
		// and no history entry.
		$keys = scgi_call($cfg['scgi_host'], $cfg['scgi_port'], 'method.list_keys',
			array('', 'event.download.inserted_new'), true);
		if (is_array($keys)) {
			$registered = Requirements::rutorrentHandlers($keys);
			check('rtorrent', count($registered) > 0, 'handlers registered', count($registered)
				? count($registered) . ' handler(s) on event.download.inserted_new'
				: 'none since rtorrent last started -- torrents added outside the web UI get no ' .
				  'ratio group, added time or history entry. Open ruTorrent once, or run ' .
				  '"php php/initplugins.php <user>" when rtorrent starts');
		}
	}
} else {
	check('rtorrent', null, 'rtorrent version', 'no usable SCGI address or transport -- cannot check rtorrent');
}

// ---- Render --------------------------------------------------------------
$req_fail = 0; $warn = 0;
foreach ($results as $r) { if ($r[1] === false) { $r[0] === 'req' ? $req_fail++ : $warn++; } }
$mark = function($ok, $section) {
	if ($ok === null) return 'INFO';
	if ($ok) return 'OK  ';
	return $section === 'req' ? 'FAIL' : 'WARN';
};
$sections = array('req' => 'Required', 'rec' => 'Recommended', 'config' => 'Configuration', 'plugins' => 'Plugins', 'rtorrent' => 'rtorrent');
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

echo $report;
exit($req_fail === 0 ? 0 : 1);
