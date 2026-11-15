<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/UnpackPlaceFixture.php');

/**
 * The unpack scripts and stage.sh under this runtime's own sh, with
 * extractors written here that do what the real unzip and unrar do with
 * a link: unzip writes through one it finds below the directory it is
 * given, and unrar without -ol- creates the links an archive holds. Each
 * extractor run gets an empty private directory, and place.php moves what
 * it wrote into the output without its links.
 */
class UnpackScriptsTest extends TestCase
{
    public const HASH = UnpackPlaceFixture::HASH;
    public const USER = 'unpacktest';
    // unrar 7.23's own "lt -v" lines for a RAR 5.0 archive with a Unix and a
    // Windows symbolic link and a junction, plus a file named like a type.
    public const LISTED_LINKS = "        Name: real\n        Type: File\n        Name: lib.so\n        Type: Unix symbolic link\n"
        . "      Target: real\n        Name: winlink\n        Type: Windows symbolic link\n      Target: real\n"
        . "        Name: junction\n        Type: NTFS junction point\n      Target: real\n"
        . "        Name: Type: Junction\n        Type: File\n";

    private $base;

    public function tearDown()
    {
        UnpackPlaceFixture::remove($this->base);
    }

    public function testALinkOneZipMadeIsNotWrittenThroughByTheNext()
    {
        $this->fresh();
        $unzip = $this->zipsWithALinkThenAFileBelowIt();
        $run = $this->script(
            'unzip_dir.sh',
            [$unzip, $this->base . '/in/', $this->base . '/out/'],
            [$this->base . '/in/a.zip', $this->base . '/in/b.zip'],
        );
        $this->assertZipLinkRefused($run, $this->base . '/out', $this->base . '/out');
    }

    public function testALinkOneZipMadeIsNotWrittenThroughByTheNextInTheTempDirectory()
    {
        $this->fresh();
        $unzip = $this->zipsWithALinkThenAFileBelowIt();
        // An output that is not there yet is made.
        $run = $this->script('unzip_dir.sh', [$unzip, $this->base . '/in/', $this->base . '/out/new/', '',
            '', $this->base . '/temp/'], [$this->base . '/in/a.zip', $this->base . '/in/b.zip']);
        $this->assertZipLinkRefused($run, $this->base . '/temp', $this->base . '/out/new');
        $this->assertTrue(!file_exists($this->base . '/temp'), 'the temp directory is removed');
    }

    public function testLinksInARarAreRefusedAndUnrarCreatesNone()
    {
        $this->fresh();
        // The real file of the archive is left out: with -ol- unrar writes
        // nothing at all, and the links are refused all the same.
        $unrar = $this->extractor('unrar', '[ "$1" = lt ] && printf %s ' . Utility::escapeShellArg(self::LISTED_LINKS)
            . "\n" . '[ "$1" = x ] || exit 0; case " $* " in *" -ol- "*) ;;'
            . ' *) ln -s real "$dest/lib.so"; ln -s real "$dest/winlink"; ln -s real "$dest/junction" ;; esac');
        file_put_contents($this->base . '/in/a.rar', 'rar');
        $run = $this->script(
            'unrar_file.sh',
            [$unrar, $this->base . '/in/a.rar', $this->base . '/out/'],
            [$this->base . '/in/a.rar'],
        );
        $this->assertEquals(1, $run['code'], 'the unpack fails: ' . json_encode($run));
        $args = file($this->base . '/unrar.args', FILE_IGNORE_NEW_LINES);
        $this->assertTrue(
            strpos($args[0], 'lt -v -p- -- ') === 0,
            'every volume is listed, without a password prompt: ' . $args[0],
        );
        $this->assertTrue(
            strpos(' ' . ($args[1] ?? '') . ' ', ' -ol- ') !== false,
            'unrar extracts with -ol-: ' . json_encode($args),
        );
        $this->assertTrue(
            strpos($run['err'], 'Type: Junction') === false,
            'a file named like a type is not refused: ' . $run['err'],
        );
        foreach (['lib.so', 'winlink', 'junction'] as $name) {
            $this->assertTrue(
                strpos($run['err'], "unpack: refused archive-symbolic-link: $name\n") !== false,
                'the task output names ' . $name . ': ' . $run['err'],
            );
        }
        $this->assertTrue(
            $run['log'] === [$this->refusedLine('archive-symbolic-link', 3)],
            'the log counts them: ' . json_encode($run['log']),
        );
        $this->assertTrue(
            UnpackPlaceFixture::tree($this->base . '/out') === [],
            'no link reaches the output: ' . json_encode(UnpackPlaceFixture::tree($this->base . '/out')),
        );
        $this->assertTrue(is_file($this->base . '/in/a.rar'), 'and the archive is kept');
    }

