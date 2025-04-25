<?php
require_once('../conf/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';

        if (file_exists($log_file)) {
                $logContents = file_get_contents($log_file);
                if (strpos($logContents, $message) !== false) {
                        echo json_encode(['status' => 'success', 'message' => 'Log already exist']);
                        exit;
                }
        }

        if (file_exists($log_file)) {
                $logContents = file_get_contents($log_file);
                if (strpos($logContents, $message) !== false) {
                        echo json_encode(['status' => 'success', 'message' => 'Log already exist']);
                        exit;
                }
        }

        $logEntry = "$message\n";
        file_put_contents($log_file, $logEntry, FILE_APPEND);

        echo json_encode(['status' => 'success', 'message' => 'Log saved']);
} else {
        echo json_encode(['status' => 'error', 'message' => 'Error on saving to log']);
}
?>
