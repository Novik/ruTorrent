<?php
require_once __DIR__.'/../../php/TestCase.php';
require_once __DIR__.'/../../php/PhpProcess.php';

class DirectoryPathSubmitTest extends TestCase
{
    private function assertSame($expected, $actual, $message) { $this->assertTrue($expected === $actual, $message); }

    private function submit($action, $path)
    {
        $repo = dirname(__DIR__, 3);
        $field = $action === 'create' ? 'path_edit' : 'dir';
        $body = http_build_query(array(
            'cmd'=>$action, $field=>$path, 'hash'=>str_repeat('A', 40), 'mode'=>'', 'no'=>'',
            'trackers'=>'https://tracker.example/announce', 'piece_size'=>'256', 'start_seeding'=>'0',
            'private'=>'0', 'hybrid'=>'0', 'comment'=>'', 'source'=>''
        ));
        $code = 'require '.var_export($repo.'/php/utility/fileutil.php', true).'; '
            .'$_SERVER["REQUEST_METHOD"] = "POST"; $_SERVER["REMOTE_USER"] = "fixture"; '
            .'$forbidUserSettings = false; parse_str('.var_export($body, true).', $_POST); $_REQUEST = $_POST; '
            .'$rootPath = "/fixture"; $topDirectory = "/fixture"; '
            .'$useExternal = "inner"; $pathToCreatetorrent = "/fixture/creator"; $recentTrackersMaxCount = 20; '
            . <<<'STUBS'
$started = 0; $validated = null;
class Requests {
    public static $posts = 0;
    public static function requirePost() { self::$posts++; }
}
class JSON { public static function safeEncode($value) { return json_encode($value); } }
class CachedEcho {
    public static function send($body) {
        echo json_encode(array('response'=>json_decode($body, true), 'started'=>$GLOBALS['started'],
            'validated'=>$GLOBALS['validated'], 'posts'=>Requests::$posts));
    }
}
class rTorrentSettings {
    public static function get() { return new self(); }
    public function correctDirectory(&$path) { $GLOBALS['validated'] = base64_encode($path); return true; }
}
class CreateExternalConfiguration { public static function path($mode, $path) { return $path; } }
class rCache {
    public function get($value) { return false; }
    public function set($value) { return true; }
}
class rTask {
    public $id = 1;
    private $parameters;
    public function __construct($parameters) { $this->parameters = $parameters; }
    public static function formatPath($id) { return '/fixture/task'; }
    public function start($commands, $flags) {
        $GLOBALS['started']++;
        return array('path'=>base64_encode($this->parameters['path_edit']),
            'commands'=>array_map('base64_encode', $commands));
    }
}
class rUnpack {
    public static function load() { return new self(); }
    public function startTask($hash, $directory, $mode, $number) {
        $GLOBALS['started']++;
        return array('path'=>base64_encode($directory), 'hash'=>$hash);
    }
}
STUBS
            .'$source = file_get_contents('.var_export($repo.'/plugins/'.$action.'/action.php', true).'); '
            .'$source = preg_replace("~^require_once[^\\n]*|^eval\\([^\\n]*~m", "", $source); '
            .'eval("?>".$source);';
        $process = PhpProcess::run(array('-d', 'display_errors=stderr', '-r', $code));
        $this->assertSame(0, $process['code'], 'actual '.$action.' controller completes: '.$process['err']);
        $this->assertSame('', $process['err'], 'path submission emits no diagnostics');
        $result = json_decode($process['out'], true);
        $this->assertTrue(is_array($result), 'the controller returns a structured result');
        return $result ?: array();
    }

    public function testSelectedPathEnvelopesReachTaskOwnersWithExactBytes()
    {
        foreach (array('create', 'unpack') as $action) {
            foreach (array("/fixture/raw\xFF %2F%25\\part+&=file", '/fixture/雪 %2F%25\\part+&=file') as $raw) {
                $result = $this->submit($action, array('name'=>'visible path', 'pathKey'=>rawurlencode($raw)));
                $this->assertSame(0, $result['posts'] ?? null, 'the upstream request owner retains its existing admission route');
                $this->assertSame(1, $result['started'] ?? null, 'the task owner is invoked exactly once');
                $this->assertSame(base64_encode($raw), $result['response']['path'] ?? null,
                    'task parameters preserve every selected path byte for '.$action);
                if ($action === 'create') {
                    $this->assertSame(base64_encode($raw), $result['validated'] ?? null,
                        'create validates the decoded path before constructing its task');
                    $command = base64_decode($result['response']['commands'][0] ?? '');
                    $this->assertTrue(strpos($command, $raw) !== false,
                        'the external creator command preserves every raw path byte');
                }
            }
        }
    }

