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
	 * Make a client-supplied value safe to put in a log line: one line, and
	 * short enough that it cannot push the rest of the entry out of view.
	 */
	private static function logValue($value)
	{
		$value = str_replace(array("\r", "\n", "\t"), ' ', (string)$value);
		if(strlen($value) > 120)
			$value = substr($value, 0, 120).'...';
		return $value;
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
	 * @param array  $safeParams  Command names allowed as load.* params, matched exactly
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

			// Trusted only when every parameter was rebuilt from parts this
			// side parsed. Anything carried over verbatim goes untrusted, so
			// rtorrent still applies its own command restrictions to it.
			$trusted = $rebuilt['rebuiltAll'];

			$state = $trusted ? "trusted" : "untrusted (a parameter could not be rebuilt)";
			if(count($rebuilt['stripped']) > 0)
			{
				$stripped = array();
				foreach($rebuilt['stripped'] as $value)
					$stripped[] = self::logValue($value);
				self::log($state.": ".$methodName." (kept ".$rebuilt['kept']." params, stripped: ".implode(', ', $stripped).")");
			}
			else
				self::log($state.": ".$methodName." (".$rebuilt['kept']." params)");

			return rXMLRPCRequest::send($rebuilt['xml'], $trusted);
		}

		// Unknown method — pass through as untrusted.
		// rtorrent's own whitelist will allow/reject.
		self::log("untrusted: ".self::logValue($methodName));
		return rXMLRPCRequest::send($rawData, false);
	}

	/**
	 * Rebuild one load.* command parameter, or return null to drop it.
	 *
	 * A parameter is not a single command: rtorrent ends a command at ';' or a
	 * newline and calls a parenthesised (command,args) found in a value, so a
	 * string that merely begins with an allowed command can carry others. The
	 * command name is therefore compared for equality, and each argument is
	 * quoted so that whatever it contains stays an argument.
	 *
	 * Arguments are split on ',' first, exactly as rtorrent would split them,
	 * so a command that takes several keeps them, and each is trimmed as
	 * rtorrent trims an unquoted argument.
	 *
	 * Values are taken literally: a client sends d.custom1.set=Movies, Inc,
	 * not a pre-quoted d.custom1.set="Movies, Inc", since the quoting is added
	 * here and a quote in the value is escaped rather than interpreted.
	 */
	private static function rebuildSafeLoadParam($paramValue, $safeParams)
	{
		$separator = strpos($paramValue, '=');
		if($separator === false)
			return null;

		$command = trim(substr($paramValue, 0, $separator));
		if(!in_array($command, $safeParams, true))
			return null;

		$arguments = array();
		foreach(explode(',', substr($paramValue, $separator + 1)) as $argument)
		{
			// rtorrent trims an unquoted argument, so trim before anything else
			// is decided about it — quoting a trimmed value must not turn a
			// leading space into a leading '$'.
			$argument = trim($argument);

			// An argument whose first character is '$' is parsed and called as a
			// command after quoting is undone, so quoting cannot make it safe.
			if(isset($argument[0]) && $argument[0] === '$')
				return null;

			// A client that quoted the value itself gets dropped and logged
			// rather than quietly mangled: the quoting is added here, and the
			// split would fall inside the client's quotes.
			if(isset($argument[0]) && $argument[0] === '"')
				return null;

			$arguments[] = '"'.str_replace(array('\\', '"'), array('\\\\', '\\"'), $argument).'"';
		}

		return $command.'='.implode(',', $arguments);
	}

	/**
	 * Extract a load.* command-param value from its <value> element.
	 *
	 * Handles both the typed form <value><string>foo</string></value> and
	 * the implicit-string form <value>foo</value>. For non-string types
	 * (<int>, <base64>) the raw text is returned; it simply won't match
	 * any allowed command name and will be stripped — safe default.
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
	 *   Param 2+: command strings (kept iff the command name is in the
	 *                              whitelist, otherwise stripped)
	 *
	 * Public for unit testing — production callers should go through
	 * process().
	 *
	 * @return array ['xml' => string, 'kept' => int, 'stripped' => array,
	 *                'rebuiltAll' => bool] — rebuiltAll is false when any
	 *               parameter had to be carried over verbatim, which means
	 *               the call must not be sent as trusted.
	 */
	public static function rebuildLoadParams($xml, $methodName, $safeParams = array())
	{
		$cleanXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$cleanXml .= '<methodCall><methodName>' . htmlspecialchars($methodName) . '</methodName>';
		$cleanXml .= '<params>';

		$kept = 0;
		$stripped = array();

		$rebuiltAll = true;

		if(isset($xml->params->param))
		{
			$index = 0;
			foreach($xml->params->param as $param)
			{
				if($index < 2)
				{
					// Target and URL/data are values, never commands, but they
					// are re-emitted rather than copied so that what was read
					// and what is sent are the same bytes.
					$payload = self::rebuildDataParam($param->value);
					if($payload === null)
					{
						$payload = '<param>' . $param->value->asXML() . '</param>';
						$rebuiltAll = false;
					}
					$cleanXml .= $payload;
					$kept++;
				}
				else
				{
					$value = self::extractParamValue($param->value);
					$rebuiltParam = self::rebuildSafeLoadParam($value, $safeParams);
					if($rebuiltParam !== null)
					{
						$cleanXml .= '<param><value><string>'
							. htmlspecialchars($rebuiltParam, ENT_NOQUOTES, 'UTF-8')
							. '</string></value></param>';
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

		return array('xml' => $cleanXml, 'kept' => $kept, 'stripped' => $stripped,
			'rebuiltAll' => $rebuiltAll);
	}

	/**
	 * Re-emit a target or URL/data parameter from its own content, keeping the
	 * type the client used. Returns null for a type this side cannot rebuild,
	 * which makes the whole request go untrusted.
	 */
	private static function rebuildDataParam($paramElement)
	{
		if(isset($paramElement->base64))
		{
			$decoded = base64_decode((string)$paramElement->base64, true);
			if($decoded === false)
				return null;
			return '<param><value><base64>'.base64_encode($decoded).'</base64></value></param>';
		}

		if(isset($paramElement->string) || count($paramElement->children()) === 0)
		{
			$text = isset($paramElement->string) ? (string)$paramElement->string : (string)$paramElement;
			return '<param><value><string>'
				. htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8')
				. '</string></value></param>';
		}

		return null;
	}
}
