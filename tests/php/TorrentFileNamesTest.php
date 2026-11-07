<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/QuietCall.php');
// TorrentEncoder: fixtures from the production bencode encoder.
define('TESTLIB_HANDLER_STUBS', true);
require_once(__DIR__ . '/../plugins/rutracker_check/TestLib.php');
require_once(__DIR__ . '/../../php/torrentfilenames.php');

function tfn_iconv($from, $to, $text)
{
    return $GLOBALS['torrentFileNamesConverter']('iconv', $text);
}

function tfn_mb_convert_encoding($text, $to, $from)
{
    return $GLOBALS['torrentFileNamesConverter']('mb', $text);
}

class TorrentFileNamesTest extends TestCase
{
    use QuietCall;

    public const RAW = "\xF1\xE5\xF0\xE8\xFF.mkv";
    public const SHOWN = '\\xF1\\xE5\\xF0\\xE8\\xFF.mkv';

    public function testValidUnicodeNeedsNoMetadataAndGetsNoLabel()
    {
        $rows = [['中文/серия 🙂.mkv', '1', '2', '3', '1', 'extension'], [null, '0', '0', '0', '1'], []];
        $this->assertTrue(
            self::quietly(function () use ($rows) {
                return TorrentFileNames::needsMetadata($rows);
            }) === [false, []],
            'valid non-Russian UTF-8 and rows without a path request no source metadata and raise nothing',
        );
        $this->assertTrue(
            self::quietly(function () use ($rows) {
                return TorrentFileNames::resolve($rows, str_repeat('A', 40), false);
            }) === [[], []],
            'a valid row or a row without a path gets no label and raises nothing',
        );
        $this->assertTrue(TorrentFileNames::needsTitleMetadata('中文') === false, 'a valid title does not request source metadata');
        $this->assertTrue(TorrentFileNames::needsTitleMetadata("raw\xFF") === true, 'a title that is not UTF-8 requests source metadata');
        $this->assertTrue(TorrentFileNames::title('中文', str_repeat('A', 40), false) === '中文', 'a valid title is its own label');
    }

    public function testCapturedLegacyEpisodeUsesItsAttestedUtf8Alias()
    {
        // Minimal names and size from the owner-supplied torrent; no announce or passkey.
        $raw = 'Panty And Stocking TV 01 ' . hex2bin('f1e5f0e8ff') . '.mkv';
        $files = [$this->file(['Anime', $raw], ['Other', 'Panty And Stocking TV 01 серия.mkv'], 3014966836)];
        $this->assertTrue(TorrentFileNames::needsMetadata($this->rows($files)), 'legacy file names request metadata');
        $this->assertTrue(
            $this->resolve($files) === [0 => 'Anime/Panty And Stocking TV 01 серия.mkv'],
            'valid raw components are kept and only the invalid component uses its alias',
        );
    }

    public function testMissingOrUnattestedMetadataShowsTheBytes()
    {
        $files = [$this->file(["raw\xFF.mkv"], ['alias.mkv'])];
        $source = $this->source($files);
        foreach ([false, null, new stdClass(), $source] as $candidate) {
            foreach ([str_repeat('0', 40), str_repeat('F', 40)] as $hash) {
                $this->assertTrue(
                    TorrentFileNames::resolve($this->rows($files), $hash, $candidate) === [0 => 'raw\\xFF.mkv'],
                    'a missing source or a mismatched info hash cannot supply an alias',
                );
            }
        }
        $this->assertTrue(self::quietly(function () use ($source) {
            return TorrentFileNames::resolve([["raw\xFF.mkv"]], $source->hash_info(), $source);
        })
            === [[0 => 'raw\\xFF.mkv'], []], 'a row without a size borrows nothing and raises nothing');
        $this->assertTrue(
            TorrentFileNames::resolve([['raw\\xFF.mkv', '0', '0', '1', '0']], str_repeat('0', 40), false) === [],
            'a valid name that spells an escape gets no label; rows are told apart by their bytes',
        );
    }

