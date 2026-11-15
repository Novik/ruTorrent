<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');
require_once(__DIR__ . '/../../../php/utility/json.php');

/**
 * init.php starts the plugin only when the session directory rtorrent
 * reports exists, and names the directory when it does not. A directory
 * holding a '"' or a '\' is looked for under its own name, and named as one
 * string literal whatever it holds.
 */
class InitSessionDirectoryTest extends TestCase
{
    public const USER = 'tester';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-rutracker-init-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testASessionDirectoryWithAQuoteAndABackslashIsFound()
    {
        $run = $this->runInit('se"ss\\ion', true);
        $this->assertTrue(
            (strpos($run['printed'], '|registered=1') !== false) && (strpos($run['printed'], 'webBadSessionError') === false),
            'init.php finds the session directory and starts the plugin: ' . $run['printed'] . $run['errors'],
        );
    }

    public function testAMissingSessionDirectoryIsNamedAsOneStringLiteral()
    {
        $session = $this->base . '/go\\ne\'"x/';
        $run = $this->runInit('go\\ne\'"x', false);
        $this->assertTrue(
            strpos($run['printed'], "+theUILang.webBadSessionError+' ('+" . JSON::jsValue($session) . "+').'") !== false,
            'init.php names the missing session directory ' . $session . ' as one literal: ' . $run['printed'] . $run['errors'],
        );
    }

    private function runInit($sessionName, $exists)
    {
        TestTree::remove($this->base);
        if ($exists && !mkdir($this->base . '/' . $sessionName, 0700, true)) {
            throw new RuntimeException('Could not create fixture directory');
        }
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache(
                $this->base . '/profile/users/' . self::USER,
                '',
                $this->base . '/dl',
                $this->base . '/' . $sessionName . '/',
            ),
            'the settings cache is warmed',
        );
        $this->daemon = new FakeRtorrentDaemon(
            RtorrentProbeFixture::responder(),
            $this->base . '/calls.log',
            false,
            RtorrentProbeFixture::replyClassFiles(),
        );
        $file = $this->base . '/drive.php';
        file_put_contents($file, "<?php\n"
            . '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
            . ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . (int) $this->daemon->port() . '; $rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
            . 'require_once(' . var_export($this->repoRoot() . '/php/rtorrent.php', true) . ");\n"
            . '$jResult = ""; $plugin = ["name" => "rutracker_check"]; $pInfo = ["perms" => 0];'
            . ' $theSettings = rTorrentSettings::get();' . "\n"
            . 'require(' . var_export($this->repoRoot() . '/plugins/rutracker_check/init.php', true) . ");\n"
            . 'echo $jResult, "|registered=", $theSettings->isPluginRegistered("rutracker_check") ? 1 : 0;' . "\n");
        $mask = umask(022);
        try {
            $run = PhpProcess::run(
                array_merge(PhpProcess::sameIni(), ['-d', 'display_errors=stderr', $file]),
                ['env' => ['LC_ALL' => 'C']],
            );
        } finally {
            umask($mask);
        }
        $this->daemon->stop();
        $this->daemon = null;
        return ['printed' => $run['out'], 'errors' => $run['err']];
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }
}
