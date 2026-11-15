<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * A fast resume re-adds the download from its copy in the session directory,
 * whose path rtSetDataDir() asks rtorrent for. rtorrent reports it as its
 * own bytes, so a session directory holding a '"' or a '\' still has to be
 * where the copy is read from, and the same goes for the file the download
 * is tied to, read when the session holds no copy. When neither can be read
 * the move falls back to a plain d.set_directory, which is how a wrong path
 * shows.
 */
class SetDirFastResumeSessionTest extends TestCase
{
    public const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
    public const USER = 'tester';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-datadir-fastresume-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testAFastResumeReadsTheCopyInASessionDirectoryWithAQuoteAndABackslash()
    {
        $this->assertFastResumed('se"ss\\ion');
    }

    public function testAFastResumeReadsTheCopyInASessionDirectoryWithABackslashBeforeAQuote()
    {
        $this->assertFastResumed('a\\"b');
    }

    public function testAFastResumeReadsTheCopyInAPlainSessionDirectory()
    {
        $this->assertFastResumed('session');
    }

    public function testAFastResumeReadsATiedFileWithAQuoteAndABackslashWhenTheSessionHoldsNoCopy()
    {
        $this->assertFastResumed('session', 'ti"ed\\x/t.torrent');
    }

    /**
     * The download's copy is in $sessionName, or, given $tiedName, only in
     * that file below the test's base.
     */
    private function assertFastResumed($sessionName, $tiedName = null)
    {
        TestTree::remove($this->base);
        $session = $this->base . '/' . $sessionName . '/';
        $dest = $this->base . '/dest';
        $copy = ($tiedName === null) ? $session . self::HASH . '.torrent' : $this->base . '/' . $tiedName;
        $tied = ($tiedName === null) ? '' : $copy;
        $this->makeDir($session);
        $this->makeDir($dest);
        $this->makeDir(dirname($copy));
        file_put_contents($copy, $this->torrentBytes('subx', ['f.bin']));
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/' . self::USER, '', $this->base . '/dl', $session),
            'the settings cache is warmed',
        );
        $this->daemon = new FakeRtorrentDaemon([
            // d.is_open, d.is_active
            ['0', '0'],
            // get_session, d.get_tied_to_file, d.get_custom1, d.get_connection_seed, d.get_throttle_name
            [$session, $tied, '', '', ''],
            // d.set_directory, then d.erase, then the load; or, after a
            // failed fast resume, d.set_directory and d.open, d.close.
            ['0'], ['0'], ['0'], ['0'],
        ], $this->base . '/calls.log');
        $run = $this->runPhp($this->driver(
            'echo rtSetDataDir(' . var_export(self::HASH, true) . ', ' . var_export($dest, true) . ', true, false, true, false) ? "ok" : "failed";',
            $this->daemon->port(),
        ));
        $bodies = implode("\n", $this->daemon->bodies());
        $this->daemon->stop();
        $this->daemon = null;
        $this->assertEquals('ok', $run['printed'], 'rtSetDataDir() succeeds: ' . $run['printed'] . $run['errors']);
        $this->assertTrue(
            (strpos($bodies, '<methodName>d.erase</methodName>') !== false)
                && (strpos($bodies, '<string>load.raw_start</string>') !== false),
            'and re-adds the download from ' . $copy . '; requests sent: ' . $bodies,
        );
    }

    private function driver($code, $port)
    {
        $file = $this->base . '/drive.php';
        file_put_contents($file, "<?php\n"
            . '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
            . ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . (int) $port . '; $rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
            // util_setdir.php's own requires are relative to the plugin's directory.
            . 'chdir(' . var_export($this->repoRoot() . '/plugins/datadir', true) . ");\n"
            . 'require_once(' . var_export($this->repoRoot() . '/plugins/datadir/util_setdir.php', true) . ");\n"
            . $code . "\n");
        return $file;
    }

    private function runPhp($file)
    {
        $mask = umask(022);
        try {
            $run = PhpProcess::run(
                array_merge(PhpProcess::sameIni(), ['-d', 'display_errors=stderr', $file]),
                ['env' => ['LC_ALL' => 'C']],
            );
        } finally {
            umask($mask);
        }
        return ['printed' => $run['out'], 'errors' => $run['err']];
    }

    private function makeDir($dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            throw new RuntimeException('Could not create fixture directory ' . $dir);
        }
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }

    private function bstr($s)
    {
        return strlen($s) . ':' . $s;
    }

    private function torrentBytes($root, $names)
    {
        $files = '';
        foreach ($names as $n) {
            $files .= 'd' . $this->bstr('length') . 'i12e' . $this->bstr('path') . 'l' . $this->bstr($n) . 'e' . 'e';
        }
        return 'd' . $this->bstr('announce') . $this->bstr('http://tracker.invalid/announce')
            . $this->bstr('info') . 'd' . $this->bstr('files') . 'l' . $files . 'e'
            . $this->bstr('name') . $this->bstr($root)
            . $this->bstr('piece length') . 'i32768e'
            . $this->bstr('pieces') . $this->bstr(str_repeat("\x01", 20)) . 'e' . 'e';
    }
}
