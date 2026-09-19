<?php

declare(strict_types=1);

@define('HISTORY_MAX_COUNT', 100, true);
@define('HISTORY_MAX_TRY', 3, true);
@define('WAIT_AFTER_LOADING', 0, true);

require_once(__DIR__ . '/../../php/TestCase.php');

$minInterval = 2;	// in minutes

$feedsWithIncorrectTimes = array(
	"iptorrents.",
	"torrentday.",
);

$rss_debug_enabled = true;
require_once(__DIR__ . '/../../../plugins/rss/rss.php');

class SnoopyMock
{
	public $status = 200, $results = NULL, $headers = array();
}

final class RSSTest extends TestCase
{
	public function testAtom(): void
	{
		$exp_url = 'https://example.org/rss';
		$exp_etag = 'some etag';
		$exp_lastModified = 'some date';
		$rssFetchURL = function ($url, $cookies, $headers) use ($exp_url, $exp_etag, $exp_lastModified) {
			$this->assertEquals($exp_url, $url);
			$this->assertEquals(['key' => 'value', 'key2'=> 'value2'], $cookies);
			$this->assertEquals(['If-None-Match' => $exp_etag, 'If-Last-Modified' => $exp_lastModified], $headers);
			$cliMock = new SnoopyMock();
			$cliMock->results = file_get_contents(__DIR__ . '/atom-sample.xml');
			return $cliMock;
		};

		$rRSS = new rRSS($exp_url.':COOKIE:key=value;key2=value2', $rssFetchURL);
		$rRSS->etag = $exp_etag;
		$rRSS->lastModified = $exp_lastModified;
		$history = new rRSSHistory();
		$succ = $rRSS->fetch($history);
		$this->assertEquals(0, count($rRSS->lastErrorMsgs));
		$this->assertTrue($succ, 'fetch success');

		// check channel
		$this->assertEquals('Example Feed', $rRSS->channel['title']);
		$this->assertEquals(strtotime('2003-12-13T20:30:02Z'), $rRSS->channel['timestamp']);
		$this->assertEquals('https://example.org/', $rRSS->channel['link']);

		// check items
		$this->assertEquals(2, count($rRSS->items));
		$this->assertEquals(array(
			"timestamp" => strtotime('2003-12-13T18:30:02Z'),
			"title" => 'Title <1>',
			"link" => 'https://example.org/2003/12/13/atom03',
			"guid" => 'https://example.org/2003/12/13/atom03',
			"description" => 'Some text.',
		), $rRSS->items['https://example.org/2003/12/13/atom03']);
		$this->assertEquals(array(
			"timestamp" => strtotime('2003-12-13T19:30:02Z'),
			"title" => 'Title <2>',
			"link" => 'https://example.org/2003/12/13/atom04',
			"guid" => 'https://example.org/2003/12/13/atom04',
			"description" => 'Some other text.'."\n\n[Content]\n".'Some content',
		), $rRSS->items['https://example.org/2003/12/13/atom04']);

		// check contents
		$contents =  $rRSS->getContents("label", "1", "1", $history);
		$this->assertEquals(array(
			"time" => strtotime('2003-12-13T18:30:02Z'),
			"title" => 'Title <1>',
			"href" => 'https://example.org/2003/12/13/atom03',
			"guid" => 'https://example.org/2003/12/13/atom03',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][0]);
		$this->assertEquals(array(
			"time" => strtotime('2003-12-13T19:30:02Z'),
			"title" => 'Title <2>',
			"href" => 'https://example.org/2003/12/13/atom04',
			"guid" => 'https://example.org/2003/12/13/atom04',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][1]);
	}

