<?php

require_once('cache.php');

class LogHandler
{
    public $hash = 'logs.dat';
    public $logs = [];
    public $max_entries = 100;

    public static function handleRequest()
    {
        $handler = new LogHandler();
        $cache = new rCache();
        $cache->get($handler);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            $status = isset($_POST['status']) ? trim($_POST['status']) : '';

            $response = $handler->saveLog($message, $status);
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            $response = $handler->getLatestLogs();
            header('Content-Type: application/json');
            echo json_encode($response);
        }
    }


    public function saveLog($message, $status)
    {
        if (empty($message)) {
            return ['status' => 'error', 'message' => 'No message provided'];
        }

        foreach ($this->logs as $log) {
            if ($log['message'] === $message) {
                return ['status' => 'success', 'message' => 'Log already exists'];
            }
        }

        $this->logs[] = [
            'message' => $message,
            'status' => $status
        ];

        if (count($this->logs) > $this->max_entries) {
            $this->logs = array_slice($this->logs, -$this->max_entries);
        }

        $cache = new rCache();
        $cache->set($this);

        return ['status' => 'success', 'message' => 'Log saved'];
    }

    public function getLatestLogs($count = 10)
{
    return array_values(array_filter(
        array_slice($this->logs, -$count),
        fn($log) => is_array($log) && isset($log['message'], $log['status'])
    ));
}

}

LogHandler::handleRequest();
