<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/AdditionCallerFixtures.php');
require_once(__DIR__ . '/TorrentAdditionSourceScanner.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/**
 * Nothing in the tree may build an addition the guard refuses.
 *
 * rTorrent::ADDITION_COMMANDS is an allowlist, and sendTorrent()/sendMagnet()
 * drop the whole add when one entry of an addition is not on it. Two of the
 * callers run d.erase on the torrent before they reload it, so for them a
 * refused addition is a deleted download that never comes back. The list and
 * the callers therefore have to be kept in step, and the only way to keep them
 * in step without anyone remembering to is to check the tree rather than a
 * list of the callers somebody wrote down.
 *
 * So this reads the source: every rTorrent::sendTorrent()/sendMagnet() call
 * site under php/ and plugins/, the addition each one passes, and the command
 * name each element of that addition carries -- resolved through the same
 * rTorrentSettings::getCommand() the guard uses, under every alias table
 * php/ ships. A new plugin that builds an unpermitted addition fails here on
 * the commit that adds it.
 *
 * An element the scanner cannot reduce to a command name fails too. A
 * construction nothing can read is a construction nothing is checking, and
 * that is how the undefined $throttle in plugins/edit/action.php got into an
 * addition list in the first place.
 */
class TorrentAdditionCoverageTest extends TestCase
{
	use AdditionCallerFixtures;

	/** Files that pass an addition today. Others are checked, not required. */
	const KNOWN_ADDITION_CALLERS = array(
		'plugins/datadir/util_setdir.php',
		'plugins/edit/action.php',
		'plugins/retrackers/update.php',
		'plugins/rss/rss.php',
		'plugins/rutracker_check/check.php',
	);

	private $previousSettings;
	private $scratch;
	private $planted = 0;

	public function setUp()
	{
		$this->previousSettings = $this->currentSettings();
		$this->scratch = sys_get_temp_dir() . '/rutorrent-addition-scan-' . getmypid();
	}

	public function tearDown()
	{
		$this->restoreSettings($this->previousSettings);
		$this->removeTree($this->scratch);
	}

	private function removeTree($path)
	{
		if (!is_dir($path)) {
			return;
		}
		foreach (glob($path . '/*') as $entry) {
			is_dir($entry) ? $this->removeTree($entry) : @unlink($entry);
		}
		@rmdir($path);
	}

	/** A throwaway tree holding one plugin file, for the scanner's controls. */
	private function plantedTree($body)
	{
		// A directory of its own each time: the scanner caches one tokenizing
		// pass per tree, so reusing a path would hand back the previous plant.
		$root = $this->scratch . '/' . (++$this->planted);
		@mkdir($root . '/plugins/planted', 0700, true);
		file_put_contents($root . '/plugins/planted/action.php', $body);
		return $root;
	}

	/** Every element of every call site, flattened, for one alias table. */
	private function elementsUnder($iVersion, $root = null)
	{
		$this->installSettingsFor($iVersion);
		$scanner = new TorrentAdditionSourceScanner($root === null ? $this->repoRoot() : $root);
		$out = array();
		foreach ($scanner->callSites() as $site) {
			foreach ($site['elements'] as $element) {
				$element['where'] = $site['file'] . ':' . $site['line'];
				$element['name'] = TorrentAdditionSourceScanner::commandName($element);
				$out[] = $element;
			}
		}
		return $out;
	}

	// ---- the fixtures are honest -----------------------------------------

	/**
	 * The alias tables below are a copy of the cascade php/settings.php runs
	 * against a live daemon. If a new one is added there and not here, every
	 * assertion in this file would quietly stop covering it.
	 */
	public function testEveryShippedAliasTableIsCovered()
	{
		$missing = $this->versionsCoverEveryMethodFile();
		$this->assertTrue($missing === array(),
			'every php/methods-*.php is loaded by one of the versions these tests run'
			. (count($missing) ? '; not covered: ' . implode(', ', $missing) : ''));
	}

	// ---- the scan finds what is there ------------------------------------

	/**
	 * A scanner that found nothing would pass every assertion below it. This
	 * is the control that says it is reading the tree.
	 */
	public function testTheScanFindsTheEntryPointCallSites()
	{
		$this->installSettingsFor(0x1012);
		$scanner = new TorrentAdditionSourceScanner($this->repoRoot());
		$sites = $scanner->callSites();

		$this->assertTrue(count($sites) >= 12,
			'the scan finds the sendTorrent/sendMagnet call sites in the tree; found ' . count($sites));

		$withAddition = array();
		foreach ($sites as $site) {
			if (count($site['elements'])) {
				$withAddition[$site['file']] = true;
			}
		}
		foreach (self::KNOWN_ADDITION_CALLERS as $file) {
			$this->assertTrue(isset($withAddition[$file]),
				$file . ' is read as a caller that builds an addition');
		}
	}

	// ---- the scan bites --------------------------------------------------

	/**
	 * The control for the coverage assertion: a planted caller that builds an
	 * addition outside the allowlist is reported as unpermitted. Without this,
	 * a scanner that silently resolved everything to a permitted name would
	 * make the whole file a false green.
	 */
	public function testAPlantedUnpermittedAdditionIsReported()
	{
		$root = $this->plantedTree("<?php\n"
			. "rTorrent::sendTorrent(\$t, true, false, \$d, \$l, false, false, false,\n"
			. "\tarray(getCmd(\"d.set_directory=\").\$anywhere));\n");
		$elements = $this->elementsUnder(0x1012, $root);

		$this->assertTrue(count($elements) === 1,
			'the planted call site is read; elements found: ' . count($elements));
		$this->assertTrue($elements[0]['name'] !== null,
			'the planted element resolves to a command name');
		$this->assertTrue(rTorrent::isValidAddition($elements[0]['name'] . '=x') === false,
			'and the planted d.set_directory addition is reported as not permitted');
	}

	/** The same, for a magnet: sendMagnet carries its addition in slot six. */
	public function testAPlantedUnpermittedMagnetAdditionIsReported()
	{
		$root = $this->plantedTree("<?php\n"
			. "rTorrent::sendMagnet(\$u, true, false, \$d, \$l,\n"
			. "\tarray('execute=/bin/sh,-c,id'));\n");
		$elements = $this->elementsUnder(0x1012, $root);

		$this->assertTrue(count($elements) === 1 && $elements[0]['name'] === 'execute',
			'the planted magnet addition is read as the command execute');
		$this->assertTrue(rTorrent::isValidAddition($elements[0]['name'] . '=x') === false,
			'and is reported as not permitted');
	}

	/**
	 * An addition assembled out of something the scanner cannot read is
	 * reported as unresolved rather than skipped, so it fails the coverage
	 * assertion instead of passing it by being invisible.
	 */
	public function testAnUnreadableAdditionIsReportedRatherThanSkipped()
	{
		$root = $this->plantedTree("<?php\n"
			. "\$addition = array(someHelper('d.set_custom3'));\n"
			. "rTorrent::sendTorrent(\$t, true, false, \$d, \$l, false, false, false, \$addition);\n");
		$elements = $this->elementsUnder(0x1012, $root);

		$this->assertTrue(count($elements) === 1, 'the planted call site is read');
		$this->assertTrue($elements[0]['name'] === null,
			'an element the scanner cannot reduce is reported as unresolved');
		$this->assertTrue(is_string($elements[0]['reason']) && strlen($elements[0]['reason']) > 0,
			'and it says why: ' . (string)$elements[0]['reason']);
	}

	/** A bare variable in an addition list is unreadable, and so reported. */
	public function testABareVariableElementIsReportedAsUnresolved()
	{
		$root = $this->plantedTree("<?php\n"
			. "rTorrent::sendTorrent(\$t, true, false, \$d, \$l, false, false, false,\n"
			. "\tarray(getCmd(\"d.set_throttle_name=\").\$n, \$maybeUnset));\n");
		$elements = $this->elementsUnder(0x1012, $root);

		$this->assertTrue(count($elements) === 2, 'both elements are read');
		$this->assertTrue($elements[1]['name'] === null,
			'a bare variable element is unresolved, the shape that put a null into the '
			. 'edit plugin addition list');
	}

	// ---- the coverage assertion ------------------------------------------

	/**
	 * The one that matters: nothing under php/ or plugins/ builds an addition
	 * the guard would refuse, on any rtorrent the tree supports.
	 */
	public function testNoCallerInTheTreeBuildsAnUnpermittedAddition()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$offenders = array();
			foreach ($this->elementsUnder($iVersion) as $element) {
				if ($element['name'] === null) {
					continue;
				}
				if (!rTorrent::isValidAddition($element['name'] . '=x')) {
					$offenders[] = $element['where'] . ' builds ' . $element['name']
						. ' (' . $element['expr'] . ')';
				}
			}
			$this->assertTrue($offenders === array(),
				'on rtorrent ' . $version . ' every addition the tree builds names a command '
				. 'rTorrent::ADDITION_COMMANDS permits'
				. (count($offenders) ? '; refused: ' . implode(' | ', $offenders) : ''));
		}
	}

	/**
	 * And every addition the tree builds is readable. An element that cannot
	 * be reduced to a command name is a hole in the assertion above.
	 */
	public function testEveryAdditionInTheTreeNamesAReadableCommand()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$unreadable = array();
			foreach ($this->elementsUnder($iVersion) as $element) {
				if ($element['name'] === null) {
					$unreadable[] = $element['where'] . ': ' . $element['expr']
						. ' -- ' . $element['reason'];
				}
			}
			$this->assertTrue($unreadable === array(),
				'on rtorrent ' . $version . ' every addition element names a command that can be '
				. 'read out of the source'
				. (count($unreadable) ? '; unreadable: ' . implode(' | ', $unreadable) : ''));
		}
	}

	// ---- the allowlist stays a boundary ----------------------------------

	/**
	 * The allowlist is not widened past what the tree uses. d.custom1 and
	 * d.custom2 are the label and the comment, which sendTorrent() already
	 * writes from its own checked parameters; an addition permitted to set
	 * them again could overwrite a value that was checked with one that was
	 * not. Nothing builds them today, and this pins that they stay out until
	 * something does.
	 */
	public function testTheUnusedCustomFieldsAreNotPermitted()
	{
		$this->installSettingsFor(0x1012);
		foreach (array('d.set_custom1', 'd.set_custom2', 'd.set_custom4', 'd.set_custom5') as $alias) {
			$this->assertTrue(rTorrent::isValidAddition(getCmd($alias) . '=x') === false,
				$alias . ' is not permitted as an addition, because nothing builds one');
		}
	}

	/** And the dangerous shapes stay refused under every alias table. */
	public function testTheRefusedCommandsStayRefusedUnderEveryAliasTable()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$this->installSettingsFor($iVersion);
			foreach (array('execute=/bin/sh,-c,id', 'execute.throw=/bin/sh,-c,id',
				'import=/tmp/rc', 'd.set_directory=/etc',
				getCmd('d.set_directory=') . '/etc') as $addition) {
				$this->assertTrue(rTorrent::isValidAddition($addition) === false,
					'on rtorrent ' . $version . ' the guard still refuses ' . $addition);
			}
		}
	}
}
