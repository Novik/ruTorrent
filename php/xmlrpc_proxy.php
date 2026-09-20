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
 * process() applies the policy and sends the result; decide() applies it and
 * returns the result, for a caller that owns its own connection to rtorrent.
 *
 * Dependencies of process() (loaded by the production caller before non-"off"
 * modes):
 *   php/util.php    — FileUtil::toLog
 *   php/xmlrpc.php  — rXMLRPCRequest::send
 * (Both are required by plugins/httprpc/action.php, the production caller.)
 * decide() has none.
 */

class XMLRPCProxy
{
	// Methods that need trusted connections but can carry command
	// parameters. We rebuild these from scratch, keeping only safe params.
	//
	// A command that is not kept costs a label or a directory and the torrent
	// is still added, so an unknown one is dropped rather than made to fail
	// the whole call.
	//
	// Every spelling rtorrent registers, both series. src/command_events.cc
	// registers eight: load.normal, load.start, load.verbose,
	// load.start_verbose, load.raw, load.raw_start, load.raw_verbose and
	// load.raw_start_verbose, unchanged between 0.9.8 and current master. The
	// four _verbose ones take the same arguments as the four they are spelled
	// after and differ only in what they print, so a list that holds one and
	// not the other does not rebuild a call it was meant to rebuild: it falls
	// through to the unknown-method path and is forwarded to rtorrent as the
	// caller wrote it.
	//
	// The legacy spellings are the ones php/methods-0.9.4.php maps, so a
	// client written against them reaches the same rebuilding.
	private static $sanitizeMethods = array(
		'load.normal', 'load.start', 'load.verbose', 'load.start_verbose',
		'load.raw', 'load.raw_start', 'load.raw_verbose', 'load.raw_start_verbose',
		'load', 'load_start', 'load_verbose', 'load_start_verbose',
		'load_raw', 'load_raw_start', 'load_raw_verbose', 'load_raw_start_verbose',
	);

	// Of those, the ones whose parameter 1 is a URI rather than the torrent
	// itself. rtorrent treats anything that is not a network or magnet URI as a
	// path on its own filesystem, opens it, and ties the download to it — see
	// $networkUri.
	//
	// The 0.9.x spellings are deliberately absent. They exist on no rtorrent
	// this supports (0.9.8 and 0.16.x both answer "not defined"), and they put
	// the URI one parameter earlier, so testing parameter 1 would read a
	// command string as the URI and refuse a valid call. A load that does not
	// happen ties nothing, so there is nothing to protect there.
	private static $uriLoadMethods = array(
		'load.start', 'load.normal', 'load.start_verbose', 'load.verbose',
	);

	// Exactly the URIs rtorrent does not treat as a local path:
	// is_network_uri() and is_magnet_uri() in core/download_factory.cc, which
	// use strncmp and are therefore case-sensitive. This has to agree with
	// them character for character — accepting a form rtorrent reads as a path
	// would be the hole this closes.
	private static $networkUri = '#^(?:http://|https://|ftp://|magnet:\?)#';

	// Multicalls carry commands in the same trailing position, and the same
	// rebuilding applies — but for these the commands ARE the request, and
	// most of them are read commands (d.name=, t.url=) that no allowlist
	// should have to enumerate. Dropping one would answer with a short row and
	// no fault, so a command this side does not rebuild sends the request on
	// untouched instead, for rtorrent's own gate to judge.
	// Refused outright in sanitize mode, whatever rtorrent would have made of
	// them. Matched as name prefixes, because these are families that differ
	// between versions — 0.9.8 has execute2 and schedule_remove2, 0.16.x has
	// execute.raw.bg and schedule.remove — and an exact list goes stale
	// silently, which for a refusal list is the wrong way to fail.
	//
	// This does not exist because rtorrent would allow them. It exists because
	// rtorrent only refuses them from 0.16.10, where UNTRUSTED_CONNECTION is
	// honoured; below that the header is read and ignored, so "forward it
	// untrusted" is a plain forward and this list is the only refusal there is.
	private static $denyPrefixes = array(
		'execute',                // and execute2, execute.capture, execute.raw.bg, ...
		'method.',                // insert / set / set_key / erase / redirect
		'import', 'try_import',   // read a file of commands
		'schedule',               // and schedule2, schedule.remove, scheduler.*
		'log.',                   // log.execute, log.open_file, log.xmlrpc
		'network.scgi',           // re-open the listener somewhere else
		'session.path.set',
		'directory.default.set',
		'catch',                  // evaluates its argument
		'system.env',
		'system.shutdown',        // and .normal / .quick -- answered by the xmlrpc-c
		                          // registry, not rtorrent's command map, so rtorrent's
		                          // own untrusted gate never sees it
	);