    public function testRowCountPathIndexAndLengthMustMatch()
    {
        $files = [$this->file(["x\xFF"], ['first']), $this->file(["y\xFF"], ['second'])];
        $source = $this->source($files);
        $rows = $this->rows($files);
        foreach ([[[$rows[0]], ['x\\xFF']],
            [[$rows[1], $rows[0]], ['y\\xFF', 'x\\xFF']],
            [[["x\xFF", '2', '3', '124', '1'], $rows[1]], ['x\\xFF', 'second']]] as $case) {
            list($changed, $expected) = $case;
            $this->assertTrue(
                TorrentFileNames::resolve($changed, $source->hash_info(), $source) === $expected,
                'only exact hash, count, index, path and size matches may borrow source aliases',
            );
        }
        $this->assertTrue(
            $this->resolve([$this->file(["x\xFF"], ['first'], '123')]) === [0 => 'x\\xFF'],
            'a length that is not an integer is not a file the daemon loaded',
        );
        $numeric = $this->source([$this->file(['100', "x\xFF"], ['100', 'alias'])]);
        $this->assertTrue(
            TorrentFileNames::resolve([["1e2/x\xFF", '0', '0', '123', '1']], $numeric->hash_info(), $numeric) === [0 => '1e2/x\\xFF'],
            'paths are compared by their bytes, not as numbers',
        );
        $encoded = $this->source([$this->file([self::RAW])], 'Windows-1251');
        $this->assertTrue(
            TorrentFileNames::resolve([[self::RAW, '0', '0', '124', '1']], $encoded->hash_info(), $encoded) === [0 => self::SHOWN],
            'a row that differs from its metainfo entry is not converted from the declared encoding',
        );
    }

    public function testInvalidAliasComponentsAndShapeUseVisibleBytes()
    {
        foreach ([[], [''], ['.'], ['..'], ['a/b'], ["a\0b"], ["a\xFF"], ['a', 'b'], 'text', [5]] as $alias) {
            $this->assertTrue(
                $this->resolve([$this->file(["x\xFF"], $alias)]) === [0 => 'x\\xFF'],
                'an invalid alternative cannot change path shape or smuggle bytes into Unicode',
            );
        }
        $source = new Torrent(TorrentEncoder::raw(['info' => ['name' => 'collection', 'files' => 'text']]));
        $this->assertTrue(self::quietly(function () use ($source) {
            return TorrentFileNames::resolve([["x\xFF", '0', '0', '123', '1']], $source->hash_info(), $source);
        }) === [[0 => 'x\\xFF'], []], 'a file list that is not a list supplies nothing and raises nothing');
        $bare = new Torrent(TorrentEncoder::raw(['info' => ['name' => "x\xFF"]]));
        $this->assertTrue(self::quietly(function () use ($bare) {
            return TorrentFileNames::resolve([["x\xFF", '0', '0', '123', '1']], $bare->hash_info(), $bare);
        }) === [[0 => 'x\\xFF'], []], 'metainfo without files or a length supplies nothing and raises nothing');
    }

    public function testConflictingDirectoryAliasesFallBackForTheWholePrefix()
    {
        $files = [$this->file(["d\xFF", "a\xFF"], ['first', 'A']),
            $this->file(["d\xFF", "b\xFF"], ['second', 'B']), $this->file(['ok', "c\xFF"], ['ok', 'C'])];
        $this->assertTrue(
            self::quietly(function () use ($files) {
                return $this->resolve($files);
            }) === [['d\\xFF/A', 'd\\xFF/B', 'ok/C'], []],
            'conflicting directory labels fall back for every descendant while file aliases remain usable',
        );
        $files = [$this->file(["a\xFF", 'file'], ['same', 'file']),
            $this->file(["b\xFF", 'file'], ['same', 'file'])];
        $this->assertTrue(
            $this->resolve($files) === ['same/file', 'same/file'],
            'different raw directories keep their own aliases even when the labels are equal',
        );
    }