	public function testRSS(): void
	{
		$exp_url = 'https://onerous.me/rss';
		$rssFetchURL = function ($url, $cookies, $headers) use ($exp_url) {
			$this->assertEquals($exp_url, $url);
			$this->assertEquals([], $cookies);
			$this->assertEquals([], $headers);
			$cliMock = new SnoopyMock();
			$cliMock->results = file_get_contents(__DIR__ . '/rss-sample-erroneous.xml');
			return $cliMock;
		};
		$rRSS = new rRSS($exp_url, $rssFetchURL);
		$history = new rRSSHistory();
		$succ = $rRSS->fetch($history);
		$this->assertEquals(1, count($rRSS->lastErrorMsgs));
		$this->assertTrue($succ);

		$this->assertEquals('Vexatious torrent channel©', $rRSS->channel['title']);
		$this->assertEquals(strtotime('Fri, 31 Dec 2021 12:00:00 +0000'), $rRSS->channel['timestamp']);
		$contents =  $rRSS->getContents("label", "1", "1", $history);
		$this->assertEquals(3, count($contents['items']));

		$this->assertEquals(array(
			"time" => strtotime('Sat, 1 Jan 2022 12:00:00 +0000'),
			"title" => 'The best title',
			"href" => 'https://onerous.me/path/to/torrent?guid=ABCD&torr',
			"guid" => 'https://onerous.me/path/to/torrent?guid=ABCD&perm',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][0]);
		$this->assertEquals(array(
			"time" => strtotime('Sat, 1 Jan 2022 13:00:00 +0000'),
			"title" => '<No Title>',
			"href" => 'https://onerous.me/no_tags_allowed/path/to/torrent?guid=ABCE',
			"guid" => 'https://onerous.me/no_tags_allowed/path/to/torrent?guid=ABCE',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][1]);
		$this->assertEquals(array(
			"time" => strtotime('Sat, 1 Jan 2022 14:00:00 +0000'),
			"title" => 'Wonders of <pubDate>Sat, 1 Jan 2022 14:30:00 +0000</pubDate> wow',
			"href" => 'https://onerous.me/path/to/torrent?guid=ABCF',
			"guid" => 'https://onerous.me/path/to/torrent?guid=ABCF',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][2]);
	}
	public function testRSS2(): void
	{
		$exp_url = 'https://example.jp/rss';
		$rssFetchURL = function ($url, $cookies, $headers) use ($exp_url) {
			$this->assertEquals($exp_url, $url);
			$this->assertEquals([], $cookies);
			$this->assertEquals([], $headers);
			$cliMock = new SnoopyMock();
			$cliMock->results = file_get_contents(__DIR__ . '/rss-jp-sample.xml');
			return $cliMock;
		};
		$rRSS = new rRSS($exp_url, $rssFetchURL);
		$history = new rRSSHistory();
		$succ = $rRSS->fetch($history);
		$this->assertEquals(0, count($rRSS->lastErrorMsgs));
		$this->assertTrue($succ);

		$this->assertEquals('アニメ 放送©', $rRSS->channel['title']);
		$this->assertEquals(strtotime('Fri, 31 Dec 2021 12:00:00 +0000'), $rRSS->channel['timestamp']);
		$contents =  $rRSS->getContents("label", "1", "1", $history);
		$this->assertEquals(1, count($contents['items']));

		$this->assertEquals(array(
			"time" => strtotime('Sat, 1 Jan 2022 12:00:00 +0000'),
			"title" => '完璧な映画',
			"href" => 'https://example.jp/path/to/torrent?guid=ABCD&torr',
			"guid" => 'https://example.jp/path/to/torrent?guid=ABCD&perm',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][0]);
	}

