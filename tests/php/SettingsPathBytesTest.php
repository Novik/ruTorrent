<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/RtorrentProbeFixture.php');
require_once(__DIR__ . '/TestTree.php');

/**
 * rtorrent reports its session directory, and the shell it runs reports the
 * home directory, as their own bytes. The settings hand both on as paths: the
 * session directory to every plugin that reads a torrent's session copy, the
 * home directory to resolve a "~" in a directory rtorrent reported. Either
 * one holding a '"' or a '\' has to reach those readers unchanged.
 *
 * The settings each case reads are the ones the real probe leaves, run
 * against a daemon that reports the directories under test.
 */
class SettingsPathBytesTest extends TestCase
{
    public const USER = 'tester';

    private $base;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-settings-paths-' . getmypid();
    }

    public function tearDown()
    {
        TestTree::remove($this->base);
    }

    public function testTheSettingsKeepTheSessionDirectoryAsRtorrentReportsIt()
    {
        foreach (['/session/', '/se"ss\\ion/', '/a\\"b/', '/a\\\\b/'] as $session) {
            $this->fresh('/data', $session);
            $run = $this->settings('echo $s->session;');
            $this->assertEquals($session, $run['printed'], 'the session directory ' . $session . ' is kept; read back: '
                . json_encode($run['printed']) . ' ' . $run['errors']);
        }
    }

    public function testTheSettingsKeepTheHomeDirectoryAsTheShellReportsIt()
    {
        foreach (['/home/rtorrent', '/ho"me\\x', '/a\\"b'] as $home) {
            $this->fresh('/data', '/session/', $home);
            $run = $this->settings('echo $s->home;');
            $this->assertEquals($home, $run['printed'], 'the home directory ' . $home . ' is kept; read back: '
                . json_encode($run['printed']) . ' ' . $run['errors']);
        }
    }

    public function testADefaultDirectoryBelowTheHomeDirectoryIsResolvedAgainstItsRealBytes()
    {
        $this->fresh('~/dl', '/session/', '/ho"me\\x');
        $run = $this->settings('echo $s->directory;');
        $this->assertEquals('/ho"me\\x/dl', $run['printed'], 'the default directory is ~/dl below the home directory; read back: '
            . json_encode($run['printed']) . ' ' . $run['errors']);
    }

    public function testAReportedDirectoryBelowTheHomeDirectoryIsResolvedAgainstItsRealBytes()
    {
        $this->fresh('/data', '/session/', '/ho"me\\x');
        $run = $this->settings('$topDirectory = "/"; $d = "~/sub"; $s->correctReportedDirectory($d); echo $d;');
        $this->assertEquals('/ho"me\\x/sub', $run['printed'], 'a directory reported as ~/sub is below the home directory; read back: '
            . json_encode($run['printed']) . ' ' . $run['errors']);
    }

    public function testTheStartTimeIsReadFromTheLockInASessionDirectoryWithAQuoteAndABackslash()
    {
        $session = $this->base . '/se"ss\\ion/';
        TestTree::remove($this->base);
        if (!mkdir($session, 0700, true) || !touch($session . 'rtorrent.lock', 1234567890)) {
            throw new RuntimeException('Could not create fixture session directory ' . $session);
        }
        $this->warm('/data', $session, '/home/rtorrent');
        $run = $this->settings('echo $s->started;');
        $this->assertEquals('1234567890', $run['printed'], 'the start time is the mtime of rtorrent.lock in ' . $session
            . '; read back: ' . json_encode($run['printed']) . ' ' . $run['errors']);
    }

    private function fresh($directory, $session, $home = '/home/rtorrent')
    {
        TestTree::remove($this->base);
        $this->warm($directory, $session, $home);
    }

    private function warm($directory, $session, $home)
    {
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/' . self::USER, '', $directory, $session, $home),
            'the settings cache is warmed',
        );
    }

    /** Run $code with $s holding the settings the probe left. */
    private function settings($code)
    {
        $file = $this->base . '/drive.php';
        file_put_contents($file, "<?php\n"
            . '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
            . ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = 1; $rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
            . 'require_once(' . var_export($this->repoRoot() . '/php/xmlrpc.php', true) . ");\n"
            . '$s = rTorrentSettings::get();' . "\n"
            . $code . "\n");
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

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../..');
    }
}
