<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * "Set data directory" with "move files" reads where the data is from
 * rtorrent: d.get_name, d.get_base_path, d.get_base_filename and the file
 * list from f.multicall. rXMLRPCRequest::run() hands every string back with
 * each backslash doubled and each double quote escaped, so a path holding
 * either names a directory that does not exist. The move then finds nothing
 * to carry, but the download is still pointed at the new directory, which is
 * empty: the data stays behind and the download no longer knows where it is.
 *
 * Every case drives plugins/datadir/setdir.php (or rtDataDirCollision(), which
 * action.php asks first) against FakeRtorrentDaemon, which answers with the
 * bytes rtorrent would, and checks the filesystem afterwards. A decoy spelled
 * the way the escaped value reads must not be touched.
 */
class SetDirSpecialNamesMoveTest extends TestCase
{
    public const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
    public const USER = 'tester';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-setdir-names-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testAMultiFileDownloadUnderAQuoteABackslashAndAnApostropheIsMoved()
    {
        $this->assertMoved('da"ta\\x\'y', 'my"t\\o\'r', ['a"b\\c\'d.bin', 'plain.bin'], true);
    }

    public function testAMultiFileDownloadIsMovedWithoutAddingItsPath()
    {
        $this->assertMoved('da"ta\\x\'y', 'my"t\\o\'r', ['a"b\\c\'d.bin', 'plain.bin'], false);
    }

    public function testABackslashBeforeAQuoteIsMoved()
    {
        $this->assertMoved('a\\"b', 'c\\"d', ['e\\"f.bin'], true);
    }

    public function testASingleFileDownloadWithAQuoteABackslashAndAnApostropheIsMoved()
    {
        $this->assertMoved('da"ta\\x\'y', null, ['a"b\\c\'d.bin'], true);
    }

    public function testPlainNamesAreMovedAsBefore()
    {
        $this->assertMoved('data', 'mytorrent', ['1.bin', 'sub/2.bin'], true);
    }

    public function testADecoySpelledTheEscapedWayIsLeftAlone()
    {
        $parent = 'da"ta\\x\'y';
        $top = 'my"t\\o\'r';
        $file = 'a"b\\c\'d.bin';
        $decoy = $this->base . '/src/' . self::esc($parent) . '/' . self::esc($top);
        $this->makeDir($decoy);
        file_put_contents($decoy . '/' . self::esc($file), 'decoy');

        $this->assertMoved($parent, $top, [$file], true, false);

        $this->assertEquals(
            'decoy',
            @file_get_contents($decoy . '/' . self::esc($file)),
            'the decoy spelled the way the escaped path reads is left where it is',
        );
        $this->assertTrue(
            !file_exists($this->base . '/dst/' . self::esc($top)),
            'and nothing of it reaches the destination',
        );
    }

    public function testTheCollisionCheckFindsATakenNameUnderAQuoteABackslashAndAnApostrophe()
    {
        $top = 'my"t\\o\'r';
        $file = 'a"b\\c\'d.bin';
        $found = $this->collision('da"ta\\x\'y', $top, [$file, 'plain.bin'], $file);

        $this->assertEquals(
            realpath($this->base . '/dst') . '/' . $top . '/' . $file,
            $found,
            'action.php is told which name is taken, spelled as it is on disk',
        );
    }

    public function testTheCollisionCheckFindsNothingWhenTheDestinationIsFree()
    {
        $this->assertEquals(
            '',
            $this->collision('da"ta\\x\'y', 'my"t\\o\'r', ['a"b\\c\'d.bin', 'plain.bin']),
            'a free destination is not reported as taken',
        );
    }