    public function testUnallHandsBothScriptsTheirContext()
    {
        $this->fresh();
        $link = 'ln -s "$outside" "$dest/l"';
        // unrar lists the link, once for each of two volumes, and creates it
        // only without -ol-.
        $unrar = $this->extractor('unrar', '[ "$1" = lt ] && printf "        Name: l\n        Type: Unix symbolic link\n%.0s" 1 2'
            . "\n" . '[ "$1" = x ] || exit 0; case " $* " in *" -ol- "*) ;; *) ' . $link . ' ;; esac');
        $unzip = $this->extractor('unzip', $link);
        file_put_contents($this->base . '/in/a.rar', 'rar');
        file_put_contents($this->base . '/in/a.zip', 'zip');
        $run = $this->script('unall_dir.sh', [$unrar, $this->base . '/in/', $this->base . '/out/', $unzip]);
        $this->assertTrue($this->calls('unrar') === ['lt', 'x'], 'unrar lists, then extracts');
        $this->assertTrue($this->calls('unzip') === ['-o'], 'unzip extracts');
        $line = $this->refusedLine('archive-symbolic-link', 1);
        $this->assertTrue(
            $run['log'] === [$line, $line],
            'each script logs its refusal, the link listed twice counted once: ' . json_encode($run['log']),
        );
        $this->assertEquals(
            2,
            substr_count($run['err'], "unpack: refused archive-symbolic-link: l\n"),
            'and each names it once: ' . $run['err'],
        );
        // ruTorrent makes the profile directory of the user it runs as.
        $this->assertTrue(is_dir($this->base . '/profile/users/' . self::USER), 'as the user the unpack was for');
    }

    public function testArchivesTwoDirectoriesDeepAreUnpacked()
    {
        $this->fresh();
        // unzip -d makes only the last directory of the path it is given.
        $unzip = $this->extractor('unzip', '{ mkdir "$dest" 2>/dev/null || [ -d "$dest" ]; } || exit 2; echo z > "$dest/z"');
        // unrar is given each directory as "<directory>/.", rar or not, and
        // makes the whole path it extracts to.
        $unrar = $this->extractor('unrar', '[ "$1" = x ] || exit 0; for a; do case "$a" in *.) d=${a%.} ;; esac; done;'
            . ' for a in "$d"*.rar; do [ -f "$a" ] && mkdir -p "$dest" && echo r > "$dest/r"; done; exit 0');
        mkdir($this->base . '/in/Subs/eng', 0700, true);
        file_put_contents($this->base . '/in/Subs/eng/x.zip', 'zip');
        file_put_contents($this->base . '/in/Subs/eng/x.rar', 'rar');
        $run = $this->script('unall_dir.sh', [$unrar, $this->base . '/in/', $this->base . '/out/', $unzip]);
        $this->assertTrue($run['code'] === 0 && $run['err'] === '', 'the unpack succeeds: ' . json_encode($run));
        $this->assertTrue(UnpackPlaceFixture::tree($this->base . '/out') === ['Subs', 'Subs/eng', 'Subs/eng/r',
            'Subs/eng/z'], 'the nested subdirectory is made and filled: '
            . json_encode(UnpackPlaceFixture::tree($this->base . '/out')));
    }

    public function testDirectoriesAnArchiveLockedArePlaced()
    {
        $this->fresh();
        // A directory that cannot be written to or entered by its owner.
        $unzip = $this->extractor('unzip', 'mkdir -p "$dest/ro/sub" "$dest/shut" && echo f > "$dest/ro/sub/f"'
            . ' && echo g > "$dest/shut/g" && chmod 555 "$dest/ro/sub" "$dest/ro" && chmod 644 "$dest/shut"');
        file_put_contents($this->base . '/in/a.zip', 'zip');
        mkdir($this->base . '/out/ro');
        $run = $this->script('unzip_file.sh', [$unzip, $this->base . '/in/a.zip', $this->base . '/out/']);
        $this->assertEquals(0, $run['code'], 'the unpack succeeds: ' . json_encode($run));
        $this->assertTrue(UnpackPlaceFixture::tree($this->base . '/out') === ['ro', 'ro/sub', 'ro/sub/f', 'shut',
            'shut/g'], 'everything is placed: ' . json_encode(UnpackPlaceFixture::tree($this->base . '/out')));
        $this->assertTrue(
            !is_executable($this->base . '/out/ro/sub/f') && !is_executable($this->base . '/out/shut/g'),
            'and a file is not made executable',
        );
    }

