<?php

require_once(dirname(__FILE__).'/../../php/settings.php');

/**
 * Per-user Telegram notification settings and rTorrent event integration.
 */
class rTelegram
{
	public $hash = 'telegram.dat';
	public $modified = false;
	public $enabled = false;
	public $token = '';
	public $chatId = '';
	public $events = array(
		'added' => false,
		'finished' => false,
		'removed' => false,
	);
	public $templates = array(
		'added' => "Torrent {STATE}: {TORRENT}\n{LINK}",
		'finished' => "Torrent {STATE}: {TORRENT}\n{LINK}",
		'removed' => "Torrent {STATE}: {TORRENT}\n{LINK}",
	);

	const MAX_TEMPLATE_LENGTH = 4096;
	const MAX_CHAT_ID_LENGTH = 256;

	public static function eventNames()
	{
		return array('added', 'finished', 'removed');
	}

	public static function load()
	{
		$cache = new rCache();
		$config = new rTelegram();
		$cache->get($config);
		self::protectStorage();
		$config->normalize();
		return $config;
	}

	public function store()
	{
		$this->normalize();
		$cache = new rCache();
		$ret = $cache->set($this);
		self::protectStorage();
		return $ret;
	}

	/**
	 * Update settings from the URL-encoded form used by ruTorrent.
	 * An empty token deliberately preserves the existing token: the browser
	 * never receives the secret and therefore cannot send it back unchanged.
	 */
	public function updateFromInput($input)
	{
		if(!is_array($input))
			$input = array();

		$this->enabled = self::truthy(isset($input['enabled']) ? $input['enabled'] : false);
		if(array_key_exists('token_clear', $input) && self::truthy($input['token_clear']))
			$this->token = '';
		else if(isset($input['token']) && is_string($input['token']) && trim($input['token']) !== '')
			$this->token = trim($input['token']);

		if(isset($input['chat_id']) && is_scalar($input['chat_id']))
			$this->chatId = self::limitString(trim((string)$input['chat_id']), self::MAX_CHAT_ID_LENGTH);

		foreach(self::eventNames() as $event)
		{
			$this->events[$event] = self::truthy(
				isset($input['event_'.$event]) ? $input['event_'.$event] : false
			);
			$key = 'template_'.$event;
			if(isset($input[$key]) && is_string($input[$key]))
				$this->templates[$event] = self::truncateMessage($input[$key]);
		}
		$this->normalize();
	}

	/** Backwards-compatible action entry point used by small third-party skins. */
	public function set()
	{
		$this->updateFromInput(self::requestInput());
		$this->store();
		$this->setHandlers();
	}

	public static function requestInput()
	{
		if(!empty($_POST))
			return $_POST;
		$raw = file_get_contents('php://input');
		$input = array();
		if($raw !== false && $raw !== '')
			parse_str($raw, $input);
		return $input;
	}

