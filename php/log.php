<?php
require_once('cache.php');
require_once('../conf/config.php');

class LogHandler
{
    public $hash = 'logs.dat';
    public $logs = [];
    public $max_entries = 100;
    public $log_count = 10;

    public function __construct()
    {
        global $LogTab_max_entry, $LogTab_count;

        if (!empty($LogTab_max_entry)) {
            $this->max_entries = intval($LogTab_max_entry);
        }

        if (!empty($LogTab_count)) {
            $this->log_count = intval($LogTab_count);
        }
    }

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

    public function getLatestLogs($count = null)
    {
        if ($count === null) {
            $count = $this->log_count;
        }
        return array_values(array_filter(
            array_slice($this->logs, -$count),
            fn($log) => is_array($log) && isset($log['message'], $log['status'])
        ));
    }

}
LogHandler::handleRequest();
