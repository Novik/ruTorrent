<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');
require_once(__DIR__ . '/../../../php/utility/json.php');

/**
 * rtorrent reports its default directory as its own bytes, and passes a
 * download's base path to the finished hook on argv the same way. check.php
 * and move.php find the download's place below the default directory by
 * comparing the two, and watch.php loads a torrent into the same
 * subdirectory of it, so the settings have to keep the directory as
 * rtorrent reported it: a directory holding a '"' or a '\' is otherwise not
 * the one the download is in. move.php reads the torrent's copy in the
 * session directory, and init.php checks that directory exists, so the same
 * holds for the session directory.
 *
 * The settings cache each script reads is the one the real probe leaves,
 * run against a daemon that reports $defaultDirectory and the session
 * directory.
 */
class DefaultDirectoryBytesTest extends TestCase
{
    public const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
    public const USER = 'tester';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-autotools-defdir-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testTheSettingsKeepTheDefaultDirectoryAsRtorrentReportsIt()
    {
        foreach (['/data', '/da\\ta"', '/a\\"b', '/a\\\\b'] as $directory) {
            $this->fresh($directory);
            $run = $this->runPhp($this->driver('echo rTorrentSettings::get()->directory;'));
            $this->assertEquals($directory, $run['printed'], 'the default directory ' . $directory . ' is kept; read back: '
                . json_encode($run['printed']) . ' ' . $run['errors']);
        }
    }

    public function testAFinishedDownloadIsMovedFromADefaultDirectoryWithAQuoteAndABackslash()
    {
        $this->assertMoved('da\\ta"', 'sub"x');
    }

    public function testAFinishedDownloadIsMovedFromADefaultDirectoryWithABackslashBeforeAQuote()
    {
        $this->assertMoved('a\\"b', 'c\\\\d');
    }

    public function testAFinishedDownloadIsMovedFromAPlainDefaultDirectory()
    {
        $this->assertMoved('dl', 'sub');
    }

    public function testAFinishedDownloadIsMovedWithASessionDirectoryWithAQuoteAndABackslash()
    {
        $this->assertMoved('dl', 'sub', 'se"ss\\ion');
    }

    public function testAFinishedDownloadIsMovedWithASessionDirectoryWithABackslashBeforeAQuote()
    {
        $this->assertMoved('dl', 'sub', 'a\\"b');
    }

    public function testASessionDirectoryWithAQuoteAndABackslashIsFoundAtStartup()
    {
        $run = $this->runInit('se"ss\\ion', true);
        $this->assertTrue(
            (strpos($run['printed'], '|registered=1') !== false) && (strpos($run['printed'], 'webBadSessionWarning') === false),
            'init.php finds the session directory and starts the plugin: ' . $run['printed'] . $run['errors'],
        );
    }

    public function testAMissingSessionDirectoryIsNamedAsOneStringLiteral()
    {
        $session = $this->base . '/go\\ne\'"x/';
        $run = $this->runInit('go\\ne\'"x', false);
        $this->assertTrue(
            strpos($run['printed'], "+theUILang.webBadSessionWarning+' ('+" . JSON::jsValue($session) . "+').'") !== false,
            'init.php names the missing session directory ' . $session . ' as one literal: ' . $run['printed'] . $run['errors'],
        );
    }

    public function testAWatchedTorrentIsLoadedBelowADefaultDirectoryWithAQuoteAndABackslash()
    {
        $this->assertWatched('da\\ta"');
    }

    public function testAWatchedTorrentIsLoadedBelowAPlainDefaultDirectory()
    {
        $this->assertWatched('dl');
    }

    /**
     * A finished multi-file download saved in $sub below the default
     * directory $default, run through check.php and then move.php as rtorrent
     * runs them, with "Move" to fin/: it lands in the same $sub below fin/.
     */
    private function assertMoved($default, $sub, $sessionName = 'session')
    {
        $this->fresh($this->base . '/' . $default, $sessionName);
        $root = 'subx';
        $source = $this->base . '/' . $default . '/' . $sub . '/' . $root;
        $named = $this->base . '/fin/' . $sub . '/' . $root;
        $this->makeDir($source);
        $this->makeDir($this->base . '/fin');
        file_put_contents($source . '/f.bin', 'download f.bin');
        file_put_contents($this->base . '/' . $sessionName . '/' . self::HASH . '.torrent', $this->torrentBytes($root, ['f.bin']));
        $seeded = $this->runPhp($this->driver(
            'require_once("' . $this->repoRoot() . '/plugins/autotools/autotools.php");'
            . ' $at = rAutoTools::load(); $at->enable_move = 1; $at->fileop_type = "Move";'
            . ' $at->path_to_finished = ' . var_export($this->base . '/fin', true) . ';'
            . ' $at->automove_filter = "/.*/"; $at->addLabel = 0; $at->addName = 0; $at->store();'
            . ' echo rAutoTools::load()->path_to_finished;',
        ));
        $this->assertEquals($this->base . '/fin', $seeded['printed'], 'the plugin settings are stored: ' . $seeded['errors']);

        // d.get_base_path, d.get_base_filename, d.is_multi_file, d.get_custom1, d.get_name
        $check = $this->runScript('check', [$source, $root, '1', '', $root, self::USER]);
        // and then, with the hash in front, the same five
        $move = $this->runScript('move', [self::HASH, $source, $root, '1', '', $root, self::USER]);
        $this->assertEquals($named . '/', $check['printed'], 'check.php names fin/' . $sub . '/' . $root . ' for a download in '
            . $default . '/' . $sub . '; it printed ' . json_encode($check['printed']) . ' ' . $check['errors']);
        $this->assertTrue(
            @file_get_contents($named . '/f.bin') === 'download f.bin' && !file_exists($source . '/f.bin'),
            'and move.php carries the file there: ' . $move['printed'] . $move['errors'],
        );
    }

