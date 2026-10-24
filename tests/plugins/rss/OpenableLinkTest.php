<?php

declare(strict_types=1);

// plugins/rss/init.js hands an item link and permalink to openExternalURL(),
// which asks isExternalURL() in js/common.js whether the browser may open it.
// ExternalURL::isOpenable() in php/utility/externalurl.php has to answer the
// same question where an address is stored, and the two are written in
// different languages in different files, so nothing makes them agree by
// itself. plugins/lookat/lookat.php shares the same check, and
// tests/plugins/lookat/LookAtListTest.php asserts the same column through it.
//
// tests/fixtures/openable-addresses.json is where they are held together. It
// records, for every address, what each side says: 'client' is what
// isExternalURL() returns and is asserted by tests/js/openable-addresses.spec.js,
// 'server' is what ExternalURL::isOpenable() returns and is asserted here.
// Editing
// either side so it stops matching its column fails that side's suite.
//
// The rule that binds the two columns is asserted below: an address the parser
// keeps must be one the browser will open. The other direction is deliberately
// free. isExternalURL() opens an address with no scheme of its own, because
// window.open() resolves it against the panel's own page, and a feed must not
// be able to put a row in front of a user that navigates back into the panel.

@define('HISTORY_MAX_COUNT', 100, true);
@define('HISTORY_MAX_TRY', 3, true);
@define('WAIT_AFTER_LOADING', 0, true);

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');

$minInterval = 2;
$feedsWithIncorrectTimes = array();
$rss_debug_enabled = false;
require_once(__DIR__ . '/../../../plugins/rss/rss.php');

final class OpenableLinkTest extends TestCase
{
	private function addresses(): array
	{
		$path = __DIR__ . '/../../fixtures/openable-addresses.json';
		$rows = json_decode(file_get_contents($path), true);
		$this->assertTrue(is_array($rows) && count($rows) > 0,
			'read ' . count(is_array($rows) ? $rows : array()) . ' addresses from the shared table');
		return($rows);
	}

	// The whole table, one row at a time. A row that stops matching is a
	// change to what a feed may carry, and has to be argued for in the table.
	public function testEveryAddressInTheSharedTableGetsTheVerdictItRecords(): void
	{
		foreach ($this->addresses() as $row) {
			$this->assertTrue(ExternalURL::isOpenable($row['url']) === $row['server'],
				($row['server'] ? 'kept: ' : 'dropped: ') .
				json_encode($row['url']) . ' -- ' . $row['why']);
		}
	}

	// The rule the two columns exist to hold. An address kept here that the
	// browser refuses is a row in the feed that does nothing when it is
	// opened, and nothing anywhere says so.
	public function testNoAddressIsKeptThatTheBrowserWouldRefuseToOpen(): void
	{
		foreach ($this->addresses() as $row) {
			$this->assertTrue(!($row['server'] && !$row['client']),
				'not a dead row: ' . json_encode($row['url']) . ' -- ' . $row['why']);
		}
	}

	// A feed that carries one of these is a feed that works, so every one of
	// them has to survive the parser as well as the check in isolation.
	public function testTheFeedKeepsEveryItemTheTableSaysItShould(): void
	{
		$kept = array();
		$xml = '<?xml version="1.0"?><rss version="2.0"><channel>' .
			'<title>C</title><link>https://example.org/</link>';
		foreach ($this->addresses() as $row) {
			$xml .= '<item><title>t</title><pubDate>Tue, 02 Jan 2024 03:04:05 GMT</pubDate>' .
				'<link>' . htmlspecialchars($row['url']) . '</link></item>';
			if ($row['server']) {
				$kept[] = $row['url'];
			}
		}
		$this->assertEquals($kept, array_keys($this->feedItems($xml . '</channel></rss>')));
	}