	public function testRSSWin1251(): void
	{
		// The fixture deliberately starts with a blank line before the XML
		// declaration: real-world feeds emit such padding, and it makes
		// libxml ignore the declared windows-1251 encoding and read the
		// bytes as if they were UTF-8, garbling every non-ASCII title.
		$exp_url = 'https://example.ru/rss';
		$rssFetchURL = function ($url, $cookies, $headers) use ($exp_url) {
			$this->assertEquals($exp_url, $url);
			$this->assertEquals([], $cookies);
			$this->assertEquals([], $headers);
			$cliMock = new SnoopyMock();
			$cliMock->results = file_get_contents(__DIR__ . '/rss-win1251-sample.xml');
			return $cliMock;
		};
		$rRSS = new rRSS($exp_url, $rssFetchURL);
		$history = new rRSSHistory();
		$succ = $rRSS->fetch($history);
		// libxml still reports the misplaced XML declaration, nothing else
		$this->assertEquals(1, count($rRSS->lastErrorMsgs));
		$this->assertTrue($succ);

		$this->assertEquals('Русский торрент канал', $rRSS->channel['title']);
		$this->assertEquals(strtotime('Fri, 31 Dec 2021 12:00:00 +0000'), $rRSS->channel['timestamp']);
		$contents =  $rRSS->getContents("label", "1", "1", $history);
		$this->assertEquals(1, count($contents['items']));

		$this->assertEquals(array(
			"time" => strtotime('Sat, 1 Jan 2022 12:00:00 +0000'),
			"title" => 'Новый фильм — четвёртый сезон',
			"href" => 'https://example.ru/path/to/torrent?torr=ABCD',
			"guid" => 'https://example.ru/path/to/torrent?perm=ABCD',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][0]);
	}

	public function testRSSUtf8(): void
	{
		// Same document as testRSSWin1251 (blank line before the XML
		// declaration included), but already encoded in UTF-8: it must pass
		// through unconverted and parse with unchanged titles.
		$exp_url = 'https://example.ru/rss-utf8';
		$rssFetchURL = function ($url, $cookies, $headers) use ($exp_url) {
			$this->assertEquals($exp_url, $url);
			$this->assertEquals([], $cookies);
			$this->assertEquals([], $headers);
			$cliMock = new SnoopyMock();
			$cliMock->results = file_get_contents(__DIR__ . '/rss-utf8-sample.xml');
			return $cliMock;
		};
		$rRSS = new rRSS($exp_url, $rssFetchURL);
		$history = new rRSSHistory();
		$succ = $rRSS->fetch($history);
		// libxml still reports the misplaced XML declaration, nothing else
		$this->assertEquals(1, count($rRSS->lastErrorMsgs));
		$this->assertTrue($succ);

		$this->assertEquals('Русский торрент канал', $rRSS->channel['title']);
		$this->assertEquals(strtotime('Fri, 31 Dec 2021 12:00:00 +0000'), $rRSS->channel['timestamp']);
		$contents =  $rRSS->getContents("label", "1", "1", $history);
		$this->assertEquals(1, count($contents['items']));

		$this->assertEquals(array(
			"time" => strtotime('Sat, 1 Jan 2022 12:00:00 +0000'),
			"title" => 'Новый фильм — четвёртый сезон',
			"href" => 'https://example.ru/path/to/torrent?torr=ABCD',
			"guid" => 'https://example.ru/path/to/torrent?perm=ABCD',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][0]);
	}

	public function testRSSMislabeledWin1251(): void
	{
		// Same document again (blank line before the XML declaration
		// included), but this time the declaration lies: it claims
		// windows-1251 while the bytes are already UTF-8, as misconfigured
		// aggregators commonly emit. The declared encoding must not trigger
		// a second cp1251-to-UTF-8 conversion, or every non-ASCII title
		// turns into mojibake.
		$exp_url = 'https://example.ru/rss-mislabeled';
		$rssFetchURL = function ($url, $cookies, $headers) use ($exp_url) {
			$this->assertEquals($exp_url, $url);
			$this->assertEquals([], $cookies);
			$this->assertEquals([], $headers);
			$cliMock = new SnoopyMock();
			$cliMock->results = file_get_contents(__DIR__ . '/rss-mislabeled-win1251-sample.xml');
			return $cliMock;
		};
		$rRSS = new rRSS($exp_url, $rssFetchURL);
		$history = new rRSSHistory();
		$succ = $rRSS->fetch($history);
		// libxml still reports the misplaced XML declaration, nothing else
		$this->assertEquals(1, count($rRSS->lastErrorMsgs));
		$this->assertTrue($succ);

		$this->assertEquals('Русский торрент канал', $rRSS->channel['title']);
		$this->assertEquals(strtotime('Fri, 31 Dec 2021 12:00:00 +0000'), $rRSS->channel['timestamp']);
		$contents =  $rRSS->getContents("label", "1", "1", $history);
		$this->assertEquals(1, count($contents['items']));

		$this->assertEquals(array(
			"time" => strtotime('Sat, 1 Jan 2022 12:00:00 +0000'),
			"title" => 'Новый фильм — четвёртый сезон',
			"href" => 'https://example.ru/path/to/torrent?torr=ABCD',
			"guid" => 'https://example.ru/path/to/torrent?perm=ABCD',
			"errcount" => 0,
			"hash" => ""
		), $contents['items'][0]);
	}