	// The name an older daemon registers for a command the list above may
	// refuse under its current name. php/methods-0.9.4.php is where these come
	// from: it is what rXMLRPCCommand puts every call through, so it is this
	// tree's own statement of which spelling a given daemon takes.
	//
	// Resolved before the list is consulted, so a family is refused under every
	// name the daemon answers to, and so a deployment that narrows the list
	// still gets the list it asked for rather than these as well.
	//
	// Matched whole, not as prefixes: set_session is the legacy spelling of
	// session.path.set, while set_session_lock and set_session_on_completion
	// are different settings the list does not refuse, and a prefix would take
	// them too.
	private static $legacyNames = array(
		'system.method.erase'     => 'method.erase',
		'system.method.get'       => 'method.get',
		'system.method.has_key'   => 'method.has_key',
		'system.method.insert'    => 'method.insert',
		'system.method.list_keys' => 'method.list_keys',
		'system.method.set'       => 'method.set',
		'system.method.set_key'   => 'method.set_key',
		'set_directory'           => 'directory.default.set',
		'set_session'             => 'session.path.set',
		'get_scgi_dont_route'     => 'network.scgi.dont_route',
		'set_scgi_dont_route'     => 'network.scgi.dont_route.set',
	);

	// Methods rtorrent refuses to an untrusted caller that a remote client
	// still needs, with the shape each argument has to have. A call that
	// matches is re-emitted from the parsed parts and sent trusted; anything
	// else is left untrusted, where rtorrent refuses it.
	//
	// The claim being made is per call, not per command: not "d.custom1.set is
	// safe" but "this call, naming one download by hash, with a value rtorrent
	// stores rather than parses, is within what the owner of this instance may
	// do". Measured, not assumed: a $-prefixed value arriving as an XMLRPC
	// parameter of these methods is stored verbatim and never executed.
	private static $elevate = array(
		'd.open'                        => array('hash'),
		'd.start'                       => array('hash'),
		'd.stop'                        => array('hash'),
		'd.custom1.set'                 => array('hash', 'text'),
		'd.custom2.set'                 => array('hash', 'text'),
		'd.custom3.set'                 => array('hash', 'text'),
		'd.custom4.set'                 => array('hash', 'text'),
		'd.custom5.set'                 => array('hash', 'text'),
		'd.custom.set'                  => array('hash', 'text', 'text'),
		'd.priority.set'                => array('hash', 'int'),
		'd.delete_tied'                 => array('hash'),
		'network.xmlrpc.size_limit.set' => array('empty', 'size'),
	);

	// Ceiling for network.xmlrpc.size_limit.set. A client raises it to add a
	// large torrent by file; without a bound it is also how a caller makes
	// rtorrent buffer as much as it likes.
	private static $sizeLimitMax = 16777216;

	// Commands whose argument is a path rtorrent will write a download into.
	// apply_d_directory() (command_download.cc:146) makes it the download's root
	// directory: for a single-file torrent the data lands at <dir>/<info.name>,
	// and the caller wrote the torrent, so it names the file too. Unconfined,
	// that is an arbitrary file write as the user rtorrent runs as — which lands
	// in a PHP-executing docroot if one is reachable and writable.
	//
	// ruTorrent already confines these everywhere else: correctDirectory() holds
	// a directory inside $topDirectory for the panel, for addtorrent.php and for
	// httprpc's own settings branch. This path skipped it.
	private static $directoryCommands = array(
		'd.directory.set', 'd.directory_base.set',
	);

