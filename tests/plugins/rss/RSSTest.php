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
}
