<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * rXMLRPCRequest::run() doubles each backslash and prefixes each double quote
 * in a string it returns, while rtorrent sends a label as its own bytes. A
 * rule has to match the label the torrent has.
 */
class CheckLabelsTest extends TestCase
{
    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-extratio-labels-' . getmypid();
        TestTree::remove($this->base);
        mkdir($this->base, 0700);
        // Past the settings handshake, so the replies below answer the plugin's own requests.
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

    public function testARuleMatchesAPlainLabel()
    {
        $this->assertChannelSet('plain', 'plain');
    }

    public function testARuleMatchesALabelWithQuotesAndABackslash()
    {
        $this->assertChannelSet('label\\"apostrophe\'&<>%25', 'label\\"apostrophe\'&<>%');
    }

    public function testAPercentEncodedLabelIsDecodedAfterItIsUnescaped()
    {
        $this->assertChannelSet('a%5C%22z', 'a\\"z');
    }

    private function assertChannelSet($label, $pattern)
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
        }
        // The label, state, views and throttle of the torrent; its trackers; the answer to the update.
        $this->daemon = new FakeRtorrentDaemon([[$label, 0, '', ''], [], [0]], $this->base . '/calls.log');
        $root = realpath(__DIR__ . '/../../..');
        file_put_contents($this->base . '/drive.php', "<?php\n"
            . '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV["RU_LOG_FILE"] = ' . var_export($this->base . '/errors.log', true) . ";\n"
            . '$_ENV["RU_SCGI_HOST"] = "127.0.0.1"; $_ENV["RU_SCGI_PORT"] = "' . $this->daemon->port() . "\";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . 'require_once(' . var_export($root . '/plugins/extratio/rules.php', true) . ");\n"
            . '$rules = new rRatioRulesList(); $rules->add(new rRatioRule("rule", RR_LABEL_CONTAIN, '
            . var_export($pattern, true) . ', 1, 0, "", "chan-7"));' . "\n"
            . '$rules->checkLabels(["0123456789ABCDEF0123456789ABCDEF01234567"]);' . "\n");
        PhpProcess::run(array_merge(PhpProcess::sameIni(), [$this->base . '/drive.php']));
        $bodies = $this->daemon->bodies();
        $this->assertTrue(strpos((string) end($bodies), 'chan-7') !== false, 'the rule sets its channel on a torrent labelled ' . $label);
    }
}
