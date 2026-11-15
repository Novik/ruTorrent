<?php

// Real Info-ZIP and UnRAR; fixtures were made by the fork's
// UnpackArchiveFixture. The regular suite also checks the shell arguments.
require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/UnpackPlaceFixture.php');


function runGlobUnpack($script, array $args)
{
    $args = array_pad($args, 6, '');
    array_push($args, PHP_BINARY, 'automatic', str_repeat('0', 40), '');
    $p = proc_open(
        array_merge([dirname(__DIR__, 3) . '/plugins/unpack/' . $script], $args),
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    if (!is_resource($p)) {
        throw new RuntimeException('cannot start unpack script');
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($p), $out, $err];
}

class UnpackGlobImageSmoke extends TestCase
{
    private $base;
    private $fixture;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/unpack-glob-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($this->base . '/profile', 0700, true);
        putenv('RU_PROFILE_PATH=' . $this->base . '/profile');
        putenv('RU_LOG_FILE=' . $this->base . '/errors.log');
        $this->fixture = dirname(__DIR__, 2) . '/fixtures/unpack-glob';
    }

    public function tearDown()
    {
        UnpackPlaceFixture::remove($this->base);
    }

    public function testZipTreatsItsFilenameLiterally()
    {
        $base = $this->base;
        $fixture = $this->fixture;
        foreach (['Al [1]' => 'Al 1', 'x*y' => 'xzy', 'q?r' => 'qsr',
            'a\\b*' => 'a\\bz', 'проба с пробелом' => 'другая'] as $name => $decoy) {
            $case = $base . '/' . bin2hex($name);
            mkdir($case . '/parent*[x]', 0700, true);
            mkdir($case . '/out');
            copy("$fixture/true.zip", "$case/parent*[x]/$name.zip");
            copy("$fixture/decoy.zip", "$case/parent*[x]/$decoy.zip");
            $run = runGlobUnpack('unzip_file.sh', ['unzip', "$case/parent*[x]/$name.zip", "$case/out/"]);
            $this->assertEquals(0, $run[0], 'zip succeeds: ' . json_encode($run));
            $this->assertEquals('true', file_get_contents("$case/out/true.txt"), 'the requested content is extracted');
            $this->assertTrue(!file_exists("$case/out/decoy.txt"), 'the decoy is not extracted');
        }
    }

    public function testRarRefusesWildcardPathsAndAllowsBrackets()
    {
        $base = $this->base;
        $fixture = $this->fixture;
        $case = $base . '/rar';
        mkdir($case . '/in', 0700, true);
        mkdir($case . '/out');
        foreach (['x*y' => 'xzy', 'q?r' => 'qsr'] as $name => $decoy) {
            copy("$fixture/decoy.rar", "$case/in/$decoy.rar");
            copy("$fixture/true.rar", "$case/in/$name.rar");
            $run = runGlobUnpack('unrar_file.sh', ['unrar', "$case/in/$name.rar", "$case/out/"]);
            $this->assertEquals(1, $run[0], 'rar is refused: ' . json_encode($run));
            $this->assertTrue(strpos($run[2], 'rename') !== false, 'the task explains what to do');
            $this->assertTrue(is_file("$case/in/$name.rar"), 'the archive is kept');
        }
        copy("$fixture/true.rar", "$case/in/Al [1].rar");
        $run = runGlobUnpack('unrar_file.sh', ['unrar', "$case/in/Al [1].rar", "$case/out/"]);
        $this->assertEquals(0, $run[0], 'brackets are supported: ' . json_encode($run));
        $this->assertEquals('true', file_get_contents("$case/out/true.txt"), 'bracketed archive is extracted');
    }
}
