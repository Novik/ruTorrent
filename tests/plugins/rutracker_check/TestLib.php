<?php

/**
 * Shared harness for the rutracker_check test suite.
 *
 * The always-defined part carries the runner, assertions and the XMLRPC test
 * doubles. Handler-facing stubs (Snoopy, a fake ruTrackerChecker, bencode
 * fixture builders backed by the real Torrent class) are defined only when the
 * including test sets TESTLIB_HANDLER_STUBS, because CheckerTest loads the
 * real ruTrackerChecker and a fake Torrent instead.
 */

function testFindRepoRoot()
{
    $path = realpath(__DIR__ . '/../../..');
    if ($path !== false && is_file($path . '/plugins/rutracker_check/trackers/rutracker.php')) return $path;
    throw new RuntimeException('Unable to locate the ruTorrent repository root');
}

class StrictTestSuite
{
    private $tests = array();

    public function test($name, $callback)
    {
        $this->tests[] = array($name, $callback);
    }

    // Register every public test* method of an object as a test case.
    public function addFromObject($object)
    {
        foreach (get_class_methods($object) as $method) {
            if (strpos($method, 'test') === 0) {
                $this->tests[] = array($method, array($object, $method));
            }
        }
    }

    public function run()
    {
        $failures = 0;
        foreach ($this->tests as $test) {
            list($name, $callback) = $test;
            try {
                call_user_func($callback);
                echo "ok - {$name}\n";
            } catch (Throwable $error) {
                $failures++;
                echo "not ok - {$name}\n";
                echo '  ' . get_class($error) . ': ' . $error->getMessage() . "\n";
            }
        }
        echo count($this->tests) . ' tests, ' . $failures . " failures\n";
        return $failures === 0 ? 0 : 1;
    }
}

function strictAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function strictAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function strictRemoveTree($path)
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path), array('.', '..')) as $entry) {
        strictRemoveTree($path . '/' . $entry);
    }
    @rmdir($path);
}

function strictSetPrivateStatic($className, $property, $value)
{
    $reflection = new ReflectionProperty($className, $property);
    if (PHP_VERSION_ID < 80100) {
        $reflection->setAccessible(true);
    }
    $reflection->setValue(null, $value);
}

function strictInvoke($className, $method, $arguments = array())
{
    $reflection = new ReflectionMethod($className, $method);
    if (PHP_VERSION_ID < 80100) {
        $reflection->setAccessible(true);
    }
    return $reflection->invokeArgs(null, $arguments);
}

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

/**
 * XMLRPC test double. Responses are queued per command-name pipeline
 * ('d.hash' or array('d.get_state', 'd.is_open')); every executed request is
 * recorded with its full command objects so tests can assert the parameters
 * (e.g. WHICH hash a d.hash probe targeted), not just the command sequence.
 */
class rXMLRPCRequest
{
    public static $responses = array();
    public static $requests = array();
    private $commands = array();
    public $important = true;
    public $fault = false;
    public $val = array();

    public function __construct($commands = null)
    {
        if (is_array($commands))
            $this->commands = $commands;
        elseif ($commands !== null)
            $this->commands[] = $commands;
    }

    public function addCommand($command)
    {
        $this->commands[] = $command;
    }

    public static function reset()
    {
        self::$responses = array();
        self::$requests = array();
    }

    public static function queue($commands, $ok, $fault, $values = array())
    {
        $key = is_array($commands) ? implode('|', $commands) : $commands;
        self::$responses[$key][] = array($ok, $fault, $values);
    }

    public static function requestsFor($key)
    {
        $matched = array();
        foreach (self::$requests as $request)
            if ($request['key'] === $key)
                $matched[] = $request;
        return $matched;
    }

    private function execute()
    {
        $key = implode('|', array_map(function ($command) { return $command->command; }, $this->commands));
        self::$requests[] = array('key' => $key, 'important' => $this->important, 'commands' => $this->commands);
        $response = (isset(self::$responses[$key]) && count(self::$responses[$key]))
            ? array_shift(self::$responses[$key])
            : array(false, true, array());
        $this->fault = $response[1];
        $this->val = is_callable($response[2]) ? call_user_func($response[2], $this->commands) : $response[2];
        return $response[0];
    }

    public function run($trusted = true)
    {
        return $this->execute();
    }

    public function success($trusted = true)
    {
        return $this->execute() && !$this->fault;
    }
}

class rTorrentSettings
{
    public $session = '/nonexistent/';
    private static $instance;

    public static function get()
    {
        if (!self::$instance)
            self::$instance = new self();
        return self::$instance;
    }
}

