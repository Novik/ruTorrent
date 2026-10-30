<?php

require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/TestTree.php');
require_once(__DIR__ . '/../../../php/utility/utility.php');

/**
 * Shared by UnpackPlaceTest.php and UnpackScriptsTest.php, which run
 * plugins/unpack/place.php and the unpack scripts in processes of their
 * own.
 */
final class UnpackPlaceFixture
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

	/**
	 * The PHP options that give place.php this process's configuration. A
	 * stock php.ini leaves E out of variables_order, and conf/config.php reads
	 * the RU_ settings a test sets from $_ENV.
	 */
	public static function phpOptions()
	{
		return array_merge(PhpProcess::sameIni(), array('-d', 'variables_order=EGPCS'));
	}

	/** Every path under $directory, sorted, links shown with their targets. */
	public static function tree($directory)
	{
		$found = array();
		$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,
			FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($entries as $path => $entry)
			$found[] = substr($path, strlen($directory) + 1) . (is_link($path) ? ' -> ' . readlink($path) : '');
		sort($found);
		return $found;
	}

	/** The lines of the application log $path, without their times. */
	public static function logLines($path)
	{
		return array_values(array_filter(array_map(function ($line) {
			return preg_replace('/^\[[^\]]*\] /', '', $line);
		}, explode("\n", (string)@file_get_contents($path))), 'strlen'));
	}

	/** Removes $base, directories a test made unwritable included. */
	public static function remove($base)
	{
		if ($base === null)
			return;
		exec('chmod -R u+rwx ' . Utility::escapeShellArg($base) . ' 2>/dev/null');
		TestTree::remove($base);
	}
}