    public function testNamesWithSpacesNewlinesAndALeadingDashArePlaced()
    {
        $this->fresh();
        $input = $this->base . "/in/-in put\nx/";
        $output = $this->base . "/out/-out put\nx/";
        mkdir($input . "-sub dir\ny", 0700, true);
        file_put_contents($input . "-a b\nc.zip", 'zip');
        file_put_contents($input . "-sub dir\ny/-d e\nf.zip", 'zip');
        // Each file is named after its archive.
        $unzip = $this->extractor('unzip', 'name=${2##*/}; echo z > "$dest/${name%.zip}"');
        $run = $this->script('unzip_dir.sh', [$unzip, $input, $output]);
        $this->assertEquals(0, $run['code'], 'the unpack succeeds: ' . json_encode($run));
        $this->assertTrue(
            UnpackPlaceFixture::tree($this->base . '/out') === ["-out put\nx", "-out put\nx/-a b\nc",
                "-out put\nx/-sub dir\ny", "-out put\nx/-sub dir\ny/-d e\nf"],
            'every name lands as it is: ' . json_encode(UnpackPlaceFixture::tree($this->base . '/out')),
        );
        $this->assertTrue($run['err'] === '' && $run['log'] === [], 'and nothing is reported: ' . json_encode($run));
    }

    public function testZipArchivePatternsKeepLiteralNames()
    {
        $this->fresh();
        $input = $this->base . '/in/parent*[x]/';
        mkdir($input);
        foreach (["Al [1].zip" => "Al [[]1].zip", "x*y.zip" => "x[*]y.zip",
            "q?r.zip" => "q[?]r.zip", "a\\b*.zip" => "a\\\\b[*].zip",
            "обычный file.zip" => "обычный file.zip", "a\\b.zip" => "a\\b.zip", "trailing*\n" => "trailing[*]\n"] as $name => $pattern) {
            file_put_contents($input . $name, 'archive');
            $unzip = $this->extractor('unzip', '[ "$2" = '
                . Utility::escapeShellArg($input . $pattern) . ' ] || exit 5; echo true > "$dest/true"');
            $run = $this->script('unzip_file.sh', [$unzip, $input . $name, $this->base . '/out/']);
            $this->assertEquals(0, $run['code'], 'the literal archive is selected: ' . json_encode($run));
            $this->assertEquals("true\n", file_get_contents($this->base . '/out/true'), 'the selected archive is placed');
            unlink($this->base . '/out/true');
            unlink($input . $name);
        }
    }

    public function testRelativeZipNameIsEscaped()
    {
        $this->fresh();
        file_put_contents($this->base . '/x*y.zip', 'zip');
        $unzip = $this->extractor('unzip', '[ "$2" = "x[*]y.zip" ] || exit 5; echo true > "$dest/true"');
        $run = $this->script('unzip_file.sh', [$unzip, 'x*y.zip', $this->base . '/out/']);
        $this->assertEquals(0, $run['code'], 'a relative name is escaped: ' . json_encode($run));
        $this->assertEquals("true\n", file_get_contents($this->base . '/out/true'), 'the archive is placed');
    }

    public function testZipDirectoryEscapesArchiveNames()
    {
        $this->fresh();
        $input = $this->base . '/in/';
        file_put_contents($input . 'x*y.zip', 'zip');
        $unzip = $this->extractor('unzip', '[ "$2" = '
            . Utility::escapeShellArg($input . 'x[*]y.zip') . ' ] || exit 5; echo true > "$dest/true"');
        $run = $this->script('unzip_dir.sh', [$unzip, $input, $this->base . '/out/']);
        $this->assertEquals(0, $run['code'], 'directory mode selects the literal archive: ' . json_encode($run));
        $this->assertEquals("true\n", file_get_contents($this->base . '/out/true'), 'the selected archive is placed');
    }

