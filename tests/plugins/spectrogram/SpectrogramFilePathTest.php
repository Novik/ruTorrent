<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * rXMLRPCRequest::run() doubles each backslash and prefixes each double quote
 * in a string it returns, while rtorrent sends a path as its own bytes. The
 * command the task runs has to name the file as it is on disk.
 */
class SpectrogramFilePathTest extends TestCase
{
    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-spectrogram-path-' . getmypid();
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

    public function testAPlainPathIsPassedToTheTool()
    {
        $this->assertPassed('plain.txt', false);
    }

    public function testAPathWithQuotesAndABackslashIsPassedToTheTool()
    {
        $this->assertPassed('sample\\"apostrophe\'file.txt', false);
    }

    public function testThePathOfAClosedTorrentIsPassedAfterItIsOpened()
    {
        $this->assertPassed('sample\\"apostrophe\'file.txt', true);
    }

    private function assertPassed($name, $closed)
    {
        $path = $this->base . '/' . $name;
        // Spares answer the task's own request to rtorrent, so the tool never runs.
        $replies = array_merge($closed ? [[''], [0, $path, 0]] : [[$path]], [['0'], ['0']]);
        if ($this->daemon !== null) {
            $this->daemon->stop();
        }
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
        $root = realpath(__DIR__ . '/../../..');
        file_put_contents($this->base . '/drive.php', "<?php\n"
            . '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV["RU_LOG_FILE"] = ' . var_export($this->base . '/errors.log', true) . ";\n"
            . '$_ENV["RU_SCGI_HOST"] = "127.0.0.1"; $_ENV["RU_SCGI_PORT"] = "' . $this->daemon->port() . "\";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$_REQUEST = ["hash" => "0123456789ABCDEF0123456789ABCDEF01234567", "no" => "0", "cmd" => "sox"];' . "\n"
            . 'chdir(' . var_export($root . '/plugins/spectrogram', true) . "); require 'action.php';\n");
        $run = PhpProcess::run(array_merge(PhpProcess::sameIni(), [$this->base . '/drive.php']));
        $task = json_decode($run['out'], true);
        $script = is_array($task) && isset($task['no'])
            ? (string) @file_get_contents($this->base . '/profile/settings/tasks/' . $task['no'] . '/start.sh') : '';
        $this->assertTrue(strpos($script, escapeshellarg($path)) !== false, 'the tool is given ' . $name . '; got ' . $run['out'] . ' ' . $run['err']);
    }
}