	// With rawurldecode() disabled (PHP 7.4 returns NULL from it, PHP 8 throws),
	// a special host that needs decoding cannot be checked and is refused; every
	// other address keeps the verdict the table records.
	public function testAnUnavailableDecoderRefusesOnlyHostsThatNeedIt(): void
	{
		$rows = $this->addresses();
		$expected = array(
			// Only the host is decoded: a percent sign elsewhere needs nothing.
			'https://example.org/a%20b?q=%41' => true,
			'https://us%40er@example.org/x' => true,
			'https://ex%61mple.org/x' => false,
			'//%41.example.org/dl/x.torrent' => false,
			// An opaque FTPS host is never decoded.
			'ftps://host%20.org/x' => true,
		);
		$urls = array_merge(array_column($rows, 'url'), array_keys($expected));
		$run = PhpProcess::run(array_merge(PhpProcess::sameIni(), array(
			'-d', 'disable_functions=rawurldecode', '-d', 'display_errors=stderr', '-r',
			'require ' . var_export(__DIR__ . '/../../../php/utility/externalurl.php', true) . ';'
			. 'echo json_encode(array_map(array("ExternalURL", "isOpenable"), json_decode($argv[1], true)));',
			'--', json_encode($urls))));
		$this->assertTrue(!$run['timedOut'] && $run['code'] === 0, 'the child finishes: ' . $run['err']);
		// Startup diagnostics ("in Unknown on line 0") belong to the PHP build.
		$errors = implode("\n", preg_grep('/ in Unknown on line 0\s*$/', explode("\n", $run['err']), PREG_GREP_INVERT));
		$this->assertTrue(trim($errors) === '', 'the child reports nothing: ' . $run['err']);
		$verdicts = json_decode($run['out'], true);
		$this->assertTrue(is_array($verdicts) && count($verdicts) === count($urls), 'one verdict per address');
		foreach ($rows as $i => $row) {
			$verdict = $verdicts[$i] ?? null;
			if (strpos($row['url'], '%') === false) {
				$this->assertTrue($verdict === $row['server'],
					'nothing to decode, the recorded verdict: ' . json_encode($row['url']));
			} else {
				$this->assertTrue($verdict === false || $row['server'],
					'never kept when the table drops it: ' . json_encode($row['url']));
			}
		}
		foreach (array_keys($expected) as $i => $url) {
			$this->assertTrue(($verdicts[count($rows) + $i] ?? null) === $expected[$url],
				($expected[$url] ? 'kept: ' : 'refused: ') . json_encode($url));
		}
	}

	// The scheme list lives in js/common.js. Read it from there rather than
	// restating it, so a scheme added to the browser's allowlist and not to
	// the parser's fails here.
	public function testEverySchemeJsCommonJsOpensIsOneTheParserKeeps(): void
	{
		$js = file_get_contents(__DIR__ . '/../../../js/common.js');
		$fn = strstr($js, 'function isExternalURL(url)');
		$this->assertTrue($fn !== false, 'found isExternalURL() in js/common.js');
		$this->assertTrue(preg_match('~\[((?:\s*"[a-z0-9+.-]+:"\s*,?)+)\]~i',
			(string)$fn, $m) === 1, 'found the scheme list in isExternalURL()');
		preg_match_all('~"([a-z0-9+.-]+):"~i', $m[1], $found);
		$schemes = $found[1];
		$this->assertEquals(array('http', 'https', 'ftp', 'ftps', 'magnet'), $schemes,
			'the scheme list js/common.js holds');
		foreach ($schemes as $scheme) {
			$this->assertTrue(ExternalURL::isOpenable($scheme . '://host.example.org/x'),
				'the scheme js/common.js opens is one this keeps: ' . $scheme);
		}
		// And nothing outside it, however the address is spelled.
		foreach (array('javascript', 'data', 'file', 'mailto', 'vbscript', 'ws', 'blob') as $scheme) {
			$this->assertTrue(!ExternalURL::isOpenable($scheme . '://host.example.org/x'),
				'the scheme js/common.js refuses is one this drops: ' . $scheme);
			$this->assertTrue(!ExternalURL::isOpenable($scheme . ':body'),
				'and drops it with an opaque body too: ' . $scheme);
		}
	}

	private function feedItems(string $xml): array
	{
		$fetch = function ($url, $cookies, $headers) use ($xml) {
			$cli = new OpenableLinkSnoopyMock();
			$cli->results = $xml;
			return($cli);
		};
		$rss = new rRSS('https://example.org/rss', $fetch);
		$rss->fetch(new rRSSHistory());
		return($rss->items);
	}
}

class OpenableLinkSnoopyMock
{
	public $status = 200, $results = NULL, $headers = array();
}
