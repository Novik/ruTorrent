<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * The claim sanitize mode makes is that no caller can name a command in
 * $denyPrefixes through this filter. These are the carriers that claim did not
 * cover: a load.* spelling the method list did not hold, a parameter encoded
 * as base64 rather than written as text, a command nested inside another
 * command's arguments, and a call wrapped in a system.multicall.
 *
 * Each one ends with rtorrent receiving the caller's own bytes on a connection
 * marked untrusted, which is a refusal only from 0.16.10. env_check.php
 * supports 0.9.8 and the whole of 0.16.x, so on every supported version below
 * that the header is read and ignored and this list is the only refusal there
 * is.
 */
class XMLRPCProxyCommandPolicyTest extends TestCase
{
    private $safe = ['d.custom1.set', 'd.directory.set'];
    private $opts;

    // The Settings dialog.
    //
    // Without the httprpc plugin, js/rtorrent.js sends the Settings dialog's
    // reads and writes to theURLs.XMLRPCMountPoint as one system.multicall
    // each, so on an install that points /RPC2 at rpc2.php they arrive here,
    // and a batch with one refused member is refused whole. The member names
    // below are what the browser sent from the Settings dialog to rtorrent
    // 0.16.28 and 0.9.8, after js/content.js and the methods-*.php renames.

    private static $settingsReads = [
        '0.16.28' => [
            'dht.statistics', 'pieces.hash.on_completion', 'network.bind_address', 'dht.port',
            'directory.default', 'throttle.global_down.max_rate', 'cat', 'cat', 'cat',
            'network.http.cacert', 'network.http.capath', 'network.proxy.http',
            'network.local_address', 'throttle.max_downloads.div', 'throttle.max_downloads.global',
            'system.file.max_size', 'pieces.memory.max', 'network.max_open_files',
            'system.sockets.http.max_size', 'throttle.max_peers.normal', 'throttle.max_peers.seed',
            'throttle.max_uploads', 'throttle.max_uploads.global', 'throttle.min_peers.seed',
            'throttle.min_peers.normal', 'protocol.pex', 'cat', 'throttle.global_up.max_rate',
            'network.listen.port.random', 'network.listen.port.range', 'pieces.preload.min_size',
            'pieces.preload.min_rate', 'pieces.preload.type', 'network.proxy.global',
            'network.receive_buffer.size', 'pieces.sync.always_safe', 'network.scgi.dont_route',
            'network.send_buffer.size', 'session.path', 'session.use_lock', 'session.on_completion',
            'system.file.split_size', 'system.file.split_suffix', 'pieces.sync.timeout_safe',
            'pieces.sync.timeout', 'trackers.numwant', 'trackers.use_udp', 'throttle.max_uploads.div',
            'system.sockets.max_size',
        ],
        '0.9.8' => [
            'dht.statistics', 'pieces.hash.on_completion', 'network.bind_address', 'dht.port',
            'directory.default', 'throttle.global_down.max_rate', 'cat', 'cat', 'cat',
            'network.http.cacert', 'network.http.capath', 'network.http.proxy_address',
            'network.local_address', 'throttle.max_downloads.div', 'throttle.max_downloads.global',
            'system.file.max_size', 'pieces.memory.max', 'network.max_open_files',
            'network.http.max_open', 'throttle.max_peers.normal', 'throttle.max_peers.seed',
            'throttle.max_uploads', 'throttle.max_uploads.global', 'throttle.min_peers.seed',
            'throttle.min_peers.normal', 'protocol.pex', 'network.port_open',
            'throttle.global_up.max_rate', 'network.port_random', 'network.port_range',
            'pieces.preload.min_size', 'pieces.preload.min_rate', 'pieces.preload.type',
            'network.proxy_address', 'network.receive_buffer.size', 'pieces.sync.always_safe',
            'network.scgi.dont_route', 'network.send_buffer.size', 'session.path',
            'session.use_lock', 'session.on_completion', 'system.file.split_size',
            'system.file.split_suffix', 'pieces.sync.timeout_safe', 'pieces.sync.timeout',
            'trackers.numwant', 'trackers.use_udp', 'throttle.max_uploads.div',
            'network.max_open_sockets',
        ],
    ];

