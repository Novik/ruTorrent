<?php

require_once(__DIR__ . '/RtorrentProbeFixture.php');

/**
 * RtorrentProbeFixture::responder() as an object rather than a closure.
 *
 * FakeRtorrentDaemon runs a closure responder through pcntl_fork() and a
 * serializable one through proc_open(). getplugins.php reaches its rTorrent
 * user checks only against a daemon that completes the probe, so a harness
 * that needs one on every image needs the serializable form.
 *
 * The answers are the probe fixture's own, so what getplugins.php is told
 * about the daemon here is what every other test is told.
 */
class GetpluginsRtorrentResponder
{
	private $uid;
	private $gids;
	private $home;
	private $version;

	/**
	 * $uid and $gids are what execute.capture reports as the rTorrent user,
	 * which is the identity the badTempPath2 family of checks asks about.
	 * $version is what system.client_version reports; null keeps the probe
	 * fixture's own.
	 */
	public function __construct($uid = 1000, $gids = '1000 1001', $home = '/home/rtorrent', $version = null)
	{
		$this->uid = $uid;
		$this->gids = $gids;
		$this->home = $home;
		$this->version = $version;
	}

	public function __invoke($body, $methods = array())
	{
		$names = RtorrentProbeFixture::methodNames($body);
		$single = (count($names) === 1) ? $names[0] : '';
		if($single === 'execute.capture')
			return array($this->uid . "\n" . $this->gids . "\n" . $this->home . "\n");
		if(($single === 'system.client_version') && ($this->version !== null))
			return array($this->version);
		$responder = RtorrentProbeFixture::responder();
		return $responder($body);
	}
}
