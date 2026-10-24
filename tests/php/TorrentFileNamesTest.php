<?php

require_once(__DIR__.'/TorrentNamesFixture.php');
require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/../../php/torrentfilenames.php');

function tfn_iconv($from, $to, $text)
{
    return $GLOBALS['torrentFileNamesConverter']($text, $from, $to);
}

function tfn_mb_convert_encoding($text, $to, $from)
{
    return $GLOBALS['torrentFileNamesConverter']($text, $from, $to);
}

class TorrentFileNamesEndpointSource
{
    public static $calls = 0;
    public static $hash;
    public static $source;
    public static $rows;
    public static function getSource($hash)
    {
        self::$calls++;
        self::$hash = $hash;
        if(self::$source instanceof Throwable) throw self::$source;
        return self::$source;
    }
}

function tfn_makeMulticall($commands, $hash, $add, $prefix)
{
    return TorrentFileNamesEndpointSource::$rows;
}

class TorrentFileNamesTest extends TestCase
{
    use TorrentNamesAssertions;
    private function source($files, $encoding = null)
    {
        $meta = array('info'=>array('name'=>'collection', 'piece length'=>16384,
            'pieces'=>str_repeat('x', 20), 'files'=>$files));
        if($encoding !== null) $meta['encoding'] = $encoding;
        return new Torrent(TorrentEncoder::raw($meta));
    }

    private function file($path, $alias = null, $length = 123)
    {
        $file = array('length'=>$length, 'path'=>$path);
        if($alias !== null) $file['path.utf-8'] = $alias;
        return $file;
    }

    private function rows($files)
    {
        return array_map(function($file) {
            return array(implode('/', $file['path']), '2', '3', (string)$file['length'], '1', 'extension-value');
        }, $files);
    }

    private function resolve($files, $encoding = null)
    {
        $source = $this->source($files, $encoding);
        return TorrentFileNames::resolve($this->rows($files), $source->hash_info(), $source);
    }

    public function testValidUnicodeKeepsExistingRowsAndNeedsNoMetadata()
    {
        $rows = array(array('中文/серия 🙂.mkv', '1', '2', '3', '1', 'extension'));
        $this->assertSame(false, TorrentFileNames::needsMetadata($rows), 'valid non-Russian UTF-8 does not request source metadata');
        $this->assertSame($rows, TorrentFileNames::resolve($rows, str_repeat('A', 40)), 'all Unicode and extension columns stay byte-identical');
    }

    public function testCapturedLegacyEpisodeUsesItsAttestedUtf8Alias()
    {
        // Minimal names and size from the owner-supplied torrent; no announce or passkey.
        $raw = 'Panty And Stocking TV 01 '.hex2bin('f1e5f0e8ff').'.mkv';
        $files = array($this->file(array('Anime', $raw), array('Other', 'Panty And Stocking TV 01 серия.mkv'), 3014966836));
        $rows = $this->rows($files);
        $this->assertTrue(TorrentFileNames::needsMetadata($rows), 'legacy file names request metadata');
        $actual = $this->resolve($files);
        $this->assertSame(array('name'=>'Anime/Panty And Stocking TV 01 серия.mkv',
            'pathKey'=>'Anime/'.rawurlencode($raw)), $actual[0][0], 'valid raw components are retained and only the invalid component uses its alias');
        $this->assertSame(array_slice($rows[0], 1), array_slice($actual[0], 1), 'sizes, priorities and plugin extension columns retain their exact slots');
    }

    public function testMissingOrUnattestedMetadataKeepsDistinctEscapedNames()
    {
        $files = array($this->file(array("raw\xFF.mkv"), array('alias.mkv')));
        $source = $this->source($files);
        foreach(array(false, null, new stdClass(), $source) as $candidate) {
            $rows = TorrentFileNames::resolve($this->rows($files), str_repeat('0',40), $candidate);
            $this->assertSame(array('name'=>'raw\\xFF.mkv', 'pathKey'=>'raw%FF.mkv'), $rows[0][0], 'missing source or a mismatched info hash cannot supply an alias');
        }
        $rows = TorrentFileNames::resolve(array(array("raw\\xFF\xFF.mkv", '0','0','1','0')), str_repeat('0',40));
        $this->assertSame('raw\\\\xFF\\xFF.mkv', $rows[0][0]['name'], 'literal backslashes cannot masquerade as escaped bytes');
    }