    // Every setter the dialog writes on either daemon, other than the two that
    // name where rtorrent keeps its session and where it writes downloads.
    private static $settingsWrites = [
        'pieces.hash.on_completion.set', 'network.bind_address.set', 'dht.port.set',
        'throttle.global_down.max_rate.set', 'network.http.cacert.set', 'network.http.capath.set',
        'network.proxy.http.set', 'network.http.proxy_address.set', 'network.local_address.set',
        'throttle.max_downloads.div.set', 'throttle.max_downloads.global.set',
        'system.file.max_size.set', 'pieces.memory.max.set', 'network.max_open_files.set',
        'network.http.max_open.set', 'throttle.max_peers.normal.set', 'throttle.max_peers.seed.set',
        'throttle.max_uploads.set', 'throttle.max_uploads.global.set',
        'throttle.min_peers.seed.set', 'throttle.min_peers.normal.set', 'protocol.pex.set',
        'network.port_open.set', 'throttle.global_up.max_rate.set',
        'network.listen.port.random.set', 'network.port_random.set',
        'network.listen.port.range.set', 'network.port_range.set', 'pieces.preload.min_size.set',
        'pieces.preload.min_rate.set', 'pieces.preload.type.set', 'network.proxy.global.set',
        'network.proxy_address.set', 'network.receive_buffer.size.set',
        'pieces.sync.always_safe.set', 'network.scgi.dont_route.set',
        'network.send_buffer.size.set', 'session.use_lock.set', 'session.on_completion.set',
        'system.file.split_size.set', 'system.file.split_suffix.set',
        'pieces.sync.timeout_safe.set', 'pieces.sync.timeout.set', 'trackers.numwant.set',
        'trackers.use_udp.set', 'throttle.max_uploads.div.set', 'dht.mode.set',
    ];

    public function setUp()
    {
        $this->opts = ['directory' => ['root' => '/', 'resolve' => null]];
    }

    // --- a load.* spelling the method list did not hold ---

