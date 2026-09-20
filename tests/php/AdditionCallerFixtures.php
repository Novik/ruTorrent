<?php

/**
 * A settings object carrying a real alias table, for every rtorrent the tree
 * supports.
 *
 * An addition is checked by name, and the name a caller builds is whatever
 * rTorrentSettings::getCommand() maps its alias to on the daemon in front of
 * it. So a test that pins one spelling proves nothing about the others: the
 * allowlist has to hold under every table php/ ships, and these fixtures hand
 * the tests one installed settings object per table.
 *
 * php/settings.php builds the table inside the method that interrogates a live
 * daemon, which a test has none of, so the cascade is repeated here. To stop
 * the two drifting apart, versionsCoverEveryMethodFile() asserts that the
 * versions below between them load every php/methods-*.php in the tree; adding
 * a table without teaching these fixtures about it fails that assertion.
 */
trait AdditionCallerFixtures
{
	/** iVersion => the version string it stands for. */
	public function supportedVersions()
	{
		return array(
			0x0805 => '0.8.5',
			0x0809 => '0.8.9',
			0x0904 => '0.9.4',
			0x0a02 => '0.10.2',
			0x1000 => '0.16.0',
			0x1010 => '0.16.16',
			0x1012 => '0.16.18',
		);
	}

	public function repoRoot()
	{
		return realpath(__DIR__ . '/../..');
	}

	/** The method files php/settings.php loads for $iVersion, in its order. */
	public function methodFilesFor($iVersion)
	{
		$files = array();
		if ($iVersion < 0x0900) {
			$files[] = 'methods-pre-0.9.0.php';
		}
		if ($iVersion >= 0x0904) {
			$files[] = 'methods-0.9.4.php';
		}
		if ($iVersion >= 0x0a02) {
			$files[] = 'methods-0.10.2.php';
		}
		if ($iVersion >= 0x1000) {
			$files[] = 'methods-0.16.0.php';
		}
		if ($iVersion >= 0x1010) {
			$files[] = 'methods-0.16.16.php';
		}
		if ($iVersion >= 0x1012) {
			$files[] = 'methods-0.16.18.php';
		}
		return $files;
	}

	/** Installs settings for $iVersion and returns the object. */
	public function installSettingsFor($iVersion)
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		$settings = $reflection->newInstanceWithoutConstructor();
		$settings->iVersion = $iVersion;
		$settings->apiVersion = 0;
		$settings->directory = sys_get_temp_dir();
		$settings->aliases = ($iVersion > 0x0806) ? array(
			'd.set_peer_exchange'   => array('name' => 'd.peer_exchange.set', 'prm' => 0),
			'd.set_connection_seed' => array('name' => 'd.connection_seed.set', 'prm' => 0),
		) : array();

		$php = $this->repoRoot() . '/php/';
		$files = $this->methodFilesFor($iVersion);
		// The method files assign to $this->aliases, so they are included in
		// the settings object's own scope -- and with include rather than
		// require_once, because one process installs several tables.
		$load = Closure::bind(function () use ($php, $files) {
			foreach ($files as $file) {
				include($php . $file);
			}
		}, $settings, 'rTorrentSettings');
		$load();

		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $settings);
		return $settings;
	}

	public function restoreSettings($previous)
	{
		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $previous);
	}

	public function currentSettings()
	{
		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		return $property->getValue();
	}

	/** Every php/methods-*.php is loaded by at least one supported version. */
	public function versionsCoverEveryMethodFile()
	{
		$loaded = array();
		foreach (array_keys($this->supportedVersions()) as $iVersion) {
			foreach ($this->methodFilesFor($iVersion) as $file) {
				$loaded[$file] = true;
			}
		}
		$missing = array();
		foreach (glob($this->repoRoot() . '/php/methods-*.php') as $path) {
			if (!isset($loaded[basename($path)])) {
				$missing[] = basename($path);
			}
		}
		return $missing;
	}

	/**
	 * rTorrent::areValidAdditions(). Reached by reflection because it was
	 * protected when this was written; it is public now, so a caller that
	 * erases a download before reloading it can ask before the erase, and the
	 * reflection stays only so this helper keeps one spelling for every test.
	 */
	public function areValidAdditions($addition)
	{
		$method = new ReflectionMethod('rTorrent', 'areValidAdditions');
		$method->setAccessible(true);
		return $method->invoke(null, $addition);
	}
}
