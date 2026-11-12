<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * rXMLRPCRequest::run() doubles each backslash and prefixes each double quote
 * in a string it returns, while rtorrent sends a path as its own bytes. The
 * plugin has to look for the file under the name it has on disk.
 */
class DataFilePathTest extends TestCase
{
    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-data-path-' . getmypid();
        TestTree::remove($this->base);
        mkdir($this->base, 0700);
        // Past the settings handshake, so the replies below answer the plugin's own request.
        $this->assertTrue(RtorrentProbeFixture::warmSettingsCache($this->base . '/profile'), 'the settings cache is warmed');
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testAPlainPathIsSent()
    {
        $this->assertSent('plain.txt', false);
    }

    public function testAPathWithQuotesAndABackslashIsSent()
    {
        $this->assertSent('sample\\"apostrophe\'file.txt', false);
    }

    public function testThePathOfAClosedTorrentIsSentAfterItIsOpened()
    {
        $this->assertSent('sample\\"apostrophe\'file.txt', true);
    }

    private function assertSent($name, $closed)
    {
        $path = $this->base . '/' . $name;
        file_put_contents($path, 'PAYLOAD');
        if ($this->daemon !== null) {
            $this->daemon->stop();
        }
        $this->daemon = new FakeRtorrentDaemon($closed ? [[''], [0, $path, 0]] : [[$path]], $this->base . '/calls.log');
        $root = realpath(__DIR__ . '/../../..');
        file_put_contents($this->base . '/drive.php', "<?php\n"
            . '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV["RU_LOG_FILE"] = ' . var_export($this->base . '/errors.log', true) . ";\n"
            . '$_ENV["RU_SCGI_HOST"] = "127.0.0.1"; $_ENV["RU_SCGI_PORT"] = "' . $this->daemon->port() . "\";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$_REQUEST = ["hash" => "0123456789ABCDEF0123456789ABCDEF01234567", "no" => "0"];' . "\n"
            . 'chdir(' . var_export($root . '/plugins/data', true) . "); require 'action.php';\n");
        $run = PhpProcess::run(array_merge(PhpProcess::sameIni(), [$this->base . '/drive.php']));
        $this->assertEquals('PAYLOAD', $run['out'], 'the file is sent; stderr: ' . $run['err']);
    }
}
