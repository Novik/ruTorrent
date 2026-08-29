<?php

/**
 * ZamundaNetEngine carries the url of the result page it is walking in
 * $this->search, and makeClient() puts it on every request as the Referer --
 * zamunda serves the listing only to a client that looks like it came from the
 * search form.
 *
 * The property was never declared, so it was created dynamically on first
 * assignment (deprecated since PHP 8.2, removed later) and read undefined
 * before action() ran, which is a warning and a null Referer.
 */

require_once(__DIR__ . '/../rutracker_check/TestLib.php');

class ZamundaFakeClient
{
    public $referer = null;
    public $url = null;
}

class commonEngine
{
    public $defaults = array('public' => true, 'page_size' => 100);
    public $categories = array('All' => '');

    public function makeClient($url)
    {
        $client = new ZamundaFakeClient();
        $client->url = $url;
        return $client;
    }
}

require_once(testFindRepoRoot() . '/plugins/extsearch/engines/ZamundaNet.php');

// Warnings and deprecations are failures here: both of the ones this engine
// used to raise print into the answer extsearch is assembling.
function zamundaStrict($callback)
{
    set_error_handler(function ($number, $message, $file, $line) {
        throw new RuntimeException("PHP diagnostic: {$message} ({$file}:{$line})");
    });
    try {
        return call_user_func($callback);
    } finally {
        restore_error_handler();
    }
}

$suite = new StrictTestSuite();

$suite->test('the referer the engine sends is a declared property', function () {
    strictAssertTrue(property_exists('ZamundaNetEngine', 'search'),
        'ZamundaNetEngine::$search must be declared, not created on assignment');
});

$suite->test('a client made before the first search raises no diagnostic', function () {
    // commonEngine::getTorrent() and the first fetch() of a search both call
    // makeClient(), and makeClient() reads $this->search.
    $engine = new ZamundaNetEngine();
    $client = zamundaStrict(function () use ($engine) {
        return $engine->makeClient('https://zamunda.net/bananas?view=list');
    });
    strictAssertSame('', $client->referer, 'with no search yet the referer is empty, not null');
});

$suite->test('the search url becomes the referer of the next request', function () {
    $engine = new ZamundaNetEngine();
    $search = 'https://zamunda.net/bananas?search=x&field=name&sort=9&type=desc&page=0';
    $client = zamundaStrict(function () use ($engine, $search) {
        $engine->search = $search;
        return $engine->makeClient('https://zamunda.net/bananas?view=list');
    });
    strictAssertSame($search, $client->referer, 'the referer must be the search page');
    strictAssertSame('https://zamunda.net/bananas?view=list', $client->url,
        'the url asked for must reach the parent unchanged');
});

exit($suite->run());
