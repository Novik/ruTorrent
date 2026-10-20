<?php

// Use a child process: exit must stop the caller, not the test runner.
function cachedEchoResponse($gzip, $exit)
{
    $directory = (getenv('TMPDIR') ?: sys_get_temp_dir())
        . '/cached-echo-exit-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700)) throw new RuntimeException('Could not create the response directory');
    $worker = <<<'PHP'
require $argv[1];
$tempDirectory = $argv[2];
$profileMask = 0700;
$phpUseGzip = $argv[3] === '1';
$phpGzipLevel = 2;
$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
$body = str_repeat('A', 2500);
if ($argv[4] === '1')
    CachedEcho::send($body, 'text/plain');
else
    CachedEcho::send($body, 'text/plain', false, false);
echo 'AFTER_SEND';
PHP;
    $process = null;
    $pipes = array();
    try {
        $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini',
            '-d', 'zlib.output_compression=0', '-r', $worker,
            __DIR__ . '/../../php/utility/cachedecho.php', $directory,
            $gzip ? '1' : '0', $exit ? '1' : '0'),
            array(0 => array('file', '/dev/null', 'r'),
                1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start the response child');
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) fclose($pipe);
        $pipes = array();
        $status = proc_close($process);
        $process = null;
        if ($status !== 0 || $errors !== '')
            throw new RuntimeException('Response child exited ' . $status . ': ' . $errors);
        if (count(scandir($directory)) !== 2)
            throw new RuntimeException('The response left a temporary file behind');
        return $body;
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
        foreach (glob($directory . '/*') as $path) unlink($path);
        rmdir($directory);
    }
}

$failures = 0;
$cases = array(
    'gzip with default exit stops the caller' => array(true, true),
    'gzip with exit false permits caller cleanup' => array(true, false),
    'plain response with default exit stops the caller' => array(false, true),
    'plain response with exit false permits caller cleanup' => array(false, false),
);
foreach ($cases as $name => $case) {
    try {
        list($gzip, $exit) = $case;
        $body = cachedEchoResponse($gzip, $exit);
        $continued = substr($body, -10) === 'AFTER_SEND';
        if ($continued === $exit)
            throw new RuntimeException('Unexpected caller continuation: ' . ($continued ? 'yes' : 'no'));
        if ($continued) $body = substr($body, 0, -10);
        if ($gzip) $body = gzdecode($body);
        if ($body !== str_repeat('A', 2500))
            throw new RuntimeException('The response body changed');
        echo 'ok - ', $name, "\n";
    } catch (Throwable $error) {
        $failures++;
        echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
    }
}
echo count($cases), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