	// An item's link and guid are handed to window.open() by
	// plugins/rss/init.js, so anything the feed puts there that is not an
	// http(s) address has to be dropped while the feed is parsed.
	private function feedItems(string $xml): array
	{
		$rRSS = new rRSS('https://example.org/rss', function () use ($xml) {
			$cliMock = new SnoopyMock();
			$cliMock->results = $xml;
			return $cliMock;
		});
		$this->assertTrue($rRSS->fetch(new rRSSHistory()), 'fetch success');
		return $rRSS->items;
	}

	public function testRSSItemsWithoutAnHttpLinkAreDropped(): void
	{
		$items = $this->feedItems(
			'<?xml version="1.0"?><rss version="2.0"><channel>'.
			'<title>C</title><link>https://example.org/</link>'.
			'<item><title>good</title><link>https://example.org/ok</link></item>'.
			'<item><title>js</title><link>javascript:window.x=1</link>'.
				'<guid>javascript:window.x=1</guid></item>'.
			'<item><title>data</title><link>data:text/html,&lt;b&gt;x&lt;/b&gt;</link></item>'.
			'<item><title>file</title><link>file:///etc/passwd</link></item>'.
			'<item><title>text</title><link>not a url at all</link></item>'.
			'</channel></rss>');
		$this->assertEquals(array('https://example.org/ok'), array_keys($items));
	}

	public function testRSSItemFallsBackToThePermalinkWhenOnlyItIsALink(): void
	{
		$items = $this->feedItems(
			'<?xml version="1.0"?><rss version="2.0"><channel>'.
			'<title>C</title><link>https://example.org/</link>'.
			'<item><title>t</title><link>javascript:window.x=1</link>'.
				'<guid>https://example.org/perma</guid></item>'.
			'</channel></rss>');
		$this->assertEquals(array('https://example.org/perma'), array_keys($items));
		$this->assertEquals('https://example.org/perma', $items['https://example.org/perma']['guid']);
	}

	public function testAtomEntriesWithoutAnHttpLinkAreDropped(): void
	{
		$items = $this->feedItems(
			'<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom">'.
			'<title>C</title><link href="https://example.org/"/><updated>2003-12-13T20:30:02Z</updated>'.
			'<entry><title>good</title><link href="https://example.org/ok"/>'.
				'<updated>2003-12-13T18:30:02Z</updated></entry>'.
			'<entry><title>js</title><link href="javascript:window.x=1"/>'.
				'<updated>2003-12-13T18:30:02Z</updated></entry>'.
			'<entry><title>data</title><link href="data:text/html,x"/>'.
				'<updated>2003-12-13T18:30:02Z</updated></entry>'.
			'</feed>');
		$this->assertEquals(array('https://example.org/ok'), array_keys($items));
	}

	// An item link is handed to openExternalURL() by plugins/rss/init.js, so
	// what a feed may carry is what isExternalURL() in js/common.js will open.
	// Every address here is one a real indexer publishes, and dropping one
	// loses the download with nothing shown to say so.
	public static function openableLinks(): array
	{
		return array(
			'magnet' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
			'ftp' => 'ftp://ftp.example.org/pub/a.torrent',
			'ftps' => 'ftps://ftp.example.org/pub/b.torrent',
			'userinfo' => 'https://user:passkey@tracker.example.org/dl/c.torrent',
			'scheme relative' => '//tracker.example.org/dl/d.torrent',
			'underscore in host' => 'http://my_tracker.example.org/dl/e.torrent',
			'ipv6 literal host' => 'http://[2001:db8::1]/dl/f.torrent',
			'query and no path' => 'https://tracker.example.org?id=8',
			'trailing dot host' => 'https://tracker.example.org./dl/g.torrent',
		);
	}

