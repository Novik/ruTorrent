<?php

require_once(__DIR__ . '/../../php/TestCase.php');

// Stub the dependencies the production callers (httprpc/action.php,
// plugins/erasedata/action.php) load before invoking the helper. The RPC layer
// is scripted per command so the helper's own logic is what gets exercised.
if (!class_exists('FileUtil')) {
    class FileUtil
    {
        public static $settingsPath = null;
        public static $log = [];
        public static function getSettingsPath()
        {
            return self::$settingsPath;
        }
        public static function makeDirectory($dir)
        {
            return @mkdir($dir, 0777, true);
        }
        public static function toLog($msg)
        {
            self::$log[] = $msg;
        }
    }
}
if (!class_exists('rXMLRPCCommand')) {
    class rXMLRPCCommand
    {
        public $command;
        public $params;
        public function __construct($command, $params = null)
        {
            $this->command = $command;
            $this->params = $params;
        }
    }
}
if (!class_exists('rXMLRPCRequest')) {
    class rXMLRPCRequest
    {
        public static $responses = [];	// first command name => array(ok, val)
        public static $requested = [];	// first command name of each request, in order
        public static $erased = [];	// hashes passed to d.erase

        public $val = [];
        private $commands = [];

        public function __construct($commands = null)
        {
            if (is_array($commands)) {
                $this->commands = $commands;
            } elseif (!is_null($commands)) {
                $this->commands = [$commands];
            }
        }
        public function addCommand($command)
        {
            $this->commands[] = $command;
        }
        public function success($trusted = true)
        {
            if (!count($this->commands)) {
                return(false);
            }
            $first = $this->commands[0]->command;
            self::$requested[] = $first;
            foreach ($this->commands as $c) {
                if ($c->command == "d.erase") {
                    self::$erased[] = $c->params;
                }
            }
            if (!array_key_exists($first, self::$responses)) {
                return(false);
            }
            $this->val = self::$responses[$first]["val"];
            return(self::$responses[$first]["ok"]);
        }
    }
}
if (!function_exists('getCmd')) {
    function getCmd($cmd)
    {
        return($cmd);
    }
}

require_once(__DIR__ . '/../../../plugins/erasedata/removewithdata.php');

class RemoveWithDataTest extends TestCase
{
    private $dir;

    public function setUp()
    {
        $this->dir = sys_get_temp_dir() . '/erasedata-test-' . getmypid();
        @mkdir($this->dir, 0777, true);
        FileUtil::$settingsPath = $this->dir;
    }

    // setUp() runs once per class, so each test starts from a clean slate here.
    private function reset()
    {
        foreach (glob($this->dir . '/erasedata/*.list') as $f) {
            @unlink($f);
        }
        FileUtil::$log = [];
        rXMLRPCRequest::$responses = [];
        rXMLRPCRequest::$requested = [];
        rXMLRPCRequest::$erased = [];
    }

