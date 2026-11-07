<?php

/**
 * A per-profile disk queue for Telegram notifications. Event processes only
 * write a small record; one lock holder batches and sends the pending events.
 */
class rTelegramQueue
{
	const MAX_QUEUE_ITEMS = 1000;
	const BATCH_WINDOW_MICROSECONDS = 1000000;
	const SEND_INTERVAL_MICROSECONDS = 3000000;
	const MAX_SEND_ATTEMPTS = 3;

	private $directory;

	public function __construct($directory = null)
	{
		if(is_string($directory) && $directory !== '')
			$this->directory = rtrim($directory, '/');
		else if(class_exists('FileUtil'))
			$this->directory = rtrim(FileUtil::getSettingsPath(), '/').'/telegram';
		else
			$this->directory = dirname(__FILE__).'/queue';
	}

	public function getDirectory()
	{
		return $this->directory;
	}

	public function getPendingCount()
	{
		return count($this->queueFiles());
	}

	public function enqueue($event, $torrent, $hash, $comment = '')
	{
		if(!in_array($event, self::eventNames(), true))
			return false;
		if(!$this->ensureDirectory())
		{
			$this->logMessage('notification queue directory is unavailable');
			return false;
		}

		$lockPath = $this->directory.'/queue.lock';
		$lock = @fopen($lockPath, 'c');
		if($lock === false)
		{
			$this->logMessage('could not lock the notification queue');
			return false;
		}
		@chmod($lockPath, 0600);
		if(!@flock($lock, LOCK_EX))
		{
			fclose($lock);
			$this->logMessage('could not acquire the notification queue lock');
			return false;
		}

		$ret = false;
		try
		{
			if(count($this->queueFiles()) >= self::MAX_QUEUE_ITEMS)
			{
				$this->logMessage('notification queue is full; dropping a new event');
				return false;
			}
			$ret = $this->writeItem(array(
				'event' => $event,
				'torrent' => self::limitString($torrent, 4096),
				'hash' => self::limitString($hash, 256),
				'comment' => self::limitString($comment, 2048),
				'created_at' => time(),
			));
			if(!$ret)
				$this->logMessage('could not write a notification queue item');
		}
		finally
		{
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return $ret;
	}

	/**
	 * Drain the queue if no other sender is active. The caller must load the
	 * profile configuration at file scope before entering this method, because
	 * ruTorrent's profile globals are initialized by config.php. The optional
	 * timing parameters keep tests deterministic; production uses a short
	 * coalescing window and conservative spacing between Telegram requests.
	 */
	public function drain($config = null, $client = null, $batchWindowMicroseconds = null, $sendIntervalMicroseconds = null)
	{
		if(!$this->ensureDirectory())
		{
			$this->logMessage('notification queue directory is unavailable to the sender');
			return false;
		}
		if($config === null)
			return false;
		if($batchWindowMicroseconds === null)
			$batchWindowMicroseconds = self::BATCH_WINDOW_MICROSECONDS;
		if($sendIntervalMicroseconds === null)
			$sendIntervalMicroseconds = self::SEND_INTERVAL_MICROSECONDS;

		$lockPath = $this->directory.'/worker.lock';
		$lock = @fopen($lockPath, 'c');
		if($lock === false)
			return false;
		@chmod($lockPath, 0600);

		$didWork = false;
		$lastSentAt = 0;
		try
		{
			while(true)
			{
				if(!@flock($lock, LOCK_EX | LOCK_NB))
					break;
				$keepDraining = $this->drainAvailable(
					$config,
					$client,
					$batchWindowMicroseconds,
					$sendIntervalMicroseconds,
					$lastSentAt,
					$didWork
				);
				flock($lock, LOCK_UN);
				// Check after releasing the lock. If an event was enqueued while
				// this worker held it, either this process or the event process
				// will acquire the lock and drain the new item.
				if(!$keepDraining || !$this->hasPendingItems())
					break;
			}
		}
		finally
		{
			@flock($lock, LOCK_UN);
			fclose($lock);
		}
		return $didWork;
	}

	private function drainAvailable($config, $client, $batchWindowMicroseconds, $sendIntervalMicroseconds, &$lastSentAt, &$didWork)
	{
		if(!$this->hasPendingItems())
			return true;
		if($batchWindowMicroseconds > 0)
			usleep($batchWindowMicroseconds);

		$chunks = $this->renderChunks($config);
		foreach($chunks as $chunk)
		{
			$result = null;
			for($attempt = 0; $attempt < self::MAX_SEND_ATTEMPTS; $attempt++)
			{
				$this->waitForSendInterval($lastSentAt, $sendIntervalMicroseconds);
				$result = $config->sendMessage($chunk['text'], $client, 'notification batch', false);
				$lastSentAt = microtime(true);
				if(is_array($result) && !empty($result['ok']))
					break;
				if(!$this->isRetryable($result) || $attempt + 1 >= self::MAX_SEND_ATTEMPTS)
					break;
				$this->waitBeforeRetry($result, $attempt);
			}

			if(is_array($result) && !empty($result['ok']))
			{
				$this->removeItems($chunk['files']);
				$didWork = true;
				continue;
			}

			$config->logNotificationFailure(
				is_array($result) ? $result : array('error' => 'Telegram request failed'),
				'notification batch'
			);
			if($this->isRetryable($result))
				return false; // Keep transient failures queued for the next event to retry.
			$this->removeItems($chunk['files']); // Permanent failures must not block later notifications.
		}
		return true;
	}

	private function renderChunks($config)
	{
		$chunks = array();
		$currentText = '';
		$currentFiles = array();
		foreach($this->queueFiles() as $file)
		{
			$item = json_decode(@file_get_contents($file), true);
			if(!is_array($item) || !isset($item['event'], $item['torrent'], $item['hash'], $item['comment']) ||
				!in_array($item['event'], self::eventNames(), true))
			{
				@unlink($file);
				$this->logMessage('discarded an invalid queued notification');
				continue;
			}
			if(!$config->canNotify($item['event']))
			{
				@unlink($file);
				continue;
			}

			$message = $config->render($item['event'], $item['torrent'], $item['hash'], $item['comment']);
			$separator = ($currentText === '') ? '' : "\n\n";
			if($currentText !== '' && strlen($currentText) + strlen($separator) + strlen($message) > rTelegram::MAX_TEMPLATE_LENGTH)
			{
				$chunks[] = array('text' => $currentText, 'files' => $currentFiles);
				$currentText = '';
				$currentFiles = array();
				$separator = '';
			}
			$currentText .= $separator.$message;
			$currentFiles[] = $file;
		}
		if($currentText !== '')
			$chunks[] = array('text' => $currentText, 'files' => $currentFiles);
		return $chunks;
	}

	private function waitForSendInterval($lastSentAt, $sendIntervalMicroseconds)
	{
		if($lastSentAt <= 0 || $sendIntervalMicroseconds <= 0)
			return;
		$remaining = $sendIntervalMicroseconds - (int)((microtime(true) - $lastSentAt) * 1000000);
		if($remaining > 0)
			usleep($remaining);
	}

	private function waitBeforeRetry($result, $attempt)
	{
		if(is_array($result) && isset($result['retry_after']) && is_numeric($result['retry_after']))
			$seconds = max(1, (int)$result['retry_after']);
		else
			$seconds = 1 << $attempt;
		if($seconds >= 1)
			sleep($seconds);
	}

	private function isRetryable($result)
	{
		if(!is_array($result) || !isset($result['http_code']))
			return true; // Transport errors have no HTTP status.
		$code = (int)$result['http_code'];
		return $code === 0 || $code === 429 || $code >= 500;
	}

	private function writeItem($item)
	{
		try
		{
			$id = sprintf('%010d%06d-%s', time(), (int)((microtime(true) - floor(microtime(true))) * 1000000), bin2hex(random_bytes(6)));
		}
		catch(Exception $e)
		{
			return false;
		}
		$tmp = $this->directory.'/.'.$id.'.tmp';
		$target = $this->directory.'/'.$id.'.json';
		$json = json_encode($item, JSON_INVALID_UTF8_SUBSTITUTE);
		if(!is_string($json))
			return false;
		$fp = @fopen($tmp, 'x');
		if($fp === false)
			return false;
		$offset = 0;
		$length = strlen($json);
		while($offset < $length)
		{
			$written = fwrite($fp, substr($json, $offset));
			if($written === false || $written === 0)
			{
				fclose($fp);
				@unlink($tmp);
				return false;
			}
			$offset += $written;
		}
		$flushed = fflush($fp);
		fclose($fp);
		@chmod($tmp, 0600);
		if(!$flushed || !@rename($tmp, $target))
		{
			@unlink($tmp);
			return false;
		}
		@chmod($target, 0600);
		return true;
	}

	private function ensureDirectory()
	{
		if(is_link($this->directory))
			return false;
		if(!is_dir($this->directory))
			@mkdir($this->directory, 0700, true);
		if(!is_dir($this->directory) || is_link($this->directory))
			return false;
		@chmod($this->directory, 0700);
		clearstatcache(true, $this->directory);
		$mode = @fileperms($this->directory);
		return is_writable($this->directory) && ($mode === false || (($mode & 0077) === 0));
	}

	private function queueFiles()
	{
		$files = glob($this->directory.'/*.json');
		if(!is_array($files))
			return array();
		sort($files, SORT_STRING);
		return $files;
	}

	private function hasPendingItems()
	{
		return count($this->queueFiles()) > 0;
	}

	private function removeItems($files)
	{
		foreach($files as $file)
			@unlink($file);
	}

	private function logMessage($message)
	{
		if(class_exists('FileUtil'))
			FileUtil::toLog('telegram: '.$message);
		else
			error_log('telegram: '.$message);
	}

	private static function eventNames()
	{
		return array('added', 'finished', 'removed');
	}

	private static function limitString($value, $length)
	{
		$value = (string)$value;
		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}
}
