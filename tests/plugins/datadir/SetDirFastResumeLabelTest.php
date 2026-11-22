<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * A fast resume erases the download and loads it again with the label it
 * had. rtorrent reports the label as its own bytes, and rXMLRPCRequest::run()
 * returns it with each backslash doubled and a backslash before each double
 * quote, so the label has to be put back into its own bytes before it is
 * URL-decoded, or the reloaded download carries a label it never had.
 */
class SetDirFastResumeLabelTest extends TestCase
{
    public const HASH = '9485646D4F5F05AC4428057712E46FC8189DD1DD';
    public const USER = 'tester';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-datadir-label-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testAPlainLabelIsKept()
    {
        $this->assertEquals('Plain', $this->reloadedLabel('Plain'));
    }

    public function testALabelWithAQuoteIsKept()
    {
        $this->assertEquals(rawurlencode('Foo"Bar'), $this->reloadedLabel('Foo"Bar'));
    }

    public function testALabelWithABackslashIsKept()
    {
        $this->assertEquals(rawurlencode('C\\D'), $this->reloadedLabel('C\\D'));
    }

    public function testALabelRuTorrentEncodedIsKept()
    {
        $this->assertEquals('TV%20Shows', $this->reloadedLabel('TV%20Shows'));
    }

    public function testAnEncodedLabelIsDecodedAfterItIsUnescaped()
    {
        $this->assertEquals('a%5C%22z', $this->reloadedLabel('a%5C%22z'));
    }

    /**
     * The value the reload sets d.custom1 to, for a download whose d.custom1
     * rtorrent reports as $label.
     */
    private function reloadedLabel($label)
    {
        TestTree::remove($this->base);
        $session = $this->base . '/session/';
        $dest = $this->base . '/dest';
        $this->makeDir($session);
        $this->makeDir($dest);
        file_put_contents($session . self::HASH . '.torrent', $this->torrentBytes('subx', ['f.bin']));
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/' . self::USER, '', $this->base . '/dl', $session),
            'the settings cache is warmed',
        );
        $this->daemon = new FakeRtorrentDaemon([
            // d.is_open, d.is_active
            ['0', '0'],
            // get_session, d.get_tied_to_file, d.get_custom1, d.get_connection_seed, d.get_throttle_name
            [$session, '', $label, '', ''],
            // d.set_directory, d.erase, the load
            ['0'], ['0'], ['0'],
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
            preg_match('|<string>load\.raw_start</string>.*<string>d\.custom1\.set=([^<]*)</string>|s', $bodies, $m) === 1,
            'the download is loaded again with a label; requests sent: ' . $bodies,
        );
        return $m[1];
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