    public function testDeclaredEncodingIsAcceptedOnlyWithAnExactRoundtrip()
    {
        $files = [$this->file([self::RAW])];
        $this->assertTrue(
            $this->resolve($files, 'Windows-1251') === [0 => self::converts('серия.mkv')],
            'an explicit source encoding with an exact reverse mapping supplies the Unicode label',
        );
        $this->assertTrue(
            self::quietly(function () use ($files) {
                return $this->resolve($files, 'not-an-encoding');
            }) === [[0 => self::SHOWN], []],
            'an unsupported declared encoding falls back without a diagnostic',
        );
        $this->assertTrue($this->resolve($files, 'Windows-1251//IGNORE') === [0 => self::SHOWN], 'encoding fields cannot request lossy converter flags');
        // PHP 8.2+ deprecates HTML-ENTITIES in mbstring: the way back is reached and must stay quiet.
        $this->assertTrue(
            self::quietly(function () use ($files) {
                return $this->resolve($files, 'HTML-ENTITIES');
            }) === [[0 => self::SHOWN], []],
            'a pseudo-encoding that does not map back falls back without a diagnostic',
        );

        // Run the real resolver with only its two converter functions replaced.
        $class = file_get_contents(__DIR__ . '/../../php/torrentfilenames.php');
        $class = str_replace(
            ['TorrentFileNames', 'iconv', 'mb_convert_encoding'],
            ['TorrentFileNamesConverterFixture', 'tfn_iconv', 'tfn_mb_convert_encoding'],
            $class,
        );
        if (!class_exists('TorrentFileNamesConverterFixture')) {
            eval(substr($class, strpos($class, 'class TorrentFileNamesConverterFixture')));
        }
        $fixture = 'TorrentFileNamesConverterFixture';
        $source = $this->source($files, 'Windows-1251');
        $exact = function ($converter, $text) {
            return ($text === TorrentFileNamesTest::RAW) ? 'серия.mkv' : TorrentFileNamesTest::RAW;
        };
        $GLOBALS['torrentFileNamesConverter'] = $exact;
        $this->assertTrue(
            $fixture::resolve($this->rows($files), $source->hash_info(), $source) === [0 => 'серия.mkv'],
            'the replaced converters are the ones the resolver calls',
        );
        $invalid = function ($converter, $text) {
            return ($text === TorrentFileNamesTest::RAW) ? "bad\xFF" : TorrentFileNamesTest::RAW;
        };
        $slash = function ($converter, $text) {
            return ($text === TorrentFileNamesTest::RAW) ? 'a/b' : TorrentFileNamesTest::RAW;
        };
        $unknown = function ($converter, $text) {
            if ($converter === 'iconv') {
                return false;
            }
            throw class_exists('ValueError') ? new ValueError('unknown encoding') : new Error('unknown encoding');
        };
        foreach ([function () {
            return false;
        }, function () {
            return 'partial';
        }, function () {
            return "bad\xFF";
        },
            function ($converter, $text) {
                return ($text === TorrentFileNamesTest::RAW) ? 'серия.mkv' : 'other bytes';
            },
            $invalid, $slash, $unknown] as $failure) {
            $GLOBALS['torrentFileNamesConverter'] = $failure;
            $this->assertTrue(
                $fixture::resolve($this->rows($files), $source->hash_info(), $source) === [0 => self::SHOWN],
                'failed, partial, one-way or reversible but unusable conversion keeps the original bytes visible',
            );
        }
        unset($GLOBALS['torrentFileNamesConverter']);
    }

    public function testSingleFileNameAlias()
    {
        $source = new Torrent(TorrentEncoder::raw(['info' => ['name' => "x\xFF", 'name.utf-8' => '🙂', 'length' => 123]]));
        $this->assertTrue(
            TorrentFileNames::resolve([["x\xFF", '0', '0', '123', '1']], $source->hash_info(), $source) === [0 => '🙂'],
            'single-file metainfo supplies name.utf-8',
        );
    }

