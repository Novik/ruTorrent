<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/trafic/stat.php');

/**
 * Statistics are stored per profile, and a front end that serves more than one
 * rtorrent has to read them from the profile of whoever owns the torrent --
 * which is not the profile the request arrived under. The directory is an
 * argument for that; left out, it is the current profile as before.
 */
class SettingsPathTest extends TestCase
{
	private $dir = '';

	private function storage()
	{
		if ($this->dir === '') {
			$this->dir = sys_get_temp_dir() . '/rutorrent-trafic-' . getmypid();
			@mkdir($this->dir . '/trafic/torrents', 0777, true);
		}
		return ($this->dir);
	}

	// One day's worth of upload in the current hour, and a month's worth in the
	// current day, written as update.php writes them.
	private function writeStat($name, $hourUp, $monthUp)
	{
		$now = getdate();
		$rows = array(
			$this->bucket(24, $now['hours'], $hourUp),
			array_fill(0, 24, 0),
			$this->bucket(24, $now['hours'], $now[0]),
			$this->bucket(31, $now['mday'] - 1, $monthUp),
			array_fill(0, 31, 0),
			$this->bucket(31, $now['mday'] - 1, $now[0]),
			array_fill(0, 12, 0),
			array_fill(0, 12, 0),
			array_fill(0, 12, 0),
		);
		$file = fopen($this->storage() . '/trafic/torrents/' . $name, 'w');
		foreach ($rows as $row) {
			fputcsv($file, $row, ",", "\"", "");
		}
		fclose($file);
	}

	private function bucket($size, $ndx, $value)
	{
		$row = array_fill(0, $size, 0);
		$row[$ndx] = $value;
		return ($row);
	}

	public function testItReadsTheProfileItIsGiven()
	{
		$this->writeStat('A1.csv', 4096, 8192);

		$st = new rStat('torrents/A1.csv', $this->storage());
		$ratios = $st->getRatios(time());

		$this->assertEquals(4096, $ratios[0], 'the day comes from the hour buckets');
		$this->assertEquals(8192, $ratios[1], 'the week from the day buckets');
		$this->assertEquals(8192, $ratios[2], 'and so does the month');
	}

	public function testWithoutOneItIsTheCurrentProfile()
	{
		$st = new rStat('torrents/A1.csv');

		$this->assertEquals(FileUtil::getSettingsPath() . '/trafic/torrents/A1.csv', $st->fname,
			'the profile of the user the request arrived under');
	}

	public function testAProfileWithoutTheFileReportsNothing()
	{
		$st = new rStat('torrents/missing.csv', $this->storage());

		$this->assertEquals(array(0, 0, 0), $st->getRatios(time()), 'no file, no traffic');
	}
}