    public function testRowCountPathIndexAndLengthMustMatch()
    {
        $files = array($this->file(array("x\xFF"), array('first')), $this->file(array("y\xFF"), array('second')));
        $source = $this->source($files); $rows = $this->rows($files);
        foreach(array(array(array($rows[0]), array('x\\xFF')),
            array(array($rows[1], $rows[0]), array('y\\xFF', 'x\\xFF')),
            array(array(array("x\xFF",'2','3','124','1'), $rows[1]), array('x\\xFF', 'second'))) as $case) {
            list($changed, $expected) = $case;
            $result = TorrentFileNames::resolve($changed, $source->hash_info(), $source);
            $this->assertSame($expected, array_map(function($row) { return $row[0]['name']; }, $result), 'only exact hash, count, index, path and size matches may borrow source aliases');
        }
    }

    public function testInvalidAliasComponentsAndShapeUseVisibleBytes()
    {
        foreach(array(array(), array(''), array('.'), array('..'), array('a/b'), array("a\0b"), array("a\xFF"), array('a','b'), 'text', array(5)) as $alias) {
            $result = $this->resolve(array($this->file(array("x\xFF"), $alias)));
            $this->assertSame('x\\xFF', $result[0][0]['name'], 'an invalid alternative cannot change path shape or smuggle bytes into Unicode');
        }
    }

    public function testConflictingDirectoryAliasesFallBackForTheWholePrefix()
    {
        $files = array($this->file(array("d\xFF", "a\xFF"), array('first', 'A')),
            $this->file(array("d\xFF", "b\xFF"), array('second', 'B')));
        $rows = $this->resolve($files);
        $this->assertSame('d\\xFF/A', $rows[0][0]['name'], 'conflicting directory labels fall back while file aliases remain usable');
        $this->assertSame('d\\xFF/B', $rows[1][0]['name'], 'every descendant uses the same deterministic fallback directory');
        $files = array($this->file(array("a\xFF",'file'), array('same','file')),
            $this->file(array("b\xFF",'file'), array('same','file')));
        $rows = $this->resolve($files);
        $this->assertTrue($rows[0][0]['pathKey'] !== $rows[1][0]['pathKey'], 'different raw directories keep different identities despite equal labels');
    }

    public function testDeclaredEncodingIsAcceptedOnlyWithAnExactRoundtrip()
    {
        $bytes = hex2bin('f1e5f0e8ff');
        $files = array($this->file(array($bytes.'.mkv')));
        $this->assertSame('серия.mkv', $this->resolve($files, 'Windows-1251')[0][0]['name'], 'an explicit source encoding with an exact reverse mapping supplies the Unicode label');
        // Run the real resolver with only its two external converter functions replaced.
        $class = file_get_contents(__DIR__.'/../../php/torrentfilenames.php');
        $class = str_replace(array('TorrentFileNames', 'iconv', 'mb_convert_encoding'),
            array('TorrentFileNamesConverterFixture', 'tfn_iconv', 'tfn_mb_convert_encoding'), $class);
        if(!class_exists('TorrentFileNamesConverterFixture')) eval(substr($class, strpos($class, 'final class')));
        $source = $this->source($files, 'Windows-1251');
        foreach(array(function(){return false;}, function(){return null;}, function(){throw new RuntimeException('disabled');},
            function(){throw new Error('disabled');}, function(){return 'partial';}, function(){return "bad\xFF";}) as $failure) {
            $GLOBALS['torrentFileNamesConverter'] = $failure;
            $resolver = new ReflectionMethod('TorrentFileNamesConverterFixture', 'resolve');
            $result = $resolver->invoke(null, $this->rows($files), $source->hash_info(), $source);
            $this->assertSame('\\xF1\\xE5\\xF0\\xE8\\xFF.mkv', $result[0][0]['name'], 'disabled, failed or partial conversion preserves visible original bytes');
        }
        unset($GLOBALS['torrentFileNamesConverter']);
        $this->assertSame('\\xF1\\xE5\\xF0\\xE8\\xFF.mkv', $this->resolve($files, 'not-an-encoding')[0][0]['name'], 'an unsupported declared encoding safely falls back');
        $this->assertSame('\\xF1\\xE5\\xF0\\xE8\\xFF.mkv', $this->resolve($files, 'Windows-1251//IGNORE')[0][0]['name'], 'encoding fields cannot request lossy converter flags');
    }

