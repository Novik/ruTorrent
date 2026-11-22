<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * rXMLRPCRequest::run() returns every string with its backslashes doubled and
 * a backslash before each double quote, while rtorrent sends a label, a
 * directory, a throttle name and a connection seed as their own bytes.
 *
 * The replacement of an updated torrent loads the new one with the old one's
 * label, directory, throttle and connection seed, so each has to be put back
 * into its own bytes first, or the new torrent is given values the old one
 * never had. The check of the ignore list, from run() and from update.php,
 * has to see the label the torrent has.
 * Also: the directory is checked before the original is stopped.
 */
class ReplacementLabelTest extends TestCase
{
    public const OLD_HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
    public const USER = 'tester';
    public const STE_IGNORED = '8';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-rutracker-label-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testAReplacementKeepsAPlainLabel()
    {
        $this->assertEquals('Plain', $this->replacement('Plain')['label']);
    }

    public function testAReplacementKeepsALabelWithAQuote()
    {
        $this->assertEquals(rawurlencode('Foo"Bar'), $this->replacement('Foo"Bar')['label']);
    }

    public function testAReplacementKeepsALabelWithABackslash()
    {
        $this->assertEquals(rawurlencode('C\\D'), $this->replacement('C\\D')['label']);
    }

    public function testAReplacementKeepsALabelRuTorrentEncoded()
    {
        $this->assertEquals('TV%20Shows', $this->replacement('TV%20Shows')['label']);
    }

    public function testAReplacementDecodesALabelAfterItIsUnescaped()
    {
        $this->assertEquals('a%5C%22z', $this->replacement('a%5C%22z')['label']);
    }

    public function testAReplacementKeepsADirectoryThrottleAndSeedWithAQuoteAndABackslash()
    {
        $sent = $this->replacement('Plain', 'Fo"o\\dir', 'thr"ot\\tle', 'se"ed\\x');
        $this->assertEquals('"' . $this->base . '/dl/Fo\\"o\\\\dir"', $sent['directory']);
        $this->assertEquals($this->base . '/dl/Fo"o\\dir', $sent['mkdir']);
        $this->assertEquals('"thr\\"ot\\\\tle"', $sent['throttle']);
        $this->assertEquals('"se\\"ed\\\\x"', $sent['seed']);
    }

    public function testAReplacementKeepsAPlainDirectoryThrottleAndSeed()
    {
        $sent = $this->replacement('Plain', 'plain', 'slow', 'seed');
        $this->assertEquals('"' . $this->base . '/dl/plain"', $sent['directory']);
        $this->assertEquals('"slow"', $sent['throttle']);
        $this->assertEquals('"seed"', $sent['seed']);
    }

    public function testRunIgnoresALabelWithAQuote()
    {
        $this->assertTrue($this->runIgnores('Foo"Bar', ['Foo"Bar']));
    }

    public function testRunIgnoresALabelWithABackslash()
    {
        $this->assertTrue($this->runIgnores('C\\D', ['C\\D']));
    }

    public function testRunIgnoresAPlainLabel()
    {
        $this->assertTrue($this->runIgnores('radarr', ['radarr']));
    }

    public function testRunIgnoresALabelRuTorrentEncodedByItsName()
    {
        $this->assertTrue($this->runIgnores('TV%20Shows', ['TV Shows']));
    }

    public function testRunIgnoresALabelRuTorrentEncodedAsStored()
    {
        $this->assertTrue($this->runIgnores('TV%20Shows', ['TV%20Shows']));
    }

    public function testRunDecodesALabelAfterItIsUnescaped()
    {
        $this->assertTrue($this->runIgnores('a%5C%22z', ['a\\"z']));
    }

    public function testRunChecksALabelThatIsNotListed()
    {
        $this->assertTrue(!$this->runIgnores('Foo"Bar', ['Foo\\"Bar', 'radarr']), 'a label the list does not name is checked');
    }

    public function testUpdateIgnoresALabelWithAQuote()
    {
        $this->assertTrue($this->updateIgnores('Foo"Bar', ['Foo"Bar']));
    }

    public function testUpdateIgnoresALabelWithABackslash()
    {
        $this->assertTrue($this->updateIgnores('C\\D', ['C\\D']));
    }

    public function testUpdateDecodesALabelAfterItIsUnescaped()
    {
        $this->assertTrue($this->updateIgnores('a%5C%22z', ['a\\"z']));
    }