	private static $multicallMethods = array(
		'd.multicall', 'd.multicall2', 'd.multicall.filtered',
		't.multicall', 'f.multicall', 'p.multicall',
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
	 * @param bool   $allowLocalPaths  Let a caller name a path on rtorrent's own
	 *                                 filesystem in load.start / load.normal
	 * @return string|null        SCGI response, or null on rejection
	 */
	public static function process($rawData, $mode = 'sanitize', $enableLog = true, $safeParams = array(), $allowLocalPaths = false, $options = array())
	{
		self::$log = $enableLog;

		$decision = self::decide($rawData, $mode, $safeParams, $allowLocalPaths, $options);

		foreach($decision['log'] as $line)
			self::log($line);

		if($decision['action'] !== 'send')
			return null;

		return rXMLRPCRequest::send($decision['payload'], $decision['trusted']);
	}

	/**
	 * Decide what to do with a raw XMLRPC payload, without acting on it.
	 *
	 * Same policy as process(), separated from the sending and the logging so
	 * that a caller holding its own connection to rtorrent can apply it —
	 * ruTorrent's SCGI plumbing needs the settings bootstrap, and an endpoint
	 * whose whole job is to filter one request should not have to carry that.
	 *
	 * @param string $rawData     Raw XMLRPC XML from the client
	 * @param string $mode        "off", "passthrough_unsafe", or "sanitize"
	 * @param array  $safeParams  Command names allowed as load.* params, matched exactly
	 * @param bool   $allowLocalPaths  Let a caller name a path on rtorrent's own
	 *                                 filesystem in load.start / load.normal.
	 *                                 Off by default: a remote client has no way
	 *                                 to know what is on that filesystem, and the
	 *                                 path it names becomes the download's tied
	 *                                 file, which d.delete_tied then unlinks.
	 * @return array  'action'  => "send" or "reject"
	 *                'payload' => the bytes to send, empty when rejecting
	 *                'trusted' => whether the connection carrying them may be trusted
	 *                'log'     => what happened, in the order it happened
	 */
	public static function decide($rawData, $mode = 'sanitize', $safeParams = array(), $allowLocalPaths = false, $options = array())
	{
		$deny = isset($options['deny']) ? $options['deny'] : self::$denyPrefixes;
		$elevate = isset($options['elevate']) ? $options['elevate'] : self::$elevate;
		$sizeLimitMax = isset($options['sizeLimitMax']) ? $options['sizeLimitMax'] : self::$sizeLimitMax;

		if($mode === 'off')
			return self::reject("rejected (proxy disabled)");

		if($mode === 'passthrough_unsafe')
			return self::forward($rawData, true, "passthrough (UNSAFE mode)");

		// sanitize mode
		$xml = self::parseXml($rawData);
		if($xml === false || !isset($xml->methodName))
			return self::forward($rawData, false, "untrusted (invalid XML)");

		$methodName = (string)$xml->methodName;

		if(self::isDenied($methodName, $deny))
			return self::reject("rejected (not allowed on this connection): ".
				self::logValue($methodName), $methodName);

		$directory = isset($options['directory']) ? $options['directory'] : null;

		if(in_array($methodName, self::$sanitizeMethods, true))
		{
			if(!$allowLocalPaths && in_array($methodName, self::$uriLoadMethods, true))
			{
				$uri = self::loadUri($xml);
				if(($uri !== null) && !preg_match(self::$networkUri, $uri))
					return self::reject("rejected (load from a local path): ".
						$methodName." ".self::logValue($uri), $methodName);
			}

			$rebuilt = self::rebuildLoadParams($xml, $methodName, $safeParams, $directory);

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
				$line = $state.": ".$methodName." (kept ".$rebuilt['kept']." params, stripped: ".implode(', ', $stripped).")";
			}
			else
				$line = $state.": ".$methodName." (".$rebuilt['kept']." params)";

			return self::forward($rebuilt['xml'], $trusted, $line);
		}

		if(in_array($methodName, self::$multicallMethods, true))
		{
			$rebuilt = self::rebuildLoadParams($xml, $methodName, $safeParams, $directory);

			if(count($rebuilt['stripped']) > 0)
			{
				// About to forward the caller's own bytes. Anything in them
				// that rtorrent would run as a command has to be refused here,
				// because untrusted is not a refusal on every version.
				foreach($rebuilt['stripped'] as $value)
				{
					$command = self::refusedCommandName($value, $deny);
					if($command !== null)
						return self::reject("rejected (not allowed on this connection): ".
							$methodName." carrying ".self::logValue($command), $command);

					// Same reason, for a command that is allowed but whose
					// directory is not: stripping it refuses it on the
					// single-call path, and here nothing is stripped.
					$command = self::refusedDirectoryCommand($value, $directory);
					if($command !== null)
						return self::reject("rejected (outside the directory this server allows): ".
							$methodName." carrying ".self::logValue($value), $command);
				}

				return self::forward($rawData, false, "untrusted: ".$methodName." (".
					count($rebuilt['stripped'])." command parameters this side does not rebuild)");
			}

			$trusted = $rebuilt['rebuiltAll'];
			$state = $trusted ? "trusted" : "untrusted (a parameter could not be rebuilt)";

			return self::forward($rebuilt['xml'], $trusted,
				$state.": ".$methodName." (".$rebuilt['kept']." params)");
		}

		if(isset($elevate[$methodName]))
		{
			$built = self::rebuildElevated($xml, $methodName, $elevate[$methodName], $sizeLimitMax);
			if($built !== null)
				return self::forward($built, true, "trusted: ".$methodName." (elevated)");
			return self::forward($rawData, false, "untrusted: ".self::logValue($methodName).
				" (arguments did not match the allowed shape)");
		}

		// system.multicall is about to be forwarded verbatim, and its members
		// are calls rather than command strings, so the check above did not see
		// them. rtorrent refuses them at inner dispatch from 0.16.10 — naming
		// the inner method, which is how we know it does — but not before.
		//
		// A member is judged the way a top-level call is: by its name, and,
		// where that name is one of the command-carrying methods, by the
		// commands its own parameters name. Checking only the member name let
		// a member rtorrent does not refuse — d.multicall — carry a command it
		// does, because nothing looked inside its parameters. The payload is
		// still forwarded as the caller wrote it; this says what may be in it,
		// it does not rebuild it.
		if($methodName === 'system.multicall')
		{
			foreach(self::multicallMembers($xml) as $member)
			{
				if(self::isDenied($member['name'], $deny))
					return self::reject("rejected (not allowed on this connection): ".
						"system.multicall carrying ".self::logValue($member['name']), $member['name']);

				// Nothing here can reason about a nested system.multicall, and
				// xmlrpc-c refuses one at dispatch in any case.
				if($member['name'] === 'system.multicall')
					return self::reject("rejected (nested system.multicall): ".
						"system.multicall carrying system.multicall", 'system.multicall');

				if(!in_array($member['name'], self::$multicallMethods, true) &&
					!in_array($member['name'], self::$sanitizeMethods, true))
					continue;

				// Parameter 0 is the target and parameter 1 the view or the
				// torrent; commands start at 2, the same position
				// rebuildLoadParams reads them from.
				foreach(array_slice($member['params'], 2) as $value)
				{
					// A parameter the single-call path would rebuild is asked
					// nothing further, because that path asks nothing further
					// either: $safeParams names the command, and
					// rebuildSafeLoadParam has already refused an argument
					// rtorrent would call. Its arguments are values, and
					// reading them for names refuses the ones people write --
					// d.custom1.set="catch-up tv" names catch, and
					// d.directory.set="/torrents/my import" names import. A
					// batch is refused whole, so one label costs every add in
					// it. What is forwarded here is still the caller's own
					// bytes, untrusted, where the single-call path rebuilds
					// the same parameter and sends it trusted.
					if(self::rebuildSafeLoadParam($value, $safeParams, $directory) !== null)
						continue;

					$command = self::refusedCommandName($value, $deny);
					if($command !== null)
						return self::reject("rejected (not allowed on this connection): ".
							"system.multicall carrying ".self::logValue($member['name']).
							" carrying ".self::logValue($command), $command);

					$command = self::refusedDirectoryCommand($value, $directory);
					if($command !== null)
						return self::reject("rejected (outside the directory this server allows): ".
							"system.multicall carrying ".self::logValue($member['name']).
							" carrying ".self::logValue($value), $command);
				}
			}
		}

		// Unknown method — pass through as untrusted.
		// rtorrent's own whitelist will allow/reject.
		return self::forward($rawData, false, "untrusted: ".self::logValue($methodName));
	}