    public function testConvertersDisabledInTheActualPhpRuntimeKeepTheVisibleFallback()
    {
        $files = [$this->file([self::RAW])];
        $source = $this->source($files, 'Windows-1251');
        $script = 'require ' . var_export(__DIR__ . '/../../php/Torrent.php', true) . ';'
            . ' require ' . var_export(__DIR__ . '/../../php/torrentfilenames.php', true) . ';'
            . ' $source=new Torrent(base64_decode(' . var_export(base64_encode((string) $source), true) . '));'
            . ' $rows=unserialize(base64_decode(' . var_export(base64_encode(serialize($this->rows($files))), true) . '));'
            . ' echo json_encode(TorrentFileNames::resolve($rows,$source->hash_info(),$source));';
        foreach (['iconv', 'mb_convert_encoding', 'iconv,mb_convert_encoding'] as $disabled) {
            $result = PhpProcess::run(array_merge(PhpProcess::sameIni(), ['-d', 'display_errors=stderr',
                '-d', 'disable_functions=' . $disabled, '-r', $script]));
            $this->assertTrue(($result['code'] === 0) && ($result['err'] === ''), 'a disabled converter causes no failure or diagnostic');
            // One available backend is enough; environments may ship neither.
            // TestLib may stand in an iconv() of its own, so ask for extensions.
            $available = ($disabled === 'iconv') ? extension_loaded('mbstring')
                : (($disabled === 'mb_convert_encoding') && extension_loaded('iconv'));
            $this->assertTrue(
                json_decode($result['out'], true) === [$available ? 'серия.mkv' : self::SHOWN],
                'the available converter is used or the original bytes remain visible',
            );
        }
    }

    public function testMixedRowsLabelOnlyTheLegacyRow()
    {
        $files = [$this->file(['parent', '中文.mkv']),
            $this->file(['parent', "raw\xFF.mkv"], ['ignored', 'серия.mkv'])];
        $this->assertTrue(TorrentFileNames::needsMetadata($this->rows($files)), 'a later legacy row still requests the source');
        $this->assertTrue($this->resolve($files) === [1 => 'parent/серия.mkv'], 'only the legacy row gets a label, under its own index');
    }

    public function testNestedDirectoryAliasesShareOnlyTheirExactRawPrefix()
    {
        $files = [
            $this->file(['Outer', "d\xFF", 'one'], ['ignored', 'Общий', 'one']),
            $this->file(['Outer', "d\xFF", 'two'], ['ignored', 'Общий', 'two']),
            $this->file(['Other', "d\xFF", 'three'], ['ignored', 'Другой', 'three']),
            $this->file(['Same', "d\xFF", 'four'], ['ignored', 'Первый', 'four']),
            $this->file(['Same', "d\xFF", 'five'], ['ignored', 'Второй', 'five']),
            $this->file(['Outer', "d\xFF", "e\xFF", 'six'], ['ignored', 'Общий', 'Первый', 'six']),
            $this->file(['Outer', "d\xFF", "e\xFF", 'seven'], ['ignored', 'Общий', 'Второй', 'seven']),
        ];
        $this->assertTrue(
            $this->resolve($files) === ['Outer/Общий/one', 'Outer/Общий/two', 'Other/Другой/three',
                'Same/d\\xFF/four', 'Same/d\\xFF/five', 'Outer/Общий/e\\xFF/six', 'Outer/Общий/e\\xFF/seven'],
            'matching aliases survive, different parent paths stay independent, and each conflicting depth falls back locally',
        );
    }

    public function testAlternativePathMustBeAListRatherThanAnEqualSizedDictionary()
    {
        $files = [$this->file(['parent', "raw\xFF"], ['dir' => 'other', 'file' => 'alias'])];
        $this->assertTrue(
            self::quietly(function () use ($files) {
                return $this->resolve($files);
            }) === [[0 => 'parent/raw\\xFF'], []],
            'a dictionary with the right element count still cannot supply positional path aliases',
        );
    }

