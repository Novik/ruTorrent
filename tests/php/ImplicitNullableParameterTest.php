<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * A parameter written `int $count = null` is nullable by implication. PHP 8.4
 * deprecates that spelling, and every call into such a function emits a
 * deprecation -- on ordinary traffic, into whatever log PHP writes to, from
 * shipped code an operator cannot fix. The explicit `?int $count = null` says
 * the same thing and says it in a way that survives PHP 9.
 *
 * Scanned rather than reflected: the shipped tree is loaded a plugin at a time
 * by a live request, and this has to see the files no test happens to include.
 */
class ImplicitNullableParameterTest extends TestCase
{
	private $root = null;

	public function setUp()
	{
		$this->root = realpath(__DIR__ . '/../..');
	}

	private function shippedSources()
	{
		$files = array();
		foreach (array('/conf', '/php', '/plugins') as $sub) {
			$dir = $this->root . $sub;
			if (!is_dir($dir)) {
				continue;
			}
			$walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
				$dir, FilesystemIterator::SKIP_DOTS));
			foreach ($walk as $file) {
				if (substr($file->getFilename(), -4) === '.php') {
					$files[] = $file->getPathname();
				}
			}
		}
		$files[] = $this->root . '/rpc2.php';
		sort($files);
		return $files;
	}

	public function testNoShippedFunctionTakesAnImplicitlyNullableParameter()
	{
		// A type name that is neither nullable (?T) nor a union carrying null,
		// immediately before a parameter defaulted to null.
		$pattern = '`(?<![?|\w\\\\])\b(?!null\b)'
			. '[A-Za-z_\\\\][A-Za-z0-9_\\\\]*'
			. '\s+&?\.{0,3}\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*null\b`i';
		$bad = array();
		foreach ($this->shippedSources() as $path) {
			$src = @file_get_contents($path);
			if ($src === false) {
				continue;
			}
			// Only signatures: the pattern would otherwise match a default in
			// any array literal or assignment that happens to read the same.
			if (!preg_match_all('`\bfunction\b[^(){;]*\(([^()]*)\)`i', $src, $sigs,
				PREG_OFFSET_CAPTURE)) {
				continue;
			}
			foreach ($sigs[1] as $sig) {
				if (!preg_match($pattern, $sig[0], $m)) {
					continue;
				}
				$line = substr_count(substr($src, 0, $sig[1]), "\n") + 1;
				$bad[] = substr($path, strlen($this->root) + 1) . ':' . $line
					. ' (' . trim($m[0]) . ')';
			}
		}
		$message = empty($bad)
			? 'no shipped signature spells a nullable parameter implicitly'
			: 'implicitly nullable parameters: ' . implode(', ', $bad);
		$this->assertEquals('[]', json_encode($bad), $message);
	}
}
