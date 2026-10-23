<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TestTree.php');

// Only the class; the constant stops the checker from running its live probing.
define('RUTORRENT_REQUIREMENTS_LIB', true);
require_once(__DIR__ . '/../../env_check.php');

/**
 * The bundled Public Suffix List is a dated file an install cannot replace:
 * php/publicsuffix.php pins its SHA-256, so a newer .dat makes every lookup
 * throw. An aged copy stops recognising public suffixes delegated since it
 * was cut, so a cookie Domain naming one is accepted again -- it admits names
 * it should refuse and refuses nothing it should admit, which is why the
 * checker warns about it and does not fail.
 *
 * Two levels. The age decision is asserted against the checker's pure logic,
 * which needs no environment. Then the checker is run for real on a tree
 * whose list carries a chosen stamp, because the rendered line is the only
 * thing an installer ever sees.
 */
class EnvCheckPublicSuffixListTest extends TestCase
{
	private $trees = array();
	private $root;

	public function setUp()
	{
		$this->root = dirname(dirname(__DIR__));
	}

	public function tearDown()
	{
		foreach ($this->trees as $tree) TestTree::remove($tree);
		$this->trees = array();
	}

	// ---- the age decision, with no environment at all ---------------------

	public function testTheShippedListCarriesAStampTheCheckerCanRead()
	{
		$data = @file_get_contents($this->root . '/php/public_suffix_list.dat');
		$this->assertTrue(is_string($data), 'The bundled list is readable');
		$this->assertTrue(Requirements::publicSuffixListDate($data) !== null,
			'The bundled list carries a "// VERSION:" stamp, so its age is knowable');
	}

	public function testAStampIsReadInUTCWhateverTheLocalZone()
	{
		$saved = date_default_timezone_get();
		date_default_timezone_set('Pacific/Kiritimati');
		$stamped = Requirements::publicSuffixListDate("// VERSION: 2026-09-24_13-26-36_UTC\n");
		date_default_timezone_set($saved);
		$this->assertEquals(gmmktime(13, 26, 36, 9, 24, 2026), $stamped,
			'The stamp is read in UTC, as the list writes it');
	}

	public function testAStampTheCheckerCannotReadIsReportedRatherThanGuessed()
	{
		$unreadable = array('', "// VERSION:\n", "// VERSION: 2026-09-24\n",
			"// VERSION: 2026-13-24_13-26-36_UTC\n", "// VERSION: 2026-09-31_13-26-36_UTC\n",
			"// VERSION: 2026-09-24_25-26-36_UTC\n", "// VERSION: 2026-09-24_13-26-36_CEST\n",
			"// VERSION 2026-09-24_13-26-36_UTC\n", "x// VERSION: 2026-09-24_13-26-36_UTC\n",
			"// date : dot Date Limited\n");
		foreach ($unreadable as $data) {
			$this->assertTrue(Requirements::publicSuffixListDate($data) === null,
				'Not a stamp the checker will read: ' . var_export($data, true));
			list($ok, $detail) = Requirements::publicSuffixListAge($data, time());
			$this->assertTrue(!$ok && strpos($detail, 'no "// VERSION:" stamp') !== false,
				'An unreadable stamp is reported, not passed: ' . var_export($data, true));
		}
	}

	public function testTheBoundIsInclusiveAndAgeIsWholeDays()
	{
		$stamp = "// VERSION: 2026-09-24_13-26-36_UTC\n";
		$cut = gmmktime(13, 26, 36, 9, 24, 2026);
		$cases = array(array(0, true), array(1, true),
			array(Requirements::MAX_PSL_AGE_DAYS, true),
			array(Requirements::MAX_PSL_AGE_DAYS + 1, false));
		foreach ($cases as $case) {
			list($days, $expected) = $case;
			// A second short of the next whole day still reads as $days.
			foreach (array($cut + $days * 86400, $cut + ($days + 1) * 86400 - 1) as $now) {
				list($ok, $detail) = Requirements::publicSuffixListAge($stamp, $now);
				$this->assertEquals($expected, $ok,
					'A list ' . $days . ' day(s) old is acceptable: ' . var_export($expected, true));
				$this->assertTrue(strpos($detail, 'published 2026-09-24, ' . $days . ' day(s) ago') === 0,
					'The detail names the publication date and ' . $days . ' day(s)');
			}
		}
	}