    public function testSingleFileNameAliasAndStrictUnicodeValidation()
    {
        $source = new Torrent(TorrentEncoder::raw(array('info'=>array('name'=>"x\xFF", 'name.utf-8'=>'🙂', 'length'=>123))));
        $rows = TorrentFileNames::resolve(array(array("x\xFF",'0','0','123','1')), $source->hash_info(), $source);
        $this->assertSame('🙂', $rows[0][0]['name'], 'single-file metainfo supplies name.utf-8');
        foreach(array("\x80", "\xC0\xAF", "\xED\xA0\x80", "\xF4\x90\x80\x80") as $invalid) {
            $this->assertTrue(TorrentFileNames::needsMetadata(array(array($invalid))), 'strict validation rejects continuation bytes, overlongs, surrogates and out-of-range scalars');
            $result = TorrentFileNames::resolve(array(array($invalid)), str_repeat('0',40));
            $this->assertSame(1, preg_match('//u', $result[0][0]['name']), 'the visible fallback always is strict UTF-8');
        }
    }

    public function testConvertersDisabledInTheActualPhpRuntimeKeepTheVisibleFallback()
    {
        $files = array($this->file(array(hex2bin('f1e5f0e8ff').'.mkv')));
        $source = $this->source($files, 'Windows-1251');
        $script = 'require '.var_export(__DIR__.'/../../php/Torrent.php', true).';'
            .' require '.var_export(__DIR__.'/../../php/torrentfilenames.php', true).';'
            .' $source=new Torrent(base64_decode('.var_export(base64_encode((string)$source), true).'));'
            .' $rows=unserialize(base64_decode('.var_export(base64_encode(serialize($this->rows($files))), true).'));'
            .' echo json_encode(TorrentFileNames::resolve($rows,$source->hash_info(),$source));';
        foreach(array('iconv', 'mb_convert_encoding', 'iconv,mb_convert_encoding') as $disabled) {
            $output = array();
            exec(escapeshellarg(PHP_BINARY).' -d disable_functions='.escapeshellarg($disabled)
                .' -r '.escapeshellarg($script).' 2>&1', $output, $status);
            $this->assertSame(0, $status, 'disabled converter functions do not cause a runtime failure');
            $result = json_decode(implode("\n", $output), true);
            $expected = $disabled === 'iconv,mb_convert_encoding' ? '\\xF1\\xE5\\xF0\\xE8\\xFF.mkv' : 'серия.mkv';
            // One available backend is enough; environments may ship neither.
            $available = $disabled === 'iconv' ? function_exists('mb_convert_encoding') : function_exists('iconv');
            if(!$available) $expected = '\\xF1\\xE5\\xF0\\xE8\\xFF.mkv';
            $this->assertSame($expected, $result[0][0]['name'] ?? null, 'the available converter is used or the original bytes remain visible');
        }
    }

    public function testProductionFlsBranchLooksUpSourceOnlyOnceAndRetainsRowsOnFailure()
    {
        $endpoint = file_get_contents(__DIR__.'/../../plugins/httprpc/action.php');
        // The production switch body is exercised with only its RPC/source dependencies replaced.
        $start = strpos($endpoint, 'case "fls":');
        $end = strpos($endpoint, 'case "prs":', $start);
        $branch = substr($endpoint, $start, $end-$start);
        // TestLib's request stub has no unescapeValue(); these rows hold no escapes.
        $branch = str_replace(array('makeMulticall(', 'rTorrent::getSource(', 'rXMLRPCRequest::unescapeValue(',
            "require_once( '../../php/torrentfilenames.php' );", "require_once( '../../php/rtorrent.php' );"),
            array('tfn_makeMulticall(', 'TorrentFileNamesEndpointSource::getSource(', 'strval(', '', ''), $branch);
        $execute = function($rows, $source, $target = null) use($branch) {
            TorrentFileNamesEndpointSource::$rows = $rows;
            TorrentFileNamesEndpointSource::$source = $source;
            TorrentFileNamesEndpointSource::$calls = 0;
            $mode = 'fls'; $hash = array($target === null ? str_repeat('0',40) : $target); $add = array();
            $result = null;
            eval('switch($mode) {'.$branch.'}');
            return $result;
        };
        $valid = array(array('中文/🙂.mkv','0','0','123','1'));
        $this->assertSame($valid, $execute($valid, new RuntimeException('metadata unavailable')), 'the ordinary UTF-8 response never depends on metadata');
        $this->assertSame(0, TorrentFileNamesEndpointSource::$calls, 'all-UTF8 files cause zero source lookups');
        $files = array($this->file(array("x\xFF")), $this->file(array("y\xFF")));
        foreach(array(false, new RuntimeException('source unavailable'), new Error('disabled function')) as $failure) {
            $rows = $execute($this->rows($files), $failure);
            $this->assertSame(1, TorrentFileNamesEndpointSource::$calls, 'multiple legacy names cause at most one source lookup');
            $this->assertSame(2, count($rows), 'source errors never erase the live list');
            $this->assertSame('x\\xFF', $rows[0][0]['name'], 'source errors preserve the visible raw bytes');
        }
        foreach(array('../source', str_repeat('A',40).':d0', str_repeat('A',40)."\n") as $target) {
            $rows = $execute($this->rows($files), false, $target);
            $this->assertSame(0, TorrentFileNamesEndpointSource::$calls, 'a non-hash target never enters the optional metadata filesystem lookup');
            $this->assertSame('x\\xFF', $rows[0][0]['name'], 'a rejected metadata target still preserves live rows');
        }
        $execute($this->rows($files), false, str_repeat('a',40));
        $this->assertSame(str_repeat('A',40), TorrentFileNamesEndpointSource::$hash, 'metadata owner receives a canonical session hash');
    }