    /**
     * A torrent in watch/sub is loaded into sub below the default directory
     * $default, and that directory is made.
     */
    private function assertWatched($default)
    {
        $this->fresh($this->base . '/' . $default);
        $expected = $this->base . '/' . $default . '/sub/';
        $this->makeDir($this->base . '/' . $default);
        $this->makeDir($this->base . '/watch/sub');
        file_put_contents($this->base . '/watch/sub/t.torrent', $this->torrentBytes('t', ['f.bin']));
        // The mkdir of the directory, then the load.
        $this->daemon = new FakeRtorrentDaemon([['0'], ['0']], $this->base . '/calls.log');
        $run = $this->runPhp($this->driver(
            'require_once("' . $this->repoRoot() . '/plugins/autotools/autotools.php");'
            . ' $at = rAutoTools::load(); $at->enable_watch = 1; $at->watch_start = 0;'
            . ' $at->path_to_watch = ' . var_export($this->base . '/watch', true) . '; $at->store();'
            . ' require(' . var_export($this->repoRoot() . '/plugins/autotools/watch.php', true) . ');',
            $this->daemon->port(),
        ), [self::USER]);
        $bodies = $this->daemon->bodies();
        $load = (string) end($bodies);
        $this->assertTrue(is_dir($expected), 'watch.php makes ' . $expected . ': ' . $run['printed'] . $run['errors']);
        $this->assertTrue(
            strpos($load, htmlspecialchars('d.directory.set=' . rTorrentCommandQuote::arg(rtrim($expected, '/')), ENT_NOQUOTES)) !== false,
            'and loads the torrent into it; requests sent: ' . count($bodies) . ', last: ' . $load,
        );
        $this->daemon->stop();
        $this->daemon = null;
    }

    /**
     * init.php, as the interface loads it, with "Move" to fin/ on and the
     * session directory $sessionName below the test's base, made first when
     * $exists. What it adds to the script is printed.
     */
    private function runInit($sessionName, $exists)
    {
        $this->fresh($this->base . '/dl', $sessionName, $exists);
        $this->makeDir($this->base . '/fin');
        $this->daemon = new FakeRtorrentDaemon(
            RtorrentProbeFixture::responder(),
            $this->base . '/calls.log',
            false,
            RtorrentProbeFixture::replyClassFiles(),
        );
        $run = $this->runPhp($this->driver(
            'require_once("' . $this->repoRoot() . '/plugins/autotools/autotools.php");'
            . ' $at = rAutoTools::load(); $at->enable_move = 1; $at->fileop_type = "Move";'
            . ' $at->path_to_finished = ' . var_export($this->base . '/fin', true) . '; $at->store();'
            . ' $jResult = ""; $do_diagnostic = true; $plugin = ["name" => "autotools"]; $pInfo = ["perms" => 0];'
            . ' $theSettings = rTorrentSettings::get();'
            . ' require(' . var_export($this->repoRoot() . '/plugins/autotools/init.php', true) . ');'
            . ' echo $jResult, "|registered=", $theSettings->isPluginRegistered("autotools") ? 1 : 0;',
            $this->daemon->port(),
        ));
        $this->daemon->stop();
        $this->daemon = null;
        return $run;
    }

    private function fresh($defaultDirectory, $sessionName = 'session', $makeSession = true)
    {
        TestTree::remove($this->base);
        if ($makeSession) {
            $this->makeDir($this->base . '/' . $sessionName);
        }
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache(
                $this->base . '/profile/users/' . self::USER,
                '',
                $defaultDirectory,
                $this->base . '/' . $sessionName . '/',
            ),
            'the settings cache is warmed',
        );
    }

    /** A driver for $code, against the test's profile and a daemon at $port. */
    private function driver($code, $port = 1)
    {
        static $n = 0;
        $file = $this->base . '/drive-' . (++$n) . '.php';
        file_put_contents($file, "<?php\n"
            . '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
            . ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . (int) $port . '; $rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
            . 'require_once(' . var_export($this->repoRoot() . '/php/rtorrent.php', true) . ");\n"
            . $code . "\n");
        return $file;
    }

    private function runScript($which, $args)
    {
        return $this->runPhp($this->driver(
            'require(' . var_export($this->repoRoot() . '/plugins/autotools/' . $which . '.php', true) . ');',
        ), $args);
    }

    private function runPhp($file, $args = [])
    {
        $mask = umask(022);
        try {
            $run = PhpProcess::run(
                array_merge(PhpProcess::sameIni(), ['-d', 'display_errors=stderr', $file], $args),
                ['env' => ['LC_ALL' => 'C']],
            );
        } finally {
            umask($mask);
        }
        return ['exit' => $run['timedOut'] ? 'timed out' : $run['code'], 'printed' => $run['out'], 'errors' => $run['err']];
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

/** rTorrent::quoteCommandArg(), without loading rtorrent.php into the test. */
class rTorrentCommandQuote
{
    public static function arg($value)
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
