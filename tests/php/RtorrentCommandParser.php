<?php

/**
 * rtorrent's own command parser, in PHP.
 *
 * The strings ruTorrent appends to a load call are not typed parameters. The
 * daemon hands each one to rpc::parse_command_multiple(), which takes it apart
 * with src/rpc/parse.cc and src/rpc/parse_commands.cc. So the only way to say
 * what a load call asks for is to parse it the way those two files do, and
 * that is what this is: parse_string, parse_object, parse_list,
 * parse_whole_list, parse_command and parse_command_multiple, with the
 * delimiter predicates from parse.h.
 *
 * Transcribed from rtorrent 0.16.x. Three things it exists to make visible:
 *
 *  - a quoted argument holds anything, including ',', ';', a newline and a
 *    space, because parse_string's quoted branch ends only at an unescaped
 *    '"'. An unquoted one ends at any of them -- parse_is_delim_command.
 *  - a value whose first character is '$' is run as a command after the quotes
 *    come off, in parse_command_execute. Quoting does not make it safe.
 *  - one parameter can hold more than one command: parse_command stops at ';'
 *    or a newline and parse_command_multiple then starts a new one.
 */

class RtorrentInputError extends Exception
{
}

class RtorrentCommandParser
{
	private $s;
	private $i;
	private $n;

	private function __construct($s)
	{
		$this->s = $s;
		$this->i = 0;
		$this->n = strlen($s);
	}

	/**
	 * Every command rtorrent would run for one load parameter, as a list of
	 * array('name'=>..., 'args'=>...). Throws RtorrentInputError where the
	 * daemon would throw torrent::input_error.
	 */
	public static function commands($parameter)
	{
		$p = new self($parameter);
		$out = array();
		while ($p->i < $p->n) {
			$command = $p->parseCommand();
			if ($command === null) {
				break;
			}
			$out[] = $command;
		}
		return $out;
	}

	/**
	 * The arguments of one command that rtorrent would replace with the result
	 * of running them -- parse_command_execute: a string starting with '$', a
	 * parenthesised call, a braced block.
	 */
	public static function substitutedArguments($args)
	{
		$hits = array();
		foreach ($args as $a) {
			if (is_string($a)) {
				if (isset($a[0]) && ($a[0] === '$')) {
					$hits[] = $a;
				}
			} else if (isset($a['call'])) {
				$hits[] = '(' . $a['call'] . ')';
			} else {
				$hits[] = '{block}';
			}
		}
		return $hits;
	}

	// parse.h: parse_is_space
	private function isSpace($c) { return ($c === ' ') || ($c === "\t"); }
	// parse_commands.cc: command_map_is_newline
	private function isNewline($c) { return ($c === "\n") || ($c === "\0") || ($c === ';'); }
	// parse.h: parse_is_delim_command
	private function isDelimCommand($c) { return ($c === ',') || ($c === ';') || (strpos(" \t\n\r\v\f", $c) !== false); }
	private function isDelimFunc($c) { return ($c === ',') || ($c === ')'); }
	private function isDelimBlock($c) { return ($c === ',') || ($c === '}'); }

	private function skipSpace()
	{
		while (($this->i < $this->n) && $this->isSpace($this->s[$this->i])) {
			$this->i++;
		}
	}

	// parse.cc: parse_string
	private function parseString($delim)
	{
		$dest = '';
		if ($this->i >= $this->n) {
			return $dest;
		}
		if ($this->s[$this->i] === '"') {
			$this->i++;
			while ($this->i < $this->n) {
				$c = $this->s[$this->i];
				if ($c === '"') {
					$this->i++;
					return $dest;
				}
				if ($c === '\\') {
					$this->i++;
					if ($this->i >= $this->n) {
						throw new RtorrentInputError('Escape character at end of input.');
					}
				}
				$dest .= $this->s[$this->i++];
			}
			throw new RtorrentInputError('Missing closing quote.');
		}
		while ($this->i < $this->n) {
			$c = $this->s[$this->i];
			if (call_user_func($delim, $c)) {
				return $dest;
			}
			if ($c === '\\') {
				$this->i++;
				if ($this->i >= $this->n) {
					throw new RtorrentInputError('Escape character at end of input.');
				}
			}
			$dest .= $this->s[$this->i++];
		}
		return $dest;
	}

