<?php

// The scheduled sender is independent of torrent events. Losing contenders
// exit before loading ruTorrent, so at most one fully initialized sender runs.
if (!chdir(dirname(__FILE__))) {
    exit(0);
}

$queueDirectory = isset($argv[1]) ? $argv[1] : '';
$_SERVER['REMOTE_USER'] = isset($argv[2]) ? (string) $argv[2] : '';

require_once(dirname(__FILE__) . '/queue.php');
$queue = new rTelegramQueue($queueDirectory);
$lock = $queue->acquireWorkerLock();
if ($lock === false) {
    exit(0);
}

try {
    if ($queue->getPendingCount() > 0) {
        // Load at file scope: deployment config variables such as $profilePath
        // must be initialized globally, not in a queue method's local scope.
        require_once(dirname(__FILE__) . '/telegram.php');
        $config = rTelegram::load();
        $queue->drain($config, null, null, null, $lock);
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
exit(0);