    public function testZipPatternConversionFailureKeepsArchive()
    {
        $this->fresh();
        $unzip = $this->extractor('unzip', 'exit 0');
        file_put_contents($this->base . '/in/x*y.zip', 'zip');
        file_put_contents($this->base . '/sed', "#!/bin/sh\nprintf partial\nexit 1\n");
        chmod($this->base . '/sed', 0700);
        $oldPath = getenv('PATH');
        putenv('PATH=' . $this->base . ':' . $oldPath);
        try {
            $run = $this->script(
                'unzip_file.sh',
                [$unzip, $this->base . '/in/x*y.zip', $this->base . '/out/'],
                [$this->base . '/in/x*y.zip'],
            );
            $dirRun = $this->script(
                'unzip_dir.sh',
                [$unzip, $this->base . '/in/', $this->base . '/out/'],
                [$this->base . '/in/x*y.zip'],
            );
        } finally {
            putenv('PATH=' . $oldPath);
        }
        $this->assertEquals(1, $run['code'], 'conversion failure refuses the unpack');
        $this->assertEquals(1, $dirRun['code'], 'conversion failure refuses the directory unpack');
        $this->assertTrue($this->calls('unzip') === [], 'unzip is not called with partial output');
        $this->assertTrue(is_file($this->base . '/in/x*y.zip'), 'the archive is kept');
        $this->assertTrue(strpos(implode('', $run['log']), 'reason=archive-name-unavailable') !== false, 'the log classifies the failure');
    }

    public function testRarWildcardPathsRefuseBeforeCallingUnrar()
    {
        $this->fresh();
        $unrar = $this->extractor('unrar', 'exit 0');
        foreach (['x*y.rar', 'q?r.rar', 'parent*/safe.rar'] as $name) {
            if (strpos($name, '/') !== false) {
                mkdir($this->base . '/in/parent*');
            }
            file_put_contents($this->base . '/in/' . $name, 'rar');
            @unlink($this->base . '/errors.log');
            $run = $this->script(
                'unrar_file.sh',
                [$unrar, $this->base . '/in/' . $name, $this->base . '/out/'],
                [$this->base . '/in/' . $name],
            );
            $this->assertEquals(1, $run['code'], 'a wildcard path is refused: ' . json_encode($run));
            $this->assertTrue(strpos($run['err'], 'rename') !== false, 'the task output asks for a rename');
            $this->assertTrue(strpos(implode('', $run['log']), 'reason=archive-name-pattern') !== false, 'the log classifies the refusal');
            $this->assertTrue($this->calls('unrar') === [], 'unrar is never called');
            $this->assertTrue(is_file($this->base . '/in/' . $name), 'the refused archive is kept');
            unlink($this->base . '/in/' . $name);
        }
        $run = $this->script('unrar_dir.sh', [$unrar, $this->base . '/in/parent*/', $this->base . '/out/']);
        $this->assertEquals(1, $run['code'], 'a wildcard directory is refused');
        $this->assertTrue($this->calls('unrar') === [], 'the directory is not listed or extracted');
    }

    public function testMixedUnpackKeepsBothArchivesWhenRarPathIsRefused()
    {
        $this->fresh();
        $dir = $this->base . '/in/parent*/';
        mkdir($dir);
        file_put_contents($dir . 'a.rar', 'rar');
        file_put_contents($dir . 'a.zip', 'zip');
        $unrar = $this->extractor('unrar', 'exit 0');
        $unzip = $this->extractor('unzip', 'echo z > "$dest/z"');
        $run = $this->script(
            'unall_dir.sh',
            [$unrar, $dir, $this->base . '/out/', $unzip],
            [$dir . 'a.rar', $dir . 'a.zip'],
        );
        $this->assertEquals(1, $run['code'], 'the mixed unpack is refused');
        $this->assertTrue(is_file($dir . 'a.rar') && is_file($dir . 'a.zip'), 'both archives are kept');
    }

