<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

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
	private function feed($descriptions, $server = array(), $public = null, $environment = null)
	{
		$root = realpath(__DIR__ . '/../../..');
		$tree = $this->base . '/tree-' . ($descriptions ? 'on' : 'off');
		foreach (array('php', 'conf', 'plugins/feeds') as $dir) {
			$this->copyTree($root . '/' . $dir, $tree . '/' . $dir);
		}
		@unlink($tree . '/conf/config.local.php');
		if ($public !== null) {
			file_put_contents($tree . '/conf/config.local.php',
				'<?php $publicURL = ' . var_export($public, true) . ';');
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
		// An install whose interface has been opened has a settings cache.
		// This script is driven against a profile of its own, so leave one
		// there rather than have it probe and spend the replies below.
		RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
		$this->daemon = new FakeRtorrentDaemon(array($values), $this->base . '/calls.log');

		$driver = $this->base . '/drive-feeds.php';
		file_put_contents($driver, "<?php\n"
			. 'ini_set(\'error_log\', ' . var_export($this->base . '/child-errors.log', true) . ");\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'putenv(' . var_export($environment === null ? 'RU_PUBLIC_URL' : 'RU_PUBLIC_URL=' . $environment, true) . ");\n"
			. 'require_once(' . var_export($tree . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1";' . "\n"
			. '$scgi_port = ' . $this->daemon->port() . ";\n"
			. '$rpcTimeOut = 10;' . "\n"
			. '$_SERVER[\'HTTP_HOST\'] = \'example.test\';' . "\n"
			. '$_SERVER[\'PHP_SELF\'] = \'/plugins/feeds/action.php\';' . "\n"
			. '$_SERVER[\'SCRIPT_NAME\'] = \'/plugins/feeds/action.php\';' . "\n"
			. '$_SERVER = array_merge($_SERVER, ' . var_export($server, true) . ");\n"
			. 'register_shutdown_function(function() { file_put_contents(' . var_export($this->base . '/response-status', true)
				. ', (string)(http_response_code() ?: 200)); });' . "\n"
			. '$_REQUEST[\'mode\'] = \'all\';' . "\n"
			. 'chdir(' . var_export($tree . '/plugins/feeds', true) . ");\n"
			. 'require_once(' . var_export($tree . '/php/util.php', true) . ");\n"
			. 'require_once(' . var_export($tree . '/php/settings.php', true) . ");\n"
			. '$settings = rTorrentSettings::get();' . "\n"
			. '$settings->linkExist = true;' . "\n"
			. '$settings->iVersion = 0x1018;' . "\n"
			. '$settings->version = $settings->libVersion = \'0.16.24\';' . "\n"
			. '$settings->server = \'fake\';' . "\n"
			. '$settings->registerPlugin(\'data\');' . "\n"
			. 'require(' . var_export($tree . '/plugins/feeds/action.php', true) . ");\n");

		$output = shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver));
		$items = array();
		preg_match_all('`<item>(.*)</item>`Us', (string)$output, $matches);
		foreach ($matches[1] as $item) {
			preg_match('`<title>(.*)</title>`U', $item, $title);
			$items[$title[1]] = preg_match('`<pubDate>(.*)</pubDate>`U', $item, $date) ? $date[1] : null;
		}
		ksort($items);
		return array($items, (string)$output, (int)file_get_contents($this->base . '/response-status'));
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

	public function testConfiguredPublicBaseReachesEveryFeedLink()
	{
		foreach (array(null, '', 'https://env.test:9443/public&tools') as $environment) {
			$base = $environment === null || $environment === ''
				? 'https://config.test:8443/torrents/' : $environment . '/';
			list($items, $output, $status) = $this->feed(false, array(
				'HTTP_HOST' => 'internal.test:8080', 'HTTP_X_FORWARDED_HOST' => 'trap.test',
				'PHP_SELF' => '/internal/plugins/feeds/action.php/path-info'),
				'https://config.test:8443/torrents', $environment);
			$xml = @simplexml_load_string($output);
			$this->assertTrue($xml !== false, 'The public feed is well-formed XML');
			if ($xml === false) {
				throw new Exception('The public feed could not be parsed');
			}
			$this->assertEquals(200, $status, 'The configured public feed succeeds');
			$this->assertTrue((string)$xml->channel->link === $base, 'The channel uses the public application base');
			$this->assertTrue((string)$xml->channel->image->url === $base . 'images/logo.png',
				'The image uses the public prefix, with XML-safe URL bytes');
			$this->assertTrue((string)$xml->channel->image->link === $base, 'The image link uses the same public base');
			$this->assertEquals(3, count($xml->channel->item), 'All data links are present');
			foreach ($xml->channel->item as $item) {
				$this->assertTrue((string)$item->link === $base . 'plugins/data/action.php?hash='
					. (string)$item->guid . '&no=0&readable=1', 'A data link uses the public base and its own hash');
			}
			$this->assertTrue(strpos($output, 'internal.test') === false && strpos($output, 'trap.test') === false,
				'Internal and forwarded hosts do not enter an explicitly configured public feed');
		}
	}

	public function testDirectFeedBaseUsesScriptNameAndTreatsHttpsOffAsHttp()
	{
		list($items, $output) = $this->feed(false, array('HTTP_HOST' => 'public.test:8443', 'HTTPS' => 'off',
			'SCRIPT_NAME' => '/torrents/plugins/feeds/action.php',
			'PHP_SELF' => '/internal/plugins/feeds/action.php/path-info'));
		$xml = @simplexml_load_string($output);
		$this->assertTrue($xml !== false, 'The direct feed is well-formed XML');
		if ($xml === false) {
			throw new Exception('The direct feed could not be parsed');
		}
		$this->assertTrue((string)$xml->channel->link === 'http://public.test:8443/torrents/',
			'The actual script path and HTTPS=off determine a direct installation base');
	}

	public function testInvalidPublicBaseRefusesBeforeReadingTheDaemon()
	{
		list($items, $output, $status) = $this->feed(false, array(), 'https://user:secret@public.test/app/');
		$this->assertEquals(500, $status, 'Invalid explicit public configuration returns a server error');
		$this->assertTrue(strpos($output, 'Configure RU_PUBLIC_URL or publicURL') !== false,
			'The refusal tells the operator which configuration to fix');
		$this->assertTrue(strpos($output, '<rss') === false && strpos($output, 'secret') === false,
			'The refusal neither emits a misleading feed nor exposes configured credentials');
		$this->assertEquals(array(), $this->daemon->calls(), 'Invalid public configuration needs no daemon request');
	}
}
