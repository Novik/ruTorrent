<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * rXMLRPCRequest::run() doubles each backslash and prefixes each double quote
 * in a string it returns, while rtorrent sends a path, a name or a label as
 * its own bytes. A manual unpack has to hand the extractor the real ones.
 */
class UnpackRawPathsTest extends TestCase
{
    private const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
    private const NAME = 'sample\\"apostrophe\'file';
    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-unpack-path-' . getmypid();
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

    public function testAPlainFileIsGivenToTheExtractor()
    {
        $file = $this->base . '/plain.zip';
        $this->assertTrue(strpos($this->command('zip', [[$file]]), escapeshellarg($file)) !== false, 'the file is extracted');
    }

    public function testAFileWithQuotesAndABackslashIsGivenToTheExtractor()
    {
        $file = $this->base . '/' . self::NAME . '.zip';
        $this->assertTrue(strpos($this->command('zip', [[$file]]), escapeshellarg($file)) !== false, 'the file is extracted');
    }

    public function testTheFileOfAClosedTorrentIsGivenToTheExtractorAfterItIsOpened()
    {
        $file = $this->base . '/' . self::NAME . '.zip';
        $command = $this->command('zip', [[''], [0, $file, 0]]);
        $this->assertTrue(strpos($command, escapeshellarg($file)) !== false, 'the file is extracted');
    }

    public function testAWholeDownloadIsExtractedToItsDirectoryLabelAndName()
    {
        $dir = $this->base . '/' . self::NAME;
        mkdir($dir);
        $command = $this->command('', [[$dir, self::NAME, self::NAME], ['a.zip']], true);
        $this->assertTrue(strpos($command, escapeshellarg($dir . '/' . self::NAME . '/' . self::NAME . '/')) !== false, 'the output path is the real one');
    }

    public function testAPercentEncodedLabelIsDecodedAfterItIsUnescaped()
    {
        $dir = $this->base . '/encoded';
        mkdir($dir);
        $command = $this->command('', [[$dir, 'a%5C%22z', 'name'], ['a.zip']], true);
        $this->assertTrue(strpos($command, escapeshellarg($dir . '/a\\"z/name/')) !== false, 'the label is decoded once');
    }

    public function testTheDirectoryOfAClosedTorrentIsExtractedAfterItIsOpened()
    {
        $dir = $this->base . '/' . self::NAME . '.closed';
        mkdir($dir);
        $command = $this->command('', [['', '', 'name'], [0, $dir, 0], ['a.zip']]);
        $this->assertTrue(strpos($command, escapeshellarg($dir . '/')) !== false, 'the directory is extracted');
    }

    private function command($mode, $replies, $labelAndName = false)
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
        }
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
        $root = realpath(__DIR__ . '/../../..');
        $log = $this->base . '/unpack.log';
        @unlink($log);
        file_put_contents($this->base . '/drive.php', "<?php\n"
            . '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV["RU_LOG_FILE"] = ' . var_export($this->base . '/errors.log', true) . ";\n"
            . '$_ENV["RU_SCGI_HOST"] = "127.0.0.1"; $_ENV["RU_SCGI_PORT"] = "' . $this->daemon->port() . "\";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$topDirectory = ' . var_export($this->base . '/', true) . '; $log_file = ' . var_export($log, true) . ";\n"
            . 'chdir(' . var_export($root . '/plugins/unpack', true) . "); require 'unpack.php';\n"
            . '$unpack_debug_enabled = "dry-run"; $up = new rUnpack(); $up->addLabel = $up->addName = ' . ($labelAndName ? 'true' : 'false') . ";\n"
            . '$up->startTask(' . var_export(self::HASH, true) . ', "", ' . var_export($mode, true) . ", 0);\n");
        PhpProcess::run(array_merge(PhpProcess::sameIni(), [$this->base . '/drive.php']));
        // The dry run logs the command it would start as a JSON list.
        preg_match('/ with arguments (\[.*\])$/m', (string) @file_get_contents($log), $match);
        $commands = isset($match[1]) ? json_decode($match[1], true) : [];
        return $commands[0] ?? '';
    }
}
