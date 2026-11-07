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

    // hash, name, seedingtime, comment, and optionally the label and the
    // tracker message
    private $torrents = [
        ['1111111111111111111111111111111111111111', 'no comment', '1700000000', ''],
        ['2222222222222222222222222222222222222222', 'with comment', '1710000000', 'VRS24mrkera%20comment'],
        ['3333333333333333333333333333333333333333', 'not finished', '', ''],
    ];

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

    public function testPubDateIsTheSeedingTimeWhetherOrNotThereIsAComment()
    {
        list($items, $output) = $this->feed(true);
        $this->assertEquals(
            $this->expected(),
            $items,
            'each finished torrent is dated by its seeding time, with or without a comment: '
            . json_encode($items) . ' ' . substr($output, 0, 300),
        );
        $this->assertEquals(
            ['d.multicall'],
            $this->daemon->calls(),
            'the feed comes from the one d.multicall',
        );
    }

    public function testEveryTorrentIsReadFromItsOwnRowWithoutDescriptions()
    {
        list($items, $output) = $this->feed(false);
        $this->assertEquals(
            $this->expected(),
            $items,
            'without item descriptions every torrent is still listed and dated from its own row: '
            . json_encode($items) . ' ' . substr($output, 0, 300),
        );
    }

    public function testConfiguredPublicBaseReachesEveryFeedLink()
    {
        foreach ([null, '', 'https://env.test:9443/public&tools'] as $environment) {
            $base = $environment === null || $environment === ''
                ? 'https://config.test:8443/torrents/' : $environment . '/';
            list($items, $output, $status) = $this->feed(
                false,
                [
                    'HTTP_HOST' => 'internal.test:8080', 'HTTP_X_FORWARDED_HOST' => 'trap.test',
                    'PHP_SELF' => '/internal/plugins/feeds/action.php/path-info'],
                'https://config.test:8443/torrents',
                $environment,
            );
            $xml = @simplexml_load_string($output);
            $this->assertTrue($xml !== false, 'The public feed is well-formed XML');
            if ($xml === false) {
                throw new Exception('The public feed could not be parsed');
            }
            $this->assertEquals(200, $status, 'The configured public feed succeeds');
            $this->assertTrue((string) $xml->channel->link === $base, 'The channel uses the public application base');
            $this->assertTrue(
                (string) $xml->channel->image->url === $base . 'images/logo.png',
                'The image uses the public prefix, with XML-safe URL bytes',
            );
            $this->assertTrue((string) $xml->channel->image->link === $base, 'The image link uses the same public base');
            $this->assertEquals(3, count($xml->channel->item), 'All data links are present');
            foreach ($xml->channel->item as $item) {
                $this->assertTrue((string) $item->link === $base . 'plugins/data/action.php?hash='
                    . (string) $item->guid . '&no=0&readable=1', 'A data link uses the public base and its own hash');
            }
            $this->assertTrue(
                strpos($output, 'internal.test') === false && strpos($output, 'trap.test') === false,
                'Internal and forwarded hosts do not enter an explicitly configured public feed',
            );
        }
    }

    public function testDirectFeedBaseUsesScriptNameAndTreatsHttpsOffAsHttp()
    {
        list($items, $output) = $this->feed(false, ['HTTP_HOST' => 'public.test:8443', 'HTTPS' => 'off',
            'SCRIPT_NAME' => '/torrents/plugins/feeds/action.php',
            'PHP_SELF' => '/internal/plugins/feeds/action.php/path-info']);
        $xml = @simplexml_load_string($output);
        $this->assertTrue($xml !== false, 'The direct feed is well-formed XML');
        if ($xml === false) {
            throw new Exception('The direct feed could not be parsed');
        }
        $this->assertTrue(
            (string) $xml->channel->link === 'http://public.test:8443/torrents/',
            'The actual script path and HTTPS=off determine a direct installation base',
        );
    }

    public function testDirectFeedKeepsTheSchemeTheServerTerminated()
    {
        list($items, $output, $status) = $this->feed(false, ['HTTP_HOST' => 'public.test',
            'HTTPS' => 'on', 'HTTP_X_FORWARDED_PROTO' => 'http',
            'SCRIPT_NAME' => '/torrents/plugins/feeds/action.php']);
        $xml = @simplexml_load_string($output);
        $this->assertTrue($xml !== false, 'The feed of a secured request is well-formed XML');
        if ($xml === false) {
            throw new Exception('The feed could not be parsed');
        }
        $this->assertEquals(200, $status, 'A secured request produces a feed');
        $this->assertTrue(
            (string) $xml->channel->link === 'https://public.test/torrents/',
            'A request header does not turn the links of a secured feed into http',
        );
        $this->assertTrue(
            strpos($output, 'http://public.test') === false,
            'No link in the document is downgraded',
        );
    }

    public function testFeedBehindChainedProxiesUsesTheClientScheme()
    {
        list($items, $output, $status) = $this->feed(false, ['HTTP_HOST' => 'public.test',
            'HTTP_X_FORWARDED_PROTO' => 'https,http',
            'SCRIPT_NAME' => '/torrents/plugins/feeds/action.php']);
        $xml = @simplexml_load_string($output);
        $this->assertEquals(200, $status, 'A chain of proxies still produces a feed: ' . substr($output, 0, 200));
        $this->assertTrue($xml !== false, 'The feed behind a proxy chain is well-formed XML');
        if ($xml === false) {
            throw new Exception('The feed could not be parsed');
        }
        $this->assertTrue(
            (string) $xml->channel->link === 'https://public.test/torrents/',
            'The scheme the client used is the first entry of the forwarded header',
        );
    }

    /**
     * The channel title and description carry rtorrent's session name,
     * which is free text its owner sets in .rtorrent.rc.
     */
    public function testSessionNameCannotBreakTheChannelHeader()
    {
        $name = 'box</title><script>alert(1)</script> & "quoted" \'single\'';
        list($items, $output, $status) = $this->feed(false, [], null, null, $name);
        $document = new DOMDocument();
        $this->assertTrue(
            @$document->loadXML($output),
            'The feed of a session name with markup is well-formed XML: ' . substr($output, 0, 400),
        );
        if (!@$document->loadXML($output)) {
            throw new Exception('The feed could not be parsed');
        }
        $this->assertEquals(200, $status, 'The feed is still served');
        $channel = $document->getElementsByTagName('channel')->item(0);
        $title = $channel->getElementsByTagName('title')->item(0)->textContent;
        $description = $channel->getElementsByTagName('description')->item(0)->textContent;
        $this->assertEquals(
            $name . ': All torrents',
            $title,
            'The channel title reads back as the session name, as text: ' . $title,
        );
        $this->assertTrue(
            strpos($description, $name) !== false,
            'The channel description reads back the session name too: ' . $description,
        );
        $this->assertEquals(
            0,
            $document->getElementsByTagName('script')->length,
            'The session name produces no element of its own',
        );
    }

    /**
     * rTorrent sends a backslash or a double quote in a name or a label as it
     * is, and the feed prints both as rTorrent has them, once: a backslash
     * already in front of a quote stays. A label the interface set is
     * URL-encoded and is decoded only after that, so %5C%22 in it is still a
     * backslash and a quote.
     */
    public function testANameOrALabelWithABackslashOrAQuoteReadsBackAsItIs()
    {
        $torrents = $this->torrents;
        $this->torrents = [
            ['4444444444444444444444444444444444444444', 'say "hi" \\ to C:\\dir\\', '1700000000', '', 'tv \\"uhd\\" \\ x'],
            ['5555555555555555555555555555555555555555', 'ends with \\"', '1710000000', '', 'a%5C%22b'],
        ];
        try {
            list(, $output) = $this->feed(false);
        } finally {
            $this->torrents = $torrents;
        }
        $xml = @simplexml_load_string($output);
        $this->assertTrue($xml !== false, 'the feed is well-formed XML: ' . substr($output, 0, 300));
        if ($xml === false) {
            throw new Exception('The feed could not be parsed');
        }
        $read = [];
        foreach ($xml->channel->item as $item) {
            $read[(string) $item->guid] = [(string) $item->title, (string) $item->category];
        }
        ksort($read);
        $this->assertEquals(
            [
                '4444444444444444444444444444444444444444' => ['say "hi" \\ to C:\\dir\\', 'tv \\"uhd\\" \\ x'],
                '5555555555555555555555555555555555555555' => ['ends with \\"', 'a\\"b'],
            ],
            $read,
            'each name and label reads back as rTorrent has it: ' . json_encode($read),
        );
    }

    /**
     * The settings probe reads the session name through rXMLRPCRequest, which
     * escapes a backslash or a double quote in it. The channel header prints
     * the name rTorrent reports.
     */
    public function testASessionNameWithABackslashOrAQuoteReadsBackAsItIs()
    {
        $name = 'box "q" \\ x';
        list(, $output, $status) = $this->feed(false, [], null, null, null, 'all', false, $name);
        $this->assertEquals(200, $status, 'the feed is served');
        $this->assertEquals(
            $name . ': All torrents',
            $this->channelTitle($output),
            'the channel title reads back the session name as rTorrent reports it',
        );
    }

    /**
     * With item descriptions on, a torrent's tracker message is shown in a
     * fieldset of its own, as rTorrent has it. A byte that is not UTF-8 shows
     * as U+FFFD rather than emptying the message, and a torrent without a
     * message has no such fieldset.
     */
    public function testATrackerMessageIsShownInTheDescriptionAsItIs()
    {
        $torrents = $this->torrents;
        $this->torrents = [
            ['6666666666666666666666666666666666666666', 'refused', '1700000000', '', '', 'Failure reason "unregistered" \\ x'],
            ['7777777777777777777777777777777777777777', 'no message', '1700000000', '', '', ''],
            ['8888888888888888888888888888888888888888', 'not UTF-8', '1700000000', '', '', "Failure reason \xff x"],
        ];
        try {
            list(, $output) = $this->feed(true);
        } finally {
            $this->torrents = $torrents;
        }
        $xml = @simplexml_load_string($output);
        $this->assertTrue($xml !== false, 'the feed is well-formed XML: ' . substr($output, 0, 300));
        if ($xml === false) {
            throw new Exception('The feed could not be parsed');
        }
        $descriptions = [];
        foreach ($xml->channel->item as $item) {
            $descriptions[(string) $item->guid] = (string) $item->description;
        }
        $shown = [
            '6666666666666666666666666666666666666666' => 'Failure reason &quot;unregistered&quot; \\ x',
            '8888888888888888888888888888888888888888' => "Failure reason \u{FFFD} x",
        ];
        foreach ($shown as $hash => $message) {
            $description = $descriptions[$hash] ?? '';
            $this->assertTrue(
                strpos($description, '<legend>Tracker Status</legend>' . $message . '</fieldset>') !== false,
                'the description shows the tracker message: ' . $description,
            );
        }
        $description = $descriptions['7777777777777777777777777777777777777777'] ?? '';
        $this->assertTrue(
            strpos($description, '</fieldset>') !== false && strpos($description, 'Tracker Status') === false,
            'a torrent without a tracker message has a description and no Tracker Status fieldset: ' . $description,
        );
    }

    /**
     * A torrent's name, label and comment may hold a byte that is not UTF-8.
     * Each is printed with that byte shown as U+FFFD, as the tracker message
     * is, rather than emptied: the item keeps its title and its category, and
     * the description its Comment fieldset.
     */
    public function testANameALabelOrACommentThatIsNotUtf8IsShownRatherThanEmptied()
    {
        $torrents = $this->torrents;
        $this->torrents = [
            ['9999999999999999999999999999999999999999', "name \xff x", '1700000000', 'VRS24mrkercomment %FF x', 'label %FF x'],
        ];
        try {
            list(, $output) = $this->feed(true);
        } finally {
            $this->torrents = $torrents;
        }
        $xml = @simplexml_load_string($output);
        $this->assertTrue($xml !== false, 'the feed is well-formed XML: ' . substr($output, 0, 300));
        if ($xml === false) {
            throw new Exception('The feed could not be parsed');
        }
        $item = $xml->channel->item[0];
        $this->assertEquals(
            ["name \u{FFFD} x", "label \u{FFFD} x"],
            [(string) $item->title, (string) $item->category],
            'the title and the category show the byte as U+FFFD: ' . substr($output, 0, 600),
        );
        $this->assertTrue(
            strpos((string) $item->description, "<legend>Comment</legend>comment \u{FFFD} x</fieldset>") !== false,
            'the description shows the comment: ' . $item->description,
        );
    }

    /**
     * mode arrives in the request and titles the channel out of the language
     * file, so an unknown key reads no string: the diagnostic that produces
     * is printed before the document the script is still building, and the
     * reader is handed bytes ahead of the XML declaration.
     */
    public function testAnUnknownModeStillProducesAWellFormedFeed()
    {
        foreach (['nosuchmode', '', '0', 'ALL', 'all\'; drop', '../../lang/en'] as $mode) {
            list($items, $output, $status) = $this->feed(false, [], null, null, 'fake', $mode, true);
            $this->assertEquals(200, $status, 'an unknown mode still serves a feed: ' . substr($output, 0, 200));
            $this->assertEquals(
                'fake: All torrents',
                $this->channelTitle($output),
                'a mode the feed does not serve is titled as the whole list',
            );
            $this->assertEquals(
                '',
                $this->viewAsked(),
                'and lists every torrent, which is what that title says',
            );
            $this->assertEquals($this->expected(), $items, 'the items are the feed\'s own');
        }
    }

    /**
     * A language file holds more than the six views. Reading a title out of
     * it by request names free text from a translation, and reaches entries
     * that are not a title at all.
     */
    public function testAModeNamingAnotherLanguageEntryDoesNotTitleTheFeed()
    {
        foreach (['Comment', 'Seeds', 'time_w', 'bytes'] as $mode) {
            list($items, $output, $status) = $this->feed(false, [], null, null, 'fake', $mode, true);
            $this->assertEquals(
                'fake: All torrents',
                $this->channelTitle($output),
                '"' . $mode . '" is an entry of the language file, not a view of this feed',
            );
        }
    }

    /** $_REQUEST carries whatever shape the query string spells. */
    public function testAModeThatIsNotAStringStillProducesAFeed()
    {
        list($items, $output, $status) = $this->feed(
            false,
            [],
            null,
            null,
            'fake',
            ['all'],
            true,
        );
        $this->assertEquals(200, $status, 'an array mode still serves a feed: ' . substr($output, 0, 200));
        $this->assertEquals(
            'fake: All torrents',
            $this->channelTitle($output),
            'and is titled as the whole list',
        );
    }

    public function testEachViewTheFeedServesKeepsItsOwnTitleAndView()
    {
        // Title, the view asked of rtorrent, and how many of the three rows
        // the daemon returns survive the filter this mode applies to them.
        // None of the fixtures carries a message or any rate, so error and
        // active keep none of them and inactive keeps all three.
        $views = [
            'all' => ['All torrents', '', 3],
            'inactive' => ['Inactive torrents', '', 3],
            'downloading' => ['Downloading torrents', 'incomplete', 3],
            'completed' => ['Completed torrents', 'complete', 3],
            'active' => ['Active torrents', 'active', 0],
            'error' => ['Error torrents', 'active', 0],
        ];
        foreach ($views as $mode => $expected) {
            list($items, $output, $status) = $this->feed(false, [], null, null, 'fake', $mode, true);
            $this->assertEquals(
                'fake: ' . $expected[0],
                $this->channelTitle($output),
                $mode . ' keeps its own title',
            );
            $this->assertEquals($expected[1], $this->viewAsked(), $mode . ' asks rtorrent for its own view, got '
                . var_export($this->viewAsked(), true));
            $this->assertEquals(
                $expected[2],
                count($items),
                $mode . ' keeps the rows it is about: ' . json_encode($items),
            );
        }
    }

    public function testInvalidPublicBaseRefusesBeforeReadingTheDaemon()
    {
        list($items, $output, $status) = $this->feed(false, [], 'https://user:secret@public.test/app/');
        $this->assertEquals(500, $status, 'Invalid explicit public configuration returns a server error');
        $this->assertTrue(
            strpos($output, 'Configure RU_PUBLIC_URL or publicURL') !== false,
            'The refusal tells the operator which configuration to fix',
        );
        $this->assertTrue(
            strpos($output, '<rss') === false && strpos($output, 'secret') === false,
            'The refusal neither emits a misleading feed nor exposes configured credentials',
        );
        $this->assertEquals([], $this->daemon->calls(), 'Invalid public configuration needs no daemon request');
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
        $label = isset($torrent[4]) ? $torrent[4] : '';
        $message = isset($torrent[5]) ? $torrent[5] : '';
        $row = [$hash, $name, $message, '0', '0', $label, $seedingtime];
        if ($descriptions) {
            $row = array_merge($row, [$comment, '4', '4', '65536', '65536', '0', '0',
                '0', '0', '16384', '0', '0', '0', '0']);
        }
        return $row;
    }

    /**
     * Runs action.php and returns [title => pubDate or null] for every item
     * in the feed it prints. $sessionName replaces the session name in the
     * settings; null keeps $probedName, the name the settings probe was told.
     */
    private function feed(
        $descriptions,
        $server = [],
        $public = null,
        $environment = null,
        $sessionName = 'fake',
        $mode = 'all',
        $reportErrors = false,
        $probedName = ''
    ) {
        $root = realpath(__DIR__ . '/../../..');
        $tree = $this->base . '/tree-' . ($descriptions ? 'on' : 'off');
        foreach (['php', 'conf', 'plugins/feeds'] as $dir) {
            $this->copyTree($root . '/' . $dir, $tree . '/' . $dir);
        }
        @unlink($tree . '/conf/config.local.php');
        if ($public !== null) {
            file_put_contents(
                $tree . '/conf/config.local.php',
                '<?php $publicURL = ' . var_export($public, true) . ';',
            );
        }
        if (!$descriptions) {
            file_put_contents(
                $tree . '/plugins/feeds/conf.local.php',
                "<?php\n\$showItemDescription = false;\n",
            );
        }

        $values = [];
        foreach ($this->torrents as $torrent) {
            $values = array_merge($values, $this->row($torrent, $descriptions));
        }
        if ($this->daemon !== null) {
            $this->daemon->stop();
        }
        // An install whose interface has been opened has a settings cache.
        // This script is driven against a profile of its own, so leave one
        // there rather than have it probe and spend the replies below.
        RtorrentProbeFixture::warmSettingsCache($this->base . '/profile', $probedName);
        $this->daemon = new FakeRtorrentDaemon([$values], $this->base . '/calls.log');

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
            . '$_REQUEST[\'mode\'] = ' . var_export($mode, true) . ";\n"
            . 'chdir(' . var_export($tree . '/plugins/feeds', true) . ");\n"
            . 'require_once(' . var_export($tree . '/php/util.php', true) . ");\n"
            . 'require_once(' . var_export($tree . '/php/settings.php', true) . ");\n"
            . '$settings = rTorrentSettings::get();' . "\n"
            . '$settings->linkExist = true;' . "\n"
            . '$settings->iVersion = 0x1018;' . "\n"
            . '$settings->version = $settings->libVersion = \'0.16.24\';' . "\n"
            . (($sessionName === null) ? '' : '$settings->server = ' . var_export($sessionName, true) . ";\n")
            . '$settings->registerPlugin(\'data\');' . "\n"
            . 'require(' . var_export($tree . '/plugins/feeds/action.php', true) . ");\n");

        // A diagnostic printed by the script lands ahead of the document it
        // is building, so a test about the document's shape has to be able to
        // see one rather than run with them switched off.
        $diagnostics = $reportErrors ? ' -d error_reporting=-1 -d display_errors=1 ' : ' -d error_reporting=0 ';
        $output = shell_exec(escapeshellarg(PHP_BINARY) . $diagnostics . escapeshellarg($driver));
        $items = [];
        preg_match_all('`<item>(.*)</item>`Us', (string) $output, $matches);
        foreach ($matches[1] as $item) {
            preg_match('`<title>(.*)</title>`U', $item, $title);
            $items[$title[1]] = preg_match('`<pubDate>(.*)</pubDate>`U', $item, $date) ? $date[1] : null;
        }
        ksort($items);
        return [$items, (string) $output, (int) file_get_contents($this->base . '/response-status')];
    }

    private function expected()
    {
        $items = [
            'no comment' => 'Tue, 14 Nov 2023 22:13:20 GMT',
            'not finished' => null,
            'with comment' => 'Sat, 09 Mar 2024 16:00:00 GMT',
        ];
        ksort($items);
        return $items;
    }

    /**
     * The view asked of rtorrent. d.multicall takes the target first and the
     * view second, so the view is the second string of the call.
     */
    private function viewAsked()
    {
        foreach ($this->daemon->bodies() as $body) {
            if (strpos($body, '<methodName>d.multicall</methodName>') === false) {
                continue;
            }
            preg_match_all('`<value><string>([^<]*)</string></value>`', $body, $strings);
            return isset($strings[1][1]) ? $strings[1][1] : null;
        }
        return null;
    }

    private function channelTitle($output)
    {
        // An empty body is the shape a script that died where it was reading
        // the title leaves behind, and loadXML() refuses it with a different
        // error than it gives malformed markup.
        $this->assertTrue($output !== '', 'the feed has a body at all');
        $document = new DOMDocument();
        $parsed = $output !== '' && @$document->loadXML($output);
        $this->assertTrue($parsed, 'the feed is well-formed XML: ' . substr($output, 0, 400));
        if (!$parsed) {
            throw new Exception('The feed could not be parsed');
        }
        return $document->getElementsByTagName('channel')->item(0)
            ->getElementsByTagName('title')->item(0)->textContent;
    }
}
