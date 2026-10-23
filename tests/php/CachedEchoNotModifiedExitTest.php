<?php

// A 304 answer carries no body, so a caller that keeps running after one is
// worse off than a caller that keeps running after a full response: whatever
// it echoes next becomes a body the status forbids. Use a child process:
// exit must stop the caller, not the test runner.
function cachedEchoNotModified($exit)
{
    $root = dirname(__DIR__, 2);
    $worker = <<<'PHP'
require $argv[1];
$phpUseGzip = false;
$body = str_repeat('A', 2500);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_IF_NONE_MATCH'] = '"' . strtoupper(dechex(crc32($body))) . '"';
if ($argv[2] === '1')
    CachedEcho::send($body, 'text/plain', true);
else
    CachedEcho::send($body, 'text/plain', true, false);
echo 'CONTINUED';
PHP;
    $pipes = array();
    $process = null;
    try {
        $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini',
            '-d', 'zlib.output_compression=0', '-r', $worker,
            $root . '/php/utility/cachedecho.php', $exit ? '1' : '0'),
            array(0 => array('file', '/dev/null', 'r'),
                1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start the response child');
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) fclose($pipe);
        $pipes = array();
        $status = proc_close($process);
        $process = null;
        if ($status !== 0 || $errors !== '')
            throw new RuntimeException('Response child exited ' . $status . ': ' . $errors);
        return $output;
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
    }
}

$failures = 0;
$cases = array(
    'a matched etag with default exit stops the caller' => true,
    'a matched etag with exit false permits caller cleanup' => false,
);
foreach ($cases as $name => $exit) {
    try {
        $output = cachedEchoNotModified($exit);
        if (strpos($output, str_repeat('A', 64)) !== false)
            throw new RuntimeException('The 304 answer carried the content as a body');
        $continued = $output === 'CONTINUED';
        if ($continued === $exit)
            throw new RuntimeException('Unexpected caller continuation: ' . ($continued ? 'yes' : 'no'));
        if (!$continued && $output !== '')
            throw new RuntimeException('Unexpected output: ' . var_export($output, true));
        echo 'ok - ', $name, "\n";
    } catch (Throwable $error) {
        $failures++;
        echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
    }
}
echo count($cases), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
