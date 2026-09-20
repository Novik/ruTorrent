<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/AdditionCallerFixtures.php');
require_once(__DIR__ . '/RtorrentCommandParser.php');
require_once(__DIR__ . '/TorrentAdditionSourceScanner.php');
require_once(__DIR__ . '/../../php/rtorrent.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * An addition is a command, and so is what a caller appends to it.
 *
 * rTorrent::ADDITION_COMMANDS names what may be sent; TorrentAdditionCoverageTest
 * holds the tree to it. This file is the other half of the same question: an
 * addition is "name=value", the name is compared against that list, and the
 * value is whatever the caller had. Some of those values come from a request --
 * an RSS filter's throttle and ratio are read out of the POST body in
 * plugins/rss/action.php and appended in plugins/rss/rss.php -- so the value has
 * to be held to the same boundary as the name.
 *
 * It does not reach rtorrent as a value. DownloadFactory runs each entry of a
 * load command list through rpc::parse_command_multiple_std()
 * (src/core/download_factory.cc), which is the daemon's command parser, so the
 * argument text is parsed too. callableCommands() below models what that parser
 * calls, and the assertion is the end condition rather than a list of payloads:
 * no string the guard accepts may make the daemon call anything but the
 * addition's own command.
 *
 * The strings it is asked about are generated rather than written down -- every
 * refused command name, in every form the daemon evaluates one, inside every
 * carrier that can put it where the form is read -- and the command names they
 * are attached to are read out of the tree by TorrentAdditionSourceScanner, so
 * a sixth caller that builds an addition from a request value is covered on the
 * commit that adds it, with nobody having to remember this file.
 */
class TorrentAdditionArgumentTest extends TestCase
{
	use AdditionCallerFixtures;

	/**
	 * Command names to attach a payload to, on the installed alias table: the
	 * whole allowlist, plus whatever the tree is actually building today.
	 */
	private function permittedNames()
	{
		$names = array();
		foreach (rTorrent::ADDITION_COMMANDS as $alias) {
			$names[getCmd($alias)] = true;
		}
		$scanner = new TorrentAdditionSourceScanner($this->repoRoot());
		foreach ($scanner->callSites() as $site) {
			foreach ($site['elements'] as $element) {
				$name = TorrentAdditionSourceScanner::commandName($element);
				if ($name !== null) {
					$names[$name] = true;
				}
			}
		}
		return array_keys($names);
	}

	// ---- what the daemon would call --------------------------------------

	/** The name rtorrent's parse_command_name() would read, or null. */
	static private function leadingName($text)
	{
		return preg_match('/^[A-Za-z][A-Za-z0-9_.]*/', $text, $match) ? $match[0] : null;
	}

	/** Every name a parsed argument list would have the daemon call. */
	static private function calledNames($args, &$found)
	{
		foreach ($args as $argument) {
			if (is_string($argument)) {
				// parse_command_execute(): a string argument is handed back to
				// parse_command() and called when its first byte is a '$', and
				// that byte is read after parse_string() has taken the quotes
				// off, so a quoted argument is judged unquoted.
				if (isset($argument[0]) && ($argument[0] === '$')) {
					$name = self::leadingName(substr($argument, 1));
					if ($name !== null) {
						$found[$name] = true;
					}
				}
			} elseif (isset($argument['call'])) {
				// A parenthesised object is a function object call_command()
				// runs (parse_object, src/rpc/parse.cc).
				$found[$argument['call']] = true;
				self::calledNames($argument['args'], $found);
			} elseif (isset($argument['block'])) {
				// A braced list, whose members get the same treatment.
				self::calledNames($argument['block'], $found);
			}
		}
	}

	/**
	 * Every command rtorrent would call for this addition besides the
	 * addition's own.
	 *
	 * The reading is RtorrentCommandParser's, which is parse.cc and
	 * parse_commands.cc transcribed rather than approximated, because the
	 * question the guard has to answer is decided by details an approximation
	 * gets wrong in both directions: a ';' inside a quoted argument is a byte
	 * of the value and not a terminator, an escaped ',' does not separate two
	 * arguments, and '(' and '{' are read on the raw byte, so quoting them
	 * makes them ordinary text where quoting a '$' does not.
	 *
	 * Where the daemon throws -- an unquoted space, an unclosed quote -- it
	 * abandons the load rather than running anything, so nothing is reported.
	 * That is not a licence for the guard to accept such a string: the load
	 * being thrown away is itself a refusal the caller has to make first, and
	 * refusedByTheDaemon() below holds the guard to it.
	 *
	 * It over-reports in one direction on purpose: parse_command_execute()
	 * does not recurse into a nested list, and this does. Over-reporting makes
	 * the guard's job harder, never easier.
	 */
	static private function callableCommands($addition)
	{
		try {
			$commands = RtorrentCommandParser::commands($addition);
		} catch (RtorrentInputError $e) {
			return array();
		}
		$found = array();
		foreach ($commands as $index => $command) {
			// Anything past the first is a command parse_command_multiple()
			// found after a ';', a newline or a NUL and ran.
			if ($index > 0) {
				$found[$command['name']] = true;
			}
			self::calledNames($command['args'], $found);
		}
		return array_keys($found);
	}

	/**
	 * Whether the daemon would throw the whole load away rather than run this
	 * addition -- an unquoted space, an unclosed quote, a trailing escape.
	 * Nothing is executed, so callableCommands() reports nothing, but an
	 * addition the guard accepts and the daemon discards is a caller told its
	 * edit was saved when it was not.
	 */
	static private function refusedByTheDaemon($addition)
	{
		try {
			RtorrentCommandParser::commands($addition);
		} catch (RtorrentInputError $e) {
			return true;
		}
		return false;
	}

	/**
	 * The strings to ask about: a refused command name, in a form the daemon
	 * evaluates, inside a carrier that puts the form where it is read.
	 */
	private function payloads()
	{
		$names = array('execute', 'execute2', 'execute.raw.bg', 'execute_capture',
			'method.insert', 'method.set_key', 'import', 'try_import', 'schedule2',
			'system.shutdown', 'system.env', 'catch', 'log.open_file',
			'network.scgi.open_port', 'session.path.set', 'directory.default.set',
			'd.delete_tied', 'd.erase');
		$forms = array('$%s=/bin/sh,-c,id', '$%s=', '(%s,/bin/sh,-c,id)',
			'{%s,/bin/sh}', '{$%s=/bin/sh}', '%s=/bin/sh,-c,id');
		$carriers = array('%s', 'normal%s', 'normal,%s', 'normal, %s', '%s,normal',
			' %s', "\t%s", '"%s"', 'normal,"%s"', 'normal;%s', 'normal; %s',
			"normal\n%s", "normal\r%s", "normal\x00%s", 'normal,(%s)',
			'normal,{%s}', '(%s)', '{%s}', 'normal,$%s', 'normal\\,%s');
		$payloads = array();
		foreach ($names as $name) {
			foreach ($forms as $form) {
				$command = sprintf($form, $name);
				foreach ($carriers as $carrier) {
					$payloads[sprintf($carrier, $command)] = true;
				}
			}
		}
		return array_keys($payloads);
	}

	/** Values the shipped callers really do append, which must keep working. */
	private function benignArguments()
	{
		return array(
			'normal', 'slow', '1', '0', 'seed', 'leech', 'initial_seed',
			'main', 'started', 'rat_5', 'group-1', 'My Group', 'Movies (HD)',
			'x-rutracker-replacement,5f3a91c07e', 'chk-state,2', 'chk-time,1758240000',
			'Earth, Wind & Fire', '', 'a=b',
		);
	}

	/**
	 * The name-only comparison the guard used to make, kept as the control for
	 * the corpus below. If the generated strings or the model above ever stop
	 * finding anything, this stops failing and says so.
	 */
	static private function nameOnlyGate($addition)
	{
		if (!is_string($addition)) {
			return false;
		}
		$separator = strpos($addition, '=');
		if ($separator === false) {
			return false;
		}
		$name = substr($addition, 0, $separator);
		foreach (rTorrent::ADDITION_COMMANDS as $permitted) {
			if (($name === $permitted) || ($name === getCmd($permitted))) {
				return true;
			}
		}
		return false;
	}

	private $previousSettings;

	public function setUp()
	{
		$this->previousSettings = $this->currentSettings();
	}

	public function tearDown()
	{
		$this->restoreSettings($this->previousSettings);
	}

	// ---- the model reads the daemon's parser correctly -------------------

	/**
	 * Controls for callableCommands(). A model that reported nothing would make
	 * every assertion below it a false green.
	 */
	public function testTheModelFindsEachFormTheDaemonEvaluates()
	{
		$cases = array(
			'd.throttle_name.set=x;execute=/bin/sh'    => 'a command after a ";"',
			"d.throttle_name.set=x\nexecute=/bin/sh"   => 'a command after a newline',
			'd.throttle_name.set=$execute=/bin/sh'     => 'a "$" string argument',
			'd.throttle_name.set="$execute=/bin/sh"'   => 'a "$" string inside quotes',
			'd.throttle_name.set=x,$execute=/bin/sh'   => 'a "$" string in a later argument',
			'd.throttle_name.set=(execute,/bin/sh)'    => 'a parenthesised call',
			'd.throttle_name.set=x,(execute,/bin/sh)'  => 'a parenthesised call in a later argument',
			'd.throttle_name.set={$execute=/bin/sh}'   => 'a braced list holding a call',
			'd.throttle_name.set={(execute,/bin/sh)}'  => 'a braced list holding a function object',
		);
		foreach ($cases as $addition => $what) {
			$this->assertTrue(in_array('execute', self::callableCommands($addition), true),
				'the daemon model reads ' . $what . ' as calling execute');
		}
		// And the two forms that make it throw the load away instead, which it
		// has to report separately because nothing runs in either.
		$discarded = array(
			'd.throttle_name.set=My Group'  => 'an unquoted space',
			'd.throttle_name.set="unclosed' => 'an unclosed quote',
		);
		foreach ($discarded as $addition => $what) {
			$this->assertTrue(self::refusedByTheDaemon($addition),
				'the daemon model reads ' . $what . ' as throwing the load away');
		}
	}

	/**
	 * Quoting is the whole of the strategy, so the model has to agree that it
	 * works -- otherwise every assertion that a quoted value is accepted is
	 * resting on the guard's own opinion of itself.
	 *
	 * parse_string()'s quoted branch ends only at an unescaped '"' and never
	 * consults the delimiter, and parse_object() reads '{' and '(' off the raw
	 * byte, which is a '"' here. So the daemon calls nothing for any of these,
	 * where the same byte bare would end the command or open a call.
	 */
	public function testQuotingMakesEveryFormInertExceptTheLeadingDollar()
	{
		$inert = array(';', "\n", ' ', ',', '(execute,/bin/sh)', '{$execute=x}',
			'"', '\\', 'a"b', 'a\\b', "x;execute=/bin/sh");
		foreach ($inert as $value) {
			$addition = rTorrent::additionCommand('d.set_throttle_name', $value);
			$this->assertTrue(is_string($addition),
				'the builder quotes ' . self::readable($value));
			$this->assertTrue(self::callableCommands($addition) === array(),
				'quoted, ' . self::readable($value) . ' makes the daemon call nothing');
			$this->assertTrue(!self::refusedByTheDaemon($addition),
				'and does not make it throw the load away');
		}
		// The exception, and the reason the builder refuses rather than quotes.
		$this->assertTrue(self::callableCommands('d.throttle_name.set="$execute=/bin/sh"')
			=== array('execute'),
			'quoting does not reach a leading "$": it is read after the quotes come off');
		$this->assertTrue(rTorrent::additionCommand('d.set_throttle_name', '$execute=/bin/sh')
			=== false,
			'so the builder refuses a value whose first byte is "$" instead of quoting it');
	}

	/** A value in a message, with the bytes that do not print made visible. */
	static private function readable($value)
	{
		return '"' . strtr($value, array("\n" => '\n', "\r" => '\r', "\t" => '\t',
			"\0" => '\0', "\v" => '\v', "\f" => '\f')) . '"';
	}

	/** And that it does not report a call where the daemon makes none. */
	public function testTheModelReportsNothingForAPlainValue()
	{
		$this->installSettingsFor(0x1012);
		$quiet = array();
		foreach ($this->benignArguments() as $value) {
			$addition = rTorrent::additionCommand('d.set_throttle_name', $value);
			$callable = self::callableCommands($addition);
			if ($callable !== array()) {
				$quiet[] = self::readable($value) . ' -> ' . implode(',', $callable);
			}
		}
		$this->assertTrue($quiet === array(),
			'the daemon model reports no call for a value a caller really appends'
			. (count($quiet) ? '; reported: ' . implode(' | ', $quiet) : ''));
	}

	/**
	 * The round trip the strategy rests on: whatever a caller has, the command
	 * receives those same bytes. Asserted against the parser rather than
	 * against the text the builder produced, so it is the daemon's reading of
	 * the quoting that is checked and not the quoting's own spelling.
	 */
	public function testEveryValueTheBuilderQuotesReachesTheCommandUnchanged()
	{
		$this->installSettingsFor(0x1012);
		$values = array_merge($this->benignArguments(), array(
			'My Group', 'Movies (HD)', 'Earth, Wind & Fire', 'a"b', 'a\\b',
			'a\\"b', "a\nb", 'a;b', 'a,b', 'a\\,b', '{b}', '(b)', ' leading',
			'trailing ', "\t", '', 'a$b', 'aé b', str_repeat('x', 300),
		));
		$wrong = array();
		foreach ($values as $value) {
			$addition = rTorrent::additionCommand('d.set_throttle_name', $value);
			if (!is_string($addition)) {
				$wrong[] = self::readable($value) . ' was not built at all';
				continue;
			}
			try {
				$commands = RtorrentCommandParser::commands($addition);
			} catch (RtorrentInputError $e) {
				$wrong[] = self::readable($value) . ' made the daemon throw: ' . $e->getMessage();
				continue;
			}
			if (count($commands) !== 1) {
				$wrong[] = self::readable($value) . ' became ' . count($commands) . ' commands';
				continue;
			}
			if ($commands[0]['args'] !== array($value)) {
				$wrong[] = self::readable($value) . ' reached the command as '
					. json_encode($commands[0]['args']);
			}
		}
		$this->assertTrue($wrong === array(),
			'every value the builder quotes arrives at its command as one argument holding '
			. 'exactly those bytes' . (count($wrong) ? '; ' . implode(' | ', $wrong) : ''));
	}

	/**
	 * And the two-argument form keeps its boundary. d.set_custom takes a key
	 * and a value; the builder is handed them separately and quotes each, so
	 * nothing has to find the comma between them again, which is the objection
	 * that kept the quoting out until now.
	 */
	public function testATwoArgumentAdditionKeepsItsTwoArguments()
	{
		$this->installSettingsFor(0x1012);
		$pairs = array(
			array('x-rutracker-replacement', '5f3a91c07e'),
			array('chk-state', '2'),
			array('chk-time', 1758240000),
			array('key,with,commas', 'value with spaces'),
			array('key', 'value"with\\quotes'),
		);
		foreach ($pairs as $pair) {
			$addition = rTorrent::additionCommand('d.set_custom', $pair[0], $pair[1]);
			$this->assertTrue(rTorrent::isValidAddition($addition),
				'the two-argument addition for ' . self::readable($pair[0]) . ' is accepted');
			$commands = RtorrentCommandParser::commands($addition);
			$this->assertTrue(count($commands) === 1 &&
				($commands[0]['args'] === array((string)$pair[0], (string)$pair[1])),
				'and reaches d.set_custom as exactly two arguments: '
				. json_encode($commands[0]['args']));
		}
	}

	/**
	 * And the corpus is large enough, and hostile enough, to be worth running:
	 * the name-only comparison the guard used to make has to fail against it.
	 */
	public function testTheCorpusCatchesTheNameOnlyComparison()
	{
		$this->installSettingsFor(0x1012);
		$name = getCmd('d.set_throttle_name');
		$caught = 0;
		$total = 0;
		foreach ($this->payloads() as $payload) {
			$addition = $name . '=' . $payload;
			if (self::callableCommands($addition) === array()) {
				continue;
			}
			$total++;
			if (self::nameOnlyGate($addition)) {
				$caught++;
			}
		}
		$this->assertTrue($total >= 500,
			'the corpus holds enough strings the daemon would call something for; it holds ' . $total);
		$this->assertTrue($caught === $total,
			'and a guard that compares only the name before the "=" accepts every one of them, '
			. 'which is what this file exists to stop; accepted ' . $caught . ' of ' . $total);
	}

	// ---- the end condition -----------------------------------------------

	/**
	 * The one that matters. For every command the tree can build as an
	 * addition, on every alias table it ships, no generated argument the guard
	 * accepts leaves the daemon calling anything else.
	 */
	public function testNoAcceptedAdditionMakesTheDaemonCallAnotherCommand()
	{
		$payloads = $this->payloads();
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$this->installSettingsFor($iVersion);
			$accepted = array();
			$checked = 0;
			foreach ($this->permittedNames() as $name) {
				foreach ($payloads as $payload) {
					$addition = $name . '=' . $payload;
					$callable = self::callableCommands($addition);
					if ($callable === array()) {
						continue;
					}
					$checked++;
					if (rTorrent::isValidAddition($addition)) {
						$accepted[] = $addition . ' would call ' . implode(',', $callable);
					}
				}
			}
			$this->assertTrue($checked > 0,
				'on rtorrent ' . $version . ' there are additions to judge; judged ' . $checked);
			$this->assertTrue($accepted === array(),
				'on rtorrent ' . $version . ' no addition the guard accepts makes the daemon call '
				. 'a command outside rTorrent::ADDITION_COMMANDS'
				. (count($accepted)
					? '; accepted ' . count($accepted) . ', first: ' . $accepted[0] : ''));
		}
	}

	/**
	 * The same for the list the entry points are handed, so the refusal is on
	 * the path sendTorrent()/sendMagnet() actually take and not only on the one
	 * function this file calls directly.
	 */
	public function testTheEntryPointGuardRefusesAListCarryingOne()
	{
		$this->installSettingsFor(0x1012);
		$refused = array();
		$hostile = array('$execute=/bin/sh,-c,id', '(execute,/bin/sh,-c,id)',
			'x;execute=/bin/sh,-c,id', "x\nexecute=/bin/sh,-c,id", '{$execute=/bin/sh}');
		foreach ($hostile as $payload) {
			$addition = array(
				getCmd('d.set_custom3') . '=1',
				getCmd('d.set_throttle_name=') . $payload,
			);
			if ($this->areValidAdditions($addition)) {
				$refused[] = $payload;
			}
		}
		$this->assertTrue($refused === array(),
			'a list holding one addition whose argument carries a command sends none of itself'
			. (count($refused) ? '; sent: ' . implode(' | ', $refused) : ''));
	}

	// ---- and the guard is still a guard and not a wall -------------------

	/**
	 * A guard that refused everything would pass every assertion above. The
	 * values the shipped callers append -- a throttle group, a view, a
	 * connection type, a custom field's key and value -- still go through, on
	 * every alias table.
	 */
	public function testTheValuesTheCallersAppendAreStillAccepted()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$this->installSettingsFor($iVersion);
			$lost = array();
			foreach (rTorrent::ADDITION_COMMANDS as $alias) {
				foreach ($this->benignArguments() as $value) {
					$addition = rTorrent::additionCommand($alias, $value);
					if (!is_string($addition) || !rTorrent::isValidAddition($addition)) {
						$lost[] = $alias . ' <- ' . self::readable($value);
					}
				}
			}
			$this->assertTrue($lost === array(),
				'on rtorrent ' . $version . ' every value a shipped caller appends is still accepted'
				. (count($lost) ? '; refused: ' . implode(' | ', $lost) : ''));
		}
	}

	/**
	 * A caller outside the tree may still paste a value in rather than build
	 * it, and one that is safe bare keeps working: an unquoted argument the
	 * daemon reads as one plain value is accepted exactly as it was.
	 */
	public function testAValueThatIsSafeUnquotedIsStillAcceptedUnquoted()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$this->installSettingsFor($iVersion);
			$lost = array();
			foreach ($this->permittedNames() as $name) {
				foreach (array('normal', 'slow', '1', '0', 'seed', 'leech',
					'initial_seed', 'main', 'started', 'rat_5', 'group-1',
					'x-rutracker-replacement,5f3a91c07e', 'chk-state,2',
					'chk-time,1758240000', '', 'a=b') as $value) {
					if (!rTorrent::isValidAddition($name . '=' . $value)) {
						$lost[] = $name . '=' . $value;
					}
				}
			}
			$this->assertTrue($lost === array(),
				'on rtorrent ' . $version . ' a bare value that is safe bare is still accepted'
				. (count($lost) ? '; refused: ' . implode(' | ', $lost) : ''));
		}
	}

	/**
	 * And a bare value the daemon would choke on is now refused here instead of
	 * being sent and discarded there. A throttle group named "My Group" pasted
	 * in raises "Junk at end of input" and the daemon abandons the load, so the
	 * old answer -- accept it, and report the add as done -- told the caller its
	 * edit was saved when it was not. The builder quotes such a value and it
	 * works; pasted in, it is refused before the erase.
	 */
	public function testABareValueTheDaemonWouldDiscardIsRefusedHere()
	{
		$this->installSettingsFor(0x1012);
		$name = getCmd('d.set_throttle_name');
		$accepted = array();
		foreach (array('My Group', 'Movies (HD)', 'Earth, Wind & Fire',
			'"unclosed', 'trailing\\') as $value) {
			$this->assertTrue(self::refusedByTheDaemon($name . '=' . $value),
				'the daemon throws the load away for a bare ' . self::readable($value));
			if (rTorrent::isValidAddition($name . '=' . $value)) {
				$accepted[] = self::readable($value);
			}
			$built = rTorrent::additionCommand('d.set_throttle_name', $value);
			$this->assertTrue(is_string($built) && rTorrent::isValidAddition($built),
				'and the same value goes through once the builder quotes it: '
				. self::readable($value));
		}
		$this->assertTrue($accepted === array(),
			'a bare value the daemon would discard is refused before the erase, not after'
			. (count($accepted) ? '; accepted: ' . implode(' | ', $accepted) : ''));
	}

	/**
	 * A NUL is refused wherever it sits, quoted or not. On the load path the
	 * daemon is given explicit bounds and reads one as a byte of the value, but
	 * command_map_is_newline() counts it as ending a command and the daemon has
	 * read commands out of NUL-terminated buffers elsewhere, so no caller gets
	 * to depend on which path its addition took.
	 */
	public function testANulIsRefusedWhereverItSits()
	{
		$this->installSettingsFor(0x1012);
		$name = getCmd('d.set_throttle_name');
		$this->assertTrue(!rTorrent::isValidAddition($name . "=x\x00execute=/bin/sh"),
			'a bare NUL in an addition argument is refused');
		$this->assertTrue(!rTorrent::isValidAddition($name . "=\"x\x00execute=/bin/sh\""),
			'and quoting it does not get it through');
		$this->assertTrue(rTorrent::additionCommand('d.set_throttle_name', "x\x00y") === false,
			'and the builder will not quote one either');
	}

	/** And the real addition lists the plugins build still go through whole. */
	public function testTheAdditionListsTheTreeBuildsAreStillAccepted()
	{
		$this->installSettingsFor(0x1012);
		$lists = array(
			'rss filter'    => array(rTorrent::additionCommand('d.set_throttle_name', 'slow'),
				rTorrent::additionCommand('view.set_visible', 'rat_5')),
			'edit reload'   => array(getCmd('d.set_custom3') . '=1',
				rTorrent::additionCommand('d.set_connection_seed', 'seed'),
				rTorrent::additionCommand('d.set_throttle_name', 'normal')),
			'retrackers'    => array(getCmd('d.set_custom3') . '=1'),
			'datadir move'  => array(rTorrent::additionCommand('d.set_connection_seed', 'leech'),
				rTorrent::additionCommand('d.set_throttle_name', 'My Group')),
			'rutracker'     => array(
				rTorrent::additionCommand('d.set_custom', 'x-rutracker-replacement', '5f3a91c07e'),
				rTorrent::additionCommand('d.set_connection_seed', 'seed'),
				rTorrent::additionCommand('d.set_custom', 'chk-state', '2'),
				rTorrent::additionCommand('d.views.push_back_unique', 'rat_5')),
		);
		foreach ($lists as $what => $addition) {
			$this->assertTrue($this->areValidAdditions($addition) === true,
				'the addition the ' . $what . ' caller builds is still sent');
		}
	}

	/**
	 * Two callers save a change by erasing the download and loading the edited
	 * bytes again, and between the two calls the download exists nowhere. So
	 * the refusal has to happen before the erase: sendTorrent() answering false
	 * afterwards is a download that no longer exists and cannot be put back.
	 *
	 * Asserted against the source rather than by driving the plugins, because
	 * for retrackers there is no argument that reaches it -- the addition it
	 * builds is the literal d.set_custom3=1, which is always accepted, so the
	 * ordering is unobservable from outside and would rot silently. The shape
	 * test beside this one drives the observable half through the edit plugin.
	 */
	public function testTheReloadingCallersAskBeforeTheyErase()
	{
		foreach (array('plugins/edit/action.php', 'plugins/retrackers/update.php') as $relative) {
			$source = file_get_contents($this->repoRoot() . '/' . $relative);
			$guard = strpos($source, 'rTorrent::areValidAdditions(');
			$erase = strpos($source, '"d.erase"');
			$this->assertTrue($guard !== false,
				$relative . ' asks rTorrent::areValidAdditions() for itself');
			$this->assertTrue($erase !== false,
				$relative . ' is a caller that erases the download before reloading it');
			$this->assertTrue($guard < $erase,
				$relative . ' asks before it erases: a refusal after the erase is a download '
				. 'that no longer exists');
		}
	}

	// ---- the two layers agree about arguments ----------------------------

	/**
	 * The raw XMLRPC proxy gates the same command strings on their way in from
	 * a client, and it has always judged the argument: rebuildSafeLoadParam()
	 * quotes each one, which makes a ';', a newline and a parenthesised call
	 * inert, and refuses outright an argument beginning '$', which quoting does
	 * not make inert. This side does not rewrite what it passes, so it refuses
	 * where the proxy rewrites -- but it must not be the more permissive of the
	 * two, which is what it was.
	 *
	 * XMLRPCProxy::refusedCommandName() is deliberately not the comparand. It
	 * splits on whitespace as well, so it refuses "normal, execute=x", which
	 * the daemon reads as four arguments to the throttle command and calls
	 * nothing for. Its own docblock says as much: over-refusing is free on a
	 * denied-name check and is not free here, where four of the five callers
	 * have already run d.erase on the download before they build the addition.
	 */
	public function testTheGuardIsNoMorePermissiveThanTheProxyAboutArguments()
	{
		$this->installSettingsFor(0x1012);
		$safeParams = array();
		foreach (rTorrent::ADDITION_COMMANDS as $alias) {
			$safeParams[] = getCmd($alias);
		}
		$rebuild = new ReflectionMethod('XMLRPCProxy', 'rebuildSafeLoadParam');
		$rebuild->setAccessible(true);

		$disagreed = array();
		$compared = 0;
		foreach ($safeParams as $name) {
			foreach ($this->payloads() as $payload) {
				$addition = $name . '=' . $payload;
				if (!rTorrent::isValidAddition($addition)) {
					continue;
				}
				$compared++;
				if ($rebuild->invoke(null, $addition, $safeParams, null) === null) {
					$disagreed[] = $addition;
				}
			}
		}
		$this->assertTrue($compared > 0,
			'there are accepted additions to compare against the proxy; compared ' . $compared);
		$this->assertTrue($disagreed === array(),
			'every addition this guard accepts, the proxy would accept too'
			. (count($disagreed) ? '; the proxy refused ' . count($disagreed)
				. ', first: ' . $disagreed[0] : ''));
	}

	// ---- the assumption the argument rule rests on -----------------------

	/**
	 * The rule judges an argument by where a call can begin in it, which holds
	 * only because no permitted command treats its own arguments as commands.
	 * None of the six does -- they set a throttle group, a view membership, a
	 * connection type and two custom fields. A multicall, a branch or a method
	 * definition on the list would run its arguments, and the rule would have
	 * to change with it.
	 */
	public function testNoPermittedCommandRunsItsOwnArguments()
	{
		$evaluating = array();
		foreach (rTorrent::ADDITION_COMMANDS as $alias) {
			foreach ($this->supportedVersions() as $iVersion => $version) {
				$this->installSettingsFor($iVersion);
				$name = getCmd($alias);
				if (preg_match('/multicall|branch|method\.|^catch|^if|^elif|^else|execute|import|schedule/', $name)) {
					$evaluating[] = $alias . ' resolves to ' . $name . ' on ' . $version;
				}
			}
		}
		$this->assertTrue($evaluating === array(),
			'no command on rTorrent::ADDITION_COMMANDS evaluates its own arguments'
			. (count($evaluating) ? '; ' . implode(' | ', $evaluating) : ''));
	}
}