	public function testAStampAheadOfTheClockIsNotTreatedAsAged()
	{
		$cut = gmmktime(13, 26, 36, 9, 24, 2026);
		list($ok, $detail) = Requirements::publicSuffixListAge(
			"// VERSION: 2026-09-24_13-26-36_UTC\n", $cut - 400 * 86400);
		$this->assertTrue($ok, 'A clock behind the stamp does not make the list stale');
		$this->assertTrue(strpos($detail, ', 0 day(s) ago') !== false,
			'and the age reads as none rather than as a negative number');
	}

	// ---- the checker, run for real ----------------------------------------

	/** The bundled list with its stamp replaced, or with no stamp at all. */
	private function listStamped($timestamp)
	{
		$data = @file_get_contents($this->root . '/php/public_suffix_list.dat');
		if (!is_string($data)) throw new RuntimeException('Could not read the bundled list');
		$replacement = $timestamp === null ? '// (this copy carries no stamp)'
			: '// VERSION: ' . gmdate('Y-m-d_H-i-s', $timestamp) . '_UTC';
		$count = 0;
		$data = preg_replace('~^// VERSION:.*$~m', $replacement, $data, 1, $count);
		if ($count !== 1) throw new RuntimeException('The bundled list has no single VERSION line');
		return $data;
	}

	/**
	 * A tree the checker runs in whose list belongs to the case. env_check.php
	 * is copied because PHP resolves __DIR__ through a symlink, which would
	 * send the checker back to the shipped list; everything the checker then
	 * reads -- the configuration, the transport, the plugins -- is linked, so
	 * only the dated file differs from the tree as it ships.
	 */
	private function treeWithList($contents)
	{
		$tree = sys_get_temp_dir() . '/rutorrent-psl-' . uniqid();
		$this->trees[] = $tree;
		if (!@mkdir($tree . '/php', 0700, true))
			throw new RuntimeException('Could not create the checker tree');
		foreach (array('', 'php') as $relative) {
			$source = $relative === '' ? $this->root : $this->root . '/' . $relative;
			$target = $relative === '' ? $tree : $tree . '/' . $relative;
			foreach (scandir($source) as $entry) {
				if (($entry === '.') || ($entry === '..')
					|| ($relative === '' && ($entry === 'php' || $entry === 'env_check.php'))
					|| ($relative === 'php' && $entry === 'public_suffix_list.dat')) continue;
				if (!@symlink($source . '/' . $entry, $target . '/' . $entry))
					throw new RuntimeException('Could not link ' . $entry . ' into the checker tree');
			}
		}
		if (!@copy($this->root . '/env_check.php', $tree . '/env_check.php'))
			throw new RuntimeException('Could not copy the checker into its tree');
		if ($contents !== null
			&& @file_put_contents($tree . '/php/public_suffix_list.dat', $contents) !== strlen($contents))
			throw new RuntimeException('Could not write the checker tree list');
		return $tree;
	}

	/**
	 * The checker's report and exit status. The SCGI address names a socket
	 * that is never created, so the rtorrent probe fails at once instead of
	 * waiting on whatever happens to listen on the runner.
	 */
	private function report($tree, &$status = null)
	{
		$output = array();
		$status = 0;
		exec('RU_SCGI_HOST=' . escapeshellarg('unix://' . $tree . '/absent.sock') . ' '
			. escapeshellarg(PHP_BINARY) . ' -d variables_order=EGPCS -d error_reporting=E_ALL'
			. ' -d display_errors=1 ' . escapeshellarg($tree . '/env_check.php') . ' 2>&1',
			$output, $status);
		return implode("\n", $output);
	}

