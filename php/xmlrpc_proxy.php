<?php
/**
 * XMLRPC Proxy — handles raw XMLRPC pass-through with configurable trust.
 *
 * Modes:
 *   "off"                — reject all raw XMLRPC
 *   "passthrough_unsafe" — send all raw XMLRPC as trusted (dangerous)
 *   "sanitize"           — parse and sanitize known methods, send safe
 *                          payload as trusted; pass unknown methods as
 *                          untrusted (rtorrent whitelist decides)
 */

class XMLRPCProxy
{
	// Methods that need trusted connections but can carry command
	// parameters. We rebuild these from scratch, keeping only safe params.
	private static $sanitizeMethods = array(
		'load.start', 'load.raw_start', 'load.raw', 'load.normal',
		'load_start', 'load_raw_start', 'load_raw',
	);

	private static $log = true;
	private static $safeParams = array();

	private static function log($msg)
	{
		if(self::$log)
			FileUtil::toLog("xmlrpc-proxy: ".$msg);
	}

	/**
	 * Process a raw XMLRPC payload according to the configured mode.
	 *
	 * @param string $rawData       Raw XMLRPC XML from the client
	 * @param string $mode          "off", "passthrough_unsafe", or "sanitize"
	 * @param bool   $enableLog     Enable/disable logging
	 * @param array  $safeParams    Whitelisted command prefixes for load.* params
	 * @return string|null          SCGI response, or null on error/rejection
	 */
	public static function process($rawData, $mode = 'sanitize', $enableLog = true, $safeParams = array())
	{
		self::$log = $enableLog;
		self::$safeParams = $safeParams;

		if($mode === 'off')
		{
			self::log("rejected (proxy disabled)");
			return null;
		}

		if($mode === 'passthrough_unsafe')
		{
			self::log("passthrough (UNSAFE mode)");
			return rXMLRPCRequest::send($rawData, true);
		}

		// sanitize mode
		$xml = @simplexml_load_string($rawData);
		if($xml === false || !isset($xml->methodName))
		{
			self::log("rejected (invalid XML)");
			return rXMLRPCRequest::send($rawData, false);
		}

		$methodName = (string)$xml->methodName;

		if(in_array($methodName, self::$sanitizeMethods))
			return self::sanitizeLoad($xml, $methodName);

		// Unknown method — pass through as untrusted.
		// rtorrent's own whitelist will allow/reject.
		self::log("untrusted: ".$methodName);
		return rXMLRPCRequest::send($rawData, false);
	}

	/**
	 * Check if a load.* command parameter is safe.
	 */
	private static function isSafeLoadParam($paramValue)
	{
		foreach(self::$safeParams as $prefix)
		{
			if(strpos($paramValue, $prefix) === 0)
				return true;
		}
		return false;
	}

	/**
	 * Rebuild a load.* call keeping only safe parameters.
	 * param 0: target (empty string)
	 * param 1: URL or raw torrent data
	 * param 2+: command strings — keep safe ones, strip dangerous ones
	 */
	private static function sanitizeLoad($xml, $methodName)
	{
		$cleanXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$cleanXml .= '<methodCall><methodName>' . htmlspecialchars($methodName) . '</methodName>';
		$cleanXml .= '<params>';

		$kept = 0;
		$stripped = array();

		if(isset($xml->params->param))
		{
			$index = 0;
			foreach($xml->params->param as $param)
			{
				if($index < 2)
				{
					// Always keep target and URL/data
					$cleanXml .= '<param>' . $param->value->asXML() . '</param>';
					$kept++;
				}
				else
				{
					// Command parameter — check whitelist
					$value = (string)$param->value->string;
					if(empty($value))
						$value = (string)$param->value;

					if(self::isSafeLoadParam($value))
					{
						$cleanXml .= '<param>' . $param->value->asXML() . '</param>';
						$kept++;
					}
					else
					{
						$stripped[] = $value;
					}
				}
				$index++;
			}
		}

		$cleanXml .= '</params></methodCall>';

		if(count($stripped) > 0)
			self::log("sanitized: ".$methodName." (kept ".$kept." params, stripped: ".implode(', ', $stripped).")");
		else
			self::log("trusted: ".$methodName." (".$kept." params)");

		return rXMLRPCRequest::send($cleanXml, true);
	}
}