	/**
	 * The URI a load.* call is being asked to fetch, or null when the call does
	 * not carry one. Read from parameter 1, which is where every version puts
	 * it — parameter 0 is the target.
	 */
	private static function loadUri($xml)
	{
		if(!isset($xml->params->param))
			return null;
		$index = 0;
		foreach($xml->params->param as $param)
		{
			if($index === 1)
			{
				// A base64 parameter is still a URI as far as rtorrent is
				// concerned: it decodes to the string it opens. Read it the
				// same way, so encoding it is not a way past this.
				if(isset($param->value->base64))
				{
					$decoded = base64_decode((string)$param->value->base64, true);
					return ($decoded === false) ? '' : $decoded;
				}
				return self::extractParamValue($param->value);
			}
			$index++;
		}
		return null;
	}

	/**
	 * Is this command name in a refused family? Prefix match, so a version that
	 * spells it execute2 or schedule.remove is covered by the same entry, and
	 * a name an older daemon registers is read as the command it names first,
	 * so the refusal follows the command rather than one of its spellings.
	 */
	private static function isDenied($name, $deny)
	{
		if(isset(self::$legacyNames[$name]))
			$name = self::$legacyNames[$name];
		foreach($deny as $prefix)
			if(strncmp($name, $prefix, strlen($prefix)) === 0)
				return true;
		return false;
	}