	// The other half of the same rule: an address the browser would refuse
	// must not reach it, whatever else the item holds.
	public static function refusedLinks(): array
	{
		return array(
			'javascript' => 'javascript:window.x=1',
			'data' => 'data:text/html,<b>x</b>',
			'file' => 'file:///etc/passwd',
			'mailto' => 'mailto:someone@example.org',
			'plain text' => 'not a url at all',
			'page relative' => '/rtorrent/plugins/rss/rss.php',
		);
	}

	private function rssFeed(string $link, string $guid): string
	{
		return('<?xml version="1.0"?><rss version="2.0"><channel>'.
			'<title>C</title><link>https://example.org/</link>'.
			'<item><title>t</title><link>'.htmlspecialchars($link).'</link>'.
			'<guid>'.htmlspecialchars($guid).'</guid></item>'.
			'</channel></rss>');
	}

	private function atomFeed(string $link): string
	{
		return('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom">'.
			'<title>C</title><link href="https://example.org/"/>'.
			'<updated>2003-12-13T20:30:02Z</updated>'.
			'<entry><title>t</title><link href="'.htmlspecialchars($link).'"/>'.
			'<updated>2003-12-13T18:30:02Z</updated></entry>'.
			'</feed>');
	}

	public function testRSSItemsKeepEveryAddressTheBrowserMayOpen(): void
	{
		foreach (self::openableLinks() as $what => $link) {
			$items = $this->feedItems($this->rssFeed($link, $link));
			$this->assertEquals(array($link), array_keys($items), $what);
			$this->assertEquals($link, $items[$link]['guid'], $what);
		}
	}

	public function testAtomEntriesKeepEveryAddressTheBrowserMayOpen(): void
	{
		foreach (self::openableLinks() as $what => $link) {
			$items = $this->feedItems($this->atomFeed($link));
			$this->assertEquals(array($link), array_keys($items), $what);
		}
	}

	public function testRSSItemsWithAnAddressTheBrowserRefusesAreDropped(): void
	{
		foreach (self::refusedLinks() as $what => $link) {
			$this->assertEquals(array(), array_keys($this->feedItems(
				$this->rssFeed($link, $link))), $what);
			$this->assertEquals(array(), array_keys($this->feedItems(
				$this->atomFeed($link))), $what);
		}
	}

	// A kept item must not carry a permalink the browser would refuse, so the
	// link stands in for it -- the same substitution an http(s) link gets.
	public function testRSSItemReplacesAPermalinkThatCouldNotBeOpened(): void
	{
		$link = 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567';
		$items = $this->feedItems($this->rssFeed($link, 'javascript:window.x=1'));
		$this->assertEquals(array($link), array_keys($items));
		$this->assertEquals($link, $items[$link]['guid']);
	}

	// A whole feed at once, the shape an indexer that has only magnet links
	// publishes: every item has to come through, not just the http one.
	public function testAFeedOfMixedAddressesKeepsEveryOpenableItem(): void
	{
		$xml = '<?xml version="1.0"?><rss version="2.0"><channel>'.
			'<title>C</title><link>https://example.org/</link>';
		$expected = array();
		foreach (array_merge(self::openableLinks(), self::refusedLinks()) as $link) {
			$xml .= '<item><title>t</title><link>'.htmlspecialchars($link).'</link>'.
				'<guid>'.htmlspecialchars($link).'</guid></item>';
		}
		foreach (self::openableLinks() as $link) {
			$expected[] = $link;
		}
		$this->assertEquals($expected, array_keys($this->feedItems($xml.'</channel></rss>')));
	}
}
