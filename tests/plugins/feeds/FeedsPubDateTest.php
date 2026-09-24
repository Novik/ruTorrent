<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');

/**
 * plugins/feeds/action.php builds an RSS item per torrent from one
 * d.multicall. Field 6 is the seedingtime custom value, which becomes the
 * item's pubDate; field 7, present only when item descriptions are shown, is
 * the torrent's comment. The pubDate has to follow field 6 alone, and each
 * torrent has to be read from its own row whether or not the description
 * fields are asked for.
 *
 * action.php is a top level script, so it runs in a process of its own
 * against FakeRtorrentDaemon, from a copy of the tree in which
 * plugins/feeds/conf.local.php can switch the descriptions off.
 */
class FeedsPubDateTest extends TestCase
{
	private $base;
	private $daemon;

	// hash, name, seedingtime, comment
	private $torrents = array(
		array('1111111111111111111111111111111111111111', 'no comment', '1700000000', ''),
		array('2222222222222222222222222222222222222222', 'with comment', '1710000000', 'VRS24mrkera%20comment'),
		array('3333333333333333333333333333333333333333', 'not finished', '', ''),
	);

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-feeds-pubdate-' . getmypid();
		@mkdir($this->base . '/profile/settings', 0700, true);
	}

	public function tearDown()
	{
		if ($this->daemon !== null) {
			$this->daemon->stop();
			$this->daemon = null;
		}
		$this->removeTree($this->base);
	}

	private function removeTree($path)
	{
		if (is_link($path) || is_file($path)) {
			@unlink($path);
			return;
		}
		if (!is_dir($path)) {
			return;
		}
		foreach (scandir($path) as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				$this->removeTree($path . '/' . $entry);
			}
		}
		@rmdir($path);
	}

	private function copyTree($from, $to)
	{
		@mkdir($to, 0700, true);
		foreach (scandir($from) as $entry) {
			if ($entry === '.' || $entry === '..' || $entry === 'config.local.php' || $entry === 'conf.local.php') {
				continue;
			}
			if (is_dir($from . '/' . $entry)) {
				$this->copyTree($from . '/' . $entry, $to . '/' . $entry);
			} else {
				copy($from . '/' . $entry, $to . '/' . $entry);
			}
		}
	}

	/** One d.multicall row, as action.php asks for it. */
	private function row($torrent, $descriptions)
	{
		list($hash, $name, $seedingtime, $comment) = $torrent;
		$row = array($hash, $name, '', '0', '0', '', $seedingtime);
		if ($descriptions) {
			$row = array_merge($row, array($comment, '4', '4', '65536', '65536', '0', '0',
				'0', '0', '16384', '0', '0', '0', '0'));
		}
		return $row;
	}

	/**
	 * Runs action.php and returns [title => pubDate or null] for every item
	 * in the feed it prints.
	 */
	private function feed($descriptions)
	{
		$root = realpath(__DIR__ . '/../../..');
		$tree = $this->base . '/tree-' . ($descriptions ? 'on' : 'off');
		foreach (array('php', 'conf', 'plugins/feeds') as $dir) {
			$this->copyTree($root . '/' . $dir, $tree . '/' . $dir);
		}
		if (!$descriptions) {
			file_put_contents($tree . '/plugins/feeds/conf.local.php',
				"<?php\n\$showItemDescription = false;\n");
		}

		$values = array();
		foreach ($this->torrents as $torrent) {
			$values = array_merge($values, $this->row($torrent, $descriptions));
		}
		if ($this->daemon !== null) {
			$this->daemon->stop();
		}
		$this->daemon = new FakeRtorrentDaemon(array($values), $this->base . '/calls.log');

		$driver = $this->base . '/drive-feeds.php';
		file_put_contents($driver, "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($tree . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$_SERVER[\'HTTP_HOST\'] = \'example.test\';' . "\n"
			. '$_SERVER[\'PHP_SELF\'] = \'/plugins/feeds/action.php\';' . "\n"
			. '$_REQUEST[\'mode\'] = \'all\';' . "\n"
			. 'chdir(' . var_export($tree . '/plugins/feeds', true) . ");\n"
			. 'require_once(' . var_export($tree . '/php/util.php', true) . ");\n"
			. 'require_once(' . var_export($tree . '/php/settings.php', true) . ");\n"
			. '$settings = rTorrentSettings::get();' . "\n"
			. '$settings->linkExist = true;' . "\n"
			. '$settings->iVersion = 0x1018;' . "\n"
			. '$settings->version = $settings->libVersion = \'0.16.24\';' . "\n"
			. '$settings->server = \'fake\';' . "\n"
			. 'require(' . var_export($tree . '/plugins/feeds/action.php', true) . ");\n");

		$output = shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver));
		$items = array();
		preg_match_all('`<item>(.*)</item>`Us', (string)$output, $matches);
		foreach ($matches[1] as $item) {
			preg_match('`<title>(.*)</title>`U', $item, $title);
			$items[$title[1]] = preg_match('`<pubDate>(.*)</pubDate>`U', $item, $date) ? $date[1] : null;
		}
		ksort($items);
		return array($items, (string)$output);
	}

	private function expected()
	{
		$items = array(
			'no comment' => 'Tue, 14 Nov 2023 22:13:20 GMT',
			'not finished' => null,
			'with comment' => 'Sat, 09 Mar 2024 16:00:00 GMT',
		);
		ksort($items);
		return $items;
	}

	public function testPubDateIsTheSeedingTimeWhetherOrNotThereIsAComment()
	{
		list($items, $output) = $this->feed(true);
		$this->assertEquals($this->expected(), $items,
			'each finished torrent is dated by its seeding time, with or without a comment: '
			. json_encode($items) . ' ' . substr($output, 0, 300));
		$this->assertEquals(array('d.multicall'), $this->daemon->calls(),
			'the feed comes from the one d.multicall');
	}

	public function testEveryTorrentIsReadFromItsOwnRowWithoutDescriptions()
	{
		list($items, $output) = $this->feed(false);
		$this->assertEquals($this->expected(), $items,
			'without item descriptions every torrent is still listed and dated from its own row: '
			. json_encode($items) . ' ' . substr($output, 0, 300));
	}
}
