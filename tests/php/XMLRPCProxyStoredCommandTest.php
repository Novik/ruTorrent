<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * Commands whose argument rtorrent keeps and runs later, or that create
 * commands of their own, refused by name in sanitize mode.
 *
 * From the registrations in rtorrent v0.9.8 and v0.16.28: directory.watch.added
 * (both) and directory.watch.ready (0.16) store a command run for every file
 * that appears in a directory; view.filter, view.filter.temp, view.sort_new,
 * view.sort_current, view.event_added and view.event_removed store a command
 * per view; view.filter_on writes "view.filter_download=<view name>" into an
 * event key, with a view name the caller chose; group.insert and
 * group.insert_persistent_view define methods and a scheduled task;
 * lua.execute and lua.execute.str run code where rtorrent is built with Lua;
 * file.append (0.9.8) writes caller text to a caller path.
 *
 * rtorrent 0.16.28 refuses all of them to an untrusted caller, because none of
 * them is marked safe. Below 0.16.9 the untrusted header is read and ignored,
 * so this list is the only refusal there is.
 */
class XMLRPCProxyStoredCommandTest extends TestCase
{
    private $safe = ['d.custom1.set', 'd.directory.set'];
    private $opts;

    // Every command refused here, under every name a daemon or
    // php/methods-*.php gives it.
    private static $refused = [
        'directory.watch.added', 'directory.watch.ready',
        'view.filter', 'view_filter', 'view.filter.temp',
        'view.filter_on', 'view_filter_on',
        'view.sort_new', 'view_sort_new', 'view.sort_current', 'view_sort_current',
        'view.event_added', 'view.event_removed',
        'group.insert', 'group.insert_persistent_view',
        'lua.execute', 'lua.execute.str',
        'file.append',
    ];

    public function setUp()
    {
        $this->opts = ['directory' => ['root' => '/', 'resolve' => null]];
    }

    public function testEachIsRefusedAsACallOfItsOwn()
    {
        foreach (self::$refused as $name) {
            $d = $this->decide($this->call($name, ['', 'main', 'd.name=']));
            $this->assertTrue($d['action'] === 'reject', $name . ' is refused as a call of its own');
            $this->assertTrue($d['payload'] === '', $name . ' puts nothing on the wire');
            $this->assertTrue($d['method'] === $name, $name . ' is the command the refusal names');
        }
    }

    public function testEachIsRefusedAsASystemMulticallMember()
    {
        foreach (self::$refused as $name) {
            $d = $this->decide($this->systemMulticall([
                ['d.name', [str_repeat('A', 40)]],
                [$name, ['', 'main', 'd.name=']],
            ]));
            $this->assertTrue($d['action'] === 'reject', $name . ' is refused as a system.multicall member');
            $this->assertTrue($d['method'] === $name, $name . ' is named by the system.multicall refusal');
        }
    }

    public function testEachIsRefusedAsAMulticallCommandParameter()
    {
        $hash = str_repeat('A', 40);
        $carriers = [
            'd.multicall2' => ['', 'main'],
            'd.multicall' => ['', 'main'],
            'd.multicall.filtered' => ['', 'main'],
            't.multicall' => [$hash, ''],
            'f.multicall' => [$hash, ''],
            'p.multicall' => [$hash, ''],
        ];
        foreach (self::$refused as $name) {
            foreach ($carriers as $carrier => $head) {
                foreach ([$name . '=main,d.name=', '$' . $name . '=main', 'd.name=;' . $name . '=x'] as $value) {
                    $params = array_merge($head, ['d.name=', $value]);
                    $d = $this->decide($this->call($carrier, $params));
                    $this->assertTrue(
                        $d['action'] === 'reject',
                        $carrier . ' carrying ' . $value . ' is refused',
                    );
                    $d = $this->decide($this->systemMulticall([[$carrier, $params]]));
                    $this->assertTrue(
                        $d['action'] === 'reject',
                        'a system.multicall member ' . $carrier . ' carrying ' . $value . ' is refused',
                    );
                }
            }
        }
    }

    public function testEachIsRefusedAsAnEvaluatorArgument()
    {
        foreach (self::$refused as $name) {
            $d = $this->decide($this->call('branch', ['', 'false=', $name . '=main,x']));
            $this->assertTrue($d['action'] === 'reject', 'branch carrying ' . $name . ' is refused');
        }
    }

    public function testEachIsRefusedAsAnHttprpcCommandParameter()
    {
        // plugins/httprpc/action.php asks this of every cmd= it is given.
        foreach (self::$refused as $name) {
            $this->assertTrue(
                XMLRPCProxy::refusedCommandName($name . '=main,x') !== null,
                $name . ' is refused as an httprpc command parameter',
            );
        }
    }

    public function testReadersAndTogglesOfTheSameFamiliesAreForwarded()
    {
        // Same prefixes, different commands: these read a view or move a
        // download into one, and store nothing that runs later. The ratio
        // plugin's PHP, rss and rutracker_check use view.set_visible, and
        // ruTorrent's columns read d.views.
        $hash = str_repeat('A', 40);
        foreach ([
            ['view.list', []], ['view_list', []], ['view.size', ['', 'main']],
            ['view.set_visible', [$hash, 'rat_1']], ['view.set_not_visible', [$hash, 'rat_1']],
            ['view.sort', ['', 'main']], ['view.add', ['', 'rat_1']],
            ['d.views', [$hash]], ['d.views.push_back_unique', [$hash, 'rat_1']],
            ['directory.default', []], ['file.prioritize_toc', []],
            ['group.seeding.ratio.min', []],
        ] as $call) {
            $d = $this->decide($this->call($call[0], $call[1]));
            $this->assertTrue($d['action'] === 'send', $call[0] . ' is still forwarded');
        }
        $d = $this->decide($this->call('d.multicall2', ['', 'main', 'd.views=', 'd.views.has=rat_1']));
        $this->assertTrue($d['action'] === 'send', 'a multicall reading d.views is still forwarded');
    }

