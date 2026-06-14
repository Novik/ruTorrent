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
 *
 * Dependencies (loaded by the production caller before non-"off" modes):
 *   php/util.php    — FileUtil::toLog
 *   php/xmlrpc.php  — rXMLRPCRequest::send
 * (Both are required by plugins/httprpc/action.php, the production caller.)
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

	private static function log($msg)
	{
		if(self::$log)
			FileUtil::toLog("xmlrpc-proxy: ".$msg);
	}

	/**
	 * Parse untrusted XMLRPC XML with entity loading disabled.
	 *
	 * PHP 8+ libxml2 defaults external-entity loading off; PHP 7.x does
	 * not, and ruTorrent still supports PHP 7. We disable it explicitly to
	 * prevent XXE on client-supplied XML.
	 */
	private static function parseXml($rawData)
	{
		$prev = null;
		if(PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader'))
			$prev = libxml_disable_entity_loader(true);
		$xml = @simplexml_load_string($rawData, 'SimpleXMLElement', LIBXML_NONET);
		if($prev !== null)
			libxml_disable_entity_loader($prev);
		return $xml;
	}

	/**
	 * Process a raw XMLRPC payload according to the configured mode.
	 *
	 * @param string $rawData     Raw XMLRPC XML from the client
	 * @param string $mode        "off", "passthrough_unsafe", or "sanitize"
	 * @param bool   $enableLog   Enable/disable logging
	 * @param array  $safeParams  Whitelisted command prefixes for load.* params
	 * @return string|null        SCGI response, or null on rejection
	 */
	public static function process($rawData, $mode = 'sanitize', $enableLog = true, $safeParams = array())
	{
		self::$log = $enableLog;

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
		$xml = self::parseXml($rawData);
		if($xml === false || !isset($xml->methodName))
		{
			self::log("untrusted (invalid XML)");
			return rXMLRPCRequest::send($rawData, false);
		}

		$methodName = (string)$xml->methodName;

		if(in_array($methodName, self::$sanitizeMethods, true))
		{
			$rebuilt = self::rebuildLoadParams($xml, $methodName, $safeParams);
			if(count($rebuilt['stripped']) > 0)
				self::log("sanitized: ".$methodName." (kept ".$rebuilt['kept']." params, stripped: ".implode(', ', $rebuilt['stripped']).")");
			else
				self::log("trusted: ".$methodName." (".$rebuilt['kept']." params)");
			return rXMLRPCRequest::send($rebuilt['xml'], true);
		}

		// Unknown method — pass through as untrusted.
		// rtorrent's own whitelist will allow/reject.
		self::log("untrusted: ".$methodName);
		return rXMLRPCRequest::send($rawData, false);
	}

	/**
	 * Check if a load.* command parameter matches the safe-prefix whitelist.
	 */
	private static function isSafeLoadParam($paramValue, $safeParams)
	{
		foreach($safeParams as $prefix)
		{
			if(strpos($paramValue, $prefix) === 0)
				return true;
		}
		return false;
	}

	/**
	 * Extract a load.* command-param value from its <value> element.
	 *
	 * Handles both the typed form <value><string>foo</string></value> and
	 * the implicit-string form <value>foo</value>. For non-string types
	 * (<int>, <base64>) the raw text is returned; it simply won't match
	 * any whitelist prefix and will be stripped — safe default.
	 */
	private static function extractParamValue($paramElement)
	{
		if(isset($paramElement->string))
			return (string)$paramElement->string;
		return trim((string)$paramElement);
	}

	/**
	 * Rebuild a load.* call keeping only safe parameters.
	 *
	 *   Param 0: target           (always kept)
	 *   Param 1: URL or raw data  (always kept)
	 *   Param 2+: command strings (kept iff prefix-matches the whitelist,
	 *                              otherwise stripped)
	 *
	 * Public for unit testing — production callers should go through
	 * process().
	 *
	 * @return array ['xml' => string, 'kept' => int, 'stripped' => array]
	 */
	public static function rebuildLoadParams($xml, $methodName, $safeParams = array())
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
					$value = self::extractParamValue($param->value);
					if(self::isSafeLoadParam($value, $safeParams))
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

		return array('xml' => $cleanXml, 'kept' => $kept, 'stripped' => $stripped);
	}
}
