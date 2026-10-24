<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * The PHP half of the language files.
 *
 * tests/js/lang.spec.js holds every lang/*.js file to the key set its own
 * en.js declares, and reaches only .js. The lang/*.php files beside them are
 * this file's subject.
 *
 * They are not a second copy of the JavaScript ones. They carry the strings a
 * plugin's action.php prints server-side -- the request names a language,
 * action.php requires that one file and then reads $theUILang["..."] directly
 * -- so a key the file does not define is the empty string in the markup the
 * browser receives, with nothing in it to say which label is missing.
 *
 * Read by requiring them, which is what the request does. What these files
 * mean is PHP's to decide: the escapes, the concatenations and the array
 * nesting are the interpreter's, and a reader that re-implemented any of it
 * would answer for something other than what a request gets.
 */
class LangPhpKeyParityTest extends TestCase
{
	private $root;

	/** The lang/*.php sets, as directory => (file name => leaf key => value). */
	private $sets = array();

	/**
	 * Read once for the whole class. require() executes the file every time it
	 * is reached, and the key sets it yields are the same for every case below.
	 */
	public function setUp()
	{
		$this->root = dirname(dirname(__DIR__));
		$files = array_merge(
			glob($this->root . '/lang/*.php'),
			glob($this->root . '/plugins/*/lang/*.php'));
		sort($files);
		foreach ($files as $file) {
			$dir = dirname($file);
			// A set with no en.php declares no key list to be held to. Plugins
			// from outside the repository are installed into plugins/ and are
			// read here too, so what is asked of a set is what that set ships.
			if (!is_file($dir . '/en.php')) {
				continue;
			}
			$this->sets[$dir][basename($file)] = $this->leavesOf($file);
		}
	}

	/**
	 * Every value the file defines, flattened to leaf paths, so that a nested
	 * group counts as its members and not as one key -- a translation cannot
	 * lose all but one member of a group and still look complete. The mirror of
	 * leafEntries() in tests/js/lang.spec.js.
	 */
	private function leavesOf($file)
	{
		// require() assigns into this method's own $theUILang, which is what
		// keeps each file's keys to itself. Read in one shared scope, every
		// file would inherit the keys of the files read before it and nothing
		// would ever be reported missing.
		$theUILang = array();
		require($file);
		$leaves = array();
		self::flatten($theUILang, '', $leaves);
		return $leaves;
	}

	private static function flatten($value, $prefix, &$out)
	{
		foreach ($value as $key => $child) {
			$name = ($prefix === '') ? (string)$key : $prefix . '.' . $key;
			if (is_array($child)) {
				self::flatten($child, $name, $out);
			} else {
				$out[$name] = $child;
			}
		}
	}

	private function shorten($path)
	{
		return substr($path, strlen($this->root) + 1);
	}

	/**
	 * The premise of the two cases below: a glob that matched nothing would
	 * leave both of them with nothing to iterate and nothing to report.
	 */
	public function testTheLanguageFilesWereFound()
	{
		$english = 0;
		$translations = 0;
		$keys = 0;
		foreach ($this->sets as $set) {
			$english++;
			$keys += count($set['en.php']);
			$translations += count($set) - 1;
		}
		$this->assertTrue($english > 0,
			'the tree ships a lang/*.php set with an en.php in it');
		$this->assertTrue($translations > 0,
			'and translations beside it to compare (' . $translations . ')');
		$this->assertTrue($keys > 0,
			'and en.php declares keys to compare them against (' . $keys . ')');
	}

	/**
	 * A key en.php defines and a translation does not is not a fallback to
	 * English: action.php requires one language file, so the key is simply
	 * absent and the value printed is the empty string.
	 *
	 * A language nobody has translated yet carries the English text, so the key
	 * is there and the page reads -- in English -- rather than showing a gap.
	 * What is required here is that the key exists, never that it differs.
	 *
	 * A blank translation is the worse half: the label is printed as nothing at
	 * all, and no reader can tell which string it was. So blankness is a fault
	 * wherever English has text.
	 */
	public function testEveryKeyOfAnEnglishFileIsDefinedInEveryTranslationBesideIt()
	{
		$checked = 0;
		foreach ($this->sets as $dir => $set) {
			foreach ($set as $name => $leaves) {
				if ($name === 'en.php') {
					continue;
				}
				foreach ($set['en.php'] as $key => $english) {
					$checked++;
					$where = $this->shorten($dir) . '/' . $name . ' -> ' . $key;
					if (!array_key_exists($key, $leaves)) {
						$this->assertTrue(false, $where . ' is defined');
						continue;
					}
					$this->assertTrue(
						!(is_string($english) && trim($english) !== ''
							&& is_string($leaves[$key]) && trim($leaves[$key]) === ''),
						$where . ' is not blank where English has text');
				}
			}
		}
		$this->assertTrue($checked > 0, 'found translated keys to check');
	}

	/**
	 * The mirror. A key a translation defines and en.php does not is a typo or
	 * a leftover: nothing reads it, and it will never be printed.
	 */
	public function testNoTranslationDefinesAKeyItsEnglishFileDoesNot()
	{
		$checked = 0;
		foreach ($this->sets as $dir => $set) {
			foreach ($set as $name => $leaves) {
				if ($name === 'en.php') {
					continue;
				}
				foreach ($leaves as $key => $ignored) {
					$checked++;
					$this->assertTrue(array_key_exists($key, $set['en.php']),
						$this->shorten($dir) . '/' . $name . ' -> ' . $key
							. ' is a key en.php declares');
				}
			}
		}
		$this->assertTrue($checked > 0, 'found translated keys to check');
	}
}