	/** Return only non-sensitive settings to JavaScript. */
	public function get()
	{
		$data = array(
			'enabled' => (bool)$this->enabled,
			'tokenConfigured' => ($this->token !== ''),
			'chatId' => $this->chatId,
			'events' => $this->events,
			'templates' => $this->templates,
		);
		return 'theWebUI.telegram = '.json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE).";\n";
	}

	public function normalize()
	{
		$this->enabled = (bool)$this->enabled;
		$this->token = is_string($this->token) ? trim($this->token) : '';
		$this->chatId = is_scalar($this->chatId) ? self::limitString(trim((string)$this->chatId), self::MAX_CHAT_ID_LENGTH) : '';
		if(!is_array($this->events))
			$this->events = array();
		if(!is_array($this->templates))
			$this->templates = array();
		foreach(self::eventNames() as $event)
		{
			$this->events[$event] = !empty($this->events[$event]);
			if(!isset($this->templates[$event]) || !is_string($this->templates[$event]))
				$this->templates[$event] = self::defaultTemplate($event);
			$this->templates[$event] = self::truncateMessage($this->templates[$event]);
		}
	}

	public static function defaultTemplate($event)
	{
		return "Torrent {STATE}: {TORRENT}\n{LINK}";
	}

	public function validationErrors($event = null)
	{
		$this->normalize();
		$errors = array();
		if($this->token === '')
			$errors[] = 'bot token is not configured';
		if($this->chatId === '')
			$errors[] = 'chat ID is not configured';
		if($event !== null)
		{
			if(!in_array($event, self::eventNames(), true))
				$errors[] = 'unknown event';
			else if(empty($this->events[$event]))
				$errors[] = 'event is disabled';
		}
		return $errors;
	}

	public function canNotify($event)
	{
		return $this->enabled && empty($this->validationErrors($event));
	}

	public function render($event, $torrent, $hash, $comment = '')
	{
		$template = isset($this->templates[$event]) ? $this->templates[$event] : self::defaultTemplate($event);
		$state = $event;
		$link = self::markdownLink($comment);
		if($link === '')
			$template = str_replace(array("\r\n{LINK}", "\n{LINK}"), '{LINK}', $template);
		$message = str_replace(
			array('{STATE}', '{TORRENT}', '{HASH}', '{LINK}'),
			array($state, self::escapeMarkdown((string)$torrent), (string)$hash, $link),
			$template
		);
		return self::truncateMessage($message);
	}

	public static function decodeComment($comment)
	{
		$comment = (string)$comment;
		$prefix = 'VRS24mrker';
		if(strpos($comment, $prefix) === 0)
			return rawurldecode(substr($comment, strlen($prefix)));
		return '';
	}

	private static function markdownLink($comment)
	{
		$comment = self::decodeComment($comment);
		if($comment === '' || !preg_match('`^https?://[^\s]+$`i', $comment))
			return '';
		$comment = str_replace(array('\\', '(', ')'), array('%5C', '%28', '%29'), $comment);
		return "[link]({$comment})";
	}

	private static function escapeMarkdown($value)
	{
		return str_replace(
			array('\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'),
			array('\\\\', '\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!'),
			$value
		);
	}

	public static function truncateMessage($message)
	{
		$message = (string)$message;
		if(strlen($message) <= self::MAX_TEMPLATE_LENGTH)
			return $message;
		$message = substr($message, 0, self::MAX_TEMPLATE_LENGTH);
		// Do not send a partial UTF-8 code point. Telegram counts characters,
		// while this conservative byte limit also works without mbstring.
		while($message !== '' && preg_match('//u', $message) !== 1)
			$message = substr($message, 0, -1);
		return $message;
	}

	public static function handlerName($event)
	{
		return 'telegram'.$event.User::getUser();
	}

	public function setHandlers()
	{
		$settings = rTorrentSettings::get();
		$commands = array();
		foreach(self::eventNames() as $event)
		{
			$handler = self::handlerName($event);
			$command = !empty($this->events[$event]) && $this->enabled
				? $this->notificationCommand($event)
				: getCmd('cat=');
			$commands[] = $this->eventCommand($settings, $event, array($handler, $command));
		}
		$req = new rXMLRPCRequest($commands);
		return $req->success();
	}

	public static function removeHandlers()
	{
		$config = new rTelegram();
		$settings = rTorrentSettings::get();
		$commands = array();
		foreach(self::eventNames() as $event)
			$commands[] = $config->eventCommand($settings, $event, array(self::handlerName($event), getCmd('cat=')));
		return (new rXMLRPCRequest($commands))->success();
	}

	private function eventCommand($settings, $event, $args)
	{
		switch($event)
		{
			case 'added': return $settings->getOnInsertCommand($args);
			case 'finished': return $settings->getOnFinishedCommand($args);
			case 'removed': return $settings->getOnEraseCommand($args);
		}
		return $settings->getOnFinishedCommand($args);
	}

	/**
	 * Keep the shell fixed and pass torrent data through "$@".  Torrent names
	 * therefore never become shell source, even when they contain quotes or
	 * shell metacharacters.
	 */
	public function notificationCommand($event)
	{
		$path = dirname(__FILE__);
		$quote = function($value) {
			return '"'.str_replace(array('\\', '"', '$'), array('\\\\', '\\"', '\\$'), (string)$value).'"';
		};
		$script = '"$0" "$@" </dev/null >/dev/null 2>&1 &';
		$value = function($command) {
			return '(cat,('.getCmd($command).'))';
		};
		return getCmd('execute.nothrow').'={sh,-c,'.$quote($script).','.
			$quote(Utility::getPHP()).','.$quote($path.'/notify.php').','.
			$quote($event).','.$value('d.get_name').','.$value('d.get_hash').','.$value('d.get_custom2').','.$quote(User::getUser()).'}';
	}

	private static function protectStorage()
	{
		$name = FileUtil::getSettingsPath().'/telegram.dat';
		if(is_file($name))
			@chmod($name, 0600);
	}

	public static function sanitizeError($message, $token = '')
	{
		$message = str_replace(array("\r", "\n"), ' ', (string)$message);
		if($token !== '')
			$message = str_replace($token, '[redacted]', $message);
		return substr($message, 0, 1000);
	}

	private static function truthy($value)
	{
		return $value === true || $value === 1 || $value === '1' || $value === 'on' || $value === 'true';
	}

	private static function limitString($value, $length)
	{
		$value = (string)$value;
		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}
}

