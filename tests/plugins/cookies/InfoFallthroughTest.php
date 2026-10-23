<?php

// Each case of the cookies action must end where it is written to end. The
// action is served from a copied tree over HTTP, so $_REQUEST and the request
// body are the real ones, and the tree's CachedEcho records a call and
// returns: a case boundary has to hold on its own, not because the function
// the case happens to end on exits the script.
function cookiesActionRequest($address, $method, $query, $body)
{
    $peer = stream_socket_client('tcp://' . $address, $errno, $error, 3);
    if ($peer === false) throw new RuntimeException('Could not connect to the HTTP fixture: ' . $error);
    try {
        stream_set_timeout($peer, 5);
        fwrite($peer, $method . ' /plugins/cookies/action.php?' . $query . " HTTP/1.0\r\nHost: " . $address
            . "\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body)
            . "\r\nConnection: close\r\n\r\n" . $body);
        $response = stream_get_contents($peer);
    } finally {
        fclose($peer);
    }
    if (!is_string($response) || strpos($response, "\r\n\r\n") === false)
        throw new RuntimeException('The HTTP fixture sent no complete response');
    list($head, $payload) = explode("\r\n\r\n", $response, 2);
    return $payload;
}

function cookiesActionRun($method, $query, $body, $seed)
{
    $root = dirname(__DIR__, 3);
    $tree = (getenv('TMPDIR') ?: sys_get_temp_dir())
        . '/cookies-fallthrough-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($tree . '/plugins/cookies', 0700, true))
        throw new RuntimeException('Could not create the cookies fixture tree');
    $process = null;
    try {
        foreach (array('plugins/cookies/action.php', 'plugins/cookies/cookies.php') as $relative) {
            $source = $root . '/' . $relative;
            $target = $tree . '/' . $relative;
            if (!copy($source, $target) || hash_file('sha256', $source) !== hash_file('sha256', $target))
                throw new RuntimeException('Could not byte-copy ' . $relative);
        }
        mkdir($tree . '/php', 0700);
        // Everything below the action: a cache that is a file, and recorders
        // for the two effects the cases are told apart by.
        file_put_contents($tree . '/php/cache.php', <<<'STUB'
<?php
class rCache
{
    public function get($object)
    {
        $raw = @file_get_contents(COOKIES_CACHE);
        if ($raw === false) return false;
        $object->list = json_decode($raw, true);
        return true;
    }
    public function set($object)
    {
        file_put_contents(COOKIES_STORES, json_encode($object->list) . "\n", FILE_APPEND);
        return file_put_contents(COOKIES_CACHE, json_encode($object->list)) !== false;
    }
}
class JSON
{
    public static function safeEncode($value) { return json_encode($value); }
}
class Utility
{
    public static function quoteAndDeslashEachItem($item) { return "'" . addslashes($item) . "'"; }
}
class CachedEcho
{
    public static function send($content, $type = null, $cacheable = false, $exit = true)
    {
        file_put_contents(COOKIES_SENDS, $type . "\n", FILE_APPEND);
    }
}
STUB
        );
        file_put_contents($tree . '/prepend.php', "<?php\ndefine('COOKIES_CACHE', "
            . var_export($tree . '/cache.json', true) . ");\ndefine('COOKIES_SENDS', "
            . var_export($tree . '/sends', true) . ");\ndefine('COOKIES_STORES', "
            . var_export($tree . '/stores', true) . ");\n");
        file_put_contents($tree . '/cache.json', json_encode($seed));
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($listener === false) throw new RuntimeException('Could not reserve the HTTP address: ' . $error);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini',
            '-d', 'auto_prepend_file=' . $tree . '/prepend.php',
            '-S', $address, '-t', $tree),
            array(0 => array('file', '/dev/null', 'r'),
                1 => array('file', $tree . '/server.log', 'a'),
                2 => array('file', $tree . '/server.log', 'a')), $pipes, $tree);
        if (!is_resource($process)) throw new RuntimeException('Could not start the HTTP fixture');
        $ready = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $peer = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if ($peer !== false) { fclose($peer); $ready = true; break; }
            if (!proc_get_status($process)['running']) break;
            usleep(10000);
        }
        if (!$ready) throw new RuntimeException('The HTTP fixture did not start: '
            . file_get_contents($tree . '/server.log'));
        $payload = cookiesActionRequest($address, $method, $query, $body);
        $sends = array_values(array_filter(explode("\n", @file_get_contents($tree . '/sends') ?: ''), 'strlen'));
        $stores = array_values(array_filter(explode("\n", @file_get_contents($tree . '/stores') ?: ''), 'strlen'));
        return array($sends, $stores, json_decode(file_get_contents($tree . '/cache.json'), true), $payload);
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tree,
            FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file)
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($tree);
    }
}

$seed = array('tracker.example' => array('uid' => '1', 'pass' => 'secret'));
$failures = 0;
$cases = array(
    'reading the cookie list leaves the list alone' => array('GET', 'mode=info', '',
        array('application/json'), array()),
    'reading one host leaves the list alone' => array('GET', 'mode=info&host=tracker.example', '',
        array('application/json'), array()),
    'adding a host stores that host and nothing else' => array('GET',
        'mode=add&host=other.example&cookies=uid%253D2', '',
        array('application/json'),
        array(array('tracker.example' => array('uid' => '1', 'pass' => 'secret'),
            'other.example' => array('uid' => '2')))),
    'an unknown mode is the only case that rewrites the list' => array('POST', 'mode=zzz',
        'cookie=tracker.example%7Cuid%3D9',
        array('application/javascript'), array(array('tracker.example' => array('uid' => '9')))),
);
foreach ($cases as $name => $case) {
    try {
        list($method, $query, $body, $expectedSends, $expectedStores) = $case;
        list($sends, $stores, $list, $payload) = cookiesActionRun($method, $query, $body, $seed);
        if ($sends !== $expectedSends)
            throw new RuntimeException('The action answered ' . count($sends) . ' times ('
                . implode(', ', $sends) . '), not once as ' . implode(', ', $expectedSends));
        if ($stores !== array_map('json_encode', $expectedStores))
            throw new RuntimeException('The action wrote the cache ' . count($stores) . ' times: '
                . implode(' | ', $stores));
        $expectedList = $expectedStores ? end($expectedStores) : $seed;
        if ($list !== $expectedList)
            throw new RuntimeException('The stored list is ' . json_encode($list)
                . ', not ' . json_encode($expectedList));
        if ($payload !== '')
            throw new RuntimeException('The action reported: ' . trim(str_replace("\n", ' ', $payload)));
        echo 'ok - ', $name, "\n";
    } catch (Throwable $error) {
        $failures++;
        echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
    }
}
echo count($cases), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
