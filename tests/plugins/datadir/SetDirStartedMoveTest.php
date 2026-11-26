<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * A download that is started but not yet active -- queued for or in its hash
 * check -- is started again after "Set data directory" moves it.
 *
 * rTorrent reports such a download with d.state 1 and d.is_active 0: it starts
 * once the check is done. d.close ends the check and leaves d.state at 1, and
 * d.start does nothing to a download whose state is already 1. Unless the run
 * stops it first and starts it after the move, the download is left started,
 * inactive and never checked, until rtorrent is restarted.
 *
 * FakeRtorrentDaemon is the rtorrent on the other end; every call asserted
 * here is one it was asked for.
 */
class SetDirStartedMoveTest extends TestCase
{
    public const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-setdir-started-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        $this->removeTree($this->base);
    }

    /**
     * The control: a running download is stopped, moved and started.
     */
    public function testARunningDownloadIsStoppedAndStartedAgain()
    {
        $r = $this->setTheDataDirectory(1, 1, 1);

        $this->assertTrue(
            in_array('d.stop', $r['before_move'], true),
            'a running download is stopped before the move; calls: ' . implode(', ', $r['calls']),
        );
        $this->assertTrue(
            in_array('d.start', $r['after_close'], true),
            'and started after it; calls: ' . implode(', ', $r['calls']),
        );
    }

    /**
     * The download is open and waiting for its hash check: started, not
     * active. It has to be stopped before it is closed, and started again.
     */
    public function testAnOpenDownloadWaitingForItsHashCheckIsStartedAgain()
    {
        $r = $this->setTheDataDirectory(1, 0, 1);

        $this->assertEquals([true, true], $r['at_dest'], 'the files are moved');
        $this->assertTrue(
            in_array('d.state', $r['before_move'], true),
            'whether it is started is asked before it is closed; calls: '
            . implode(', ', $r['calls']),
        );
        $this->assertTrue(
            in_array('d.stop', $r['before_move'], true),
            'the started download is stopped before it is closed; calls: '
            . implode(', ', $r['calls']),
        );
        $this->assertTrue(
            in_array('d.start', $r['after_close'], true),
            'and started again after the move; calls: ' . implode(', ', $r['calls']),
        );
    }

    /**
     * The same before rtorrent has opened it: a closed download in the hash
     * queue is opened only to read its paths, and is still started after.
     */
    public function testAClosedDownloadWaitingForItsHashCheckIsStartedAgain()
    {
        $r = $this->setTheDataDirectory(0, 0, 1);

        $this->assertTrue(
            in_array('d.stop', $r['before_move'], true),
            'the started download is stopped before the move; calls: '
            . implode(', ', $r['calls']),
        );
        $this->assertTrue(
            in_array('d.start', $r['after_close'], true),
            'and started again after it; calls: ' . implode(', ', $r['calls']),
        );
    }

    /**
     * A stopped download stays stopped.
     */
    public function testAStoppedDownloadIsNotStarted()
    {
        $r = $this->setTheDataDirectory(1, 0, 0);

        $this->assertTrue(
            !in_array('d.start', $r['calls'], true),
            'a stopped download is not started by the move; calls: ' . implode(', ', $r['calls']),
        );
        $this->assertTrue(
            !in_array('d.stop', $r['calls'], true),
            'nor stopped; calls: ' . implode(', ', $r['calls']),
        );
    }

    /**
     * rTorrent reports a download it is still adding as stopped for a moment
     * and starts it after. A start that lands while the files are moved is
     * ended by the run's d.close and leaves the download started, inactive
     * and not checking: the run looks again at the end and restarts it.
     */
    public function testAStartThatLandedDuringTheMoveIsRestarted()
    {
        $r = $this->setTheDataDirectory(1, 0, 0, [1, 0, 0]);

        $this->assertTrue(
            in_array('d.stop', $r['after_close'], true) && in_array('d.start', $r['after_close'], true),
            'a download started during the run is stopped and started; calls: '
            . implode(', ', $r['calls']),
        );
    }

    /**
     * And left alone when rtorrent is already checking it: that start landed
     * after the run let go of the download, and is going ahead.
     */
    public function testADownloadThatIsBeingCheckedAfterTheMoveIsLeftAlone()
    {
        $r = $this->setTheDataDirectory(1, 0, 0, [1, 0, 1]);

        $this->assertTrue(
            !in_array('d.start', $r['calls'], true) && !in_array('d.stop', $r['calls'], true),
            'a download rtorrent is checking is not restarted; calls: ' . implode(', ', $r['calls']),
        );
    }

    private function removeTree($path)
    {
        if (is_link($path) || !is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (glob($path . '/*') as $entry) {
            $this->removeTree($entry);
        }
        @rmdir($path);
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }

    /**
     * Runs plugins/datadir/setdir.php (no fast resume) for a download that
     * rtorrent reports as $isOpen, $isActive and $state (and as $late, its
     * state, activity and hashing, when the run looks again), and returns the calls
     * the daemon was asked for and what is at the destination afterwards.
     */
    private function setTheDataDirectory($isOpen, $isActive, $state, $late = [0, 0, 0])
    {
        $this->removeTree($this->base);
        $names = ['1.bin', '2.bin'];
        @mkdir($this->base . '/profile/settings', 0700, true);
        @mkdir($this->base . '/src/mytorrent', 0777, true);
        @mkdir($this->base . '/dst', 0777, true);
        foreach ($names as $n) {
            file_put_contents($this->base . '/src/mytorrent/' . $n, 'download ' . $n);
        }

        // One reply per request, in order: the run state; d.open for a closed
        // download; name, base path, base filename, multi file, complete (0:
        // not checked yet); f.multicall; d.stop and/or d.close; d.set_directory;
        // the restart, or the open (and close); the run state at the end, for
        // a download that was not started ($late); and spares.
        $replies = [ [$isOpen, $isActive, $state] ];
        if (!$isOpen) {
            $replies[] = [0];
        }
        $replies[] = ['mytorrent', $this->base . '/src/mytorrent', 'mytorrent', 1, 0];
        $replies[] = $names;
        $replies[] = [0, 0];
        $replies[] = [0];
        $replies[] = [0, 0];
        $replies[] = $late;
        for ($i = 0; $i < 4; $i++) {
            $replies[] = [0, 0];
        }
        RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/tester');
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');

        $driver = $this->base . '/drive-setdir.php';
        file_put_contents($driver, "<?php\n"
            . '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1";' . "\n"
            . '$scgi_port = ' . $this->daemon->port() . ";\n"
            . '$rpcTimeOut = 10; $rpcLogCalls = false;' . "\n"
            . 'require(' . var_export($this->repoRoot() . '/plugins/datadir/setdir.php', true) . ");\n");

        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($driver)
            . ' ' . escapeshellarg(self::HASH)
            . ' ' . escapeshellarg($this->base . '/dst')
            . ' 1 1 0 tester';
        $out = [];
        exec($cmd . ' 2>&1', $out);

        $calls = $this->daemon->calls();
        $last = -1;
        foreach ($calls as $index => $call) {
            if ($call === 'd.close') {
                $last = $index;
            }
        }
        return [
            'calls' => $calls,
            'before_move' => $last < 0 ? $calls : array_slice($calls, 0, $last + 1),
            'after_close' => $last < 0 ? [] : array_slice($calls, $last + 1),
            'at_dest' => array_map(function ($n) {
                return is_file($this->base . '/dst/mytorrent/' . $n);
            }, $names),
        ];
    }
}
