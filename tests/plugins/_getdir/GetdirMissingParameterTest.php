<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');

/**
 * info.php and listdir.php answer a request that leaves out their main
 * parameter -- info.php without "mode", listdir.php without "dir" -- with
 * the same JSON they give for an empty one, and log nothing.
 *
 * Each page runs in a child PHP from the plugin's own directory, as the web
 * server runs it, against a top directory and profile of its own and an
 * rtorrent address that does not exist.
 */
class GetdirMissingParameterTest extends TestCase
{
    private $base;

    public function setUp() {}

    public function tearDown()
    {
        if ($this->base !== null) {
            exec('rm -rf ' . escapeshellarg($this->base));
        }
    }

    public function testInfoWithoutModeAnswersAnEmptyObjectAndLogsNothing()
    {
        $r = $this->page('info.php', []);
        $this->assertEquals('[]', $r['out'], 'info.php answers as for a mode it does not know');
        $this->assertEquals('', $r['log'], 'and logs nothing' . ($r['log'] === '' ? '' : ': ' . $r['log']));
    }

    public function testInfoWithAModeStillAnswers()
    {
        $r = $this->page('info.php', ['mode' => 'dirlist', 'basedir' => $this->top() . 'sub']);
        $answer = json_decode($r['out'], true);
        $this->assertEquals(['.', '..', 'inner'], $answer['dirlist'] ?? null, 'info.php lists the directory: ' . $r['out']);
        $this->assertEquals('', $r['log'], 'and logs nothing' . ($r['log'] === '' ? '' : ': ' . $r['log']));
    }

    public function testListdirWithoutDirListsTheTopDirectoryAndLogsNothing()
    {
        $r = $this->page('listdir.php', []);
        $answer = json_decode($r['out'], true);
        $this->assertEquals($this->top(), $answer['path'] ?? null, 'listdir.php starts at the top directory: ' . $r['out']);
        $this->assertEquals(['sub'], $answer['directories'] ?? null, 'and lists what is there');
        $this->assertEquals('', $r['log'], 'and logs nothing' . ($r['log'] === '' ? '' : ': ' . $r['log']));
    }

    public function testListdirWithEmptyDirAnswersTheSame()
    {
        $this->assertEquals(
            $this->page('listdir.php', ['dir' => ''])['out'],
            $this->page('listdir.php', [])['out'],
            'an empty dir and a missing one are the same request',
        );
    }

    private function top()
    {
        if ($this->base === null) {
            $this->base = (getenv('TMPDIR') ?: sys_get_temp_dir()) . '/getdir-param-' . getmypid() . '-' . bin2hex(random_bytes(4));
            foreach (['/top/sub/inner', '/profile/settings'] as $dir) {
                mkdir($this->base . $dir, 0700, true);
            }
        }
        return $this->base . '/top/';
    }

    private function page($script, $request)
    {
        $top = $this->top();
        $log = $this->base . '/php.log';
        @unlink($log);
        $run = PhpProcess::run(
            ['-c', __DIR__ . '/../../php-test.ini', '-d', 'variables_order=EGPCS', '-d', 'error_log=' . $log,
                '-r', '$_REQUEST = ' . var_export($request, true) . '; require ' . var_export($script, true) . ';'],
            [
                'cwd' => dirname(__DIR__, 3) . '/plugins/_getdir',
                'env' => [
                    'RU_TOP_DIR' => $top,
                    'RU_PROFILE_PATH' => $this->base . '/profile',
                    'RU_LOG_FILE' => $this->base . '/rutorrent.log',
                    'RU_SCGI_PORT' => '0',
                    'RU_SCGI_HOST' => 'unix://' . $this->base . '/no-rtorrent.sock',
                ],
            ],
        );
        return ['out' => $run['out'], 'log' => trim((string) @file_get_contents($log) . $run['err'])];
    }
}