    public function testManuallyTypedPathsKeepLiteralPercentAndBackslashBytes()
    {
        $raw = '/fixture/manual %FF%2F%25\\xFF+&=雪';
        foreach (array('create', 'unpack') as $action) {
            $result = $this->submit($action, $raw);
            $this->assertSame(base64_encode($raw), $result['response']['path'] ?? null,
                'manual UTF-8 text is not interpreted as a byte-escape grammar by '.$action);
            $this->assertSame(1, $result['started'] ?? null, 'the manual path reaches its existing task owner');
        }
    }

    public function testSelectedPathsAreTrimmedLikeTypedInput()
    {
        foreach(array("/fixture/raw\xFF ", "/fixture/line\n") as $raw) {
            $result = $this->submit('create', array('name'=>$raw, 'pathKey'=>rawurlencode($raw)));
            $this->assertSame(base64_encode(trim($raw)), $result['validated'] ?? null, 'surrounding whitespace is trimmed before validation');
            $command = base64_decode($result['response']['commands'][0] ?? '');
            $this->assertTrue(strpos($command, "'".trim($raw)."'") !== false, 'the creator command names the trimmed path');
        }
    }

    public function testMalformedPathEnvelopesNeverStartTasks()
    {
        foreach (array('create', 'unpack') as $action) {
            foreach (array(
                array('name'=>'label'), array('pathKey'=>'%2Ffixture%2Fraw%FF'),
                array('name'=>array('label'), 'pathKey'=>'%2Ffixture'),
                array('name'=>'label', 'pathKey'=>array('%2Ffixture')),
                array('name'=>'label', 'pathKey'=>'%2ffixture'),
                array('name'=>'label', 'pathKey'=>'%2Ffixture%'),
            ) as $path) {
                $result = $this->submit($action, $path);
                $this->assertSame(0, $result['started'] ?? null, 'malformed path cannot reach '.$action.' task owner');
                $this->assertSame(null, $result['validated'] ?? null, 'malformed metadata is refused before path validation');
                $response = $result['response'] ?? array();
                $this->assertTrue(!empty($response['errors']) || !empty($response['error']),
                    'refusal is observable in the existing controller response');
            }
        }
    }
    public function testAddTorrentDirectoryValidationTrimsAndKeepsLegacyBytes()
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root.'/php/addtorrent.php');
        $start = strpos($source, '$dir_edit = null;');
        $branch = substr($source, $start, strpos($source, '$addition = null;', $start)-$start);
        foreach(array("/fixture/raw\xFF ", '/fixture/雪 ', "/fixture/line\n", '/fixture/%FF') as $raw) {
            $code = 'class rTorrentSettings { static function get(){return new self();}'
                .'function correctDirectory(&$path){$GLOBALS["validated"][]=$path;return true;}}'
                .'class rTorrent { static function isInsideTopDirectory($path){$GLOBALS["validated"][]=$path;return true;}}'
                .'$validated=array();$uploaded_files=array();parse_str('.var_export('dir_edit='.rawurlencode($raw), true).',$_REQUEST);'
                .$branch.'echo json_encode(array("path"=>base64_encode($dir_edit),"validated"=>array_map("base64_encode",$validated)));';
            $result = PhpProcess::run(array('-d', 'display_errors=stderr', '-r', $code));
            $this->assertSame(0, $result['code'], 'the actual add directory branch completes');
            $this->assertSame('', $result['err'], 'selected paths emit no diagnostics');
            $actual = json_decode($result['out'], true);
            $this->assertSame(base64_encode(trim($raw)), $actual['path'] ?? null, 'the load receives the trimmed path with its bytes');
            $this->assertSame(array(base64_encode(trim($raw)), base64_encode(trim($raw))), $actual['validated'] ?? null,
                'both current upstream directory boundaries validate the same path');
        }
    }

}