    public function testDeclaredEncodingCannotIntroducePathSeparators()
    {
        // CP500 maps byte 0x61 to slash and 0xFF to U+009F with an exact roundtrip.
        $this->assertTrue(
            $this->resolve([$this->file(["a\xFF"])], 'CP500') === [0 => 'a\\xFF'],
            'a reversible conversion still cannot turn one raw component into a directory path',
        );
    }

    public function testTitleRequiresTheExactSourceIdentity()
    {
        $source = new Torrent(TorrentEncoder::raw(['info' => ['name' => "raw\xFF", 'name.utf-8' => 'серия', 'length' => 123]]));
        $hash = $source->hash_info();
        $this->assertTrue(TorrentFileNames::title("raw\xFF", $hash, $source) === 'серия', 'a matching title uses the declared Unicode alias');
        $this->assertTrue(TorrentFileNames::title("raw\xFF", strtolower($hash), $source) === 'серия', 'the daemon hash matches in either case');
        $this->assertTrue(TorrentFileNames::title("raw\xFF", str_repeat('0', 40), $source) === 'raw\\xFF', 'an unrelated source cannot supply the title');
        $this->assertTrue(TorrentFileNames::title("other\xFF", $hash, $source) === 'other\\xFF', 'the daemon title must match metainfo bytes');
        $plain = new Torrent(TorrentEncoder::raw(['encoding' => 'Windows-1251', 'info' => ['name' => 'серия', 'name.utf-8' => 'alias', 'length' => 123]]));
        $this->assertTrue(TorrentFileNames::title('серия', $plain->hash_info(), $plain) === 'серия', 'a UTF-8 title is its own label, whatever the metadata declares');
        $unsafe = new Torrent(TorrentEncoder::raw(['info' => ['name' => "raw\xFF", 'name.utf-8' => 'a/b', 'length' => 123]]));
        $this->assertTrue(TorrentFileNames::title("raw\xFF", $unsafe->hash_info(), $unsafe) === 'raw\\xFF', 'an alias that is not one path component is not used');
        $this->assertTrue(
            self::quietly(function () use ($source) {
                return TorrentFileNames::title("raw\xFF", null, $source);
            }) === ['raw\\xFF', []],
            'a torrent without a hash supplies nothing and raises nothing',
        );
        $encoded = new Torrent(TorrentEncoder::raw(['encoding' => 'Windows-1251', 'info' => ['name' => "raw\xFF", 'length' => 123]]));
        $this->assertTrue(
            TorrentFileNames::title("raw\xFF", $encoded->hash_info(), $encoded) === self::converts('rawя', 'raw\\xFF'),
            'without an alias the title converts from the declared encoding',
        );
        $this->assertTrue(
            TorrentFileNames::title("other\xFF", $encoded->hash_info(), $encoded) === 'other\\xFF',
            'a daemon title that differs from the metainfo name is not converted',
        );
    }

    private function source($files, $encoding = null)
    {
        $meta = ['info' => ['name' => 'collection', 'piece length' => 16384,
            'pieces' => str_repeat('x', 20), 'files' => $files]];
        if ($encoding !== null) {
            $meta['encoding'] = $encoding;
        }
        return new Torrent(TorrentEncoder::raw($meta));
    }

    private function file($path, $alias = null, $length = 123)
    {
        $file = ['length' => $length, 'path' => $path];
        if ($alias !== null) {
            $file['path.utf-8'] = $alias;
        }
        return $file;
    }

    private function rows($files)
    {
        return array_map(function ($file) {
            return [implode('/', $file['path']), '2', '3', (string) $file['length'], '1', 'extension-value'];
        }, $files);
    }

    private function resolve($files, $encoding = null)
    {
        $source = $this->source($files, $encoding);
        return TorrentFileNames::resolve($this->rows($files), $source->hash_info(), $source);
    }

    // A declared Windows-1251 name converts where iconv or mbstring is loaded.
    private static function converts($converted, $shown = self::SHOWN)
    {
        return (extension_loaded('iconv') || extension_loaded('mbstring')) ? $converted : $shown;
    }
}
