<?php

require_once(__DIR__ . '/FakeRtorrentDaemon.php');

/**
 * A daemon that answers rTorrentSettings::obtain() the way rtorrent 0.16.20
 * answers it, and that holds d.multicall to the signature 0.16.16 gave it:
 * (target, view, command...).
 *
 * The probe is seven round trips and the alias map is only built from what
 * they return, so a test about the map cannot stand on a canned reply list --
 * the answer to each request has to suit the request. A call that puts the
 * view where the target belongs is answered with rtorrent's own refusal, so
 * a caller can be told apart by what came back rather than by reading its
 * request.
 */
class RtorrentProbeFixture
{
	const VERSION = '0.16.20';
	const INVALID_TARGET = 'invalid parameters: invalid target';

	/** The method names one request asks for, in order. */
	public static function methodNames($body)
	{
		if (preg_match('|<methodName>(.*)</methodName>|Us', $body, $outer) &&
			($outer[1] !== 'system.multicall')) {
			return array($outer[1]);
		}
		if (preg_match_all(
			'|<name>methodName</name><value><string>(.*)</string></value>|Us', $body, $inner)) {
			return $inner[1];
		}
		return array();
	}

	/** The first parameter of a single-command request, or null. */
	public static function firstParameter($body)
	{
		if (preg_match('|<params>\s*<param><value><string>(.*)</string></value></param>|Us',
			$body, $m)) {
			return $m[1];
		}
		return null;
	}

	/** One d.multicall row: the hash, then a value per remaining command. */
	public static function row($hash, $columns)
	{
		$row = array($hash);
		for ($i = 1; $i < $columns; $i++) {
			$row[] = '0';
		}
		return $row;
	}

	/**
	 * Leave in $profilePath the settings cache that probing this daemon
	 * leaves, so a script driven against that profile starts where an
	 * install whose interface has been opened starts: past the handshake,
	 * with the alias map already in hand.
	 *
	 * For a test whose daemon answers from a fixed reply list and whose
	 * subject is what the script asks rtorrent for, not how it learned to
	 * ask. The file is produced by running the real probe once rather than
	 * written out here, so it holds whatever that probe produces.
	 */
	public static function warmSettingsCache($profilePath)
	{
		static $cache = null;
		if ($cache === null) {
			$cache = self::probeForCache();
		}
		if ($cache === false) {
			return false;
		}
		@mkdir($profilePath . '/settings', 0700, true);
		return file_put_contents($profilePath . '/settings/rtorrent.dat', $cache) !== false;
	}

	private static function probeForCache()
	{
		$base = sys_get_temp_dir() . '/rutorrent-probe-fixture-' . getmypid();
		@mkdir($base . '/settings', 0700, true);
		$daemon = new FakeRtorrentDaemon(self::responder(), $base . '/calls.log');
		$root = dirname(__DIR__, 2);
		$driver = $base . '/probe.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($base, true) . ";\n"
			. '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
			. '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string)$daemon->port(), true) . ";\n"
			. 'require_once(' . var_export($root . '/php/xmlrpc.php', true) . ");\n"
			. 'rTorrentSettings::get(true);' . "\n");
		$ignored = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($driver) . ' 2>&1', $ignored);
		$daemon->stop();
		$cache = @file_get_contents($base . '/settings/rtorrent.dat');
		foreach (array($base . '/settings', $base) as $dir) {
			foreach (glob($dir . '/*') as $entry) {
				if (!is_dir($entry)) {
					@unlink($entry);
				}
			}
		}
		@rmdir($base . '/settings');
		@rmdir($base);
		return $cache;
	}

	public static function responder($hash = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA')
	{
		return function ($body) use ($hash) {
			$names = RtorrentProbeFixture::methodNames($body);
			$single = (count($names) === 1) ? $names[0] : '';

			if ($single === 'd.multicall') {
				if (RtorrentProbeFixture::firstParameter($body) !== '') {
					return FakeRtorrentDaemon::fault(RtorrentProbeFixture::INVALID_TARGET);
				}
				// The httprpc list mode asks for 36 columns.
				return RtorrentProbeFixture::row($hash, 36);
			}
			switch ($single) {
				case 'system.client_version':
					return array(RtorrentProbeFixture::VERSION);
				case 'system.api_version':
					return array('11');
				case 'convert.kb':
					return array('1');
				case 'network.listen.port':
					return array('6890');
				case 'execute.capture':
					// uid, group list, home -- the three lines obtain() reads.
					return array("1000\n1000 1001\n/home/rtorrent\n");
			}
			// The settings batch: directory, session, library version, name,
			// port range, bind address, local address. Every one of these
			// but the library version is answered with the value the class
			// carries unprobed, so that warmSettingsCache() changes what a
			// script knows about the daemon's commands and nothing else.
			if (count($names) === 7) {
				return array('/tmp', '', RtorrentProbeFixture::VERSION, '',
					'6890-6999', '0.0.0.0', '0.0.0.0');
			}
			return array_fill(0, max(1, count($names)), '0');
		};
	}
}