	// parse.cc: parse_object
	private function parseObject($delim)
	{
		if (($this->i < $this->n) && ($this->s[$this->i] === '{')) {
			$this->i++;
			$block = $this->parseList(array($this, 'isDelimBlock'));
			$this->skipSpace();
			if (($this->i >= $this->n) || ($this->s[$this->i] !== '}')) {
				throw new RtorrentInputError("Could not find closing '}'.");
			}
			$this->i++;
			return array('block' => $block);
		}
		if (($this->i < $this->n) && ($this->s[$this->i] === '(')) {
			$depth = 1;
			while ((($this->i + 1) < $this->n) && ($this->s[$this->i + 1] === '(')) {
				$this->i++;
				$depth++;
			}
			if ($depth > 3) {
				throw new RtorrentInputError('Max 3 parentheses per object allowed.');
			}
			$this->i++;
			$key = $this->parseString(array($this, 'isDelimFunc'));
			$this->skipSpace();
			if (($this->i >= $this->n) || !$this->isDelimFunc($this->s[$this->i])) {
				throw new RtorrentInputError("Could not find closing ')'.");
			}
			$args = array();
			if ($this->s[$this->i] === ',') {
				$this->i++;
				$args = $this->parseList(array($this, 'isDelimFunc'));
				$this->skipSpace();
			}
			while (($depth !== 0) && ($this->i < $this->n) && ($this->s[$this->i] === ')')) {
				$this->i++;
				$depth--;
			}
			if ($depth !== 0) {
				throw new RtorrentInputError('Parentheses mismatch.');
			}
			return array('call' => $key, 'args' => $args);
		}
		return $this->parseString($delim);
	}

	// parse.cc: parse_list
	private function parseList($delim)
	{
		$out = array();
		while (true) {
			$this->skipSpace();
			$out[] = $this->parseObject($delim);
			$this->skipSpace();
			if (($this->i >= $this->n) || ($this->s[$this->i] !== ',')) {
				break;
			}
			$this->i++;
		}
		return $out;
	}

	// parse.cc: parse_whole_list
	private function parseWholeList($delim)
	{
		$this->skipSpace();
		$first = $this->parseObject($delim);
		$this->skipSpace();
		if (($this->i < $this->n) && ($this->s[$this->i] === ',')) {
			$this->i++;
			return array_merge(array($first), $this->parseList($delim));
		}
		return array($first);
	}

	// parse_commands.cc: parse_command
	private function parseCommand()
	{
		while (($this->i < $this->n) && $this->isSpace($this->s[$this->i])) {
			$this->i++;
		}
		if (($this->i >= $this->n) || ($this->s[$this->i] === '#')) {
			return null;
		}
		if (!ctype_alpha($this->s[$this->i])) {
			throw new RtorrentInputError('Invalid start of command name.');
		}
		$name = '';
		while (($this->i < $this->n) && (ctype_alnum($this->s[$this->i]) ||
			($this->s[$this->i] === '_') || ($this->s[$this->i] === '.'))) {
			$name .= $this->s[$this->i++];
			if (strlen($name) >= 127) {
				break;
			}
		}
		while (($this->i < $this->n) && $this->isSpace($this->s[$this->i])) {
			$this->i++;
		}
		if (($this->i >= $this->n) || ($this->s[$this->i] !== '=')) {
			throw new RtorrentInputError("Could not find '=' in command '" . $name . "'.");
		}
		$this->i++;
		$args = $this->parseWholeList(array($this, 'isDelimCommand'));
		while (($this->i < $this->n) && $this->isSpace($this->s[$this->i])) {
			$this->i++;
		}
		if ($this->i < $this->n) {
			if (!$this->isNewline($this->s[$this->i])) {
				throw new RtorrentInputError('Junk at end of input.');
			}
			$this->i++;
		}
		return array('name' => $name, 'args' => $args);
	}
}