/** Small, injectable Telegram Bot API client. */
class rTelegramClient
{
	private $transport;

	public function __construct($transport = null)
	{
		$this->transport = $transport;
	}

	public function send($token, $chatId, $message, $parseMode = null)
	{
		$message = rTelegram::truncateMessage($message);
		$url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
		$payload = array('chat_id' => $chatId, 'text' => $message);
		if($parseMode !== null && $parseMode !== '')
			$payload['parse_mode'] = $parseMode;
		$options = array('connect_timeout' => 5, 'timeout' => 10);
		if(is_callable($this->transport))
		{
			try
			{
				$result = call_user_func($this->transport, $url, $payload, $options);
			}
			catch(Exception $e)
			{
				return array('ok' => false, 'error' => rTelegram::sanitizeError($e->getMessage(), $token));
			}
			return $this->normalizeTransportResult($result, $token);
		}

		if(!function_exists('curl_init'))
			return array('ok' => false, 'error' => 'cURL extension is unavailable');
		$curl = curl_init($url);
		if($curl === false)
			return array('ok' => false, 'error' => 'could not initialize cURL');
		curl_setopt_array($curl, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($payload, '', '&'),
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 10,
		));
		$body = curl_exec($curl);
		$curlError = curl_error($curl);
		$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($body === false)
			return array('ok' => false, 'error' => rTelegram::sanitizeError('transport: '.$curlError, $token), 'http_code' => $httpCode);
		return $this->parseResponse($body, $httpCode, $token);
	}

	private function normalizeTransportResult($result, $token)
	{
		if(!is_array($result))
			return array('ok' => false, 'error' => 'invalid transport response');
		if(array_key_exists('ok', $result) && !array_key_exists('body', $result))
			return $result['ok'] === true ? array('ok' => true) : array('ok' => false, 'error' => rTelegram::sanitizeError(isset($result['error']) ? $result['error'] : 'Telegram API error', $token));
		return $this->parseResponse(isset($result['body']) ? $result['body'] : '', isset($result['http_code']) ? (int)$result['http_code'] : 0, $token);
	}

	private function parseResponse($body, $httpCode, $token)
	{
		if($httpCode < 200 || $httpCode >= 300)
			return array('ok' => false, 'error' => 'HTTP '.$httpCode, 'http_code' => $httpCode);
		$data = json_decode($body, true);
		if(!is_array($data))
			return array('ok' => false, 'error' => 'malformed Telegram response', 'http_code' => $httpCode);
		if(isset($data['ok']) && $data['ok'] === true)
			return array('ok' => true, 'http_code' => $httpCode);
		$error = isset($data['description']) ? $data['description'] : 'Telegram API returned ok=false';
		return array('ok' => false, 'error' => rTelegram::sanitizeError($error, $token), 'http_code' => $httpCode);
	}
}
