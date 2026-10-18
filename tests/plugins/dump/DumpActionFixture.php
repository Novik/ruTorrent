<?php

require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * Drives plugins/dump/action.php the way the interface does -- a POST of
 * cmd and hash -- against a daemon whose answers the case chooses, and hands
 * back the json the script printed together with anything PHP said on the
 * way.
 *
 * The script is a top level file: it reads $_REQUEST, talks SCGI and ends in
 * CachedEcho::send(), so there is no function to call and the only way to see
 * what it decided is to run it and read what came out.
 */
trait DumpActionFixture
{
	private $base;
	private $daemon;
	private $caseDir;
	private $caseNo = 0;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-dump-' . getmypid();
		$this->removeTree($this->base);
		@mkdir($this->base . '/profile/settings', 0700, true);
		// Past the handshake, so the replies a case gives answer the script's
		// own request rather than the seven of rTorrentSettings::obtain().
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->caseDir = null;
		$this->caseNo = 0;
	}

	public function tearDown()
	{
		$this->stopDaemon();
		$this->removeTree($this->base);
	}

	/**
	 * setUp() runs once per file, so a directory left behind by one case is
	 * read by the next. Each case asks for its own.
	 */
	private function newCase()
	{
		$this->caseNo++;
		$this->caseDir = $this->base . '/case' . $this->caseNo;
		@mkdir($this->caseDir, 0700, true);
	}

	private function hash()
	{
		return '0123456789ABCDEF0123456789ABCDEF01234567';
	}

	/** What rtorrent answers for its session directory, with the trail slash. */
	private function sessionDir()
	{
		return $this->caseDir . '/session/';
	}

	/** Where the script therefore looks for the session copy. */
	private function sessionCopyPath()
	{
		return $this->sessionDir() . $this->hash() . '.torrent';
	}

	private function writeSessionCopy($mode)
	{
		@mkdir($this->sessionDir(), 0700, true);
		file_put_contents($this->sessionCopyPath(), 'd8:announce0:4:infodee');
		chmod($this->sessionCopyPath(), $mode);
		return $this->sessionCopyPath();
	}

	private function writeTiedFile($mode)
	{
		$path = $this->caseDir . '/tied.torrent';
		file_put_contents($path, 'd8:announce0:4:infodee');
		chmod($path, $mode);
		return $path;
	}

	/** The user the script is running as, by the same measure it uses. */
	private function processUser()
	{
		$info = posix_getpwuid(posix_geteuid());
		return (is_array($info) && isset($info['name']) && strlen($info['name']))
			? $info['name'] : (string)posix_geteuid();
	}

	private function stopDaemon()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
	}

	private function removeTree($path)
	{
		if (!is_dir($path)) {
			@unlink($path);
			return;
		}
		foreach (scandir($path) as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				$this->removeTree($path . '/' . $entry);
			}
		}
		@rmdir($path);
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	/**
	 * Runs the script against a daemon answering $replies, one per request,
	 * followed by spares.
	 *
	 * The spares are not padding. rTask::run() does not start a local
	 * process when rtorrent is reachable -- it asks rtorrent to run the
	 * command, over the same socket -- so a case that gets as far as starting
	 * a task sends a second request, and a daemon out of replies leaves it
	 * waiting on a read instead of answering. Keeping the task inside the
	 * daemon is also what stops these cases running anything on the machine
	 * the suite is on.
	 */
	private function runDump($replies)
	{
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon(
			array_merge($replies, array(array('0'), array('0'))),
			$this->caseDir . '/calls.log');
		return $this->drive($this->daemon->port(), $this->hash());
	}

	/** The same, with no hash in the request. */
	private function runDumpWithoutHash()
	{
		$this->stopDaemon();
		$this->daemon = new FakeRtorrentDaemon(
			array(array($this->sessionDir(), ''), array('0')), $this->caseDir . '/calls.log');
		return $this->drive($this->daemon->port(), null);
	}

	/**
	 * The same, against a port nothing is listening on. A daemon that is down
	 * is the ordinary way the request fails, and it fails earlier than a
	 * fault does -- before any reply is parsed.
	 */
	private function runDumpAgainstNothing()
	{
		$this->stopDaemon();
		$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$name = stream_socket_get_name($probe, false);
		$port = (int)substr($name, strrpos($name, ':') + 1);
		fclose($probe);
		return $this->drive($port, $this->hash());
	}

	private function drive($port, $hash)
	{
		$root = $this->repoRoot();
		$driver = $this->caseDir . '/drive-dump.php';
		$request = '$_REQUEST[\'cmd\'] = "dumptorrent";' . "\n";
		if ($hash !== null) {
			$request .= '$_REQUEST[\'hash\'] = ' . var_export($hash, true) . ";\n";
		}
		// conf/config.php assigns $scgi_host and $scgi_port from the
		// environment, overwriting whatever a caller set before requiring it,
		// so the environment is where a test has to put them.
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
			. '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string)$port, true) . ";\n"
			. '$_ENV[\'RU_LOG_FILE\'] = ' . var_export($this->caseDir . '/errors.log', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
			. $request
			. 'chdir(' . var_export($root . '/plugins/dump', true) . ");\n"
			. 'require(' . var_export($root . '/plugins/dump/action.php', true) . ");\n");
		$output = array();
		// Diagnostics on: whether the script reads a value it never set is
		// one of the things being measured.
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=-1 -d display_errors=1 ' .
			escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	/**
	 * The json the script ended with, or null if it printed none.
	 *
	 * Taken from the first '{' that decodes to the end of the output: the
	 * script prints one document and prints it last, but a diagnostic ahead
	 * of it may hold a brace of its own, and the document itself holds
	 * nested ones -- so neither the first nor the last brace is the answer.
	 */
	private function response($run)
	{
		for ($at = strpos($run, '{'); $at !== false; $at = strpos($run, '{', $at + 1)) {
			$decoded = json_decode(substr($run, $at), true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		return null;
	}

	/**
	 * Whether the script refused rather than handing off to a task.
	 * rTask::start() always answers with a task number; the plugin's own
	 * refusal carries status and errors and nothing else.
	 */
	private function isPluginError($run)
	{
		$response = $this->response($run);
		return is_array($response) && !isset($response['no']) && isset($response['errors'][0]);
	}

	private function errorEntry($run)
	{
		$response = $this->response($run);
		return (is_array($response) && isset($response['errors'][0]) &&
			is_array($response['errors'][0])) ? $response['errors'][0] : array();
	}

	private function reason($run)
	{
		$entry = $this->errorEntry($run);
		return isset($entry['reason']) ? $entry['reason'] : '';
	}

	private function field($run, $name)
	{
		$entry = $this->errorEntry($run);
		return isset($entry[$name]) ? $entry[$name] : '';
	}

	/**
	 * The diagnostics that say the script read a value it never assigned.
	 * Narrow on purpose: error_reporting is -1, and the suite's own 8.5 run
	 * already carries deprecations from elsewhere in the tree.
	 */
	private function undefinedValueDiagnostics($run)
	{
		$found = array();
		foreach (explode("\n", $run) as $line) {
			// 7.4 says "Undefined variable: fname", 8.x "Undefined variable
			// $fname", and 8.1 adds a deprecation for the null that reaches
			// escapeshellarg().
			if ((stripos($line, 'undefined variable') !== false) ||
				(stripos($line, 'escapeshellarg') !== false)) {
				$found[] = trim($line);
			}
		}
		return implode(' | ', $found);
	}

	/**
	 * The command line the dumper was actually given.
	 *
	 * rTask writes the commands into a shell script under the profile and
	 * asks rtorrent to run that, so the script is where the filename that
	 * came out of escapeshellarg() can be read -- the XMLRPC call carries
	 * only the path of the script.
	 */
	private function dumpCommand($run)
	{
		$response = $this->response($run);
		if (!is_array($response) || !isset($response['no'])) {
			return '';
		}
		$script = (string)@file_get_contents(
			$this->base . '/profile/settings/tasks/' . $response['no'] . '/start.sh');
		foreach (explode("\n", $script) as $line) {
			if (strpos($line, 'dumptorrent') !== false) {
				return trim($line);
			}
		}
		return '';
	}

	/** A run, flattened, for an assertion message. */
	private function describe($run)
	{
		return substr(preg_replace('/\s+/', ' ', $run), 0, 300);
	}

	/** Whether plugins/dump/lang/en.js defines $key. */
	private function englishDefines($key)
	{
		if (!strlen($key)) {
			return false;
		}
		$en = (string)@file_get_contents($this->repoRoot() . '/plugins/dump/lang/en.js');
		// Anchored on the assignment, so dumpErrorUnreadable is not answered
		// for by dumpErrorUnreadableUnknownUser.
		return preg_match('/theUILang\.' . preg_quote($key, '/') . '\s*=/', $en) === 1;
	}
}
