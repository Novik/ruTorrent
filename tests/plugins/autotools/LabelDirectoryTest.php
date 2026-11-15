<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * rXMLRPCRequest::run() doubles each backslash and prefixes each double quote
 * in a string it returns, while rtorrent sends a directory as its own bytes.
 * The {DIR} label is the torrent's directory below the default one, so both
 * have to be read the same way.
 */
class LabelDirectoryTest extends TestCase
{
    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-autotools-label-' . getmypid();
        TestTree::remove($this->base);
        mkdir($this->base, 0700);
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testASubdirectoryIsTheLabel()
    {
        $this->assertLabelIs('Movies', '/data', '/data/Movies');
    }

    public function testASubdirectoryWithAQuoteAndABackslashIsTheLabelAsItIs()
    {
        $this->assertLabelIs('A"B\\C', '/data', '/data/A"B\\C');
    }

    public function testADefaultDirectoryWithAQuoteAndABackslashStillPrecedesTheTorrentDirectory()
    {
        $this->assertLabelIs('sub"x', '/da\\ta"', '/da\\ta"/sub"x');
    }

    public function testADefaultDirectoryWithABackslashBeforeAQuoteStillPrecedesTheTorrentDirectory()
    {
        $this->assertLabelIs('c\\\\d', '/a\\"b', '/a\\"b/c\\\\d');
    }

    private function assertLabelIs($label, $defaultDirectory, $torrentDirectory)
    {
        // Past the settings handshake, with the default directory the probe read from a daemon reporting it.
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache($this->base . '/profile', '', $defaultDirectory),
            'the settings cache is warmed',
        );
        // The torrent's directory, custom3 and multi-file flag, then the label being set.
        $this->daemon = new FakeRtorrentDaemon([[$torrentDirectory, '', 0], [0]], $this->base . '/calls.log');
        $root = realpath(__DIR__ . '/../../..');
        file_put_contents($this->base . '/drive.php', "<?php\n"
            . '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV["RU_LOG_FILE"] = ' . var_export($this->base . '/errors.log', true) . ";\n"
            . '$_ENV["RU_SCGI_HOST"] = "127.0.0.1"; $_ENV["RU_SCGI_PORT"] = "' . $this->daemon->port() . "\";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . 'chdir(' . var_export($root . '/plugins/autotools', true) . ");\n"
            . 'require_once("./util_rt.php"); require_once("./autotools.php");' . "\n"
            . '$at = rAutoTools::load(); $at->enable_label = 1; $at->label_template = "{DIR}"; $at->store();' . "\n"
            . 'require(' . var_export($root . '/plugins/autotools/label.php', true) . ");\n");
        PhpProcess::run(array_merge(PhpProcess::sameIni(), [$this->base . '/drive.php', '--daemon', '0123456789ABCDEF0123456789ABCDEF01234567']));
        $bodies = $this->daemon->bodies();
        $this->assertTrue(
            strpos((string) end($bodies), '<string>' . rawurlencode($label) . '</string>') !== false,
            'the torrent in ' . $torrentDirectory . ' is labelled ' . $label . '; requests sent: ' . count($bodies),
        );
        $this->daemon->stop();
        $this->daemon = null;
    }
}