    public function tearDown()
    {
        foreach (glob($this->dir . '/erasedata/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/erasedata');
        @rmdir($this->dir);
    }

    // -- helpers ------------------------------------------------------------

    private function frozen($ok, $val)
    {
        rXMLRPCRequest::$responses["d.get_base_path"] = ["ok" => $ok, "val" => $val];
    }
    private function stored($ok, $val)
    {
        rXMLRPCRequest::$responses["d.get_directory"] = ["ok" => $ok, "val" => $val];
    }
    private function eraseOk()
    {
        rXMLRPCRequest::$responses["d.set_custom5"] = ["ok" => true, "val" => ["","",""]];
    }

    private function listFor($hash)
    {
        $f = $this->dir . '/erasedata/' . $hash . '.list';
        return(is_file($f) ? file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false);
    }

    // -- frozen paths available (an opened download) ------------------------

    public function testFrozenPathsUsedForMultiFile()
    {
        $this->reset();
        $this->frozen(true, ["/d/name", 1, "/d/name/a.bin", "/d/name/sub/b.bin"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A"], "1");
        $this->assertEquals(["/d/name/a.bin","/d/name/sub/b.bin","/d/name","1","1"], $this->listFor("A"), 'multi-file list from frozen paths');
        $this->assertEquals(["d.get_base_path","d.set_custom5"], rXMLRPCRequest::$requested, 'no fallback request when frozen paths exist');
    }

    public function testFrozenPathsUsedForSingleFile()
    {
        $this->reset();
        $this->frozen(true, ["/d/movie.mkv", 0, "/d/movie.mkv"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A"], "1");
        $this->assertEquals(["/d/movie.mkv","/d/movie.mkv","0","1"], $this->listFor("A"), 'single-file list from frozen paths');
    }

    // -- frozen paths empty (a download never opened this session) ----------

    public function testFallsBackToStoredPathsForMultiFile()
    {
        $this->reset();
        $this->frozen(true, ["", 1, "", ""]);
        $this->stored(true, ["/d/name", 1, "a.bin", "sub/b.bin"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A"], "1");
        $this->assertEquals(["/d/name/a.bin","/d/name/sub/b.bin","/d/name","1","1"], $this->listFor("A"), 'multi-file list rebuilt from d.directory + f.path');
        $this->assertEquals(["d.get_base_path","d.get_directory","d.set_custom5"], rXMLRPCRequest::$requested, 'fallback request issued');
    }

    public function testFallsBackToStoredPathsForSingleFile()
    {
        $this->reset();
        $this->frozen(true, ["", 0, ""]);
        $this->stored(true, ["/d", 0, "movie.mkv"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A"], "1");
        // d.base_path reports the file itself for a single-file torrent, while
        // d.directory reports the directory holding it.
        $this->assertEquals(["/d/movie.mkv","/d/movie.mkv","0","1"], $this->listFor("A"), 'single-file base path is the file, not its directory');
    }

    public function testFallbackNormalisesTrailingSlash()
    {
        $this->reset();
        $this->frozen(true, ["", 1, ""]);
        $this->stored(true, ["/d/name/", 1, "a.bin"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A"], "1");
        $this->assertEquals(["/d/name/a.bin","/d/name","1","1"], $this->listFor("A"), 'no doubled separator from a trailing slash');
    }

    public function testFallbackUsedWhenFrozenRequestFails()
    {
        $this->reset();
        $this->frozen(false, []);
        $this->stored(true, ["/d/name", 1, "a.bin"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A"], "1");
        $this->assertEquals(["/d/name/a.bin","/d/name","1","1"], $this->listFor("A"), 'a failed frozen request also falls back');
    }

    // -- the delete mode is recorded verbatim -------------------------------

    public function testForceDeleteFlagRecorded()
    {
        $this->reset();
        $this->frozen(true, ["/d/name", 1, "/d/name/a.bin"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A"], "2");
        $lines = $this->listFor("A");
        $this->assertEquals("2", end($lines), 'delete-path mode recorded as the last line');
    }

    // -- refuse to erase what cannot be cleaned up ---------------------------

    public function testTorrentNotErasedWhenNoPathsResolve()
    {
        $this->reset();
        $this->frozen(true, ["", 1, "", ""]);
        $this->stored(true, ["", 1, "", ""]);
        $this->eraseOk();
        $result = erasedataRemoveWithData(["A"], "1");
        $this->assertTrue($this->listFor("A") === false, 'no list written when no path resolves');
        $this->assertEquals([], rXMLRPCRequest::$erased, 'torrent must not be erased when its files are unknown');
        $this->assertTrue($result === false, 'caller is told the removal did not happen');
        $this->assertEquals(1, count(FileUtil::$log), 'the refusal is logged');
    }

    public function testResolvableHashesStillErasedInAMixedBatch()
    {
        $this->reset();
        // The scripted RPC layer answers per command, so both hashes see the
        // same empty frozen reply; only the stored reply resolves.
        $this->frozen(true, ["", 1, ""]);
        $this->stored(true, ["/d/name", 1, "a.bin"]);
        $this->eraseOk();
        erasedataRemoveWithData(["A","B"], "1");
        $this->assertEquals(["A","B"], rXMLRPCRequest::$erased, 'every resolvable hash is erased');
    }
}
