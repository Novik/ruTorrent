<?php

/**
 * A per-profile disk queue for Telegram notifications. rTorrent's shell helper
 * writes NUL-delimited .event records; one scheduled PHP sender drains them.
 */
class rTelegramQueue
{
    public const MAX_QUEUE_ITEMS = 1000;
    public const BATCH_WINDOW_MICROSECONDS = 1000000;
    public const SEND_INTERVAL_MICROSECONDS = 3000000;
    public const MAX_SEND_ATTEMPTS = 3;

    private $directory;

    public function __construct($directory = null)
    {
        if (is_string($directory) && $directory !== '') {
            $this->directory = rtrim($directory, '/');
        } elseif (class_exists('FileUtil')) {
            $this->directory = rtrim(FileUtil::getSettingsPath(), '/') . '/telegram';
        } else {
            $this->directory = dirname(__FILE__) . '/queue';
        }
    }

    public function getDirectory()
    {
        return $this->directory;
    }

    public function getPendingCount()
    {
        return count($this->queueFiles());
    }

    /** Acquire the sender lock before loading the ruTorrent/PHP stack. */
    public function acquireWorkerLock()
    {
        if (!$this->ensureDirectory()) {
            return false;
        }
        $lockPath = $this->directory . '/worker.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            return false;
        }
        @chmod($lockPath, 0600);
        if (!@flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return false;
        }
        return $lock;
    }

    /**
     * Drain the queue if no other sender is active. The caller must load the
     * profile configuration at file scope before entering this method, because
     * ruTorrent's profile globals are initialized by config.php. The optional
     * timing parameters keep tests deterministic; production uses a short
     * coalescing window and conservative spacing between Telegram requests.
     * A caller that supplies an acquired worker lock retains ownership of it.
     */
    public function drain($config = null, $client = null, $batchWindowMicroseconds = null, $sendIntervalMicroseconds = null, $workerLock = null)
    {
        if (!$this->ensureDirectory()) {
            $this->logMessage('notification queue directory is unavailable to the sender');
            return false;
        }
        if ($config === null) {
            return false;
        }
        if ($batchWindowMicroseconds === null) {
            $batchWindowMicroseconds = self::BATCH_WINDOW_MICROSECONDS;
        }
        if ($sendIntervalMicroseconds === null) {
            $sendIntervalMicroseconds = self::SEND_INTERVAL_MICROSECONDS;
        }

        $ownsLock = ($workerLock === null);
        $lock = $ownsLock ? $this->acquireWorkerLock() : $workerLock;
        if ($lock === false) {
            return false;
        }

        $didWork = false;
        $lastSentAt = 0;
        try {
            while (true) {
                $keepDraining = $this->drainAvailable(
                    $config,
                    $client,
                    $batchWindowMicroseconds,
                    $sendIntervalMicroseconds,
                    $lastSentAt,
                    $didWork,
                );
                // Keep the lock through the whole drain, including new arrivals.
                // An event arriving at shutdown is picked up by the next tick.
                if (!$keepDraining || !$this->hasPendingItems()) {
                    break;
                }
            }
        } finally {
            if ($ownsLock) {
                @flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        return $didWork;
    }

    private function drainAvailable($config, $client, $batchWindowMicroseconds, $sendIntervalMicroseconds, &$lastSentAt, &$didWork)
    {
        if (!$this->hasPendingItems()) {
            return true;
        }
        if ($batchWindowMicroseconds > 0) {
            usleep($batchWindowMicroseconds);
        }

        $chunks = $this->renderChunks($config);
        foreach ($chunks as $chunk) {
            $result = null;
            for ($attempt = 0; $attempt < self::MAX_SEND_ATTEMPTS; $attempt++) {
                $this->waitForSendInterval($lastSentAt, $sendIntervalMicroseconds);
                $result = $config->sendMessage($chunk['text'], $client, 'notification batch', false);
                $lastSentAt = microtime(true);
                if (is_array($result) && !empty($result['ok'])) {
                    break;
                }
                if (!$this->isRetryable($result) || $attempt + 1 >= self::MAX_SEND_ATTEMPTS) {
                    break;
                }
                $this->waitBeforeRetry($result, $attempt);
            }

            if (is_array($result) && !empty($result['ok'])) {
                $this->removeItems($chunk['files']);
                $didWork = true;
                continue;
            }

            $config->logNotificationFailure(
                is_array($result) ? $result : ['error' => 'Telegram request failed'],
                'notification batch',
            );
            if ($this->isRetryable($result)) {
                return false;
            } // Keep transient failures queued for the next scheduled tick.
            $this->removeItems($chunk['files']); // Permanent failures must not block later notifications.
        }
        return true;
    }

    private function renderChunks($config)
    {
        $chunks = [];
        $currentText = '';
        $currentFiles = [];
        foreach ($this->queueFiles() as $file) {
            $item = $this->readItem($file);
            if (!is_array($item) || !isset($item['event'], $item['torrent'], $item['hash'], $item['comment'], $item['details'])
                || !in_array($item['event'], self::eventNames(), true)) {
                @unlink($file);
                $this->logMessage('discarded an invalid queued notification');
                continue;
            }
            if (!$config->canNotify($item['event'])) {
                @unlink($file);
                continue;
            }

            $message = $config->render($item['event'], $item['torrent'], $item['hash'], $item['comment'], $item['details']);
            if (trim($message) === '') {
                @unlink($file);
                $this->logMessage('discarded an empty rendered notification');
                continue;
            }
            $separator = ($currentText === '') ? '' : "\n\n";
            if ($currentText !== '' && strlen($currentText) + strlen($separator) + strlen($message) > rTelegram::MAX_TEMPLATE_LENGTH) {
                $chunks[] = ['text' => $currentText, 'files' => $currentFiles];
                $currentText = '';
                $currentFiles = [];
                $separator = '';
            }
            $currentText .= $separator . $message;
            $currentFiles[] = $file;
        }
        if ($currentText !== '') {
            $chunks[] = ['text' => $currentText, 'files' => $currentFiles];
        }
        return $chunks;
    }

    private function readItem($file)
    {
        $data = @file_get_contents($file);
        $parts = explode("\0", $data === false ? '' : $data);
        // Seven NUL-terminated fields. A record queued by an older version has
        // only the first four: its size, label and ratio are left empty.
        if ((count($parts) !== 8 && count($parts) !== 5) || array_pop($parts) !== '') {
            return null;
        }
        $parts = array_pad($parts, 7, '');
        // Bound metadata and replace invalid UTF-8 bytes before rendering.
        $item = [
            'event' => $parts[0],
            'torrent' => self::limitString($parts[1], 4096),
            'hash' => self::limitString($parts[2], 256),
            'comment' => self::limitString($parts[3], 2048),
            'details' => [
                'size' => self::limitString($parts[4], 32),
                'label' => self::limitString($parts[5], 4096),
                'ratio' => self::limitString($parts[6], 32),
            ],
        ];
        return json_decode(json_encode($item, JSON_INVALID_UTF8_SUBSTITUTE), true);
    }

    private function waitForSendInterval($lastSentAt, $sendIntervalMicroseconds)
    {
        if ($lastSentAt <= 0 || $sendIntervalMicroseconds <= 0) {
            return;
        }
        $remaining = $sendIntervalMicroseconds - (int) ((microtime(true) - $lastSentAt) * 1000000);
        if ($remaining > 0) {
            usleep($remaining);
        }
    }

    private function waitBeforeRetry($result, $attempt)
    {
        if (is_array($result) && isset($result['retry_after']) && is_numeric($result['retry_after'])) {
            $seconds = max(1, (int) $result['retry_after']);
        } else {
            $seconds = 1 << $attempt;
        }
        if ($seconds >= 1) {
            sleep($seconds);
        }
    }

    private function isRetryable($result)
    {
        if (!is_array($result) || !isset($result['http_code'])) {
            return true;
        } // Transport errors have no HTTP status.
        $code = (int) $result['http_code'];
        return $code === 0 || $code === 429 || $code >= 500;
    }

    private function ensureDirectory()
    {
        if (is_link($this->directory)) {
            return false;
        }
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0700, true);
        }
        if (!is_dir($this->directory) || is_link($this->directory)) {
            return false;
        }
        @chmod($this->directory, 0700);
        clearstatcache(true, $this->directory);
        $mode = @fileperms($this->directory);
        return is_writable($this->directory) && ($mode === false || (($mode & 0077) === 0));
    }

    private function queueFiles()
    {
        $files = glob($this->directory . '/*.event') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    private function hasPendingItems()
    {
        return count($this->queueFiles()) > 0;
    }

    private function removeItems($files)
    {
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function logMessage($message)
    {
        if (class_exists('FileUtil')) {
            FileUtil::toLog('telegram: ' . $message);
        } else {
            error_log('telegram: ' . $message);
        }
    }

    private static function eventNames()
    {
        return ['added', 'finished', 'removed', 'checkfailed'];
    }

    private static function limitString($value, $length)
    {
        $value = (string) $value;
        return strlen($value) > $length ? substr($value, 0, $length) : $value;
    }
}
