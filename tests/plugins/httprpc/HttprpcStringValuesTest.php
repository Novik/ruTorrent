<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/HttprpcStringResponder.php');

/**
 * Every string plugins/httprpc/action.php relays reaches the browser as
 * rTorrent reported it.
 *
 * rTorrent sends a double quote or a backslash in a value as it is. Without
 * this plugin the browser reads the XML-RPC answer itself and shows those
 * bytes; with it, the plugin reads the answer through rXMLRPCRequest, which
 * hands back each string with its backslashes doubled and a backslash before
 * each quote, and encodes what it was handed as json. A torrent named na"me
 * was listed as na\"me, a label C:\x as C:\\x.
 *
 * Each mode runs in a process of its own against FakeRtorrentDaemon, which
 * escapes values the way rTorrent does: &, < and > and nothing else. Numbers
 * come back as rTorrent sent them.
 */
class HttprpcStringValuesTest extends TestCase
{
    public const HASH = 'C0FFEE0123456789C0FFEE0123456789C0FFEE01';

    private $base;
    private $daemon;
    private $runs = 0;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-httprpc-strings-' . getmypid();
        $this->removeTree($this->base);
        @mkdir($this->base, 0700, true);
    }

    public function tearDown()
    {
        $this->stopDaemon();
        $this->removeTree($this->base);
    }

    public function testListShowsEveryStringColumnAsRtorrentHasIt()
    {
        // d.multicall columns: the hash, then 35 more, then one a plugin asked
        // for. name, custom1 (the label), base_path, message, custom2 (the
        // comment) and the plugin's column are strings.
        $strings = [5, 15, 26, 30, 31, 36];
        $values = [];
        $expected = [];
        $n = 0;
        foreach ($this->samples() as $sample) {
            $hash = sprintf('%040X', ++$n);
            $values[] = $hash;
            $row = [];
            for ($col = 1; $col <= 36; $col++) {
                $value = in_array($col, $strings, true) ? $sample : (1000 + $col);
                $values[] = $value;
                $row[] = (string) $value;
            }
            $expected[$hash] = $row;
        }
        $json = $this->post(
            'mode=list&cid=0&cmd=' . rawurlencode('d.get_custom=x-test'),
            ['d.multicall' => $values],
        );
        $this->assertSame($expected, $json['t'] ?? null, 'mode=list');
    }

    public function testFilesShowEachPathAsRtorrentHasIt()
    {
        // f.path, four numbers, and a column a plugin asked for.
        $values = [];
        $expected = [];
        foreach ($this->samples() as $sample) {
            $values = array_merge($values, [$sample, 3, 4, 65536, 1, $sample]);
            $expected[] = [$sample, '3', '4', '65536', '1', $sample];
        }
        $json = $this->post(
            'mode=fls&hash=' . self::HASH . '&cmd=' . rawurlencode('f.get_frozen_path='),
            ['f.multicall' => $values],
        );
        $this->assertSame($expected, $json, 'mode=fls');
    }

    public function testPeersShowEachStringAsRtorrentHasIt()
    {
        // p.id, p.address, p.client_version, eight numbers, p.id_html, three
        // numbers.
        $values = [];
        $expected = [];
        foreach ($this->samples() as $sample) {
            $row = ['0123456789ABCDEF', $sample, $sample, 1, 0, 0, 50, 7, 8, 9, 10, $sample, 12, 13, 6881];
            $values = array_merge($values, $row);
            $expected[] = array_map('strval', $row);
        }
        $json = $this->post('mode=prs&hash=' . self::HASH, ['p.multicall' => $values]);
        $this->assertSame($expected, $json, 'mode=prs');
    }

    public function testTrackersShowEachStringAsRtorrentHasIt()
    {
        // t.url, eight numbers, and t.id, which a plugin asked for.
        $values = [];
        $expected = [];
        foreach ($this->samples() as $sample) {
            $row = [$sample, 1, 1, 0, 5, 6, 7, 1800, 1700000000, $sample];
            $values = array_merge($values, $row);
            $expected[] = array_map('strval', $row);
        }
        $json = $this->post(
            'mode=trk&hash=' . self::HASH . '&cmd=' . rawurlencode('t.get_id='),
            ['t.multicall' => $values],
        );
        $this->assertSame($expected, $json, 'mode=trk');
    }

    public function testAllTrackersOfNamedTorrentsShowEachUrlAsRtorrentHasIt()
    {
        $values = [];
        $expected = [];
        foreach ($this->samples() as $sample) {
            $row = [$sample, 1, 1, 0, 5, 6, 7];
            $values = array_merge($values, $row);
            $expected[] = array_map('strval', $row);
        }
        $json = $this->post('mode=trkall&hash=' . self::HASH, ['t.multicall' => $values]);
        $this->assertSame([self::HASH => $expected], $json, 'mode=trkall with a hash');
    }

    public function testAllTrackersOfEveryTorrentShowEachUrlAsRtorrentHasIt()
    {
        // Without a hash every torrent's trackers come back as one string per
        // torrent, the fields of each tracker followed by '#'.
        $values = [];
        $expected = [];
        $n = 0;
        foreach ($this->samples() as $sample) {
            $hash = sprintf('%040X', ++$n);
            $values[] = $hash;
            $values[] = $sample . '#1#1#0#5#6#7#';
            $expected[$hash] = [[$sample, '1', '1', '0', '5', '6', '7']];
        }
        $json = $this->post('mode=trkall', ['d.multicall/t.multicall' => $values]);
        $this->assertSame($expected, $json, 'mode=trkall without a hash');
    }

    public function testSettingsShowEachStringAsRtorrentHasIt()
    {
        // dht.statistics of a daemon with DHT off, then the 48 settings; the
        // bind address, directory, http cacert, capath and proxy, ip, proxy
        // address, port range, session and split suffix are strings.
        $strings = [1, 3, 8, 9, 10, 11, 28, 32, 37, 41];
        foreach ($this->samples() as $label => $sample) {
            $values = ['0', 'disable', ''];
            $expected = [0];
            for ($i = 0; $i < 48; $i++) {
                $value = in_array($i, $strings, true) ? $sample : (100 + $i);
                $values[] = $value;
                $expected[] = (string) $value;
            }
            $json = $this->post('mode=stg', ['dht.statistics' => $values]);
            $this->assertSame($expected, $json, 'mode=stg, ' . $label);
        }
    }

    public function testPropertiesShowTheConnectionSeedAsRtorrentHasIt()
    {
        foreach ($this->samples() as $label => $sample) {
            $values = [1, 100, 40, 50, 25, 0, $sample];
            $json = $this->post('mode=prp&hash=' . self::HASH, ['d.peer_exchange' => $values]);
            $this->assertSame(array_map('strval', $values), $json, 'mode=prp, ' . $label);
        }
    }

    public function testSavePathIsTheBasePathAsRtorrentHasIt()
    {
        foreach ($this->samples() as $label => $sample) {
            $json = $this->post('mode=getsavepath&hash=' . self::HASH, ['d.open' => [0, '/data/' . $sample, 0]]);
            $this->assertSame(['0', '/data/' . $sample, '0'], $json, 'mode=getsavepath, ' . $label);
        }
    }

    public function testTotalsShowAPluginStringAsRtorrentHasIt()
    {
        foreach ($this->samples() as $label => $sample) {
            $json = $this->post(
                'mode=ttl&cmd=' . rawurlencode('session.name'),
                ['throttle.global_up.total' => [1024, 2048, 0, 0, $sample]],
            );
            $this->assertSame(['1024', '2048', '0', '0', $sample], $json, 'mode=ttl, ' . $label);
        }
    }

    /** The strings every field is tried with. */
    private function samples()
    {
        return [
            'quote' => 'na"me',
            'backslash' => 'C:\\dir\\',
            'apostrophe' => "it's",
            'backslash before a quote' => '\\"already\\"',
            'doubled backslash' => '\\\\server\\share',
            'unicode' => "\u{fc}n\u{ef}c\u{f8}d\u{e9} \u{65e5}\u{672c} \"q\" \\",
            'markup' => 'a&b<c>d &amp;',
            'tab' => "tab\there",
            'plain' => 'plain name',
        ];
    }

    /** Strict: assertEquals compares with ==, which takes "1e1" for "10". */
    private function assertSame($expected, $actual, $what)
    {
        $this->assertTrue(
            $expected === $actual,
            $what . ' relays every value as rTorrent sent it: expected '
                . json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . ', got ' . json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    private function stopDaemon()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
    }

    private function removeTree($path)
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (glob($path . '/*') as $entry) {
            is_dir($entry) ? $this->removeTree($entry) : @unlink($entry);
        }
        @rmdir($path);
    }

    /**
     * Runs plugins/httprpc/action.php with $body against a daemon answering
     * from $answers, and returns the json it printed, decoded.
     */
    private function post($body, $answers)
    {
        $root = realpath(__DIR__ . '/../../..');
        $this->stopDaemon();
        $run = $this->base . '/run' . (++$this->runs);
        @mkdir($run, 0700, true);
        RtorrentProbeFixture::warmSettingsCache($run . '/profile');
        $this->daemon = new FakeRtorrentDaemon(
            new HttprpcStringResponder(self::HASH, $answers),
            $run . '/calls.log',
            false,
            [__DIR__ . '/HttprpcStringResponder.php'],
        );

        $driver = $run . '/drive-httprpc.php';
        file_put_contents($driver, "<?php\n"
            . '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($run . '/profile', true) . ";\n"
            . '$_ENV[\'RU_SCGI_HOST\'] = "127.0.0.1";' . "\n"
            . '$_ENV[\'RU_SCGI_PORT\'] = ' . var_export((string) $this->daemon->port(), true) . ";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$rpcTimeOut = 10;' . "\n"
            . '$rpcLogCalls = false;' . "\n"
            . '$HTTP_RAW_POST_DATA = ' . var_export($body, true) . ";\n"
            . 'chdir(' . var_export($root . '/plugins/httprpc', true) . ");\n"
            . 'require(' . var_export($root . '/plugins/httprpc/action.php', true) . ");\n");

        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($driver) . ' 2>&1', $output);
        $out = implode("\n", $output);
        $json = json_decode($out, true);
        $this->assertTrue(
            is_array($json),
            $body . ' answers with json: ' . substr($out, 0, 300),
        );
        return $json;
    }
}
