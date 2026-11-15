<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * With $deleteAutoArchives, an automatic unpack of a copied download deletes
 * the copy's archives once they are unpacked. The command
 * rUnpack::startSilentTask() builds is run here as the task would run it,
 * from the test's base directory, with extractors that succeed and write
 * nothing: each archive is deleted whole whatever its name holds, and a file
 * named like a piece of that name, absolute or relative to the working
 * directory, is kept.
 */
class UnpackDeleteListTest extends TestCase
{
    private const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
    private $base;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-unpack-delete-' . getmypid();
    }

    public function tearDown()
    {
        TestTree::remove($this->base);
    }

    public function testTheArchivesOfADirectoryWithASemicolonAreDeletedWhole()
    {
        $this->fresh();
        $copy = $this->base . '/copy/Show;1';
        mkdir($copy, 0700, true);
        mkdir($this->base . '/dl/Show;1', 0700, true);
        $archives = [$copy . '/a.rar', $copy . '/b.zip', $copy . '/c;d.zip'];
        // The pieces a ';' cuts those names into.
        $decoys = [$this->base . '/copy/Show', $this->base . '/1/a.rar', $this->base . '/1/b.zip',
            $this->base . '/1/c', $this->base . '/d.zip'];
        $this->files($archives, 'archive');
        mkdir($this->base . '/1');
        $this->files($decoys, 'decoy');
        $run = $this->unpack($copy, $this->base . '/dl/Show;1');
        $this->assertTrue(strpos($run['command'], 'unall_dir.sh') !== false, 'both extractors are run: ' . $run['command']);
        $this->assertEquals(0, $run['code'], 'the unpack succeeds: ' . $run['out']);
        $this->assertTrue($run['php'] === '', 'without a diagnostic: ' . $run['php']);
        foreach ($archives as $archive) {
            $this->assertTrue(!file_exists($archive), 'the archive is deleted: ' . $archive);
        }
        $this->assertKept($decoys);
    }

    public function testASingleArchiveWithASemicolonIsDeletedWhole()
    {
        foreach (['x;y.rar', 'x;y.zip', 'plain.rar'] as $name) {
            $this->fresh();
            mkdir($this->base . '/copy');
            mkdir($this->base . '/dl');
            $archive = $this->base . '/copy/' . $name;
            $this->files([$archive, $this->base . '/dl/' . $name], 'archive');
            $decoys = [$archive . ';', $this->base . '/copy/x', $this->base . '/y.rar', $this->base . '/y.zip'];
            $this->files($decoys, 'decoy');
            $run = $this->unpack($archive, $this->base . '/dl/' . $name);
            $this->assertTrue(strpos($run['command'], '_file.sh') !== false, $name . ': one archive is extracted: ' . $run['command']);
            $this->assertEquals(0, $run['code'], $name . ': the unpack succeeds: ' . $run['out']);
            $this->assertTrue($run['php'] === '', $name . ': without a diagnostic: ' . $run['php']);
            $this->assertTrue(!file_exists($archive), $name . ': the archive is deleted');
            $this->assertKept($decoys);
        }
    }

    public function testNothingIsDeletedWithoutDeleteAutoArchives()
    {
        foreach (['S;1/a.zip' => 'S;1', 'x;y.rar' => 'x;y.rar'] as $archive => $download) {
            $this->fresh();
            mkdir(dirname($this->base . '/copy/' . $archive), 0700, true);
            mkdir(dirname($this->base . '/dl/' . $archive), 0700, true);
            $this->files([$this->base . '/copy/' . $archive, $this->base . '/dl/' . $archive], 'archive');
            $run = $this->unpack($this->base . '/copy/' . $download, $this->base . '/dl/' . $download, false);
            $this->assertEquals(0, $run['code'], $archive . ': the unpack succeeds: ' . $run['out']);
            $this->assertTrue($run['php'] === '', $archive . ': without a diagnostic: ' . $run['php']);
            $this->assertTrue(is_file($this->base . '/copy/' . $archive), $archive . ': and keeps the archive');
        }
    }

    private function fresh()
    {
        TestTree::remove($this->base);
        mkdir($this->base, 0700);
        $this->base = realpath($this->base);
        $this->assertTrue(RtorrentProbeFixture::warmSettingsCache($this->base . '/profile'), 'the settings cache is warmed');
        foreach (['unrar', 'unzip'] as $tool) {
            file_put_contents($this->base . '/' . $tool, "#!/bin/sh\nexit 0\n");
            chmod($this->base . '/' . $tool, 0700);
        }
    }

    private function files($paths, $content)
    {
        foreach ($paths as $path) {
            file_put_contents($path, $content);
        }
    }

    private function assertKept($decoys)
    {
        foreach ($decoys as $decoy) {
            $this->assertTrue(@file_get_contents($decoy) === 'decoy', 'a file named like a piece of an archive name is kept: ' . $decoy);
        }
    }

    /**
     * startSilentTask() on the download at $basename, a copy of the one at
     * $downloadname, as a dry run; then its command, run with sh from the
     * base directory.
     */
    private function unpack($basename, $downloadname, $delete = true)
    {
        $root = realpath(__DIR__ . '/../../..');
        $log = $this->base . '/unpack.log';
        @unlink($log);
        file_put_contents($this->base . '/drive.php', "<?php\n"
            . '$_ENV["RU_PROFILE_PATH"] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV["RU_SCGI_HOST"] = "127.0.0.1"; $_ENV["RU_SCGI_PORT"] = "1";' . "\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$topDirectory = ' . var_export($this->base . '/', true) . '; $log_file = ' . var_export($log, true) . ";\n"
            . '$pathToExternals["unrar"] = ' . var_export($this->base . '/unrar', true) . ';'
            . ' $pathToExternals["unzip"] = ' . var_export($this->base . '/unzip', true) . ';'
            . ' $pathToExternals["php"] = ' . var_export(PHP_BINARY, true) . ";\n"
            . 'chdir(' . var_export($root . '/plugins/unpack', true) . "); require 'unpack.php';\n"
            . '$unpack_debug_enabled = "dry-run"; $deleteAutoArchives = ' . var_export($delete, true) . ";\n"
            . '$up = new rUnpack(); $up->startSilentTask(' . var_export($basename, true) . ', '
            . var_export($downloadname, true) . ', "", "name", ' . var_export(self::HASH, true) . ");\n");
        $driven = PhpProcess::run(array_merge(PhpProcess::sameIni(), [$this->base . '/drive.php']));
        // The dry run logs the commands it would start as a JSON list.
        preg_match('/ with arguments (\[.*\])$/m', (string) @file_get_contents($log), $match);
        $commands = isset($match[1]) ? json_decode($match[1], true) : [];
        $command = $commands[0] ?? '';
        if ($command === '') {
            return ['command' => '', 'code' => -1, 'out' => 'no command: ' . $driven['out'] . $driven['err'], 'php' => $driven['err']];
        }
        $process = proc_open(
            ['sh', '-c', $command],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->base . '/out', 'w'], 2 => ['file', $this->base . '/out', 'a']],
            $pipes,
            $this->base,
            array_merge(getenv(), ['RU_PROFILE_PATH' => $this->base . '/profile', 'RU_LOG_FILE' => $this->base . '/errors.log']),
        );
        $code = proc_close($process);
        return ['command' => $command, 'code' => $code, 'out' => (string) file_get_contents($this->base . '/out'),
            'php' => $driven['out'] . $driven['err']];
    }
}
