<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * Where a plugin's init.php looks for the files it requires.
 *
 * init.php is never run on its own. Both loaders -- php/initplugins.php, which
 * opens with chdir(dirname(__FILE__)), and php/getplugins.php, which the web
 * server enters with php/ as the working directory -- reach it as
 *
 *     require_once( "../plugins/".$file."/init.php" );
 *
 * so an unanchored path inside init.php is resolved against php/, not against
 * the plugin directory the file lives in. That is why paths like
 * "../plugins/ratio/ratio.php" and a bare "xmlrpc.php" find their target in a
 * running ruTorrent while every static reader of the file says they do not
 * exist.
 *
 * Both properties are worth holding:
 *
 *   - under the loader's working directory every path must resolve, which is
 *     the guard for anyone who later "tidies" one of these lines; and
 *   - away from it every path must still resolve, so the file is honest about
 *     what it loads and a reader (human or analyser) sees the same file PHP
 *     does.
 */
class PluginInitPathsTest extends TestCase
{
	private $root;

	public function setUp()
	{
		$this->root = realpath(__DIR__ . '/../..');
	}

	private function initFiles()
	{
		$files = glob($this->root . '/plugins/*/init.php');
		sort($files);
		return $files;
	}

	/**
	 * Every require/include in $file whose argument is a constant expression,
	 * as array('line' => ..., 'expr' => raw source, 'path' => the path with the
	 * file's own directory substituted for either spelling of the directory
	 * anchor constant.
	 *
	 * A path built from a runtime value (a variable, a function call other than
	 * the two anchors) cannot be judged from the source and is skipped.
	 */
	private function requiresIn($file)
	{
		$tokens = token_get_all(file_get_contents($file));
		$wanted = array(T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE);
		$found = array();
		for ($i = 0; $i < count($tokens); $i++) {
			if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], $wanted)) {
				continue;
			}
			$line = $tokens[$i][2];
			$expr = '';
			$path = '';
			$constant = true;
			$depth = 0;
			for ($j = $i + 1; $j < count($tokens); $j++) {
				$t = $tokens[$j];
				$text = is_array($t) ? $t[1] : $t;
				if ($text === ';' && $depth === 0) {
					break;
				}
				if ($text === '(') {
					$depth++;
				}
				if ($text === ')') {
					$depth--;
					// The closing paren of require( ... ) itself.
					if ($depth < 0) {
						break;
					}
				}
				$expr .= $text;
				if (!is_array($t)) {
					continue;
				}
				switch ($t[0]) {
					case T_CONSTANT_ENCAPSED_STRING:
						$path .= substr($t[1], 1, -1);
						break;
					case T_DIR:
						$path .= dirname($file);
						break;
					case T_STRING:
						// dirname(__FILE__), the pre-5.3 spelling of the anchor.
						if (strtolower($t[1]) === 'dirname'
							&& isset($tokens[$j + 2]) && is_array($tokens[$j + 2])
							&& $tokens[$j + 2][0] === T_FILE) {
							$path .= dirname($file);
							$j += 3;    // dirname ( __FILE__ )
							$expr .= '(__FILE__)';
							break;
						}
						$constant = false;
						break;
					case T_FILE:
					case T_WHITESPACE:
						break;
					case T_VARIABLE:
					default:
						$constant = false;
						break;
				}
			}
			if ($constant && $path !== '') {
				$found[] = array(
					'line' => $line,
					'expr' => trim($expr),
					'path' => $path,
				);
			}
		}
		return $found;
	}

	/**
	 * PHP's own lookup order for a require argument, so the test judges the
	 * path the way the interpreter will rather than the way it reads.
	 *
	 * An absolute path, or one written with a leading ./ or ../, is taken as
	 * given and the include_path is ignored. Anything else is looked for along
	 * the include_path, then in the directory of the file doing the requiring,
	 * then in the working directory.
	 */
	private function resolveAsPhpWould($includingFile, $path, $cwd)
	{
		$candidates = array();
		if (substr($path, 0, 1) === '/') {
			$candidates[] = $path;
		} elseif (substr($path, 0, 2) === './' || substr($path, 0, 3) === '../') {
			$candidates[] = $cwd . '/' . $path;
		} else {
			foreach (explode(PATH_SEPARATOR, ini_get('include_path')) as $dir) {
				if ($dir === '') {
					continue;
				}
				$candidates[] = ($dir === '.' ? $cwd : $dir) . '/' . $path;
			}
			$candidates[] = dirname($includingFile) . '/' . $path;
			$candidates[] = $cwd . '/' . $path;
		}
		foreach ($candidates as $candidate) {
			if (is_file($candidate)) {
				return realpath($candidate);
			}
		}
		return false;
	}

	private function checkAll($cwd, $note)
	{
		$checked = 0;
		foreach ($this->initFiles() as $file) {
			$short = substr($file, strlen($this->root) + 1);
			foreach ($this->requiresIn($file) as $req) {
				$checked++;
				$this->assertTrue(
					$this->resolveAsPhpWould($file, $req['path'], $cwd) !== false,
					$short . ':' . $req['line'] . ' ' . $req['expr'] . ' resolves ' . $note);
			}
		}
		$this->assertTrue($checked > 0, 'found require statements to check in plugins/*/init.php');
	}

	/**
	 * The state of the world in a running ruTorrent. Nothing here may regress:
	 * a path that stops resolving under php/ is a plugin that stops loading.
	 */
	public function testEveryInitRequireResolvesUnderTheLoaderWorkingDirectory()
	{
		$this->checkAll($this->root . '/php', 'from the loader working directory php/');
	}

	/**
	 * And away from it, which is what a static reader sees. A path that only
	 * resolves from php/ is one nobody can check without running the loader.
	 */
	public function testEveryInitRequireResolvesWithoutTheLoaderWorkingDirectory()
	{
		// An empty directory of our own, so nothing that happens to sit in the
		// system temp directory can answer for a path that does not resolve.
		$elsewhere = sys_get_temp_dir() . '/rutorrent-initpaths-' . getmypid();
		@mkdir($elsewhere);
		$this->checkAll($elsewhere, 'with an unrelated working directory');
		@rmdir($elsewhere);
	}

	/**
	 * The premise of the first test. initplugins.php runs from cron and from
	 * rTorrent's execute= with an arbitrary working directory, so the chdir is
	 * what puts every unanchored plugin path on php/.
	 */
	public function testTheLoaderStillMovesToThePhpDirectory()
	{
		$loader = file_get_contents($this->root . '/php/initplugins.php');
		$this->assertTrue(
			preg_match('/chdir\(\s*dirname\(\s*__FILE__\s*\)\s*\)/', $loader) === 1,
			'php/initplugins.php still chdir()s to its own directory before loading plugins');
	}

	/**
	 * Stands in for a variable part of a path built at runtime.
	 */
	const HOLE = "\0";

	/**
	 * The index of the next token that carries meaning, skipping whitespace and
	 * comments, or null at the end of the file.
	 */
	private function nextSignificant($tokens, $k)
	{
		for ($total = count($tokens); $k < $total; $k++) {
			if (!is_array($tokens[$k]) || !in_array($tokens[$k][0],
				array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				return $k;
			}
		}
		return null;
	}

	/**
	 * Every string $file builds out of literals and variables, as
	 * array('line' => ..., 'template' => the text with the file's own directory
	 * substituted for either spelling of the directory anchor and a HOLE where
	 * each variable part goes, 'holes' => how many variable parts there were).
	 *
	 * Neither loader requires a plugin's init.php by a literal path: each one
	 * builds the path from the plugin's directory name, tests it with
	 * is_readable() and requires the result. The expression that builds it is
	 * therefore what has to be judged, and reading it from the tokens leaves
	 * this test indifferent to whether it is written as a concatenation or as
	 * an interpolation, and to how either is spaced.
	 *
	 * A run that reaches a call other than the two directory anchors is
	 * dropped: what it produces cannot be known without running it.
	 */
	private function stringTemplatesIn($file)
	{
		$tokens = token_get_all(file_get_contents($file));
		$total = count($tokens);
		$found = array();
		$template = '';
		$holes = 0;
		$line = 0;
		$usable = true;
		for ($i = 0; $i < $total; $i++) {
			$t = $tokens[$i];
			$id = is_array($t) ? $t[0] : null;
			$text = is_array($t) ? $t[1] : $t;
			$piece = null;

			// Separators, and the punctuation of an interpolated string: they
			// carry no text of their own and do not end the run.
			if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT
				|| $id === T_CURLY_OPEN || $text === '"' || $text === '}'
				|| $text === '.') {
				continue;
			}

			if ($id === T_CONSTANT_ENCAPSED_STRING) {
				$piece = substr($t[1], 1, -1);
			} elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
				// A literal run between the interpolations of "...".
				$piece = $t[1];
			} elseif ($id === T_DIR) {
				$piece = dirname($file);
			} elseif ($id === T_VARIABLE) {
				$piece = self::HOLE;
				$holes++;
			} elseif ($id === T_STRING && strtolower($t[1]) === 'dirname') {
				// dirname(__FILE__), the pre-5.3 spelling of the anchor.
				$open = $this->nextSignificant($tokens, $i + 1);
				$arg = ($open === null) ? null : $this->nextSignificant($tokens, $open + 1);
				$close = ($arg === null) ? null : $this->nextSignificant($tokens, $arg + 1);
				if ($close !== null && $tokens[$open] === '(' && $tokens[$close] === ')'
					&& is_array($tokens[$arg]) && $tokens[$arg][0] === T_FILE) {
					$piece = dirname($file);
					$i = $close;
				} else {
					$usable = false;
					continue;
				}
			} elseif ($id === T_STRING || $text === '(') {
				$usable = false;
				continue;
			}

			if ($piece !== null) {
				if ($template === '') {
					$line = is_array($t) ? $t[2] : $line;
				}
				$template .= $piece;
				continue;
			}

			// Anything else ends the run.
			if ($template !== '' && $usable) {
				$found[] = array('line' => $line, 'template' => $template,
					'holes' => $holes);
			}
			$template = '';
			$holes = 0;
			$usable = true;
		}
		if ($template !== '' && $usable) {
			$found[] = array('line' => $line, 'template' => $template,
				'holes' => $holes);
		}
		return $found;
	}

	/**
	 * A template with its hole spelled out, for a failure message.
	 */
	public function readable($template)
	{
		return str_replace(self::HOLE, '<plugin>', $template);
	}

	/**
	 * What each loader does with that working directory, rather than how the
	 * line is written. The path a loader builds for a plugin must reach every
	 * plugin in the tree from php/; must reach none of them from anywhere else,
	 * which is the dependency the first test rests on; and must be the same
	 * path in both loaders, so that a plugin cannot be loaded from one place by
	 * one loader and from another by the other.
	 */
	public function testBothLoadersBuildTheSameCwdRelativePluginInitPath()
	{
		// An empty directory of our own, so nothing that happens to sit in the
		// system temp directory can answer for a path that does not resolve.
		$elsewhere = sys_get_temp_dir() . '/rutorrent-initpaths-loaders-' . getmypid();
		@mkdir($elsewhere);

		$built = array();
		foreach (array('initplugins.php', 'getplugins.php') as $name) {
			$loader = $this->root . '/php/' . $name;
			$reaching = array();
			foreach ($this->stringTemplatesIn($loader) as $tpl) {
				if ($tpl['holes'] === 1 && substr($tpl['template'], -9) === '/init.php') {
					$reaching[$tpl['template']] = true;
				}
			}
			$this->assertTrue(count($reaching) === 1,
				'php/' . $name . ' builds one plugin init.php path out of the'
				. ' plugin name (' . count($reaching) . ': '
				. implode(', ', array_map(array($this, 'readable'),
					array_keys($reaching))) . ')');
			if (count($reaching) !== 1) {
				continue;
			}
			$template = key($reaching);

			$checked = 0;
			foreach ($this->initFiles() as $init) {
				$plugin = basename(dirname($init));
				$path = str_replace(self::HOLE, $plugin, $template);
				$this->assertTrue(
					$this->resolveAsPhpWould($loader, $path, $this->root . '/php')
						=== realpath($init),
					'php/' . $name . ' reaches plugins/' . $plugin
						. '/init.php from the loader working directory php/');
				$this->assertTrue(
					$this->resolveAsPhpWould($loader, $path, $elsewhere) === false,
					'php/' . $name . ' reaches plugins/' . $plugin
						. '/init.php only from php/, so it is the working'
						. ' directory that places it');
				$checked++;
			}
			$this->assertTrue($checked > 0,
				'found plugins for php/' . $name . ' to be checked against');
			$built[$name] = $template;
		}
		@rmdir($elsewhere);

		$this->assertTrue(count($built) === 2 && count(array_unique($built)) === 1,
			'both loaders build the same path ('
			. implode(' vs ', array_map(array($this, 'readable'), $built)) . ')');
	}
}
