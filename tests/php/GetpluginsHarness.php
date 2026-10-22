<?php

require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/GetpluginsRtorrentResponder.php');

/**
 * Runs php/getplugins.php for real and hands back what it emitted.
 *
 * getplugins.php is a top level script, not a class: it is included, it reads
 * the configuration and the profile, it talks to rTorrent, and it writes the
 * JavaScript that sets up the interface. Everything it reports about the
 * install it reports as a noty() call in that output, so driving the script
 * and reading its output is the only way to see a diagnostic it sends.
 *
 * A case describes the install it wants -- a temp directory and its mode, a
 * profile, whether rTorrent answers -- and reads back the diagnostics. The
 * script runs in a process of its own so that the configuration a case sets
 * is the configuration it runs under, and so that one case cannot leave a
 * global behind for the next.
 *
 * Directory modes are the subject of several of these checks, so every
 * directory the harness creates is created under umask(0) and its mode is
 * verified afterwards. Without that a runner's own umask decides the mode
 * and a mode test silently stops testing its mode.
 */
class GetpluginsHarness
{
	private $tree;
	private $daemon = null;
	private $port = 0;
	private $host;

	public function __construct($name)
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-getplugins-' . $name . '-' . getmypid();
		$this->release();
		$this->directory('', 0700);
		$this->directory('profile/settings', 0700);
		$this->directory('profile/torrents', 0700);
		// No socket of this name is ever created, so the probe fails and the
		// script takes the branch an install with no rTorrent takes.
		$this->host = 'unix://' . $this->tree . '/absent.sock';
	}

	/** A path inside the harness tree. */
	public function path($relative = '')
	{
		return ($relative === '') ? $this->tree : $this->tree . '/' . ltrim($relative, '/');
	}

	/**
	 * Creates a directory inside the tree with exactly $mode and returns it.
	 * umask(0) is set around the call, and the result is read back, so the
	 * mode a case asked for is the mode its subject sees.
	 */
	public function directory($relative, $mode)
	{
		$path = $this->path($relative);
		$mask = umask(0);
		// mkdir() applies $mode to the leaf only; any parent it has to invent
		// gets the default, so each level is created and chmod'ed in turn.
		$parts = explode('/', trim(substr($path, 1), '/'));
		$walk = '';
		foreach($parts as $part)
		{
			$walk .= '/' . $part;
			if(!is_dir($walk))
				@mkdir($walk, $mode);
		}
		@chmod($path, $mode);
		umask($mask);
		clearstatcache(true, $path);
		$actual = is_dir($path) ? (fileperms($path) & 07777) : -1;
		if($actual !== $mode)
			throw new Exception('could not create ' . $path . ' with mode '
				. decoct($mode) . '; it is ' . (($actual < 0) ? 'absent' : decoct($actual)));
		return $path;
	}

	/**
	 * A path inside the tree that getplugins.php cannot create, for the case
	 * where a configured temp directory is not there at all: a configured one
	 * that is merely missing is created on the spot, so the directory it does
	 * not have is one whose parent denies it.
	 */
	public function uncreatablePath($relative)
	{
		$this->directory($relative, 0500);
		return $this->path($relative) . '/temp';
	}

	/**
	 * Makes rTorrent answer, so the script gets past the probe and runs the
	 * checks that ask what the rTorrent user may reach. Returns the uid the
	 * daemon reports as that user.
	 */
	public function withRtorrent($uid = 1000, $gids = '1000 1001')
	{
		$this->daemon = new FakeRtorrentDaemon(
			new GetpluginsRtorrentResponder($uid, $gids),
			$this->path('calls.log'), false,
			array(dirname(__FILE__) . '/GetpluginsRtorrentResponder.php'));
		$this->port = $this->daemon->port();
		$this->host = '127.0.0.1';
		return $uid;
	}

	/**
	 * Runs getplugins.php against this tree and returns everything it wrote.
	 * $environment overrides or adds RU_* configuration entries; a null value
	 * leaves that entry unset, which is what an install that configured
	 * nothing has.
	 */
	public function run($environment = array())
	{
		$settings = array_merge(array(
			'RU_PROFILE_PATH' => $this->path('profile'),
			'RU_LOG_FILE' => $this->path('errors.log'),
			'RU_SCGI_PORT' => (string)$this->port,
			'RU_SCGI_HOST' => $this->host,
		), $environment);

		$source = "<?php\n";
		foreach($settings as $name => $value)
			if($value !== null)
				$source .= '$_ENV[' . var_export($name, true) . '] = '
					. var_export((string)$value, true) . ";\n";
		// getplugins.php reads ../plugins and ../conf relative to the working
		// directory, so it is run from the one it is installed in.
		$source .= 'chdir(' . var_export(dirname(__DIR__, 2) . '/php', true) . ");\n"
			. 'require("getplugins.php");' . "\n";

		$script = $this->path('run.php');
		file_put_contents($script, $source);
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -d display_errors=0 '
			. escapeshellarg($script) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	/** The theUILang keys the run reported, in the order it reported them. */
	public static function diagnostics($output)
	{
		preg_match_all('/noty\(\s*theUILang\.([A-Za-z0-9_]+)/', $output, $found);
		return $found[1];
	}

	/** Whether the run reported $key. */
	public static function reported($output, $key)
	{
		return in_array($key, self::diagnostics($output), true);
	}

	/**
	 * Whether the run got far enough to be worth reading: a run that died on
	 * the way in reports nothing, which reads the same as a clean install.
	 */
	public static function ran($output)
	{
		return strpos($output, 'theWebUI.systemInfo') !== false;
	}

	public function release()
	{
		if($this->daemon !== null)
		{
			$this->daemon->stop();
			$this->daemon = null;
		}
		$this->wipe($this->tree);
	}

	private function wipe($path)
	{
		if(!is_dir($path) || is_link($path))
			return;
		// A fixture directory may deny its own listing; it is the harness's
		// own, inside the harness's own tree, so it is reopened to remove it.
		@chmod($path, 0700);
		foreach(scandir($path) as $entry)
			if(($entry !== '.') && ($entry !== '..'))
			{
				$child = $path . '/' . $entry;
				(is_dir($child) && !is_link($child)) ? $this->wipe($child) : @unlink($child);
			}
		@rmdir($path);
	}
}
