<?php
require_once('cache.php');
require_once('../conf/config.php');
header('Content-Type: application/json');
if (!isset($LogTab_array) || !is_array($LogTab_array)) {
    http_response_code(204);
    exit;
}

class LogHandler
{
    public $hash = 'log_history.dat';
    public $logs = [];
    public $modified = false;
    public $max_entries;
    public $log_count;
    protected $cache;

    static public function load()
    {
        global $LogTab_array;
        $cache = new rCache();
        $handler = new LogHandler($cache);
        if ($cache->get($handler)) {
            $handler->cache = $cache;
        } else {
            $handler->logs = [];
        }
        $handler->max_entries = max(1, intval($LogTab_array['max_entries']));
        $handler->log_count   = max(1, intval($LogTab_array['log_count']));
        return $handler;
    }
    public function __construct(rCache $cache)
    {
        $this->cache = $cache;
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
        $this->logs[] = ['message' => $message, 'status' => $status];
        if (count($this->logs) > $this->max_entries) {
            $before = count($this->logs);
            $this->logs = array_slice($this->logs, -$this->max_entries);
        }
        $this->cache->set($this);
        return ['status' => 'success', 'message' => 'Log saved'];
    }
    public function getLatestLogs(int $count = null): array
    {
        $count = $count !== null ? max(1, $count) : $this->log_count;
        return array_values(array_filter(
            array_slice($this->logs, -$count),
            fn($l) => is_array($l) && isset($l['message'], $l['status'])
        ));
    }
    static public function handleRequest()
    {
        $handler = self::load();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $msg  = trim($_POST['message'] ?? '');
            $st   = trim($_POST['status'] ?? '');
            $resp = $handler->saveLog($msg, $st);
        } else {
            $resp = $handler->getLatestLogs();
        }
        echo JSON::safeEncode($resp);
    }
}
LogHandler::handleRequest();