    public function testMixedUnpackKeepsBothArchivesWhenZipPatternFails()
    {
        $this->fresh();
        $dir = $this->base . '/in/';
        file_put_contents($dir . 'a.rar', 'rar');
        file_put_contents($dir . 'x*y.zip', 'zip');
        $unrar = $this->extractor('unrar', 'exit 0');
        $unzip = $this->extractor('unzip', 'echo z > "$dest/z"');
        file_put_contents($this->base . '/sed', "#!/bin/sh\nexit 1\n");
        chmod($this->base . '/sed', 0700);
        $oldPath = getenv('PATH');
        putenv('PATH=' . $this->base . ':' . $oldPath);
        try {
            $run = $this->script(
                'unall_dir.sh',
                [$unrar, $dir, $this->base . '/out/', $unzip],
                [$dir . 'a.rar', $dir . 'x*y.zip'],
            );
        } finally {
            putenv('PATH=' . $oldPath);
        }
        $this->assertEquals(1, $run['code'], 'the mixed unpack is refused');
        $this->assertTrue(is_file($dir . 'a.rar') && is_file($dir . 'x*y.zip'), 'both archives are kept');
    }

    public function testMixedUnpackDeletesOnlyTheListedArchivesAfterBothSucceed()
    {
        $this->fresh();
        $dir = $this->base . '/in/';
        file_put_contents($dir . 'a.rar', 'rar');
        file_put_contents($dir . 'x*y.zip', 'zip');
        file_put_contents($dir . 'xzy.zip', 'unrelated');
        $unrar = $this->extractor('unrar', 'exit 0');
        $unzip = $this->extractor('unzip', 'exit 0');
        $run = $this->script(
            'unall_dir.sh',
            [$unrar, $dir, $this->base . '/out/', $unzip],
            [$dir . 'a.rar', $dir . 'x*y.zip'],
        );
        $this->assertEquals(0, $run['code'], 'both extractors succeed');
        $this->assertTrue(!file_exists($dir . 'a.rar') && !file_exists($dir . 'x*y.zip'), 'listed archives are deleted');
        $this->assertTrue(is_file($dir . 'xzy.zip'), 'an unlisted matching path is kept');
    }

    public function testDirectoryCleanupDoesNotExpandArchiveNames()
    {
        $this->fresh();
        foreach (['zip', 'rar'] as $format) {
            $dir = $this->base . '/in/' . (($format === 'zip') ? 'zip*' : 'rar') . '/';
            mkdir($dir);
            file_put_contents($dir . 'x*y.' . $format, 'archive');
            file_put_contents($dir . 'xzy.' . $format, 'decoy');
            $tool = $this->extractor('un' . $format, 'exit 0');
            $run = $this->script(
                'un' . $format . '_dir.sh',
                [$tool, $dir, $this->base . '/out/'],
                [$dir . 'x*y.' . $format],
            );
            $this->assertEquals(0, $run['code'], 'the directory unpack succeeds');
            $this->assertTrue(!file_exists($dir . 'x*y.' . $format), 'the selected archive is removed');
            $this->assertTrue(is_file($dir . 'xzy.' . $format), 'the unrelated archive remains');
        }
    }

    /**
     * Each archive to delete is an argument of its own, so a name holding a
     * ';' or a newline is deleted whole, and nothing named like a piece of it
     * is touched. The relative pieces would be resolved against the
     * script's working directory, the test's base.
     */
    public function testArchivesWhoseNamesHoldASeparatorAreDeletedWhole()
    {
        $this->fresh();
        $dir = $this->base . "/in/Show;1\nx/";
        mkdir($dir);
        $archives = [$dir . 'a;b.rar', $dir . "c\nd.zip"];
        $decoys = [$this->base . '/in/Show', $this->base . "/1\nx/a", $this->base . '/b.rar',
            $this->base . '/in/Show;1', $this->base . "/d.zip", $dir . 'a', $dir . 'c'];
        foreach ($archives as $archive) {
            file_put_contents($archive, 'archive');
        }
        mkdir($this->base . "/1\nx");
        foreach ($decoys as $decoy) {
            file_put_contents($decoy, 'decoy');
        }
        $unrar = $this->extractor('unrar', 'exit 0');
        $unzip = $this->extractor('unzip', 'exit 0');
        $run = $this->script('unall_dir.sh', [$unrar, $dir, $this->base . '/out/', $unzip], $archives);
        $this->assertEquals(0, $run['code'], 'the unpack succeeds: ' . json_encode($run));
        $this->assertTrue(!file_exists($archives[0]) && !file_exists($archives[1]), 'both archives are deleted: '
            . json_encode(UnpackPlaceFixture::tree($this->base . '/in')));
        foreach ($decoys as $decoy) {
            $this->assertTrue(@file_get_contents($decoy) === 'decoy', 'a file named like a piece of a name is kept: ' . $decoy);
        }
        foreach (['unrar_file.sh' => 'e;f.rar', 'unzip_file.sh' => "g;h\n.zip"] as $script => $name) {
            file_put_contents($this->base . '/in/' . $name, 'archive');
            file_put_contents($this->base . '/in/' . $name . ';', 'decoy');
            $tool = ($script === 'unrar_file.sh') ? $unrar : $unzip;
            $run = $this->script(
                $script,
                [$tool, $this->base . '/in/' . $name, $this->base . '/out/'],
                [$this->base . '/in/' . $name],
            );
            $this->assertEquals(0, $run['code'], $script . ' succeeds: ' . json_encode($run));
            $this->assertTrue(!file_exists($this->base . '/in/' . $name), $script . ' deletes ' . json_encode($name));
            $this->assertTrue(
                @file_get_contents($this->base . '/in/' . $name . ';') === 'decoy',
                'and keeps the file named like it with a ";" after it',
            );
        }
    }

