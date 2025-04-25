<?php
require_once('../conf/config.php'); // või vajadusel '../conf/config.php'

if (!isset($log_file) || !file_exists($log_file)) {
    http_response_code(404);
    echo "Log file not found.";
    exit;
}
$lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lines = array_slice($lines, -10);
header('Content-Type: text/plain');
echo implode("\n", $lines);
?>
