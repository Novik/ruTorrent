<?php

/**
 * Reads the addition an rTorrent::sendTorrent()/sendMagnet() call site passes,
 * out of the source rather than out of a transcription of it.
 *
 * A test that hardcodes the string a plugin builds stops testing that plugin
 * the moment the plugin changes, and says nothing at all about a plugin written
 * next year. This walks php/ and plugins/ with PHP's own tokenizer, finds every
 * call to the two entry points, resolves the addition argument to the list of
 * command names it will carry, and reports anything it could not resolve.
 *
 * Resolution covers the shapes the tree uses:
 *   - the argument is absent, or the literal null              -> no addition
 *   - an inline array() / [] literal                           -> its elements
 *   - a variable                                               -> every
 *     "$var = array(...)", "$var[] = expr" and "$var = null" in the file
 *   - a variable assigned from a same-file method call         -> that method's
 *     body, resolved for the variable the method returns
 *
 * An element is reported as the alias key its getCmd() names rather than as a
 * resolved command, so one scan answers for every alias table: commandName()
 * resolves it through rTorrentSettings::getCommand() at the moment it is asked,
 * under whatever settings are installed then.
 *
 * An element it cannot reduce to a command name is reported as unresolved, and
 * the coverage test fails on unresolved just as it fails on unpermitted: a
 * construction this cannot read is a construction nobody is checking.
 */
class TorrentAdditionSourceScanner
{
	/** Argument index (0 based) of $addition on each entry point. */
	const ADDITION_ARGUMENT = array(
		'sendTorrent' => 8,
		'sendMagnet'  => 5,
	);

	private $root;

	/** One tokenizing pass per tree, reused by every alias table. */
	static private $cache = array();

	public function __construct($root)
	{
		$this->root = rtrim($root, '/');
	}

	/**
	 * The command an element names, under the settings installed now. null
	 * when the element could not be reduced to one.
	 */
	static public function commandName($element)
	{
		if ($element['alias'] !== null) {
			return getCmd($element['alias']);
		}
		return $element['literal'];
	}

	/** Every .php file under php/ and plugins/. */
	public function sourceFiles()
	{
		$files = array();
		foreach (array('php', 'plugins') as $dir) {
			$base = $this->root . '/' . $dir;
			if (!is_dir($base)) {
				continue;
			}
			$walk = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
			foreach ($walk as $entry) {
				if ($entry->isFile() && strtolower($entry->getExtension()) === 'php') {
					$files[] = $entry->getPathname();
				}
			}
		}
		sort($files);
		return $files;
	}

	/**
	 * One record per call site: file, line, method, and either 'elements' (the
	 * element expressions, each already reduced) or a note that there is none.
	 */
	public function callSites()
	{
		if (isset(self::$cache[$this->root])) {
			return self::$cache[$this->root];
		}
		$sites = array();
		foreach ($this->sourceFiles() as $file) {
			foreach ($this->callSitesIn($file) as $site) {
				$sites[] = $site;
			}
		}
		self::$cache[$this->root] = $sites;
		return $sites;
	}

	private function callSitesIn($file)
	{
		$tokens = $this->significantTokens(file_get_contents($file));
		$sites = array();
		foreach ($tokens as $i => $token) {
			if ($token[0] !== T_STRING || !isset(self::ADDITION_ARGUMENT[$token[1]])) {
				continue;
			}
			// A method call, not a declaration or an unrelated identifier.
			if (!isset($tokens[$i - 1]) || $tokens[$i - 1][0] !== T_DOUBLE_COLON) {
				continue;
			}
			if (!isset($tokens[$i + 1]) || $tokens[$i + 1][1] !== '(') {
				continue;
			}
			$method = $token[1];
			$args = $this->splitArguments($tokens, $i + 1);
			$index = self::ADDITION_ARGUMENT[$method];
			$site = array(
				'file'      => $this->relative($file),
				'line'      => $token[2],
				'method'    => $method,
				'argc'      => count($args),
				'elements'  => array(),
				'hasAddition' => false,
			);
			if (isset($args[$index])) {
				$site['hasAddition'] = true;
				$site['elements'] = $this->resolveExpression($args[$index], $tokens, $file);
			}
			$sites[] = $site;
		}
		return $sites;
	}