    public function testNothingIsDeletedWhenNoArchiveIsListed()
    {
        $this->fresh();
        file_put_contents($this->base . '/in/a.zip', 'zip');
        $unzip = $this->extractor('unzip', 'exit 0');
        foreach (['unzip_file.sh' => $this->base . '/in/a.zip', 'unzip_dir.sh' => $this->base . '/in/'] as $script => $input) {
            $run = $this->script($script, [$unzip, $input, $this->base . '/out/']);
            $this->assertTrue($run['code'] === 0 && $run['err'] === '', $script . ' succeeds quietly: ' . json_encode($run));
            $this->assertTrue(is_file($this->base . '/in/a.zip'), 'and keeps the archive');
        }
    }

    public function testAPrivateDirectoryThatCannotBeMadeIsRefusedAndLogged()
    {
        $this->fresh();
        $unzip = $this->extractor('unzip', 'echo f > "$dest/f"');
        file_put_contents($this->base . '/in/a.zip', 'zip');
        chmod($this->base . '/out', 0500);
        $this->assertTrue(!is_writable($this->base . '/out'), 'precondition: not run as root');
        $run = $this->script('unzip_file.sh', [$unzip, $this->base . '/in/a.zip', $this->base . '/out/']);
        $this->assertEquals(1, $run['code'], 'the unpack fails: ' . json_encode($run));
        $this->assertTrue($this->calls('unzip') === [], 'without starting the extractor');
        $this->assertTrue(strpos($run['err'], 'unpack: refused private-directory-unavailable: '
            . $this->base . "/out/\n") !== false, 'the task output says why: ' . $run['err']);
        $this->assertTrue(
            $run['log'] === [$this->refusedLine('private-directory-unavailable', 1)],
            'and so does the log: ' . json_encode($run['log']),
        );
    }

    /** The runner calls setUp() once a class: each case starts with a tree of its own. */
    private function fresh()
    {
        $this->tearDown();
        $this->base = sys_get_temp_dir() . '/rutorrent-unpack-scripts-' . getmypid() . '-' . bin2hex(random_bytes(3));
        foreach (['profile', 'in', 'out', 'outside'] as $directory) {
            mkdir($this->base . '/' . $directory, 0700, true);
        }
        $this->base = realpath($this->base);
        // PHP as $pathToExternals['php'] names it, with this process's settings.
        file_put_contents($this->base . '/php', "#!/bin/sh\nexec " . implode(' ', array_map(
            'Utility::escapeShellArg',
            array_merge([PHP_BINARY], UnpackPlaceFixture::phpOptions()),
        )) . " \"\$@\"\n");
        chmod($this->base . '/php', 0700);
    }

