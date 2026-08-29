<?php

/**
 * Shared by UnpackQuotaTest.php and UnpackQuotaMissingTest.php.
 *
 * They cannot share a process: one has ruTorrent load rQuota out of
 * plugins/quotaspace, the other needs that class never to exist, and a class
 * cannot be undeclared once it is there. php-test.sh runs each test file in a
 * PHP of its own, so a file per case is what keeps the two independent.
 *
 * The runner only executes classes whose immediate parent is TestCase, so a
 * shared base class would silently stop both from running. Hence a trait.
 */
trait UnpackQuotaProbe
{
	private $probeLog;

	private function beginProbe()
	{
		$this->probeLog = sys_get_temp_dir() . '/rutorrent-unpack-quota-' . getmypid() . '.log';
		$GLOBALS['log_file'] = $this->probeLog;
		$GLOBALS['unpack_debug_enabled'] = true;
		@unlink($this->probeLog);
	}

	private function endProbe()
	{
		@unlink($this->probeLog);
		unset($GLOBALS['log_file']);
		$GLOBALS['unpack_debug_enabled'] = false;
	}

	private function quotaspaceRegistered($registered)
	{
		if ($registered) {
			rTorrentSettings::get()->registerPlugin('quotaspace');
		} else {
			rTorrentSettings::get()->unregisterPlugin('quotaspace');
		}
	}

	private function rquotaPath()
	{
		return dirname(__FILE__) . '/../../../plugins/quotaspace/rquota.php';
	}

	/**
	 * The quota gate is the first statement in startSilentTask(), and the first
	 * thing logged after it is "[Auto] Check torrent". A basename that is
	 * neither a directory nor an archive makes the whole remainder of the method
	 * inert -- no task is started and no XMLRPC request is made -- so the log
	 * says precisely whether the gate let the call through.
	 */
	private function silentTaskWasAllowed()
	{
		@unlink($this->probeLog);
		$unpack = new rUnpack();
		$unpack->startSilentTask(
			'/nonexistent/rutorrent-unpack-quota-probe.bin',
			'/nonexistent',
			'',
			'probe',
			str_repeat('0', 40)
		);
		$body = is_file($this->probeLog) ? file_get_contents($this->probeLog) : '';
		return strpos($body, '[Auto] Check torrent') !== false;
	}

	/**
	 * startTask() is the manual entry point. Refused by the quota it answers
	 * with a message of its own; allowed through, it needs an rtorrent that is
	 * not there and falls back to its "Unknown error." default. Either way the
	 * errors array says which side of the gate it ended up on.
	 */
	private function startTaskErrors()
	{
		$unpack = new rUnpack();
		$ret = $unpack->startTask(str_repeat('0', 40), '', '', 0);
		return $ret['errors'];
	}

	private function quotaRefusal()
	{
		return array('Quota limitation was reached. Unpack failed.');
	}
}