	/** Tokens with whitespace and comments dropped, single chars normalised. */
	private function significantTokens($code)
	{
		$out = array();
		foreach (token_get_all($code) as $token) {
			if (is_array($token)) {
				if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT ||
					$token[0] === T_DOC_COMMENT) {
					continue;
				}
				$out[] = array($token[0], $token[1], $token[2]);
			} else {
				$line = count($out) ? $out[count($out) - 1][2] : 0;
				$out[] = array(-1, $token, $line);
			}
		}
		return $out;
	}

	/**
	 * The comma separated argument expressions of the call whose '(' is at
	 * $open. Each argument comes back as a token list.
	 */
	private function splitArguments($tokens, $open)
	{
		$depth = 0;
		$args = array();
		$current = array();
		for ($i = $open; $i < count($tokens); $i++) {
			$text = $tokens[$i][1];
			if ($text === '(' || $text === '[' || $text === '{') {
				$depth++;
				if ($depth === 1) {
					continue;
				}
			} elseif ($text === ')' || $text === ']' || $text === '}') {
				$depth--;
				if ($depth === 0) {
					if (count($current) || count($args)) {
						$args[] = $current;
					}
					return $args;
				}
			} elseif ($text === ',' && $depth === 1) {
				$args[] = $current;
				$current = array();
				continue;
			}
			$current[] = $tokens[$i];
		}
		return $args;
	}

	/**
	 * An argument expression -> the reduced elements of the addition it names.
	 * Each element is array('expr'=>source text, 'name'=>command name or null,
	 * 'reason'=>why it could not be reduced).
	 */
	private function resolveExpression($expr, $tokens, $file, $depth = 0)
	{
		$expr = $this->trimParens($expr);
		if (!count($expr)) {
			return array();
		}
		if (count($expr) === 1 && strtolower($expr[0][1]) === 'null') {
			return array();
		}
		if ($this->isArrayLiteral($expr)) {
			$elements = array();
			foreach ($this->arrayElements($expr) as $element) {
				$elements[] = $this->reduceElement($element);
			}
			return $elements;
		}
		if (count($expr) === 1 && $expr[0][0] === T_VARIABLE && $depth < 3) {
			return $this->resolveVariable($expr[0][1], $tokens, $file, $depth, 0, count($tokens));
		}
		return array($this->unresolved($expr, 'the addition argument is neither null, an array literal nor a local variable'));
	}

	/** Every assignment to $name between $from and $to, reduced. */
	private function resolveVariable($name, $tokens, $file, $depth, $from, $to)
	{
		$elements = array();
		$seen = false;
		for ($i = $from; $i < $to; $i++) {
			if ($tokens[$i][0] !== T_VARIABLE || $tokens[$i][1] !== $name) {
				continue;
			}
			// $name[] = expr;
			if (isset($tokens[$i + 3]) && $tokens[$i + 1][1] === '[' &&
				$tokens[$i + 2][1] === ']' && $tokens[$i + 3][1] === '=') {
				$seen = true;
				$elements[] = $this->reduceElement($this->statementAfter($tokens, $i + 4));
				continue;
			}
			// $name = expr;
			if (isset($tokens[$i + 1]) && $tokens[$i + 1][1] === '=') {
				$rhs = $this->statementAfter($tokens, $i + 2);
				$rhs = $this->trimParens($rhs);
				if (!count($rhs)) {
					continue;
				}
				$seen = true;
				if (count($rhs) === 1 && strtolower($rhs[0][1]) === 'null') {
					continue;
				}
				if ($this->isArrayLiteral($rhs)) {
					foreach ($this->arrayElements($rhs) as $element) {
						$elements[] = $this->reduceElement($element);
					}
					continue;
				}
				// $name = self::builder(...) / Klass::builder(...)
				$callee = $this->staticCallee($rhs);
				if ($callee !== null && $depth < 3) {
					$body = $this->methodBody($tokens, $callee);
					if ($body === null) {
						$elements[] = $this->unresolved($rhs,
							'assigned from ' . $callee . '(), which is not declared in this file');
						continue;
					}
					$returned = $this->returnedVariable($tokens, $body[0], $body[1]);
					if ($returned === null) {
						$elements[] = $this->unresolved($rhs,
							$callee . '() does not return a local variable');
						continue;
					}
					foreach ($this->resolveVariable($returned, $tokens, $file,
						$depth + 1, $body[0], $body[1]) as $element) {
						$elements[] = $element;
					}
					continue;
				}
				$elements[] = $this->unresolved($rhs, 'assigned from an expression this cannot read');
			}
		}
		if (!$seen) {
			return array($this->unresolved(array(array(T_VARIABLE, $name, 0)),
				'no assignment to ' . $name . ' found in the file'));
		}
		return $elements;
	}

	/**
	 * One element expression -> the command name it will carry.
	 *
	 * The two shapes the tree writes are getCmd("name=")."value" and
	 * getCmd("name")."=value"; both name the alias key inside getCmd(), which
	 * is resolved through the same rTorrentSettings::getCommand() the guard
	 * itself uses, so an alias table change moves the test with the code.
	 */
	private function reduceElement($expr)
	{
		$expr = $this->trimParens($expr);
		$source = $this->source($expr);
		if (!count($expr)) {
			return $this->unresolved($expr, 'empty element');
		}
		if ($expr[0][0] === T_STRING && $expr[0][1] === 'getCmd' &&
			isset($expr[1]) && $expr[1][1] === '(' &&
			isset($expr[2]) && $expr[2][0] === T_CONSTANT_ENCAPSED_STRING &&
			isset($expr[3]) && $expr[3][1] === ')') {
			$alias = $this->literal($expr[2][1]);
			$rest = array_slice($expr, 4);
			if (substr($alias, -1) === '=') {
				return $this->named($source, substr($alias, 0, -1), null);
			}
			// getCmd("name") with the '=' in the next concatenated literal.
			if (count($rest) >= 2 && $rest[0][1] === '.' &&
				$rest[1][0] === T_CONSTANT_ENCAPSED_STRING &&
				substr($this->literal($rest[1][1]), 0, 1) === '=') {
				return $this->named($source, $alias, null);
			}
			return $this->unresolved($expr,
				'getCmd(' . $alias . ') is not followed by an "=", so the element names no command');
		}
		if ($expr[0][0] === T_CONSTANT_ENCAPSED_STRING) {
			$text = $this->literal($expr[0][1]);
			$eq = strpos($text, '=');
			if ($eq === false) {
				return $this->unresolved($expr, 'a literal element with no "="');
			}
			return $this->named($source, null, substr($text, 0, $eq));
		}
		return $this->unresolved($expr,
			'the element is built in a way this cannot reduce to a command name');
	}

	// ---- token helpers ----------------------------------------------------

	private function named($source, $alias, $literal)
	{
		return array('expr' => $source, 'alias' => $alias, 'literal' => $literal, 'reason' => null);
	}

	private function unresolved($expr, $reason)
	{
		return array('expr' => $this->source($expr), 'alias' => null,
			'literal' => null, 'reason' => $reason);
	}

	private function source($expr)
	{
		$out = '';
		foreach ($expr as $token) {
			$out .= $token[1];
		}
		return $out;
	}

	private function literal($raw)
	{
		$quote = substr($raw, 0, 1);
		$body = substr($raw, 1, -1);
		if ($quote === "'") {
			return str_replace(array("\\'", '\\\\'), array("'", '\\'), $body);
		}
		return stripcslashes($body);
	}

	private function trimParens($expr)
	{
		while (count($expr) >= 2 && $expr[0][1] === '(' &&
			$this->matching($expr, 0) === count($expr) - 1) {
			$expr = array_slice($expr, 1, -1);
		}
		return $expr;
	}

	private function matching($expr, $open)
	{
		$depth = 0;
		for ($i = $open; $i < count($expr); $i++) {
			$t = $expr[$i][1];
			if ($t === '(' || $t === '[' || $t === '{') {
				$depth++;
			} elseif ($t === ')' || $t === ']' || $t === '}') {
				$depth--;
				if ($depth === 0) {
					return $i;
				}
			}
		}
		return -1;
	}

	private function isArrayLiteral($expr)
	{
		if (count($expr) >= 3 && $expr[0][0] === T_ARRAY && $expr[1][1] === '(') {
			return true;
		}
		return count($expr) >= 2 && $expr[0][1] === '[';
	}

	private function arrayElements($expr)
	{
		$open = ($expr[0][0] === T_ARRAY) ? 1 : 0;
		$close = $this->matching($expr, $open);
		$inner = array_slice($expr, $open + 1, $close - $open - 1);
		$elements = array();
		$current = array();
		$depth = 0;
		foreach ($inner as $token) {
			$t = $token[1];
			if ($t === '(' || $t === '[' || $t === '{') {
				$depth++;
			} elseif ($t === ')' || $t === ']' || $t === '}') {
				$depth--;
			} elseif ($t === ',' && $depth === 0) {
				if (count($current)) {
					$elements[] = $current;
				}
				$current = array();
				continue;
			}
			$current[] = $token;
		}
		if (count($current)) {
			$elements[] = $current;
		}
		return $elements;
	}

	/** Tokens from $start up to the ';' that ends the statement. */
	private function statementAfter($tokens, $start)
	{
		$depth = 0;
		$out = array();
		for ($i = $start; $i < count($tokens); $i++) {
			$t = $tokens[$i][1];
			if ($t === '(' || $t === '[' || $t === '{') {
				$depth++;
			} elseif ($t === ')' || $t === ']' || $t === '}') {
				if ($depth === 0) {
					return $out;
				}
				$depth--;
			} elseif (($t === ';' || $t === ',') && $depth === 0) {
				return $out;
			}
			$out[] = $tokens[$i];
		}
		return $out;
	}

	/** 'self::name' when $expr is a static call, else null. */
	private function staticCallee($expr)
	{
		if (count($expr) >= 4 && $expr[0][0] === T_STRING &&
			$expr[1][0] === T_DOUBLE_COLON && $expr[2][0] === T_STRING &&
			$expr[3][1] === '(') {
			return $expr[2][1];
		}
		if (count($expr) >= 4 && $expr[0][0] === T_STATIC &&
			$expr[1][0] === T_DOUBLE_COLON && $expr[2][0] === T_STRING &&
			$expr[3][1] === '(') {
			return $expr[2][1];
		}
		return null;
	}

	/** [start,end) token range of the body of "function $name". */
	private function methodBody($tokens, $name)
	{
		for ($i = 0; $i < count($tokens); $i++) {
			if ($tokens[$i][0] !== T_FUNCTION) {
				continue;
			}
			if (!isset($tokens[$i + 1]) || $tokens[$i + 1][0] !== T_STRING ||
				$tokens[$i + 1][1] !== $name) {
				continue;
			}
			for ($j = $i + 2; $j < count($tokens); $j++) {
				if ($tokens[$j][1] === '{') {
					$close = $this->matching(array_slice($tokens, $j), 0);
					if ($close < 0) {
						return null;
					}
					return array($j + 1, $j + $close);
				}
				if ($tokens[$j][1] === ';') {
					break;
				}
			}
		}
		return null;
	}

	/** The variable name in the last "return $v" / "return($v)" of a range. */
	private function returnedVariable($tokens, $from, $to)
	{
		for ($i = $to - 1; $i >= $from; $i--) {
			if ($tokens[$i][0] !== T_RETURN) {
				continue;
			}
			$rest = $this->trimParens($this->statementAfter($tokens, $i + 1));
			if (count($rest) === 1 && $rest[0][0] === T_VARIABLE) {
				return $rest[0][1];
			}
		}
		return null;
	}

	private function relative($file)
	{
		return (strpos($file, $this->root . '/') === 0)
			? substr($file, strlen($this->root) + 1) : $file;
	}
}
