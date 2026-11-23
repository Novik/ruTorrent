<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/QuietCall.php');
require_once(__DIR__ . '/../../php/Torrent.php');

/**
 * The order of the files in a torrent built by plugins/create, which is part
 * of its info dictionary and so of its info hash: deeper paths first, then
 * by name under the LC_COLLATE that php/util.php sets.
 *
 * strcoll() did the name comparison until it was deprecated in PHP 8.6.
 * Collator, from the intl extension, does it now where the locale names a
 * language; in C, POSIX and C.UTF-8, and where intl is not loaded, names
 * are compared byte for byte, which is what strcoll() did in those locales.
 *
 * Both comparators are reached directly, so each is checked whichever
 * extensions and locales the PHP running the suite has.
 */
class TorrentSortNamesTest extends TestCase
{
    use QuietCall;

    private $base;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-sortnames-' . getmypid();
        @mkdir($this->base, 0700, true);
    }

    public function tearDown()
    {
        $this->remove($this->base);
    }

    public function testABuiltTorrentListsDeeperFilesFirstAndRaisesNoDeprecation()
    {
        // In C both comparators compare bytes, so the expected order holds
        // with and without intl. The names share a depth, so each pair of
        // them reaches the name comparison, and so would strcoll().
        list($order, $errors) = $this->build('C', ['b.bin', 'B.bin', 'a.bin', 'A.bin', '_x.bin', 'sub/z.bin']);
        $this->assertTrue(
            $order === ['sub/z.bin', 'A.bin', 'B.bin', '_x.bin', 'a.bin', 'b.bin'],
            'in C the deeper file comes first and the rest are in byte order: ' . json_encode($order),
        );
        $this->assertTrue($errors === [], 'building the torrent raises no deprecation, notice or warning: '
            . json_encode($errors));
    }

    public function testABuiltTorrentIsInCollationOrderUnderAUtf8Locale()
    {
        $locale = $this->utf8Locale();
        if (($locale === null) || !class_exists('Collator')) {
            $this->assertTrue(true, 'not checked here: en_US.UTF-8 is ' . (($locale === null) ? 'not installed' : 'installed')
                . ' and intl is ' . (class_exists('Collator') ? 'loaded' : 'not loaded'));
            return;
        }
        list($order, $errors) = $this->build($locale, ['b.bin', 'B.bin', 'a.bin', 'A.bin', '_x.bin', 'été.bin', 'sub/z.bin']);
        $this->assertTrue(
            $order === ['sub/z.bin', 'a.bin', 'A.bin', 'b.bin', 'B.bin', 'été.bin', '_x.bin'],
            'in ' . $locale . ' the deeper file comes first and the rest are in collation order: ' . json_encode($order),
        );
        $this->assertTrue($errors === [], 'building the torrent raises no deprecation, notice or warning: '
            . json_encode($errors));
    }

    public function testTheByteFallbackIsStrcmpOrder()
    {
        $names = $this->corpus();
        $expected = $names;
        sort($expected, SORT_STRING);
        list($sorted, $errors) = self::quietly(function () use ($names) {
            usort($names, function ($a, $b) {
                return self::call('compareNames', $a, $b, null);
            });
            return $names;
        });
        $this->assertTrue($sorted === $expected, 'without a collator names are in byte order: ' . json_encode($sorted));
        $this->assertTrue($errors === [], 'the byte comparison raises nothing: ' . json_encode($errors));
        foreach (['C', 'POSIX', 'C.UTF-8', 'C.utf8', 'UTF8', 'UTF-8', ''] as $locale) {
            $this->assertTrue(
                self::call('collator', $locale) === null,
                json_encode($locale) . ' compares bytes, as strcoll() did there',
            );
        }
    }

    public function testACollatorThatCannotBeOpenedFallsBackToBytes()
    {
        list($collator, $errors) = self::quietly(function () {
            return self::call('openCollator', 'en-US-u-kv-bogus');
        });
        $this->assertTrue($collator === null, 'a locale ICU refuses gives no collator');
        $this->assertTrue($errors === [], 'and raises nothing: ' . json_encode($errors));
    }

    public function testTheCollatorOrdersMixedNames()
    {
        if (!class_exists('Collator')) {
            $this->assertTrue(true, 'not checked here: intl is not loaded');
            return;
        }
        $collator = self::call('collator', 'en_US.UTF-8');
        $this->assertTrue($collator instanceof Collator, 'en_US.UTF-8 has a collator');
        $names = $this->corpus();
        list($sorted, $errors) = self::quietly(function () use ($names, $collator) {
            usort($names, function ($a, $b) use ($collator) {
                return self::call('compareNames', $a, $b, $collator);
            });
            return $names;
        });
        $this->assertTrue($sorted === [
            '01 Intro.flac', '10 Outro.flac', '1 Intro.flac', '2 Song.flac',
            'a10.mkv', 'a1.mkv', 'a2.mkv', 'a.mkv', 'A.mkv', 'Cafe.mkv', 'Café.mkv',
            'Episode 1.mkv', 'episode_2.mkv', 'Episode-3.mkv', 'ete.mkv', 'Ete.mkv', 'été.mkv',
            'f.mkv', '.nfo', 'u.mkv', 'ü.mkv', 'z.mkv', 'а.mkv', 'Я.mkv', '中.mkv',
        ], 'names are in the order strcoll() gave in en_US.UTF-8 with glibc 2.41: punctuation, case and accents '
            . 'decide only between names that are otherwise equal: '
            . json_encode($sorted, JSON_UNESCAPED_UNICODE));
        $this->assertTrue($errors === [], 'the collation raises nothing: ' . json_encode($errors));
    }

    public function testTheCollatorKeepsATotalOrder()
    {
        if (!class_exists('Collator')) {
            $this->assertTrue(true, 'not checked here: intl is not loaded');
            return;
        }
        $collator = self::call('collator', 'en_US.UTF-8');
        // Canonically equivalent spellings collate equal; names that are not
        // UTF-8 cannot be collated at all. Both still get a fixed place.
        $names = ["e\u{301}.mkv", "\u{e9}.mkv", "z\xFF.mkv", "a\xFF.mkv", 'b.mkv', "\xE9t\xE9.mkv"];
        list($sorted, $errors) = self::quietly(function () use ($names, $collator) {
            usort($names, function ($a, $b) use ($collator) {
                return self::call('compareNames', $a, $b, $collator);
            });
            return $names;
        });
        $this->assertTrue(
            array_map('bin2hex', $sorted) === array_map('bin2hex', ['b.mkv', "e\u{301}.mkv", "\u{e9}.mkv", "a\xFF.mkv", "z\xFF.mkv", "\xE9t\xE9.mkv"]),
            'equal collation falls back to bytes, and names that are not UTF-8 follow the others in byte order: '
                . json_encode(array_map('bin2hex', $sorted)),
        );
        $this->assertTrue($errors === [], 'no name raises anything: ' . json_encode($errors));
        foreach ($names as $a) {
            foreach ($names as $b) {
                $ab = self::call('compareNames', $a, $b, $collator);
                $ba = self::call('compareNames', $b, $a, $collator);
                if (($a === $b) ? ($ab !== 0) : (($ab <=> 0) !== -($ba <=> 0) || ($ab === 0))) {
                    $this->assertTrue(false, 'compareNames is antisymmetric and zero only for the same name: '
                        . bin2hex($a) . ' ' . bin2hex($b));
                }
            }
        }
    }

    /** Names that byte order and collation order differently. */
    private function corpus()
    {
        return [
            'z.mkv', 'Я.mkv', 'а.mkv', '中.mkv', 'ü.mkv', 'u.mkv', 'f.mkv', 'été.mkv', 'Ete.mkv', 'ete.mkv',
            'Episode-3.mkv', 'episode_2.mkv', 'Episode 1.mkv', 'Café.mkv', 'Cafe.mkv', 'a2.mkv', 'a10.mkv',
            'a1.mkv', 'A.mkv', 'a.mkv', '2 Song.flac', '10 Outro.flac', '1 Intro.flac', '01 Intro.flac', '.nfo',
        ];
    }

    // Torrent's comparators are private; this reaches them from its scope.
    private static function call($method, ...$arguments)
    {
        return Closure::bind(function () use ($method, $arguments) {
            return call_user_func_array([Torrent::class, $method], $arguments);
        }, null, Torrent::class)();
    }

    /** The UTF-8 locale php/util.php falls back to, if this host has it. */
    private function utf8Locale()
    {
        $previous = setlocale(LC_COLLATE, '0');
        $found = setlocale(LC_COLLATE, 'en_US.UTF-8', 'en_US.UTF8', 'en_US.utf8');
        setlocale(LC_COLLATE, $previous);
        return ($found === false) ? null : $found;
    }

    /**
     * Builds a torrent out of $files under LC_COLLATE $locale and returns
     * [its file paths in torrent order, the diagnostics raised].
     */
    private function build($locale, $files)
    {
        $root = $this->base . '/case-' . md5($locale . implode("\0", $files)) . '/payload';
        foreach ($files as $file) {
            $path = $root . '/' . $file;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            file_put_contents($path, $file);
        }
        $previous = setlocale(LC_COLLATE, '0');
        try {
            setlocale(LC_COLLATE, $locale);
            list($torrent, $errors) = self::quietly(function () use ($root) {
                return new Torrent($root, [], 16, null, null);
            });
        } finally {
            setlocale(LC_COLLATE, $previous);
        }
        $order = [];
        foreach ((isset($torrent->info['files']) ? $torrent->info['files'] : []) as $entry) {
            $order[] = implode('/', $entry['path']);
        }
        return [$order, $errors];
    }

    private function remove($path)
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $item) {
                $this->remove($path . '/' . $item);
            }
            @rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            @unlink($path);
        }
    }
}