if (defined('TESTLIB_HANDLER_STUBS')) {

    $testLibRepoRoot = testFindRepoRoot();
    $testLibPrevCwd = getcwd();
    chdir($testLibRepoRoot . '/php');
    require_once($testLibRepoRoot . '/php/Torrent.php');
    chdir($testLibPrevCwd);

    if (!function_exists('iconv')) {
        function iconv($from, $to, $content)
        {
            $utf8 = 'Поглощено';
            $cp1251 = "\xCF\xEE\xE3\xEB\xEE\xF9\xE5\xED\xEE";
            if (stripos($from, 'UTF-8') === 0 && stripos($to, 'CP1251') === 0) {
                return str_replace($utf8, $cp1251, $content);
            }
            if (stripos($from, 'CP1251') === 0 && stripos($to, 'UTF-8') === 0) {
                return str_replace($cp1251, $utf8, $content);
            }
            return false;
        }
    }

    // Fixtures use the production encoder, so they are byte-identical to what
    // the plugin itself produces and re-parses.
    class TorrentEncoder extends Torrent
    {
        public static function raw($value)
        {
            return self::encode($value);
        }
    }

    function strictTorrentRaw($name, $announce, $comment = '', $announceList = null, $extra = array())
    {
        $root = array(
            'announce' => $announce,
            'info' => array(
                'length' => 1,
                'name' => $name,
                'piece length' => 16384,
                'pieces' => str_repeat("\0", 20),
            ),
        );
        if ($comment !== '') {
            $root['comment'] = $comment;
        }
        if ($announceList !== null) {
            $root['announce-list'] = $announceList;
        }
        foreach ($extra as $key => $value) {
            $root[$key] = $value;
        }
        return TorrentEncoder::raw($root);
    }

    // Built by hand: Torrent::encode drops dictionary keys that start with a
    // NUL byte, and scrape dictionaries are keyed by raw 20-byte hashes.
    function strictScrapePayload($hash, $found)
    {
        if (!$found) {
            return 'd5:filesdee';
        }
        return 'd5:filesd20:' . hex2bin($hash)
            . 'd8:completei1e10:downloadedi1e10:incompletei0eee'
            . 'e';
    }

    function strictCp1251($html)
    {
        $encoded = iconv('UTF-8', 'CP1251//IGNORE', $html);
        if ($encoded === false) {
            throw new RuntimeException('Unable to create CP1251 test fixture');
        }
        return $encoded;
    }

    class Snoopy
    {
        public static $responses = array();
        public static $requests = array();

        public $status = -1;
        public $results = '';
        public $read_timeout = 0;
        public $_fp_timeout = 0;
        public $agent = '';

        public static function reset()
        {
            self::$responses = array();
            self::$requests = array();
        }

        public static function queue($url, $status, $results)
        {
            if (!isset(self::$responses[$url])) {
                self::$responses[$url] = array();
            }
            self::$responses[$url][] = array($status, $results);
        }

        private function respond($method, $url)
        {
            self::$requests[] = array($method, $url);
            if (!isset(self::$responses[$url]) || count(self::$responses[$url]) === 0) {
                throw new RuntimeException("Unexpected {$method} request: {$url}");
            }
            list($this->status, $this->results) = array_shift(self::$responses[$url]);
            return true;
        }

        public function fetch($url, $method = 'GET', $contentType = '', $body = '')
        {
            return $this->respond('fetch', $url);
        }

        public function fetchComplex($url, $method = 'GET', $contentType = '', $body = '')
        {
            return $this->respond('fetchComplex', $url);
        }

        public function setcookies()
        {
        }
    }

    class ruTrackerChecker
    {
        const STE_INPROGRESS = 1;
        const STE_UPDATED = 2;
        const STE_UPTODATE = 3;
        const STE_DELETED = 4;
        const STE_CANT_REACH_TRACKER = 5;
        const STE_ERROR = 6;
        const STE_NOT_NEED = 7;
        const STE_IGNORED = 8;

        const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
            . "AppleWebKit/537.36 (KHTML, like Gecko) "
            . "Chrome/120.0.0.0 Safari/537.36";

        public static $created = array();
        public static $logs = array();
        public static $registrations = array();
        public static $createResult = null;

        public static function reset()
        {
            self::$created = array();
            self::$logs = array();
            self::$registrations = array();
            self::$createResult = null;
            Snoopy::reset();
            rXMLRPCRequest::reset();
        }

        public static function registerTracker($commentFilter, $announceFilter, $handler)
        {
            self::$registrations[] = array($commentFilter, $announceFilter, $handler);
        }

        public static function makeClient($url, $method = 'GET', $contentType = '', $body = '')
        {
            $client = new Snoopy();
            $client->fetchComplex($url, $method, $contentType, $body);
            return $client;
        }

        public static function createTorrent($payload, $oldHash)
        {
            $parsed = @new Torrent($payload);
            if ($parsed->errors() || strlen((string) $parsed->hash_info()) !== 40) {
                return self::STE_ERROR;
            }
            self::$created[] = array('payload' => $payload, 'old_hash' => $oldHash);
            return self::$createResult;
        }

        public static function logDebug($message)
        {
            self::$logs[] = $message;
        }
    }
}