	/**
	 * The command a parameter would run, or null if it does not look like one.
	 * Only the name is wanted here; whether its arguments are acceptable is
	 * rebuildSafeLoadParam's question.
	 */
	private static function commandName($paramValue)
	{
		$separator = strpos($paramValue, '=');
		if($separator === false)
			return null;
		return trim(substr($paramValue, 0, $separator));
	}

	/**
	 * The name of a confined command this parameter carries, or null when it
	 * carries none.
	 *
	 * Asked only of a parameter the rebuild declined. A confined command whose
	 * directory is inside the stated boundary is rebuilt and never reaches
	 * here, so one that does named a directory outside it, or an argument this
	 * side could not read -- and an open question about a write target is a no,
	 * the same answer directoryIsAllowed() gives.
	 *
	 * A caller that stated no boundary is not policed, which is the behaviour
	 * every caller had before the confinement existed.
	 */
	private static function refusedDirectoryCommand($value, $directory)
	{
		if($directory === null)
			return null;
		$command = self::commandName($value);
		if(($command === null) || !in_array($command, self::$directoryCommands, true))
			return null;
		return $command;
	}

	/**
	 * The name of a refused command inside this command string, or null when
	 * it names none.
	 *
	 * A command string is not one name. rtorrent nests them: the arguments a
	 * multicall takes after its view are themselves commands, separated by
	 * ','; a name introduced by '$' is called wherever it stands, at any
	 * depth; and a braced list is a command with its own arguments. Reading
	 * only the name before the first '=' therefore answers for the outermost
	 * call and for nothing it carries, which is how "d.multicall=main,
	 * execute=..." and "$execute=..." read as commands nobody refuses.
	 *
	 * Every ','-, '$'-, brace-, paren-, quote- or space-separated element
	 * starts with a name, so each of those leading names is judged. What
	 * follows '=' inside one element is that command's argument text and is
	 * not judged, so a custom field whose value begins with a refused word is
	 * not refused for it.
	 *
	 * Public because the httprpc plugin reaches rtorrent without a raw XMLRPC
	 * body and still has to ask the same question over the same list.
	 */
	public static function refusedCommandName($value, $deny = null)
	{
		if($deny === null)
			$deny = self::$denyPrefixes;
		$elements = preg_split('/[,${}()"\s]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
		if($elements === false)
			return null;
		foreach($elements as $element)
		{
			if(!preg_match('/^[A-Za-z0-9_.]+/', $element, $match))
				continue;
			if(self::isDenied($match[0], $deny))
				return $match[0];
		}
		return null;
	}

	/**
	 * Every member of a system.multicall as array('name' => …, 'params' => …),
	 * so that one can be judged like any other call rather than smuggled past
	 * inside a struct — by its name and by what its parameters name.
	 */
	private static function multicallMembers($xml)
	{
		$members = array();
		if(!isset($xml->params->param->value->array->data->value))
			return $members;
		foreach($xml->params->param->value->array->data->value as $member)
		{
			if(!isset($member->struct->member))
				continue;
			$name = null;
			$params = array();
			foreach($member->struct->member as $field)
			{
				if(!isset($field->name))
					continue;
				$fieldName = (string)$field->name;
				if($fieldName === 'methodName')
					$name = isset($field->value->string)
						? (string)$field->value->string
						: trim((string)$field->value);
				else
				if(($fieldName === 'params') && isset($field->value->array->data->value))
					foreach($field->value->array->data->value as $value)
						$params[] = self::extractParamValue($value);
			}
			if($name !== null)
				$members[] = array('name' => $name, 'params' => $params);
		}
		return $members;
	}

	/**
	 * Re-emit a call whose arguments all match the shapes declared for it, or
	 * null if any of them does not. Nothing is copied from the client: every
	 * argument is emitted from the value this side validated.
	 */
	private static function rebuildElevated($xml, $methodName, $shapes, $sizeLimitMax)
	{
		$values = array();
		if(isset($xml->params->param))
			foreach($xml->params->param as $param)
				$values[] = self::extractParamValue($param->value);

		if(count($values) !== count($shapes))
			return null;

		$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<methodCall><methodName>' . htmlspecialchars($methodName)
			. '</methodName><params>';

		foreach($shapes as $index => $shape)
		{
			$emitted = self::emitArgument($shape, $values[$index], $sizeLimitMax);
			if($emitted === null)
				return null;
			$out .= $emitted;
		}

		return $out . '</params></methodCall>';
	}

	private static function emitArgument($shape, $value, $sizeLimitMax)
	{
		switch($shape)
		{
			case 'hash':
				if(!preg_match('/^[0-9A-Fa-f]{40}$/', $value))
					return null;
				return '<param><value><string>'.strtoupper($value).'</string></value></param>';

			case 'empty':
				if($value !== '')
					return null;
				return '<param><value><string></string></value></param>';

			case 'int':
				if(!preg_match('/^-?[0-9]{1,18}$/', $value))
					return null;
				return '<param><value><i8>'.$value.'</i8></value></param>';

			case 'size':
				if(!preg_match('/^[0-9]{1,18}$/', $value))
					return null;
				$size = (int)$value;
				if($size < 1)
					return null;
				if($size > $sizeLimitMax)
					$size = $sizeLimitMax;
				return '<param><value><i8>'.$size.'</i8></value></param>';

			case 'text':
				// rtorrent stores an XMLRPC string argument, it does not parse
				// it as a command, so nothing in it needs rejecting — only the
				// XML carrying it has to stay well formed.
				return '<param><value><string>'
					. htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8')
					. '</string></value></param>';
		}
		return null;
	}

	/**
	 * May a download be written into this path?
	 *
	 * $directory is the policy: array('root' => <absolute path>, 'resolve' =>
	 * <callable|null>). Without one, no — a caller naming a write target has to
	 * be answered from a stated boundary, and "none was stated" is not a boundary.
	 *
	 * The value normally does not exist yet, so realpath() on it returns false
	 * and cannot be the check. A lexical check alone is not enough either: one
	 * symlink inside the tree, which the customer can create, points anywhere.
	 * So the lexical check runs first and the resolver is then asked about the
	 * deepest part that does exist.
	 */
	private static function directoryIsAllowed($path, $directory)
	{
		if(!is_array($directory) || !isset($directory['root']))
			return false;
		$root = self::normalisePath($directory['root']);
		if(($root === null) || ($root === ''))
			return false;

		$path = self::normalisePath($path);
		if($path === null)
			return false;
		if(!self::isInside($path, $root))
			return false;

		if(isset($directory['resolve']) && is_callable($directory['resolve']))
		{
			$real = call_user_func($directory['resolve'], $path);
			$realRoot = call_user_func($directory['resolve'], $root);
			// A resolver that cannot answer for either side leaves the question
			// open, and an open question about a write target is a no.
			if(!is_string($real) || !is_string($realRoot) || ($real === '') || ($realRoot === ''))
				return false;
			if(!self::isInside($real, $realRoot))
				return false;
		}

		return true;
	}

	/**
	 * Collapse '.', '..' and repeated separators without touching the
	 * filesystem. Returns null for anything that is not an absolute path,
	 * including one that climbs above '/'.
	 */
	private static function normalisePath($path)
	{
		$path = trim((string)$path);
		if(($path === '') || ($path[0] !== '/'))
			return null;
		$out = array();
		foreach(explode('/', $path) as $part)
		{
			if(($part === '') || ($part === '.'))
				continue;
			if($part === '..')
			{
				if(count($out) === 0)
					return null;
				array_pop($out);
				continue;
			}
			$out[] = $part;
		}
		return '/'.implode('/', $out);
	}

	/**
	 * Is $path the root itself or something under it? Compared with the
	 * separator attached, so /torrents1x is not inside /torrents1.
	 */
	private static function isInside($path, $root)
	{
		if($root === '/')
			return true;
		return ($path === $root) || (strpos($path, rtrim($root, '/').'/') === 0);
	}

	private static function forward($payload, $trusted, $line)
	{
		return array('action' => 'send', 'payload' => $payload,
			'trusted' => $trusted, 'method' => null, 'log' => array($line));
	}

	private static function reject($line, $method = null)
	{
		return array('action' => 'reject', 'payload' => '',
			'trusted' => false, 'method' => $method, 'log' => array($line));
	}

	/**
	 * The sentence both doors show when this filter refuses a call. It names the
	 * command so a refusal reads the same at either door, and it says this
	 * server refused the call rather than blaming rtorrent for an outage that
	 * did not happen -- rtorrent never saw it.
	 */
	public static function rejectionMessage($method)
	{
		return (($method !== null) && ($method !== ''))
			? "The command '".$method."' was rejected by this server."
			: "This XMLRPC call was rejected by this server.";
	}

	/**
	 * The same sentence wrapped in the XMLRPC fault a door returns: faultCode
	 * -501, matching what rpc2.php answers for the same refusals.
	 */
	public static function rejectionFault($method)
	{
		$faultString = self::rejectionMessage($method);
		return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
			.'<methodResponse><fault><value><struct>'
			.'<member><name>faultCode</name><value><i4>-501</i4></value></member>'
			.'<member><name>faultString</name><value><string>'
			.htmlspecialchars($faultString, ENT_NOQUOTES, 'UTF-8')
			.'</string></value></member>'
			.'</struct></value></fault></methodResponse>';
	}

	/**
	 * Split command arguments the way rtorrent does: commas separate them, and
	 * a double-quoted string is one argument even when it contains commas.
	 * Unquoted arguments are trimmed; quoted ones are not. An unclosed quote,
	 * or text after a quoted argument that is not a comma, is malformed and
	 * returns null so the whole parameter is dropped.
	 *
	 * Clients such as cross-seed quote every value (d.custom1.set="cross-seed").
	 * Splitting on ',' first would cut inside those quotes; dropping them left
	 * torrents unlabeled and in the default directory. Unquoting here, then
	 * re-quoting in rebuildSafeLoadParam, keeps the value as one argument.
	 */
	private static function splitLoadArguments($value)
	{
		$arguments = array();
		$len = strlen($value);
		$i = 0;
		while($i < $len)
		{
			while($i < $len && ($value[$i] === ' ' || $value[$i] === "\t"))
				$i++;
			if($i >= $len)
				break;

			if($value[$i] === '"')
			{
				$i++;
				$argument = '';
				$closed = false;
				while($i < $len)
				{
					$c = $value[$i];
					if($c === '\\' && $i + 1 < $len)
					{
						$argument .= $value[$i + 1];
						$i += 2;
						continue;
					}
					if($c === '"')
					{
						$closed = true;
						$i++;
						break;
					}
					$argument .= $c;
					$i++;
				}
				if(!$closed)
					return null;
				$arguments[] = $argument;
			}
			else
			{
				// Only one escape changes where an argument ends: rtorrent reads
				// '\\,' as a comma inside the value rather than a separator
				// (parse_string, src/rpc/parse.cc), so splitting on every comma
				// cut such a value in two and delivered a second argument the
				// client never sent.
				//
				// Its other escapes are deliberately left alone. rtorrent would
				// read '\\' as an escape everywhere, which turns C:\\downloads
				// into C:downloads -- but this side re-quotes the value, so the
				// backslash reaches rtorrent intact, and clients have been
				// sending paths and labels through here on that basis. Matching
				// rtorrent exactly would eat those backslashes.
				$argument = '';
				$keep = 0;
				while($i < $len)
				{
					$c = $value[$i];
					if($c === ',')
						break;
					if(($c === '\\') && ($i + 1 < $len) && ($value[$i + 1] === ','))
					{
						$argument .= ',';
						$i += 2;
						$keep = strlen($argument);
						continue;
					}
					$argument .= $c;
					$i++;
					// Trailing whitespace is trimmed as rtorrent trims it.
					if(($c !== ' ') && ($c !== "\t"))
						$keep = strlen($argument);
				}
				$arguments[] = substr($argument, 0, $keep);
			}

			while($i < $len && ($value[$i] === ' ' || $value[$i] === "\t"))
				$i++;
			if($i < $len)
			{
				if($value[$i] !== ',')
					return null;
				$i++;
			}
		}
		return $arguments;
	}

	/**
	 * Rebuild one command parameter, or return null to drop it.
	 *
	 * A parameter is not a single command: rtorrent ends a command at ';' or a
	 * newline and calls a parenthesised (command,args) found in a value, so a
	 * string that merely begins with an allowed command can carry others. The
	 * command name is therefore compared for equality, and each argument is
	 * quoted so that whatever it contains stays an argument.
	 *
	 * Arguments are split the way rtorrent splits them (commas, with quoted
	 * strings kept whole), so a command that takes several keeps them, and
	 * each unquoted argument is trimmed as rtorrent trims one. A value the
	 * client already quoted is unquoted here, then re-quoted, rather than
	 * dropped: cross-seed and others send d.custom1.set="label".
	 */
	private static function rebuildSafeLoadParam($paramValue, $safeParams, $directory = null)
	{
		$separator = strpos($paramValue, '=');
		if($separator === false)
			return null;

		$command = trim(substr($paramValue, 0, $separator));
		if(!in_array($command, $safeParams, true))
			return null;

		$parts = self::splitLoadArguments(substr($paramValue, $separator + 1));
		if($parts === null)
			return null;

		// A caller that states no boundary is not policed here, which is the
		// behaviour every caller had before this existed. rpc2.php always states
		// one and refuses to start without it; the httprpc plugin does not, and
		// its door needs a ruTorrent session rather than a machine credential.
		if(($directory !== null) && in_array($command, self::$directoryCommands, true))
		{
			$path = isset($parts[0]) ? $parts[0] : '';
			if(!self::directoryIsAllowed($path, $directory))
				return null;
		}

		$arguments = array();
		foreach($parts as $argument)
		{
			// An argument whose first character is '$' is parsed and called as a
			// command after quoting is undone, so quoting cannot make it safe.
			// Unquoted values are already trimmed by the split; trimming again
			// here would not turn a leading space into a leading '$'.
			if(isset($argument[0]) && $argument[0] === '$')
				return null;

			$arguments[] = '"'.str_replace(array('\\', '"'), array('\\\\', '\\"'), $argument).'"';
		}

		return $command.'='.implode(',', $arguments);
	}

	/**
	 * Extract a command-param value from its <value> element.
	 *
	 * Handles the typed form <value><string>foo</string></value>, the
	 * implicit-string form <value>foo</value>, and <value><base64>...</base64>
	 * </value>.
	 *
	 * base64 is decoded because that is what rtorrent does with it: xmlrpc-c
	 * hands the decoded bytes to the command parser, so the command a base64
	 * parameter names is the decoded one. Returning the encoded text instead
	 * meant the name this side judged was not the name rtorrent ran — the
	 * encoded text matches no allowed command, so the parameter was stripped
	 * and the request forwarded verbatim, carrying the command that was never
	 * looked at.
	 */
	private static function extractParamValue($paramElement)
	{
		if(isset($paramElement->base64))
		{
			$decoded = base64_decode((string)$paramElement->base64, true);
			return ($decoded === false) ? '' : $decoded;
		}
		if(isset($paramElement->string))
			return (string)$paramElement->string;
		return trim((string)$paramElement);
	}

	/**
	 * Rebuild a command-carrying call keeping only safe parameters.
	 *
	 *   Param 0: target                    (always kept)
	 *   Param 1: URL, raw data, or view    (always kept)
	 *   Param 2+: command strings (kept iff the command name is in the
	 *                              whitelist, otherwise stripped)
	 *
	 * Both families put their commands at param 2: load.start, load.normal,
	 * load.raw, load.raw_start, d.multicall, d.multicall2,
	 * d.multicall.filtered and t/f/p.multicall all do, on 0.9.8 and on 0.16.x
	 * alike. Measured by side effect against both, rather than read off a
	 * signature — the caller acts on the answer by deciding what is data.
	 *
	 * Public for unit testing — production callers should go through
	 * process().
	 *
	 * @return array ['xml' => string, 'kept' => int, 'stripped' => array,
	 *                'rebuiltAll' => bool] — rebuiltAll is false when any
	 *               parameter had to be carried over verbatim, which means
	 *               the call must not be sent as trusted.
	 */
	public static function rebuildLoadParams($xml, $methodName, $safeParams = array(), $directory = null)
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
					$rebuiltParam = self::rebuildSafeLoadParam($value, $safeParams, $directory);
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
