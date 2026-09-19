<?php

declare(strict_types=1);

@define('HISTORY_MAX_COUNT', 100, true);
@define('HISTORY_MAX_TRY', 3, true);
@define('WAIT_AFTER_LOADING', 0, true);

require_once(__DIR__ . '/../../php/TestCase.php');

$minInterval = 2;	// in minutes
$feedsWithIncorrectTimes = array();
$rss_debug_enabled = true;
require_once(__DIR__ . '/../../../plugins/rss/rss.php');

class ErrorSnoopyMock
{
	public $status = 200, $results = NULL, $headers = array(), $error = '';
}

final class RSSErrorTest extends TestCase
{
	// An error entry is json the browser renders. It names a theUILang key and
	// carries the free text separately, so nothing in it has to be evaluated
	// to produce a message.
	public function testErrorEntryNamesAKeyAndKeepsDetailApart(): void
	{
		$list = new rRSSMetaList();
		$list->addError('cantFetchRSS', 'https://feed.example.org/rss', 'Status: 503');

		$errors = $list->formatErrors();
		$this->assertEquals(1, count($errors));
		$this->assertEquals('cantFetchRSS', $errors[0]['key']);
		$this->assertEquals('https://feed.example.org/rss', $errors[0]['prm']);
		$this->assertEquals('Status: 503', $errors[0]['detail']);
		$this->assertTrue(!array_key_exists('desc', $errors[0]), 'no javascript source in the entry');
	}

	public function testErrorEntryWithoutDetailCarriesNone(): void
	{
		$list = new rRSSMetaList();
		$list->addError('rssDontExist');

		$errors = $list->formatErrors();
		$this->assertEquals('rssDontExist', $errors[0]['key']);
		$this->assertEquals('', $errors[0]['prm']);
		$this->assertTrue(!array_key_exists('detail', $errors[0]), 'no detail was given');
		$this->assertTrue(!array_key_exists('desc', $errors[0]), 'no javascript source in the entry');
	}

	// Snoopy takes $status from the status token of the feed server's own
	// response status line, so a feed server chooses this text. It has to reach
	// the error list as text and nothing else.
	public function testHostileHttpStatusIsCarriedAsText(): void
	{
		$status = "500'+window.__rssErrorEval=1+'";
		$fetchURL = function ($url, $cookies, $headers) use ($status) {
			$cli = new ErrorSnoopyMock();
			$cli->status = $status;
			return $cli;
		};

		$rss = new rRSS('https://feed.example.org/rss', $fetchURL);
		$history = new rRSSHistory();
		$this->assertTrue(!$rss->fetch($history), 'a failing status is not a successful fetch');
		$this->assertEquals(1, count($rss->lastErrorMsgs));
		$this->assertEquals('[RSS-HTTP-Error] Status: ' . $status, $rss->lastErrorMsgs[0]);

		// And that is what an error entry carries: a detail, never a fragment
		// of javascript built around it.
		$list = new rRSSMetaList();
		$list->addError('cantFetchRSS', 'https://feed.example.org/rss', join('; ', $rss->lastErrorMsgs));
		$errors = $list->formatErrors();
		$this->assertEquals('[RSS-HTTP-Error] Status: ' . $status, $errors[0]['detail']);
		$this->assertTrue(!array_key_exists('desc', $errors[0]), 'no javascript source in the entry');
	}
}