    /** What run() makes of a string rtorrent sends. */
    private static function esc($s)
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $s);
    }

    /**
     * Data at src/$parent/$top/$files ($top null: one file at src/$parent/),
     * moved to dst. The download is open and stopped.
     */
    private function assertMoved($parent, $top, $files, $addPath, $fresh = true)
    {
        if ($fresh) {
            TestTree::remove($this->base);
        }
        $multi = ($top !== null);
        $srcDir = $this->base . '/src/' . $parent . ($multi ? '/' . $top : '');
        $dst = $this->base . '/dst';
        $this->makeDir($dst);
        foreach ($files as $f) {
            $this->makeDir(dirname($srcDir . '/' . $f));
            file_put_contents($srcDir . '/' . $f, 'download ' . $f);
        }
        $name = $multi ? $top : $files[0];
        $basePath = $multi ? $srcDir : $srcDir . '/' . $files[0];

        // d.is_open + d.is_active + d.get_state; d.get_name, d.get_base_path,
        // d.get_base_filename, d.is_multi_file, d.get_complete; f.multicall;
        // d.close; then whatever follows the move.
        $replies = [['1', '0', '0'], [$name, $basePath, $name, $multi ? 1 : 0, 1], $files, ['0']];
        for ($i = 0; $i < 6; $i++) {
            $replies[] = ['0'];
        }
        $calls = $this->runSetDir($replies, $dst, $addPath);

        $landing = $dst . (($multi && $addPath) ? '/' . $top : '');
        foreach ($files as $f) {
            $this->assertEquals(
                'download ' . $f,
                @file_get_contents($landing . '/' . $f),
                $f . ' arrives in ' . $landing . '; calls: ' . implode(', ', $calls),
            );
            $this->assertTrue(
                !file_exists($srcDir . '/' . $f),
                'and is gone from ' . $srcDir,
            );
        }
        $this->assertTrue(
            in_array($addPath ? 'd.set_directory' : 'd.set_directory_base', $calls, true)
                || in_array($addPath ? 'd.directory.set' : 'd.directory_base.set', $calls, true),
            'and the download is pointed at the destination; calls: ' . implode(', ', $calls),
        );
    }

    private function runSetDir($replies, $dst, $addPath)
    {
        $this->makeDir($this->base . '/profile/settings');
        RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/' . self::USER);
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
        $driver = $this->base . '/drive-setdir.php';
        file_put_contents($driver, "<?php\n"
            . '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . ";\n"
            . '$rpcTimeOut = 10; $rpcLogCalls = false;' . "\n"
            . 'require(' . var_export($this->repoRoot() . '/plugins/datadir/setdir.php', true) . ");\n");
        $this->runPhp([$driver, self::HASH, $dst, $addPath ? '1' : '0', '1', '0', self::USER]);
        $calls = $this->daemon->calls();
        $this->daemon->stop();
        $this->daemon = null;
        return $calls;
    }

    /** rtDataDirCollision() for the download, with $occupied already at the destination. */
    private function collision($parent, $top, $files, $occupied = null)
    {
        TestTree::remove($this->base);
        $srcDir = $this->base . '/src/' . $parent . '/' . $top;
        foreach ($files as $f) {
            $this->makeDir(dirname($srcDir . '/' . $f));
            file_put_contents($srcDir . '/' . $f, 'download ' . $f);
        }
        $this->makeDir($this->base . '/dst/' . $top);
        if ($occupied !== null) {
            file_put_contents($this->base . '/dst/' . $top . '/' . $occupied, 'already there');
        }
        $this->makeDir($this->base . '/profile/settings');
        RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/' . self::USER);
        // d.is_open, d.get_name, d.get_base_path, d.get_base_filename,
        // d.is_multi_file; then f.multicall.
        $this->daemon = new FakeRtorrentDaemon(
            [['1', $top, $srcDir, $top, 1], $files],
            $this->base . '/calls.log',
        );
        $driver = $this->base . '/drive-collision.php';
        file_put_contents($driver, "<?php\n"
            . '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
            . ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . $this->daemon->port() . '; $rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
            . 'chdir(' . var_export($this->repoRoot() . '/plugins/datadir', true) . ");\n"
            . 'require_once(' . var_export($this->repoRoot() . '/plugins/datadir/util_setdir.php', true) . ");\n"
            . 'echo "[", rtDataDirCollision(' . var_export(self::HASH, true) . ', '
            . var_export($this->base . '/dst', true) . ', true, false), "]";' . "\n");
        $out = $this->runPhp([$driver]);
        $this->daemon->stop();
        $this->daemon = null;
        if (!preg_match('/^\[(.*)\]$/s', $out, $m)) {
            return 'unexpected output: ' . $out;
        }
        return $m[1];
    }

    private function runPhp($args)
    {
        $mask = umask(022);
        try {
            $run = PhpProcess::run(
                array_merge(PhpProcess::sameIni(), $args),
                ['env' => ['LC_ALL' => 'C']],
            );
        } finally {
            umask($mask);
        }
        return $run['out'];
    }

    private function makeDir($dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException('Could not create fixture directory ' . $dir);
        }
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }
}