    public function testLoadStartVerboseRebuildsLikeLoadStart()
    {
        // load.start_verbose takes the arguments load.start takes and differs
        // only in what it prints. Absent from the list it was not rebuilt, so
        // the command parameter travelled to rtorrent as written.
        $xml = $this->call(
            'load.start_verbose',
            ['', 'http://example.invalid/a.torrent', 'execute=/bin/id'],
        );
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'send',
            'load.start_verbose is still a call this filter forwards',
        );
        $this->assertTrue(
            strpos($d['payload'], 'execute') === false,
            'the execute parameter is not in what load.start_verbose forwards',
        );
    }

    public function testLoadVerboseIsHeldToTheSameUriRuleAsLoadNormal()
    {
        // load.verbose reads parameter 1 as a URI exactly as load.normal does,
        // and a value that is not a network URI is a path on rtorrent's own
        // filesystem, which becomes the download's tied file.
        $xml = $this->call('load.verbose', ['', '/etc/passwd']);
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'reject',
            'load.verbose from a local path is refused, as load.normal is',
        );
    }

    public function testEveryLoadSpellingRtorrentRegistersIsRebuilt()
    {
        // src/command_events.cc registers these eight, unchanged between 0.9.8
        // and current master. A spelling missing from the list is a spelling
        // whose command parameters are never looked at.
        $spellings = [
            'load.normal', 'load.start', 'load.verbose', 'load.start_verbose',
            'load.raw', 'load.raw_start', 'load.raw_verbose', 'load.raw_start_verbose',
        ];
        foreach ($spellings as $method) {
            $xml = $this->call($method, ['', 'http://example.invalid/a.torrent',
                'execute=/bin/id']);
            $d = $this->decide($xml);
            $this->assertTrue(
                ($d['action'] === 'reject')
                || (strpos($d['payload'], 'execute') === false),
                $method . ' does not carry an execute parameter to rtorrent',
            );
        }
    }

    // --- a parameter encoded as base64 rather than written as text ---

    public function testABase64CommandParameterIsJudgedDecoded()
    {
        // xmlrpc-c hands rtorrent the decoded bytes, so the command a base64
        // parameter names is the decoded one. Read as text it matches no
        // allowed command, is stripped, and the request goes on verbatim with
        // the command nobody looked at still in it.
        $xml = '<?xml version="1.0"?><methodCall><methodName>d.multicall</methodName>'
            . '<params>'
            . '<param><value><string></string></value></param>'
            . '<param><value><string>main</string></value></param>'
            . '<param><value><base64>' . base64_encode('execute=/bin/id')
            . '</base64></value></param>'
            . '</params></methodCall>';
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'reject',
            'a base64-encoded execute parameter is refused',
        );
        $this->assertTrue(
            isset($d['method']) && $d['method'] === 'execute',
            'the refusal names execute, the command the base64 decodes to',
        );
    }

    public function testABase64AllowedParameterStillGetsThrough()
    {
        // The decoding must not turn an allowed command into a refusal.
        $xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName>'
            . '<params>'
            . '<param><value><string></string></value></param>'
            . '<param><value><base64>' . base64_encode('d1:ae') . '</base64></value></param>'
            . '<param><value><base64>' . base64_encode('d.custom1.set=label')
            . '</base64></value></param>'
            . '</params></methodCall>';
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'send',
            'a base64-encoded allowed command parameter is forwarded',
        );
        $this->assertTrue(
            strpos($d['payload'], 'd.custom1.set="label"') !== false,
            'and it is rebuilt as the command it decodes to',
        );
    }

    // --- a command nested inside another command's arguments ---

    public function testACommandNestedInAMulticallArgumentIsRefused()
    {
        // d.multicall runs the commands it is given after the view, so a
        // command parameter that is itself a multicall carries commands of its
        // own. Reading only the name before the first '=' answers for the
        // outer call and for nothing it carries.
        $xml = $this->call(
            'd.multicall',
            ['', 'main', 'd.multicall=main,execute=/bin/id'],
        );
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'reject',
            'a multicall parameter carrying a nested execute is refused',
        );
        $this->assertTrue(
            isset($d['method']) && $d['method'] === 'execute',
            'the refusal names the nested execute',
        );
    }

    public function testADollarIntroducedCommandIsRefused()
    {
        // '$' is what makes rtorrent call a name rather than read it as text,
        // and it can stand anywhere. '$execute' has no '=' before it, so the
        // name read off the front of the string was "$execute", which begins
        // with no refused prefix.
        $xml = $this->call('d.multicall', ['', 'main', '$execute=/bin/id']);
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'reject',
            'a $-introduced execute is refused',
        );
        $this->assertTrue(
            isset($d['method']) && $d['method'] === 'execute',
            'the refusal names execute',
        );
    }

    // --- a call wrapped in a system.multicall ---

    public function testSystemMulticallMemberParametersAreJudged()
    {
        // The member's own name is d.multicall, which nothing refuses. Its
        // parameters are where a multicall carries its commands, and they were
        // never looked at.
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
            . '<params><param><value><array><data>'
            . '<value><struct>'
            . '<member><name>methodName</name><value><string>d.multicall</string></value></member>'
            . '<member><name>params</name><value><array><data>'
            . '<value><string></string></value>'
            . '<value><string>main</string></value>'
            . '<value><string>execute=/bin/id</string></value>'
            . '</data></array></value></member>'
            . '</struct></value>'
            . '</data></array></value></param></params></methodCall>';
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'reject',
            'a system.multicall member carrying execute is refused',
        );
        $this->assertTrue(
            isset($d['method']) && $d['method'] === 'execute',
            'the refusal names execute, not the wrapper',
        );
    }

    public function testSystemMulticallMemberNamesAreStillJudged()
    {
        // What the member-name check already covered has to keep working.
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
            . '<params><param><value><array><data>'
            . '<value><struct>'
            . '<member><name>methodName</name><value><string>system.shutdown</string></value></member>'
            . '<member><name>params</name><value><array><data></data></array></value></member>'
            . '</struct></value>'
            . '</data></array></value></param></params></methodCall>';
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'reject',
            'a system.multicall member named system.shutdown is refused',
        );
        $this->assertTrue(
            isset($d['method']) && $d['method'] === 'system.shutdown',
            'the refusal names system.shutdown',
        );
    }

    public function testANestedSystemMulticallIsRefused()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
            . '<params><param><value><array><data>'
            . '<value><struct>'
            . '<member><name>methodName</name><value><string>system.multicall</string></value></member>'
            . '<member><name>params</name><value><array><data></data></array></value></member>'
            . '</struct></value>'
            . '</data></array></value></param></params></methodCall>';
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'reject',
            'a system.multicall nested in a system.multicall is refused',
        );
    }

    // --- what must keep working ---

    public function testAnOrdinaryReadMulticallIsStillForwarded()
    {
        $xml = $this->call('d.multicall', ['', 'main', 'd.name=', 'd.get_custom=seedingtime']);
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'send',
            'an ordinary read multicall is still forwarded',
        );
    }

    public function testACustomFieldNamedAfterARefusedWordIsNotRefusedForIt()
    {
        // "scheduled" begins with the refused prefix "schedule", but it stands
        // where a command's argument stands, not where a command stands. What
        // follows '=' inside one element is argument text and is not a name.
        $xml = $this->call('d.multicall', ['', 'main', 'd.get_custom=scheduled']);
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'send',
            'a custom field named "scheduled" is not mistaken for schedule',
        );
    }

    public function testTheShippedTrackerColumnExpressionIsNotRefused()
    {
        // plugins/show_peers_like_wtorrent ships this, nested and $-introduced.
        $expression = 'cat="$t.multicall=d.get_hash=,t.get_scrape_complete=,cat={#}"';
        $xml = $this->call('d.multicall', ['', 'main', $expression]);
        $d = $this->decide($xml);

        $this->assertTrue(
            $d['action'] === 'send',
            'the shipped nested tracker-column expression is still forwarded',
        );
    }

    // --- the Settings dialog ---

    public function testTheSettingsDialogReadsAreForwardedOnBothDaemons()
    {
        foreach (self::$settingsReads as $version => $names) {
            $calls = [];
            foreach ($names as $name) {
                $calls[] = [$name, []];
            }
            $d = $this->decide($this->systemMulticall($calls));
            $this->assertTrue(
                $d['action'] === 'send',
                'the Settings dialog read batch for rtorrent ' . $version . ' is forwarded ('
                . implode(' | ', $d['log']) . ')',
            );
            // rtorrent refuses some of these to an untrusted caller, and one
            // refused member fails the dialog.
            $this->assertTrue(
                $d['trusted'] === true,
                'the Settings dialog read batch for rtorrent ' . $version . ' goes trusted ('
                . implode(' | ', $d['log']) . ')',
            );
        }
    }

    /**
     * The same reads for every daemon tier this tree has a rename table for,
     * derived the way settings.php layers the tables, so a setting added to
     * the dialog or a getter renamed for a later daemon fails here.
     */
    public function testEverySettingsReadTheAliasTablesProduceGoesTrusted()
    {
        $source = file_get_contents(__DIR__ . '/../../js/rtorrent.js');
        $this->assertTrue(
            preg_match('/stg:\s*\{\s*commands:\s*\[(.*?)\]/s', $source, $list) === 1,
            'the Settings dialog command list is found in js/rtorrent.js',
        );
        preg_match_all('/"([^"]+)"/', $list[1], $found);
        $this->assertTrue(count($found[1]) > 40, 'it names the dialog\'s settings (' . count($found[1]) . ')');

        $tiers = [
            '0.9.8' => ['methods-0.9.4.php'],
            '0.16.0' => ['methods-0.9.4.php', 'methods-0.10.2.php', 'methods-0.16.0.php'],
            '0.16.16' => ['methods-0.9.4.php', 'methods-0.10.2.php', 'methods-0.16.0.php',
                'methods-0.16.16.php'],
            '0.16.18' => ['methods-0.9.4.php', 'methods-0.10.2.php', 'methods-0.16.0.php',
                'methods-0.16.16.php', 'methods-0.16.18.php'],
        ];
        $untrusted = [];
        foreach ($tiers as $version => $files) {
            $aliases = [];
            foreach ($files as $file) {
                $aliases = array_merge($aliases, XMLRPCProxyCommandPolicyAliases::read($file));
            }
            foreach ($found[1] as $setting) {
                // js/rtorrent.js reads these three as cat from 0.9.0 on.
                $name = in_array($setting, ['hash_interval', 'hash_max_tries', 'hash_read_ahead'], true)
                    ? 'cat'
                    : (isset($aliases['get_' . $setting]) ? $aliases['get_' . $setting]['name'] : 'get_' . $setting);
                $d = $this->decide($this->call($name));
                if (($d['action'] !== 'send') || ($d['trusted'] !== true)) {
                    $untrusted[] = $version . ': ' . $setting . ' -> ' . $name;
                }
            }
        }
        $this->assertTrue(
            count($untrusted) === 0,
            'every read the dialog makes on every tier goes trusted'
            . (count($untrusted) ? ', not: ' . implode('; ', array_unique($untrusted)) : ''),
        );
    }

    public function testASettingsReadIsTrustedOnlyWithNoArguments()
    {
        $bare = $this->decide($this->call('network.listen.port.range'));
        $this->assertTrue($bare['trusted'] === true, 'a bare settings read goes trusted');
        $this->assertTrue(
            strpos($bare['payload'], '<params></params>') !== false,
            'and is re-emitted with no arguments',
        );

        foreach ([['network.listen.port.range', ['']], ['cat', ['$execute=/bin/id']],
            ['session.path', ['', 'x']]] as $call) {
            $d = $this->decide($this->call($call[0], $call[1]));
            $this->assertTrue(
                $d['trusted'] === false,
                $call[0] . ' with arguments is not vouched for as a settings read',
            );
        }

        foreach (['d.name', 'system.client_version', 'network.xmlrpc.size_limit',
            'network.listen.port.range.set', 'session.path.set'] as $name) {
            $d = $this->decide($this->call($name));
            $this->assertTrue(
                $d['trusted'] === false,
                $name . ' is not a settings read and is not sent trusted',
            );
        }

        // One member that is not a settings read leaves the whole batch untrusted.
        $d = $this->decide($this->systemMulticall([
            ['network.listen.port.range', []],
            ['throttle.global_down.max_rate.set', ['', '1']],
        ]));
        $this->assertTrue(
            ($d['action'] === 'send') && ($d['trusted'] === false),
            'a batch mixing a settings read with a write goes untrusted',
        );
    }

    public function testEverySettingsDialogWriteIsForwarded()
    {
        $refused = [];
        foreach (self::$settingsWrites as $name) {
            $d = $this->decide($this->systemMulticall([[$name, ['', '1']]]));
            if ($d['action'] !== 'send') {
                $refused[] = $name;
            }
        }
        $this->assertTrue(
            count($refused) === 0,
            'every setter the Settings dialog writes is forwarded'
            . (count($refused) ? ', refused: ' . implode(', ', $refused) : ''),
        );

        $calls = [];
        foreach (self::$settingsWrites as $name) {
            $calls[] = [$name, ['', '1']];
        }
        $d = $this->decide($this->systemMulticall($calls));
        $this->assertTrue(
            $d['action'] === 'send',
            'a save that changes all of them at once is forwarded',
        );
        // Forwarded, not vouched for: what rtorrent lets an untrusted caller
        // set stays its decision.
        $this->assertTrue(
            $d['trusted'] === false,
            'a save goes untrusted, for rtorrent to judge each write',
        );
    }

    public function testTheScgiRouteFlagIsForwardedUnderEverySpelling()
    {
        foreach (['network.scgi.dont_route', 'get_scgi_dont_route',
            'network.scgi.dont_route.set', 'set_scgi_dont_route'] as $name) {
            $this->assertTrue(
                XMLRPCProxy::refusedCommandName($name) === null,
                $name . ' names no refused command',
            );
            $this->assertTrue(
                $this->decide($this->call($name, ['', '1']))['action'] === 'send',
                $name . ' is forwarded as a call of its own',
            );
            $this->assertTrue(
                $this->decide($this->systemMulticall([[$name, ['', '1']]]))['action'] === 'send',
                $name . ' is forwarded as a system.multicall member',
            );
        }
    }

    public function testTheRestOfTheScgiFamilyIsStillRefused()
    {
        // network.scgi is refused for the commands that open the listener; the
        // route flag is the only member let through, by its exact name.
        foreach (['network.scgi.open_port', 'network.scgi.open_local',
            'network.scgi.open_systemd', 'network.scgi.use_gzip.set',
            'network.scgi.gzip.min_size.set', 'network.scgi.dont_route.set.x',
            'network.scgi.dont_routex', 'network.scgi.something_new'] as $name) {
            $this->assertTrue(
                $this->decide($this->call($name, ['', '0.0.0.0:5000']))['action'] === 'reject',
                $name . ' is refused as a call of its own',
            );
            $this->assertTrue(
                $this->decide($this->systemMulticall([
                    ['network.scgi.dont_route', []],
                    [$name, ['', '0.0.0.0:5000']],
                ]))['action'] === 'reject',
                $name . ' is refused beside the route flag in one batch',
            );
        }

        // A command string that starts with the route flag is still read to
        // its end, so what follows it is judged.
        foreach (['network.scgi.dont_route.set=1;network.scgi.open_port=0.0.0.0:5000',
            'network.scgi.dont_route=,$execute=/bin/id'] as $value) {
            $this->assertTrue(
                XMLRPCProxy::refusedCommandName($value) !== null,
                $value . ' still names a refused command',
            );
            $this->assertTrue(
                $this->decide($this->call('d.multicall', ['', 'main', $value]))['action'] === 'reject',
                $value . ' is refused inside a multicall',
            );
        }
    }

    public function testTheSettingsDialogStillCannotMoveTheSessionOrTheDefaultDirectory()
    {
        // The dialog offers both, and both stay refused: one names where
        // rtorrent keeps its state, the other where every download without a
        // directory of its own is written, outside any $topDirectory check.
        foreach (['session.path.set', 'set_session', 'directory.default.set', 'set_directory'] as $name) {
            $this->assertTrue(
                $this->decide($this->systemMulticall([[$name, ['', '/tmp']]]))['action'] === 'reject',
                $name . ' is still refused',
            );
        }
    }

    public function testADeploymentThatNamesTheRouteFlagStillRefusesIt()
    {
        // The exception is from a family entry. A deny list that names the
        // command itself is taken at its word.
        foreach (['network.scgi.dont_route', 'network.scgi.dont_route.set'] as $name) {
            $d = XMLRPCProxy::decide(
                $this->call($name, ['', '1']),
                'sanitize',
                $this->safe,
                false,
                $this->opts + ['deny' => [$name]],
            );
            $this->assertTrue($d['action'] === 'reject', $name . ' named exactly in the deny list is refused');
        }
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
            foreach ($call[1] as $i => $p) {
                $xml .= ($i === 0)
                    ? '<value><string>' . htmlspecialchars($p, ENT_NOQUOTES) . '</string></value>'
                    : '<value><i8>' . htmlspecialchars($p, ENT_NOQUOTES) . '</i8></value>';
            }
            $xml .= '</data></array></value></member></struct></value>';
        }
        return $xml . '</data></array></value></param></params></methodCall>';
    }

    private function call($method, $params = [])
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>' . $method
            . '</methodName><params>';
        foreach ($params as $p) {
            $xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
                . '</string></value></param>';
        }
        $xml .= '</params></methodCall>';
        return $xml;
    }
}

/**
 * One php/methods-*.php table, read the way settings.php reads it.
 */
class XMLRPCProxyCommandPolicyAliases
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