    public function testDisabledDisplayPrimitivesNeverInventNamesOrPathKeys()
    {
        $script = 'define("TESTLIB_HANDLER_STUBS",true); require '
            .var_export(__DIR__.'/../plugins/rutracker_check/TestLib.php', true).'; require '
            .var_export(__DIR__.'/../../php/torrentfilenames.php', true).';'
            .' $name="dir\\xFF/file\\xFF"; $hash=str_repeat("A",40);'
            .' $row=TorrentFileNames::resolve(array(array($name,"0","0","1","1")),$hash)[0][0];'
            .' $title=TorrentFileNames::title($name,$hash);'
            .' echo json_encode(array("name"=>base64_encode(is_array($row)?$row["name"]:$row),'
            .' "key"=>is_array($row)?$row["pathKey"]:null,"title"=>base64_encode($title)));';
        foreach(array('rawurlencode', 'preg_replace_callback') as $disabled) {
            $result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d','disable_functions='.$disabled,'-r',$script)));
            $this->assertSame(0, $result['code'], 'a disabled display primitive does not abort the response');
            $this->assertSame('', $result['err'], 'disabled display primitives produce no hidden diagnostics');
            $actual = json_decode($result['out'], true);
            $this->assertSame($disabled === 'rawurlencode' ? null : 'dir%FF/file%FF', $actual['key'], 'missing path encoding never invents a partial identity');
            $this->assertSame(base64_encode($disabled === 'rawurlencode' ? "dir\xFF/file\xFF" : 'dir\\xFF/file\\xFF'), $actual['name'], 'byte display stays available unless the file action identity cannot be encoded');
            $this->assertSame(base64_encode('dir\\xFF/file\\xFF'), $actual['title'], 'title byte display does not depend on optional formatters');
        }
    }

    public function testLargeUtf8ListsKeepTheOrdinaryWireShape()
    {
        $rows = array_fill(0, 20000, array('中文/серия 🙂.mkv','0','0','123','1','plugin'));
        $this->assertSame(false, TorrentFileNames::needsMetadata($rows), 'large ordinary lists need no metadata');
        $this->assertSame($rows, TorrentFileNames::resolve($rows, str_repeat('0',40)), 'large valid lists retain every original row and extension column');
    }

    public function testMixedRowsFindLegacyNamesAfterUtf8WithoutChangingOrdinaryRows()
    {
        $files = array($this->file(array('parent', '中文.mkv')),
            $this->file(array('parent', "raw\xFF.mkv"), array('ignored', 'серия.mkv')));
        $source = $this->source($files);
        $rows = $this->rows($files);
        $this->assertTrue(TorrentFileNames::needsMetadata($rows), 'a later legacy row still requests the source');
        $actual = TorrentFileNames::resolve($rows, $source->hash_info(), $source);
        $this->assertSame($rows[0], $actual[0], 'a UTF-8 row in a mixed list keeps its original wire shape');
        $this->assertSame(array('name'=>'parent/серия.mkv', 'pathKey'=>'parent/raw%FF.mkv'),
            $actual[1][0], 'the later legacy row keeps its slot and raw identity');
    }