    public function testUpdateChecksALabelThatIsNotListed()
    {
        $this->assertTrue(!$this->updateIgnores('Foo"Bar', ['Foo\\"Bar', 'radarr']), 'a label the list does not name is checked');
    }

    public function testEmptyReportedDirectoryUsesDefault()
    {
        $sent = $this->replacement('Plain', 'plain', '', '', '');
        $this->assertEquals('"' . $this->base . '/dl"', $sent['directory']);
    }

    public function testOutsideDirectoryIsRefusedBeforeStop()
    {
        $this->assertPreflightRefusal($this->base . '/outside', 'directory-not-reusable');
        $this->assertPreflightRefusal($this->base . '/dl/' . str_repeat('x', 256), 'directory-not-reusable');
        $this->assertPreflightRefusal('~/same', 'directory-not-reusable');
        $this->assertPreflightRefusal('0', 'directory-not-reusable');
        $this->assertPreflightRefusal('symlink', 'directory-not-reusable');
    }

    public function testDirectoryFaultIsRefusedBeforeStop()
    {
        $this->assertPreflightRefusal(FakeRtorrentDaemon::fault('unavailable'), 'directory-unreadable');
    }

    public function testEmptyDirectoryReplyIsRefusedBeforeStop()
    {
        $this->assertPreflightRefusal([], 'directory-unreadable');
    }

    private function assertPreflightRefusal($answer, $reason)
    {
        TestTree::remove($this->base);
        $session = $this->base . '/session/';
        $this->makeDir($session);
        $this->makeDir($this->base . '/dl');
        if ($answer === 'symlink') {
            $this->makeDir($this->base . '/outside');
            $answer = $this->base . '/dl/link"\\name';
            symlink($this->base . '/outside', $answer);
        }
        file_put_contents($session . self::OLD_HASH . '.torrent', $this->torrentBytes('old'));
        $this->warm($session);
        $this->daemon = new FakeRtorrentDaemon([
            FakeRtorrentDaemon::fault('not found'), [$session, ''], [],
            is_array($answer) ? $answer : [$answer],
        ], $this->base . '/calls.log');
        $run = $this->drive('var_export(ruTrackerChecker::createTorrent(' . var_export($this->torrentBytes('new'), true)
            . ', ' . var_export(self::OLD_HASH, true) . '));');
        $calls = implode("\n", $this->daemon->bodies());
        $this->assertEquals('6', $run['printed']);
        $this->assertTrue(strpos($calls, 'd.stop') === false && strpos($calls, 'd.erase') === false, 'no stop or erase on refusal');
        $log = (string) @file_get_contents($this->base . '/errors.log');
        $this->assertTrue(strpos($log, $reason) !== false && strpos($log, self::OLD_HASH) !== false, 'classified refusal identifies torrent');
        $this->daemon->stop();
        $this->daemon = null;
    }

    /**
     * Replace the torrent OLD_HASH, whose d.custom1, directory base below the
     * default directory, throttle and connection seed rtorrent reports as
     * given, by a newer one. Answers with what the load of the new one sets.
     */
    private function replacement($label, $dirName = 'plain', $throttle = '', $seed = '', $preflight = null)
    {
        TestTree::remove($this->base);
        $session = $this->base . '/session/';
        $this->makeDir($session);
        $this->makeDir($this->base . '/dl');
        file_put_contents($session . self::OLD_HASH . '.torrent', $this->torrentBytes('old'));
        $this->warm($session);
        $this->daemon = new FakeRtorrentDaemon([
            // d.hash of the new torrent: not loaded
            FakeRtorrentDaemon::fault('Could not find info-hash.', -501),
            // get_session, d.get_tied_to_file
            [$session, ''],
            // d.views
            [],
            [$preflight === null ? $this->base . '/dl/' . $dirName : $preflight],
            // d.get_directory_base, d.get_custom1, d.get_throttle_name,
            // d.get_connection_seed, d.get_state, d.is_open, d.stop, d.close
            [$preflight === null ? $this->base . '/dl/' . $dirName : $preflight, $label, $throttle, $seed, '0', '0', '0', '0'],
            // execute mkdir -p
            ['0'],
            // the load
            ['0'],
            // the replacement marker of the new torrent: not this run's, so
            // the replacement stops here and the old torrent is left as it was
            ['someone-else'],
        ], $this->base . '/calls.log');
        $run = $this->drive(
            'var_export(ruTrackerChecker::createTorrent(' . var_export($this->torrentBytes('new'), true) . ', '
                . var_export(self::OLD_HASH, true) . '));',
        );
        $bodies = implode("\n", $this->daemon->bodies());
        $this->daemon->stop();
        $this->daemon = null;
        $this->assertTrue(
            preg_match('|<methodName>load\.raw</methodName>.*</methodCall>|s', $bodies, $load) === 1,
            'the new torrent is loaded; output: ' . $run['printed'] . $run['errors'] . ' requests sent: ' . $bodies,
        );
        $sent = [];
        foreach ([
            'label' => '|<string>d\.custom1\.set=([^<]*)</string>|',
            'directory' => '|<string>d\.directory_base\.set=([^<]*)</string>|',
            'throttle' => '|<string>d\.throttle_name\.set=([^<]*)</string>|',
            'seed' => '|<string>d\.connection_seed\.set=([^<]*)</string>|',
        ] as $key => $pattern) {
            $sent[$key] = (preg_match($pattern, $load[0], $m) === 1) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : null;
        }
        $sent['mkdir'] = (preg_match('|<string>mkdir</string>.*?<string>-p</string>.*?<string>([^<]*)</string>|s', $bodies, $m) === 1)
            ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : null;
        return $sent;
    }