    /**
     * What the web UI itself sends: the list, tracker, file and peer requests
     * from js/rtorrent.js, under their names before and after the
     * php/methods-*.php renames, plus the actions and the plugin columns.
     */
    public function testTheWebUIRequestsAreStillForwarded()
    {
        $source = file_get_contents(__DIR__ . '/../../js/rtorrent.js');
        $lists = [];
        foreach (['trt', 'trk', 'fls', 'prs', 'ttl', 'prp'] as $key) {
            $this->assertTrue(
                preg_match('/\b' . $key . ':\s*\{\s*commands:\s*\[(.*?)\]/s', $source, $m) === 1,
                'the ' . $key . ' command list is found in js/rtorrent.js',
            );
            preg_match_all('/"([^"]+)"/', $m[1], $found);
            $lists[$key] = $found[1];
        }
        $aliases = [];
        foreach (['methods-0.9.4.php', 'methods-0.10.2.php', 'methods-0.16.0.php',
            'methods-0.16.16.php', 'methods-0.16.18.php'] as $file) {
            $aliases = array_merge($aliases, XMLRPCProxyStoredCommandAliases::read($file));
        }
        $renamed = function ($command) use ($aliases) {
            $name = rtrim($command, '=');
            $suffix = (substr($command, -1) === '=') ? '=' : '';
            return isset($aliases[$name]) ? $aliases[$name]['name'] . $suffix : $command;
        };

        $hash = str_repeat('A', 40);
        $columns = ['d.get_custom=seedingtime', 'd.get_custom=addtime', 'd.get_throttle_name=',
            'd.views=', 'cat="$t.multicall=d.get_hash=,t.get_scrape_complete=,cat={#}"'];
        $requests = [];
        foreach ([false, true] as $rename) {
            $map = function ($list) use ($rename, $renamed) {
                return $rename ? array_map($renamed, $list) : $list;
            };
            $requests[] = ['d.multicall2', array_merge(['', 'main'], $map($lists['trt']), $columns)];
            $requests[] = ['d.multicall', array_merge(['', 'main'], $map($lists['trt']))];
            $requests[] = ['t.multicall', array_merge([$hash, ''], $map($lists['trk']))];
            $requests[] = ['f.multicall', array_merge([$hash, ''], $map($lists['fls']))];
            $requests[] = ['p.multicall', array_merge([$hash, ''], $map($lists['prs']))];
            foreach ($map($lists['ttl']) as $name) {
                $requests[] = [$name, []];
            }
            foreach ($map($lists['prp']) as $name) {
                $requests[] = [$name, [$hash]];
            }
        }
        $requests[] = ['d.custom1.set', [$hash, 'my label']];
        $requests[] = ['d.start', [$hash]];
        $requests[] = ['d.stop', [$hash]];
        $requests[] = ['d.priority.set', [$hash, '2']];
        $requests[] = ['d.throttle_name.set', [$hash, 'thr_1']];
        $requests[] = ['f.priority.set', [$hash . ':f0', '2']];
        $requests[] = ['branch', [$hash, 'd.is_active=',
            'cat=$d.stop=,$d.close=,$d.connection_seed.set=seed,$d.open=,$d.start=']];
        $requests[] = ['load.start', ['', 'http://example.test/x.torrent',
            'd.custom1.set=label', 'd.directory.set=/torrents/x', 'view.set_visible=rat_1']];

        $refused = [];
        foreach ($requests as $request) {
            if ($this->decide($this->call($request[0], $request[1]))['action'] !== 'send') {
                $refused[] = $request[0];
            }
            if ($this->decide($this->systemMulticall([$request]))['action'] !== 'send') {
                $refused[] = 'system.multicall ' . $request[0];
            }
        }
        $this->assertTrue(
            count($refused) === 0,
            'every request the web UI makes is forwarded'
            . (count($refused) ? ', refused: ' . implode(', ', $refused) : ''),
        );
    }

    private function decide($xml)
    {
        return XMLRPCProxy::decide($xml, 'sanitize', $this->safe, false, $this->opts);
    }

    private function systemMulticall($calls)
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall'
            . '</methodName><params><param><value><array><data>';
        foreach ($calls as $call) {
            $xml .= '<value><struct><member><name>methodName</name><value><string>'
                . htmlspecialchars($call[0], ENT_NOQUOTES) . '</string></value></member>'
                . '<member><name>params</name><value><array><data>';
            foreach ($call[1] as $p) {
                $xml .= '<value><string>' . htmlspecialchars($p, ENT_NOQUOTES) . '</string></value>';
            }
            $xml .= '</data></array></value></member></struct></value>';
        }
        return $xml . '</data></array></value></param></params></methodCall>';
    }

    private function call($method, $params = [])
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>'
            . htmlspecialchars($method, ENT_NOQUOTES) . '</methodName><params>';
        foreach ($params as $p) {
            $xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
                . '</string></value></param>';
        }
        return $xml . '</params></methodCall>';
    }
}

/**
 * One php/methods-*.php table, read the way settings.php reads it.
 */
class XMLRPCProxyStoredCommandAliases
{
    public $aliases = [];

    public static function read($file)
    {
        $holder = new self();
        $holder->load(__DIR__ . '/../../php/' . $file);
        return $holder->aliases;
    }

    private function load($path)
    {
        require($path);
    }
}