    public function testNestedDirectoryAliasesShareOnlyTheirExactRawPrefix()
    {
        $files = array(
            $this->file(array('Outer', "d\xFF", 'one'), array('ignored', 'Общий', 'one')),
            $this->file(array('Outer', "d\xFF", 'two'), array('ignored', 'Общий', 'two')),
            $this->file(array('Other', "d\xFF", 'three'), array('ignored', 'Другой', 'three')),
            $this->file(array('Same', "d\xFF", 'four'), array('ignored', 'Первый', 'four')),
            $this->file(array('Same', "d\xFF", 'five'), array('ignored', 'Второй', 'five')),
            $this->file(array('Outer', "d\xFF", "e\xFF", 'six'), array('ignored', 'Общий', 'Первый', 'six')),
            $this->file(array('Outer', "d\xFF", "e\xFF", 'seven'), array('ignored', 'Общий', 'Второй', 'seven'))
        );
        $actual = $this->resolve($files);
        $this->assertSame(array('Outer/Общий/one', 'Outer/Общий/two', 'Other/Другой/three',
            'Same/d\\xFF/four', 'Same/d\\xFF/five', 'Outer/Общий/e\\xFF/six', 'Outer/Общий/e\\xFF/seven'),
            array_column(array_column($actual, 0), 'name'),
            'matching aliases survive, different parent paths stay independent, and each conflicting depth falls back locally');
        $this->assertSame(array('Outer/d%FF/one', 'Outer/d%FF/two', 'Other/d%FF/three',
            'Same/d%FF/four', 'Same/d%FF/five', 'Outer/d%FF/e%FF/six', 'Outer/d%FF/e%FF/seven'),
            array_column(array_column($actual, 0), 'pathKey'), 'display reconciliation never rewrites raw path keys');
    }

    public function testAlternativePathMustBeAListRatherThanAnEqualSizedDictionary()
    {
        $files = array($this->file(array('parent', "raw\xFF"), array('dir'=>'other', 'file'=>'alias')));
        $actual = $this->resolve($files);
        $this->assertSame(array('name'=>'parent/raw\\xFF', 'pathKey'=>'parent/raw%FF'), $actual[0][0],
            'a dictionary with the right element count still cannot supply positional path aliases');
    }

    public function testDeclaredEncodingCannotIntroducePathSeparators()
    {
        // CP500 maps byte 0x61 to slash and 0xFF to U+009F with an exact roundtrip.
        $actual = $this->resolve(array($this->file(array("a\xFF"))), 'CP500');
        $this->assertSame(array('name'=>'a\\xFF', 'pathKey'=>'a%FF'), $actual[0][0],
            'a reversible conversion still cannot turn one raw component into a directory path');
    }
    public function testUnavailableTitleMetadataFunctionsKeepFallbackWithoutDiagnostics()
    {
        // Isolate the optional resolver from bootstrap's unrelated capability probes.
        $script = 'require '.var_export(__DIR__.'/../../php/torrentfilenames.php', true).';'
            .' class Torrent { public $info=array("name"=>"raw\\xFF"); public $encoding="windows-1251";'
            .' public function errors(){return false;} public function hash_info(){return str_repeat("A",40);} }'
            .' $title=TorrentFileNames::title("raw\\xFF",str_repeat("A",40),new Torrent());'
            .' echo json_encode(array("name"=>$title));';
        // With nothing disabled the declared encoding converts, so each failure below is observable.
        $result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d','display_errors=stderr','-r',$script)));
        $this->assertSame(array('name'=>'rawя'), json_decode($result['out'], true), 'the declared encoding converts when every function is available');
        foreach(array('function_exists', 'preg_match', 'strcasecmp', 'is_array', 'strpos') as $disabled)
        {
            $result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d','display_errors=stderr',
                '-d','disable_functions='.$disabled,'-r',$script)));
            $this->assertSame(0, $result['code'], 'optional '.$disabled.' failure cannot abort a title consumer');
            $this->assertSame('', $result['err'], 'optional '.$disabled.' warnings do not corrupt a feed or notification');
            $actual = json_decode($result['out'], true);
            $this->assertSame('raw\\xFF', $actual['name'] ?? null, 'optional '.$disabled.' failure retains the original bytes visibly');
        }
    }

}