    /**
     * Whether run() marks the torrent ignored, given the d.custom1 rtorrent
     * reports for it and the configured ignore list.
     */
    private function runIgnores($label, $ignoreLabels)
    {
        TestTree::remove($this->base);
        $this->warm($this->base . '/session/');
        $this->daemon = new FakeRtorrentDaemon([
            // d.hash
            [self::OLD_HASH],
            // chk-state, chk-time, chk-stime, d.get_custom1
            ['0', '0', '0', $label],
            // the state written next
            ['0', '0'],
        ], $this->base . '/calls.log');
        $this->drive(
            '$ignoreLabels = ' . var_export($ignoreLabels, true) . ";\n"
                . 'ruTrackerChecker::run(' . var_export(self::OLD_HASH, true) . ');',
        );
        return $this->markedIgnored();
    }

    /**
     * Whether update.php marks the torrent ignored, given the d.custom1
     * rtorrent reports for it and the configured ignore list.
     */
    private function updateIgnores($label, $ignoreLabels)
    {
        TestTree::remove($this->base);
        $this->warm($this->base . '/session/');
        $this->daemon = new FakeRtorrentDaemon([
            // the seeding view: hash, chk-state, chk-time, chk-stime, d.get_custom1, trackers
            [self::OLD_HASH, '0', '0', '0', $label, 'http://bt.t-ru.org/ann?magnet#'],
            // d.hash
            [self::OLD_HASH],
            // the state written next
            ['0', '0'],
        ], $this->base . '/calls.log');
        $this->drive(
            '$ignoreLabels = ' . var_export($ignoreLabels, true) . ";\n"
                . 'require ' . var_export($this->repoRoot() . '/plugins/rutracker_check/update.php', true) . ';',
        );
        return $this->markedIgnored();
    }

    private function markedIgnored()
    {
        $bodies = implode("\n", $this->daemon->bodies());
        $this->daemon->stop();
        $this->daemon = null;
        return preg_match(
            '|<string>chk-state</string></value>\s*<value><string>' . self::STE_IGNORED . '</string>|',
            $bodies,
        ) === 1;
    }

    private function warm($session)
    {
        $this->makeDir($session);
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/' . self::USER, '', $this->base . '/dl', $session, $this->base . '/dl'),
            'the settings cache is warmed',
        );
    }

    private function drive($code)
    {
        $file = $this->base . '/drive.php';
        file_put_contents($file, "<?php\n"
            . '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
            . ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . (int) $this->daemon->port() . '; $rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
            // check.php's own requires are relative to the plugin's directory.
            . 'chdir(' . var_export($this->repoRoot() . '/plugins/rutracker_check', true) . ");\n"
            . 'require_once(' . var_export($this->repoRoot() . '/plugins/rutracker_check/check.php', true) . ");\n"
            . '$topDirectory = ' . var_export($this->base . '/dl/', true) . '; $log_file = ' . var_export($this->base . '/errors.log', true) . ';' . "\n"
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

    private function torrentBytes($name)
    {
        return 'd' . $this->bstr('announce') . $this->bstr('http://bt.t-ru.org/ann')
            . $this->bstr('info') . 'd' . $this->bstr('length') . 'i12e'
            . $this->bstr('name') . $this->bstr($name)
            . $this->bstr('piece length') . 'i32768e'
            . $this->bstr('pieces') . $this->bstr(str_repeat("\x01", 20)) . 'e' . 'e';
    }
}
