<?php
	// configuration parameters

	// for snoopy client
	$httpUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';
	$httpTimeOut = 30;			// in seconds
	$httpUseGzip = true;
	// Largest response body Snoopy accepts, in bytes. It is applied to the body
	// as it arrives and again to a gzip body once inflated; a larger answer is
	// refused outright rather than truncated, so a caller cannot mistake a
	// cut-off document for a complete one. Raise it where legitimate downloads
	// are bigger -- the metainfo of a very large torrent runs to several
	// megabytes of piece hashes alone -- bearing in mind that an inflated body
	// is held in memory and so has to fit within PHP's memory_limit.
	$httpMaxResponseLength = 4194304;	// 4 MiB
	$httpIP = null;				// IP string. Or null for any.
	$httpProxy = array
	(
		'use'	=> false,
		'proto'	=> 'http',		// 'http' or 'https'
		'host'	=> 'PROXY_HOST_HERE',
		'port'	=> 3128
	);
	// Refuse to fetch URLs that resolve to a private or otherwise non-public
	// address. Off by default: feeds and torrent links commonly point at an
	// indexer on localhost or the LAN. Turn it on where the URLs come from
	// someone you do not want reaching services the web server can see.
	$httpBlockPrivateNetworks = false;
	// Hosts exempt from the check above, matched against the URL host verbatim,
	// e.g. array( '127.0.0.1', 'jackett.lan' ).
	$httpPrivateNetworkAllowlist = array();
	// Check the certificate an HTTPS server presents. Earlier releases passed
	// curl -k on every HTTPS fetch and could not be told not to, so an
	// indexer with a self-signed or expired certificate worked without anyone
	// arranging it. Those installs have to set this to false, or install the
	// certificate authority the server's certificate is signed by. Off means
	// every HTTPS fetch -- feeds, torrent downloads, tracker logins with their
	// passwords -- can be read and changed by anything on the path.
	$httpVerifyCertificates = true;

	// for xmlrpc actions
	$rpcTimeOut = 5;			// connect timeout, in seconds; unusable values mean 30
	// One total budget for the complete request write and response read, in seconds.
	// null, and any value that is not a finite positive number, uses the larger of
	// the connect timeout and PHP's positive default_socket_timeout (or 60).
	// For slow, large responses, set a finite positive value (e.g. 180 seconds)
	// and raise PHP/web-server timeouts too. Maximum: 2147483 seconds; there is
	// no unlimited mode. php env_check.php reports unusable values.
	$rpcTransferTimeOut = null;
	// Largest accepted daemon response body, in bytes. The default is rTorrent's
	// own maximum, 100 MiB; a larger or unusable value also means 100 MiB and
	// is reported by php env_check.php. Use an integer byte count, not "64M".
	// Without a usable temporary directory, a response this large needs about
	// twice its size in PHP memory.
	$rpcMaxResponseBytes = 104857600;
	$rpcLogCalls = false;
	$rpcLogFaults = true;

	// for php
	$phpUseGzip = false;
	$phpGzipLevel = 2;

	$schedule_rand = 10;			// rand for schedulers start, +0..X seconds

	$do_diagnostic = true;			// Diagnose ruTorrent. Recommended to keep enabled, unless otherwise required.
	$al_diagnostic = true;			// Diagnose auto-loader. Set to "false" to make composer plugins work.

	$log_file = $_ENV['RU_LOG_FILE'] ?? '/tmp/errors.log'; // absolute path or stream URI (comment or leave blank to disable logging)

	$saveUploadedTorrents = true;		// Save uploaded torrents to profile/torrents directory or not
	$overwriteUploadedTorrents = false;	// Overwrite existing uploaded torrents in profile/torrents directory or make unique name

	$topDirectory = $_ENV['RU_TOP_DIR'] ?? '/';			// Upper available directory. Absolute path with trail slash.
	$forbidUserSettings = false;

	$scgi_port = $_ENV['RU_SCGI_PORT'] ?? 5000;
	$scgi_host = $_ENV['RU_SCGI_HOST'] ?? "127.0.0.1";

	// For web->rtorrent link through unix domain socket
	// (scgi_local in rtorrent conf file), change variables
	// above to something like this:
	//
	// $scgi_port = 0;
	// $scgi_host = "unix:///tmp/rpc.socket";

	$XMLRPCMountPoint = "/RPC2";		// DO NOT DELETE THIS LINE!!! DO NOT COMMENT THIS LINE!!!

	$throttleMaxSpeed = 327625*1024;	// DO NOT EDIT THIS LINE!!! DO NOT COMMENT THIS LINE!!!
	// Can't be greater then 327625*1024 due to limitation in libtorrent ResourceManager::set_max_upload_unchoked function.

	$pathToExternals = array(
		"php"	=> '',			// Something like /usr/bin/php. If empty, will be found in PATH.
		"curl"	=> '',			// Something like /usr/bin/curl. If empty, will be found in PATH.
		"gzip"	=> '',			// Something like /usr/bin/gzip. If empty, will be found in PATH.
		"id"	=> '',			// Something like /usr/bin/id. If empty, will be found in PATH.
		"stat"	=> '',			// Something like /usr/bin/stat. If empty, will be found in PATH.
	);

	$localHostedMode = false;		// Set to true if rTorrent is hosted on the SAME machine as ruTorrent

	$cachedPluginLoading = false;		// Set to true to enable rapid cached loading of ruTorrent plugins
										// Required to clear web browser cache when upgrading versions

	$pluginMinification = true; 	// Stable change to reduce loading times by minimizing JavaScript networked
									// Only recommended to disable when required for debuging purposes

	$localhosts = array(			// list of local interfaces
		"::1",
		"127.0.0.1",
		"localhost",
	);

	// Read from $_ENV, like every other RU_ setting here. Asking getenv()
	// whether it is set and then reading $_ENV disagrees whenever
	// variables_order has no E, which appends null instead of the address.
	if(isset($_ENV['RU_LOCALHOSTS']) && ($_ENV['RU_LOCALHOSTS'] !== ''))
		$localhosts[] = $_ENV['RU_LOCALHOSTS'];

	$profilePath = $_ENV['RU_PROFILE_PATH'] ?? '../../share';		// Path to user profiles
	// Environment values are strings; parse the documented octal notation before using it as a file mode.
	// An unset, empty, or malformed RU_PROFILE_MASK uses the documented 0777 default -- and a mask that
	// was set but could not be read names the value it rejected, because the default is wider than any
	// mask worth setting and naming only the variable leaves the operator to find where it was set.
	// json_encode quotes the value and escapes anything non-printable in it, so one error_log() call
	// stays one entry and trailing whitespace is visible; a mask is four characters, so 32 bounds what
	// a wrong setting writes to the error log on every request that loads this file.
	$profileMask = $_ENV['RU_PROFILE_MASK'] ?? '';
	if(($profileMask !== '') && !preg_match('/^0?[0-7]{3}$/D', $profileMask))
		error_log('RU_PROFILE_MASK is not three or four octal digits: '
			.json_encode(substr($profileMask, 0, 32), JSON_INVALID_UTF8_SUBSTITUTE).'; using the 0777 default.');
	$profileMask = preg_match('/^0?[0-7]{3}$/D', $profileMask) ? intval($profileMask, 8) : 0777;
						// Both Webserver and rtorrent users must have read-write access to it.
						// For example, if Webserver and rtorrent users are in the same group then the value may be 0770.

	$tempDirectory = $_ENV['RU_TEMP_DIRECTORY'] ?? null; // Temp directory. Absolute path. If null, then autodetect will be used.

	$canUseXSendFile = false;		// If true then use X-Sendfile feature if it exist

	$locale = "UTF8";

	$enableCSRFCheck = false;		// If true then Origin and Referer will be checked
	$enabledOrigins = array();		// List of enabled domains for CSRF check (only hostnames, without protocols, port etc.).
						// If empty, then will retrieve domain from HTTP_HOST / HTTP_X_FORWARDED_HOST

	// Absolute public application base, including a reverse proxy's path prefix, for feed links.
	// A nonempty RU_PUBLIC_URL environment variable overrides this setting.
	$publicURL = '';

	// Load local deployment overrides if present
	$configLocalPath = dirname(__FILE__)."/config.local.php";
	if(is_file($configLocalPath) && is_readable($configLocalPath))
		require($configLocalPath);