	/** The one rendered line an installer reads about the list. */
	private function listLine($tree, &$status = null)
	{
		$lines = array();
		foreach (explode("\n", $this->report($tree, $status)) as $line)
			if (strpos($line, 'Public Suffix List') !== false) $lines[] = trim($line);
		$this->assertEquals(1, count($lines), 'The checker reports the list exactly once');
		return isset($lines[0]) ? $lines[0] : '';
	}

	public function testACurrentListIsReportedWithoutAWarning()
	{
		$line = $this->listLine($this->treeWithList($this->listStamped(time())), $status);
		$this->assertTrue(strpos($line, '[OK  ] Public Suffix List') === 0,
			'A list published today is reported as OK: ' . $line);
		$this->assertEquals(0, $status, 'and the checker still exits clean');
	}

	public function testAListAtTheBoundIsStillCurrentAndADayPastItIsNot()
	{
		$day = 86400;
		$atBound = $this->listLine($this->treeWithList(
			$this->listStamped(time() - Requirements::MAX_PSL_AGE_DAYS * $day - 60)));
		$this->assertTrue(strpos($atBound, '[OK  ] Public Suffix List') === 0,
			'A list exactly at the bound is still current: ' . $atBound);
		$past = $this->listLine($this->treeWithList(
			$this->listStamped(time() - (Requirements::MAX_PSL_AGE_DAYS + 1) * $day - 60)));
		$this->assertTrue(strpos($past, '[WARN] Public Suffix List') === 0,
			'A list one day past the bound is reported as a warning: ' . $past);
	}

	public function testAnAgedListWarnsAndNamesTheRemedyWithoutFailingTheCheck()
	{
		$line = $this->listLine($this->treeWithList($this->listStamped(time() - 400 * 86400)), $status);
		$this->assertTrue(strpos($line, '[WARN] Public Suffix List') === 0,
			'A long aged list is reported as a warning: ' . $line);
		$this->assertTrue(strpos($line, 'Upgrade ruTorrent') !== false,
			'and names the only remedy an install has: ' . $line);
		$this->assertTrue(strpos($line, 'published ') !== false && strpos($line, ' day(s) ago') !== false,
			'and says when it was published and how long ago: ' . $line);
		$this->assertEquals(0, $status,
			'An aged list limits a feature rather than breaking the install, so the check still exits clean');
	}

	public function testAListWithNoStampWarnsThatItsAgeCannotBeRead()
	{
		$line = $this->listLine($this->treeWithList($this->listStamped(null)));
		$this->assertTrue(strpos($line, '[WARN] Public Suffix List') === 0
			&& strpos($line, 'no "// VERSION:" stamp') !== false,
			'A list with no stamp is reported as a warning: ' . $line);
	}

	public function testAnAbsentListWarnsThatDomainCookiesAreRefused()
	{
		$tree = $this->treeWithList(null);
		$this->assertTrue(!file_exists($tree . '/php/public_suffix_list.dat'),
			'The tree for this case has no list at all');
		$line = $this->listLine($tree);
		$this->assertTrue(strpos($line, '[WARN] Public Suffix List') === 0
			&& strpos($line, 'unreadable') !== false && strpos($line, 'refused') !== false,
			'An absent list is reported with what it costs: ' . $line);
	}

	/**
	 * The tree as it ships, where only the wording that cannot rot is read: a
	 * stamped list will cross the bound by simply sitting there, and asserting
	 * it has not would turn this case into a calendar.
	 */
	public function testTheCheckerReportsTheShippedListsOwnStamp()
	{
		$line = $this->listLine($this->treeWithList($this->listStamped(
			Requirements::publicSuffixListDate(
				file_get_contents($this->root . '/php/public_suffix_list.dat')))));
		$this->assertTrue(strpos($line, 'published '
			. gmdate('Y-m-d', Requirements::publicSuffixListDate(
				file_get_contents($this->root . '/php/public_suffix_list.dat')))) !== false,
			'The checker names the date the shipped list was published: ' . $line);
	}
}
