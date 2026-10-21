<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * "Remove and delete data" is two steps in two processes. removewithdata.php
 * asks rtorrent to d.set_custom5, d.delete_tied and d.erase the download and,
 * once that is confirmed, publishes <hash>.list2 -- the files to unlink, the
 * base path, the multi-file flag and the deletion mode. update.php, started
 * by rtorrent's schedule every $garbageCheckInterval seconds, applies the
 * lists it finds and removes them.
 *
 * What this pins is that neither step deletes the data of a download rtorrent
 * still holds. The collector deletes what a list names once rtorrent no
 * longer lists the hash, so a list must only exist for an erase rtorrent
 * confirmed: the writer publishes nothing when the reply never came or was a
 * fault. And the collector asks rtorrent which hashes it holds before each
 * list it applies, because the same hash can be loaded again before its pass
 * or in the middle of one: such a list is withdrawn and the data kept, and
 * when rtorrent does not answer, the pass stops and the lists wait.
 *
 * Each case drives the shipped writer against a daemon scripted for one
 * outcome, checks that the sequence really happened as described, and then
 * runs the shipped collector as production runs it: its own process, over the
 * real settings directory, against the same daemon.
 *
 * The daemon is a scripted SCGI listener (tests/php/FakeRtorrentDaemon.php); a
 * reply entry answers one request, and a system.multicall is one request.
 */
class UnconfirmedEraseTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
	/** Another download the daemon may hold; never the one being erased. */
	const OTHER = '89ABCDEF0123456789ABCDEF0123456789ABCDEF';

	private $base;
	private $daemon;
	private $port;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-unconfirmed-erase-' . getmypid();
		$this->removeTree($this->base);
		@mkdir($this->base . '/profile/settings', 0700, true);
	}

	public function tearDown()
	{
		$this->stopDaemon();
		$this->removeTree($this->base);
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
			return;
		}
		foreach (array_diff(scandir($path), array('.', '..')) as $name) {
			$entry = $path . '/' . $name;
			is_dir($entry) && !is_link($entry) ? $this->removeTree($entry) : @unlink($entry);
		}
		@rmdir($path);
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	private function listPath()
	{
		return $this->base . '/profile/settings/erasedata';
	}

	/**
	 * setUp() runs once for the whole class, so every case starts from its
	 * own download, an empty list directory and an empty log.
	 */
	private function fresh()
	{
		$this->stopDaemon();
		$this->removeTree($this->listPath());
		$this->removeTree($this->base . '/data');
		@unlink($this->base . '/calls.log');
		@unlink($this->base . '/calls.log.bodies');
		@unlink($this->base . '/app.log');
	}

	/** A multi-file download on disk, as rtorrent would have written it. */
	private function download($name = 'Download')
	{
		$base = $this->base . '/data/' . $name;
		@mkdir($base . '/sub', 0700, true);
		file_put_contents($base . '/one.bin', 'one');
		file_put_contents($base . '/sub/two.bin', 'two');
		return array(
			'base' => $base,
			'files' => array($base . '/one.bin', $base . '/sub/two.bin'),
		);
	}

	/**
	 * What the daemon answers the writer's first request -- the base path,
	 * the multi-file flag and f.frozen_path of every file, in one multicall.
	 */
	private function pathsReply($download)
	{
		return array_merge(array($download['base'], 1), $download['files']);
	}

	/** What the daemon answers the erase request: one value per command. */
	private function eraseReply()
	{
		return array(0, 0, 0);
	}

	private function startDaemon($replies)
	{
		$this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
		// Kept apart from the daemon, so a collector can be pointed at the
		// address of a daemon that has since gone away.
		$this->port = $this->daemon->port();
	}

	/**
	 * A script carrying the production configuration pointed at this test's
	 * profile and daemon; the stub stands in for the settings cache that a
	 * running instance would have filled from the daemon.
	 */
	private function preamble()
	{
		$root = $this->repoRoot();
		return "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. '$_ENV[\'RU_LOG_FILE\'] = ' . var_export($this->base . '/app.log', true) . ";\n"
			. 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = ' . (int)$this->port . ";\n"
			. '$rpcTimeOut = 5; $rpcLogCalls = false; $rpcLogFaults = false;' . "\n"
			. 'chdir(' . var_export($root . '/php', true) . "); require_once('xmlrpc.php');\n"
			. '$__r = new ReflectionClass("rTorrentSettings");'
			. ' $__s = $__r->newInstanceWithoutConstructor();'
			. ' $__s->iVersion = 0x904; $__s->aliases = array(); $__s->plugins = array();'
			. ' $__s->directory = ' . var_export($this->base, true) . '; $__s->linkExist = true;'
			. ' $__p = $__r->getProperty("theSettings");'
			// Before 8.1 a private static property cannot be written through
			// reflection without lifting its visibility first; from 8.1 the call
			// has no effect, and on 8.5 its deprecation notice would land in the
			// driver output the cases compare. The driver runs under PHP_BINARY,
			// so this process's version is the driver's.
			. (PHP_VERSION_ID < 80100 ? ' $__p->setAccessible(true);' : '')
			. ' $__p->setValue(null, $__s);' . "\n";
	}

	private function run_($driver, $options = '')
	{
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 ' . $options . ' '
			. escapeshellarg($driver) . ' 2>&1', $output);
		return implode("\n", $output);
	}

	/**
	 * erasedataRemoveWithData() for one hash, as plugins/httprpc/action.php
	 * and plugins/erasedata/action.php call it for "Remove and delete data".
	 * Answers "erased" or "refused": what the caller reports to the user.
	 */
	private function removeWithData($socketTimeout = 60, $hash = self::HASH)
	{
		$root = $this->repoRoot();
		$driver = $this->base . '/drive-remove.php';
		file_put_contents($driver, $this->preamble()
			. 'require_once(' . var_export($root . '/plugins/erasedata/removewithdata.php', true) . ");\n"
			. '$__result = erasedataRemoveWithData(array(' . var_export($hash, true) . '), "1");' . "\n"
			. 'echo ($__result === false) ? "refused" : "erased";' . "\n");
		// rSCGITransport::sendConfigured() sets the transfer budget to
		// max(default_socket_timeout, $rpcTimeOut): config.php leaves
		// $rpcTransferTimeOut null, and the preamble sets $rpcTimeOut = 5.
		return $this->run_($driver, '-d default_socket_timeout=' . (int)$socketTimeout);
	}

	/**
	 * plugins/erasedata/update.php, as rtorrent's schedule runs it: a process
	 * of its own, with no argument on a single-user install. It talks to the
	 * daemon too, so it carries the same configuration as the writer.
	 */
	private function collect()
	{
		$root = $this->repoRoot();
		$driver = $this->base . '/drive-collect.php';
		file_put_contents($driver, $this->preamble()
			. 'require(' . var_export($root . '/plugins/erasedata/update.php', true) . ");\n");
		return $this->run_($driver);
	}

	/**
	 * is_file() for a path another process has created or removed since this
	 * one last looked: PHP answers from its stat and realpath caches otherwise,
	 * and on 7.4 and 8.1 that is a list the collector has already unlinked.
	 */
	private function isFile($path)
	{
		clearstatcache(true, $path);
		return is_file($path);
	}

	private function isDir($path)
	{
		clearstatcache(true, $path);
		return is_dir($path);
	}

	private function listPublished($hash = self::HASH)
	{
		return $this->isFile($this->listPath() . '/' . $hash . '.list2');
	}

	/** Every file in the list directory, by name. */
	private function listed()
	{
		$names = array();
		foreach ((array)glob($this->listPath() . '/*') as $entry) {
			$names[] = basename($entry);
		}
		return $names;
	}

	private function logged()
	{
		$log = $this->base . '/app.log';
		return $this->isFile($log) ? file_get_contents($log) : '';
	}

	// -- the erase never reached the daemon -----------------------------------

	public function testNothingIsPublishedForAnEraseTheDaemonNeverAnswered()
	{
		$this->fresh();
		$download = $this->download();
		// One reply: the paths. The daemon's worker then closes its listener
		// and exits, so the erase request meets a refused connection -- a
		// daemon that stopped between the two requests. No timeout is
		// reached. To the writer, the download is still loaded.
		$this->startDaemon(array($this->pathsReply($download)));

		$verdict = $this->removeWithData(2);

		$this->assertTrue($verdict === 'refused',
			'the writer tells the caller the removal did not happen: ' . $verdict);
		$this->assertTrue(!$this->daemon->received('d.erase'),
			'and the daemon never saw the erase');
		$this->assertTrue(!$this->listPublished(),
			'so no list authorises deleting the files');
		$this->assertTrue($this->listed() === array(),
			'and nothing staged is left behind: ' . implode(' ', $this->listed()));
		$this->assertTrue(strpos($this->logged(), 'did not confirm the erase of ' . self::HASH) !== false,
			'the log says the erase was not confirmed, and where the data is: ' . $this->logged());

		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		foreach ($download['files'] as $file) {
			$this->assertTrue($this->isFile($file),
				'a file of a download the daemon still holds is still there: ' . basename($file));
		}
	}

	// -- the daemon refused the erase -----------------------------------------

	public function testNothingIsPublishedForAnEraseTheDaemonRefused()
	{
		$this->fresh();
		$download = $this->download();
		// A fault makes the writer report failure. It cannot tell which of
		// the three calls ran; here none did. The third reply is for a
		// collector that asks about hashes; one with nothing to apply does
		// not ask, and the reply stays unconsumed.
		$this->startDaemon(array(
			$this->pathsReply($download),
			FakeRtorrentDaemon::fault('Could not erase download.'),
			array(self::HASH),
		));

		$verdict = $this->removeWithData();

		$this->assertTrue($verdict === 'refused',
			'the writer tells the caller the removal did not happen: ' . $verdict);
		$this->assertTrue($this->daemon->received('d.erase'),
			'the daemon saw the erase and answered it with a fault');
		$this->assertTrue(!$this->listPublished(),
			'so no list authorises deleting the files');
		$this->assertTrue($this->listed() === array(),
			'and nothing staged is left behind: ' . implode(' ', $this->listed()));

		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(!$this->daemon->received('d.multicall'),
			'with nothing to apply, the collector does not ask the daemon');
		foreach ($download['files'] as $file) {
			$this->assertTrue($this->isFile($file),
				'a file of a download the daemon still holds is still there: ' . basename($file));
		}
	}

	// -- the same hash is loaded again before the collector's pass -------------

	public function testAListWhoseHashIsLoadedAgainIsWithdrawnAndTheDataKept()
	{
		$this->fresh();
		$download = $this->download();
		// The erase succeeds and the list is published. Before the collector's
		// next pass -- up to $garbageCheckInterval seconds later, longer when
		// a pass is skipped as busy -- the torrent is added again to the same
		// directory: a removal noticed as a mistake, a watch directory, an
		// RSS rule. rtorrent finds the files on disk and lists the hash
		// again; the third reply is what it tells the collector.
		$this->startDaemon(array(
			$this->pathsReply($download),
			$this->eraseReply(),
			array(self::OTHER, self::HASH),
		));

		$verdict = $this->removeWithData();

		$this->assertTrue($verdict === 'erased',
			'the writer tells the caller the removal happened: ' . $verdict);
		$this->assertTrue($this->daemon->received('d.erase'), 'the daemon erased the download');
		$this->assertTrue($this->listPublished(), 'and the list is published');

		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue($this->daemon->received('d.multicall'),
			'the collector asked the daemon which hashes it holds');
		$this->assertTrue(!$this->listPublished(), 'and withdrew the list');
		foreach ($download['files'] as $file) {
			$this->assertTrue($this->isFile($file),
				'a file of a download the daemon holds again is still there: ' . basename($file));
		}
		$this->assertTrue(strpos($this->logged(),
			'erasedata: ' . self::HASH . ' is loaded again; list withdrawn, data kept') !== false,
			'and the log says so: ' . $this->logged());
	}

	// -- the daemon does not answer the collector ------------------------------

	public function testNothingIsDeletedWhenTheDaemonDoesNotAnswerTheCollector()
	{
		$this->fresh();
		$download = $this->download();
		$this->startDaemon(array($this->pathsReply($download), $this->eraseReply()));

		$verdict = $this->removeWithData();

		$this->assertTrue($verdict === 'erased',
			'the writer tells the caller the removal happened: ' . $verdict);
		$this->assertTrue($this->listPublished(), 'and the list is published');
		// rtorrent is down when the collector's pass comes: restarting,
		// crashed, its socket refused. Whether the download has been loaded
		// again is unknown, and the collector may not guess.
		$this->stopDaemon();

		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue($this->listPublished(),
			'the list is kept for a pass the daemon answers');
		foreach ($download['files'] as $file) {
			$this->assertTrue($this->isFile($file),
				'and nothing is deleted on an unknown answer: ' . basename($file));
		}
		$this->assertTrue(strpos($this->logged(), 'rtorrent did not answer, 1 list(s) kept') !== false,
			'and the log says the pass was skipped: ' . $this->logged());
	}

	public function testNothingIsDeletedWhenTheDaemonRefusesTheCollectorsQuestion()
	{
		$this->fresh();
		$download = $this->download();
		// rtorrent answers the collector's question with a fault -- what a
		// daemon that does not know the command as spelled does -- which is
		// as unknown an answer as none, and is told apart in the log so that
		// a line every pass can be read for what it is.
		$this->startDaemon(array(
			$this->pathsReply($download),
			$this->eraseReply(),
			FakeRtorrentDaemon::fault('Unsupported target type found.'),
		));

		$verdict = $this->removeWithData();

		$this->assertTrue($verdict === 'erased',
			'the writer tells the caller the removal happened: ' . $verdict);
		$this->assertTrue($this->listPublished(), 'and the list is published');

		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue($this->listPublished(), 'the list is kept for a pass the daemon answers');
		foreach ($download['files'] as $file) {
			$this->assertTrue($this->isFile($file),
				'and nothing is deleted on an unknown answer: ' . basename($file));
		}
		$this->assertTrue(strpos($this->logged(), 'rtorrent answered with a fault, 1 list(s) kept') !== false,
			'and the log says the question was refused: ' . $this->logged());
	}

	// -- a hash is loaded again in the middle of a pass -------------------------

	public function testAHashLoadedAgainBetweenTwoListsOfOnePassIsSeen()
	{
		$this->fresh();
		$first = $this->download('First');
		$second = $this->download('Second');
		// Two downloads erased, two lists. rtorrent is asked before each list,
		// not once per pass: it holds nothing when asked the first time, and
		// both hashes when asked the second time. The order the collector
		// reads the directory in is the filesystem's, so what is pinned is
		// that exactly one list is applied and the other withdrawn, and that
		// the withdrawn one is the download whose files are still there.
		$this->startDaemon(array(
			$this->pathsReply($first),
			$this->eraseReply(),
			$this->pathsReply($second),
			$this->eraseReply(),
			array(),
			array(self::HASH, self::OTHER),
		));

		$this->assertTrue($this->removeWithData(60, self::HASH) === 'erased', 'the first removal happened');
		$this->assertTrue($this->removeWithData(60, self::OTHER) === 'erased', 'and the second');
		$this->assertTrue($this->listPublished(self::HASH) && $this->listPublished(self::OTHER),
			'and both lists are published');

		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(count(array_keys($this->daemon->calls(), 'd.multicall', true)) === 2,
			'the collector asked the daemon once per list: ' . implode(' ', $this->daemon->calls()));
		$this->assertTrue(!$this->listPublished(self::HASH) && !$this->listPublished(self::OTHER),
			'both lists are gone');
		$withdrawn = array();
		if (preg_match_all('/erasedata: ([0-9A-F]{40}) is loaded again; list withdrawn, data kept/',
				$this->logged(), $m)) {
			$withdrawn = $m[1];
		}
		$this->assertTrue(count($withdrawn) === 1,
			'exactly one list was withdrawn: ' . $this->logged());
		$kept = ($withdrawn === array(self::OTHER)) ? $second : $first;
		$deleted = ($withdrawn === array(self::OTHER)) ? $first : $second;
		foreach ($kept['files'] as $file) {
			$this->assertTrue($this->isFile($file),
				'the download loaded again in the middle of the pass keeps its files: ' . basename($file));
		}
		foreach ($deleted['files'] as $file) {
			$this->assertTrue(!$this->isFile($file),
				'and the one applied before the answer changed is deleted: ' . basename($file));
		}
	}

	// -- the erase is confirmed and the hash stays gone -------------------------

	public function testAConfirmedEraseIsCollectedOnceTheHashIsGone()
	{
		$this->fresh();
		$download = $this->download();
		// At the collector's pass the daemon holds another download, not
		// this one.
		$this->startDaemon(array(
			$this->pathsReply($download),
			$this->eraseReply(),
			array(self::OTHER),
		));

		$verdict = $this->removeWithData();

		$this->assertTrue($verdict === 'erased',
			'the writer tells the caller the removal happened: ' . $verdict);
		$this->assertTrue($this->daemon->calls() === array(
			'd.get_base_path', 'd.is_multi_file', 'f.multicall',
			'd.set_custom5', 'd.delete_tied', 'd.erase'),
			'the paths are read first, then the download is erased in one request: '
			. implode(' ', $this->daemon->calls()));
		$this->assertTrue($this->listPublished(), 'and the list is published');

		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		foreach ($download['files'] as $file) {
			$this->assertTrue(!$this->isFile($file),
				'a file of the erased download is deleted: ' . basename($file));
		}
		$this->assertTrue(!$this->isDir($download['base']), 'and so is its directory');
		$this->assertTrue(!$this->listPublished(), 'and the list is consumed');
		$this->assertTrue($this->logged() === '', 'with nothing to report: ' . $this->logged());
	}
}
