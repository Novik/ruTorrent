<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * rXMLRPCRequest::run() doubles each backslash and prefixes each double quote
 * in a string it returns, while rtorrent sends a label as its own bytes. The
 * label list has to show the labels the torrents have.
 */
class LabelListTest extends TestCase
{
    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-getdir-labels-' . getmypid();
        TestTree::remove($this->base);
        mkdir($this->base, 0700);
        // Past the settings handshake, so the reply below answers the plugin's own request.
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

    public function testAPlainLabelIsListed()
    {
        $this->assertEquals(['Plain'], $this->listLabels(['Plain']));
    }

    public function testLabelsWithAQuoteAndABackslashAreListedOnceAsTheyAre()
    {
        $this->assertEquals(['C\\D', 'Foo"Bar'], $this->listLabels(['Foo"Bar', 'C\\D', 'Foo"Bar']));
    }

    public function testAPercentEncodedLabelIsDecodedAfterItIsUnescaped()
    {
        $this->assertEquals(['a\\"z'], $this->listLabels(['a%5C%22z']));
    }

    private function listLabels(array $labels)
    {
        $this->daemon = new FakeRtorrentDaemon([$labels], $this->base . '/calls.log');
        $root = realpath(__DIR__ . '/../../..');
        file_put_contents($this->base . '/drive.php', "<?php\n"
            . '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV["RU_LOG_FILE"] = ' . var_export($this->base . '/errors.log', true) . ";\n"
            . '$_ENV["RU_SCGI_HOST"] = "127.0.0.1"; $_ENV["RU_SCGI_PORT"] = "' . $this->daemon->port() . "\";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$_REQUEST["mode"] = "labels";' . "\n"
            . 'chdir(' . var_export($root . '/plugins/_getdir', true) . "); require 'info.php';\n");
        $run = PhpProcess::run(array_merge(PhpProcess::sameIni(), [$this->base . '/drive.php']));
        $json = json_decode($run['out'], true);
        $this->assertTrue(isset($json['labels']), 'the labels are listed; stdout: ' . $run['out'] . ' stderr: ' . $run['err']);
        $this->daemon->stop();
        $this->daemon = null;
        return $json['labels'];
    }
}