    /**
     * An extractor that records each call, its arguments and the directory
     * it was given, and runs $body with $dest, its last argument, that
     * directory, $1 the command ("x" or "lt" for unrar, "-o" for unzip) and
     * $outside the directory beside the output that nothing may reach.
     */
    private function extractor($name, $body)
    {
        $path = $this->base . '/' . $name;
        file_put_contents($path, "#!/bin/sh\nfor dest; do :; done\noutside=\"\$(dirname \"\$0\")/outside\"\n"
            . "echo \"\$1\" >> \"\$0.calls\"\necho \"\$*\" >> \"\$0.args\"\necho \"\$dest\" >> \"\$0.dest\"\n"
            . $body . "\n");
        chmod($path, 0700);
        return $path;
    }

    /** What extractor $name recorded of its calls: "calls", "args" or "dest". */
    private function calls($name, $what = 'calls')
    {
        $calls = $this->base . '/' . $name . '.' . $what;
        return is_file($calls) ? file($calls, FILE_IGNORE_NEW_LINES) : [];
    }

    /**
     * Runs plugins/unpack/$script with the arguments unpack.php gives it:
     * $arguments up to the temp directory, the four for place.php, then
     * each archive to delete in $delete.
     */
    private function script($script, array $arguments, array $delete = [])
    {
        $arguments = array_pad($arguments, 6, '');
        array_push($arguments, $this->base . '/php', 'automatic', self::HASH, self::USER);
        $arguments = array_merge($arguments, $delete);
        $process = proc_open(
            array_merge([dirname(__DIR__, 3) . '/plugins/unpack/' . $script], $arguments),
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->base . '/stdout', 'w'],
                2 => ['file', $this->base . '/stderr', 'w']],
            $pipes,
            $this->base,
            array_merge(getenv(), ['RU_PROFILE_PATH' => $this->base . '/profile',
                'RU_LOG_FILE' => $this->base . '/errors.log']),
        );
        if (!is_resource($process)) {
            throw new RuntimeException('cannot start ' . $script);
        }
        $code = proc_close($process);
        return ['code' => $code, 'out' => file_get_contents($this->base . '/stdout'),
            'err' => file_get_contents($this->base . '/stderr'),
            'log' => UnpackPlaceFixture::logLines($this->base . '/errors.log')];
    }

    private function refusedLine($reason, $entries)
    {
        return 'unpack: automatic unpack refused hash=' . self::HASH . ' reason=' . $reason . ' entries=' . $entries;
    }

    /**
     * The first zip makes d -> outside; the second has d/pwn.txt, and unzip
     * writes it through that link.
     */
    private function zipsWithALinkThenAFileBelowIt()
    {
        file_put_contents($this->base . '/in/a.zip', 'zip');
        file_put_contents($this->base . '/in/b.zip', 'zip');
        return $this->extractor('unzip', 'case "$2" in'
            . ' *a.zip) ln -s "$outside" "$dest/d" ;;'
            . ' *b.zip) mkdir -p "$dest/d" && echo PWN > "$dest/d/pwn.txt" ;; esac');
    }

    /** $root holds the private directories, $output gets what is placed. */
    private function assertZipLinkRefused(array $run, $root, $output)
    {
        $private = $this->calls('unzip', 'dest');
        $this->assertTrue(
            count($private) === 2 && count(array_unique($private)) === 2 && count(array_filter(
                $private,
                function ($dest) use ($root) {
                    return strpos($dest, $root . '/.rutorrent-unpack.') === 0;
                },
            )) === 2,
            'each zip is unpacked into a private directory of its own in ' . $root . ': ' . json_encode($private),
        );
        $this->assertTrue(
            UnpackPlaceFixture::tree($this->base . '/outside') === [],
            'nothing is written outside: ' . json_encode($run),
        );
        $this->assertTrue(
            is_dir($output) && UnpackPlaceFixture::tree($output) === ['d', 'd/pwn.txt'],
            'the second zip is unpacked into the output, the link of the first is not: '
            . json_encode(is_dir($output) ? UnpackPlaceFixture::tree($output) : null),
        );
        $this->assertEquals(1, $run['code'], 'the unpack fails');
        $this->assertTrue(
            strpos($run['err'], "unpack: refused archive-symbolic-link: d\n") !== false,
            'the task output names the link: ' . $run['err'],
        );
        $this->assertTrue(
            $run['log'] === [$this->refusedLine('archive-symbolic-link', 1)],
            'the log has the refusal: ' . json_encode($run['log']),
        );
        $this->assertTrue(
            is_file($this->base . '/in/a.zip') && is_file($this->base . '/in/b.zip'),
            'and the archives are kept',
        );
    }
}
