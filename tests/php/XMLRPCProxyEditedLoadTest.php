<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * rtorrent 0.16.29 adds eight load commands, the *_edited forms of the eight
 * it already had. They take the same arguments as the command each is
 * spelled after: a target, the torrent (a URI or a file for load.edited,
 * load.start_edited and their _verbose forms, the bencoded bytes for the
 * load.raw ones), then commands to run against the new download. None of
 * them is allowed on an untrusted connection, so a call of one that this
 * filter does not rebuild is one rtorrent refuses.
 *
 * Each is held to exactly what its plain counterpart is held to: the same
 * call with the plain name gets the same decision, and the same payload but
 * for the name.
 */
class XMLRPCProxyEditedLoadTest extends TestCase
{
    private const PAIRS = [
        'load.edited' => 'load.normal',
        'load.start_edited' => 'load.start',
        'load.verbose_edited' => 'load.verbose',
        'load.start_verbose_edited' => 'load.start_verbose',
        'load.raw_edited' => 'load.raw',
        'load.raw_start_edited' => 'load.raw_start',
        'load.raw_verbose_edited' => 'load.raw_verbose',
        'load.raw_start_verbose_edited' => 'load.raw_start_verbose',
    ];

    private $safe = ['d.custom1.set', 'd.directory.set', 'd.directory_base.set'];
    private $opts = ['directory' => ['root' => '/home/user/torrents', 'resolve' => null]];

    public function testAnEditedLoadWithSafeAdditionsIsRebuiltAndSentTrusted()
    {
        foreach (array_keys(self::PAIRS) as $method) {
            $d = $this->decide($this->call($method, ['', 'http://example.invalid/a.torrent',
                'd.custom1.set=label', 'd.directory.set="/home/user/torrents/x"']));
            $this->assertTrue(
                ($d['action'] === 'send') && ($d['trusted'] === true),
                $method . ' is rebuilt and sent trusted: ' . json_encode($d),
            );
            $this->assertTrue(
                (strpos($d['payload'], '<methodName>' . $method . '</methodName>') !== false)
                    && (strpos($d['payload'], 'label') !== false),
                $method . ' keeps its name and its safe additions',
            );
        }
    }

    public function testAnEditedLoadIsDecidedExactlyAsItsPlainCounterpart()
    {
        $cases = [
            'safe' => ['', 'http://example.invalid/a.torrent', 'd.custom1.set=label'],
            'execute' => ['', 'http://example.invalid/a.torrent', 'execute=/bin/id'],
            'execute among safe' => ['', 'http://example.invalid/a.torrent', 'd.custom1.set=a',
                'execute.throw=sh,-c,id', 'd.custom1.set=b'],
            'chained' => ['', 'http://example.invalid/a.torrent', 'd.custom1.set=A;execute=id'],
            'nested' => ['', 'http://example.invalid/a.torrent', 'd.custom1.set=$execute.capture=id'],
            'method.insert' => ['', 'http://example.invalid/a.torrent', 'method.insert=x,simple,"execute=id"'],
            'unlisted' => ['', 'http://example.invalid/a.torrent', 'd.custom2.set=x'],
            'outside directory' => ['', 'http://example.invalid/a.torrent', 'd.directory.set=/etc'],
            'dotdot directory' => ['', 'http://example.invalid/a.torrent',
                'd.directory_base.set="/home/user/torrents/../../../etc"'],
            'local path' => ['', '/etc/passwd', 'd.custom1.set=label'],
            'file uri' => ['', 'file:///etc/passwd'],
            'magnet' => ['', 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567'],
            'raw bytes' => ['', 'd4:infod4:name1:xee', 'd.custom1.set=label'],
        ];
        foreach (self::PAIRS as $edited => $plain) {
            foreach ($cases as $label => $params) {
                $e = $this->decide($this->call($edited, $params));
                $p = $this->decide($this->call($plain, $params));
                $this->assertEquals(self::renamed($p, $plain, $plain), self::renamed($e, $edited, $plain), $edited . ' / ' . $label . ' is decided as ' . $plain . ' is');
            }
        }
    }

    public function testDangerousAdditionsNeverReachTheDaemon()
    {
        foreach (array_keys(self::PAIRS) as $method) {
            foreach (['execute=/bin/id', 'execute2={sh,-c,id}', 'system.shutdown=', 'import=/tmp/x.rc',
                'schedule2=a,0,0,"execute=id"'] as $addition) {
                $d = $this->decide($this->call($method, ['', 'http://example.invalid/a.torrent', $addition]));
                $this->assertTrue(
                    ($d['action'] === 'reject') || (strpos($d['payload'], strtok($addition, '=')) === false),
                    $method . ' does not carry ' . $addition . ': ' . json_encode($d),
                );
            }
        }
    }

    public function testTheUriFormsRefuseALocalPath()
    {
        foreach (['load.edited', 'load.start_edited', 'load.verbose_edited', 'load.start_verbose_edited'] as $method) {
            $d = $this->decide($this->call($method, ['', '/home/user/other/a.torrent']));
            $this->assertTrue($d['action'] === 'reject', $method . ' from a local path is refused');
        }
        foreach (['load.raw_edited', 'load.raw_start_edited', 'load.raw_verbose_edited', 'load.raw_start_verbose_edited'] as $method) {
            $d = $this->decide($this->call($method, ['', 'd4:infod4:name1:xee']));
            $this->assertTrue($d['action'] === 'send', $method . ' carries bytes, not a path, and is sent');
        }
    }

    public function testAsSystemMulticallMembersTheyAreHeldToTheSameRules()
    {
        foreach (self::PAIRS as $edited => $plain) {
            foreach ([
                [['', 'http://example.invalid/a.torrent', 'execute=/bin/id']],
                [['', 'http://example.invalid/a.torrent', 'd.directory.set=/etc']],
                [['', '/etc/passwd']],
                [['', 'http://example.invalid/a.torrent', 'd.custom1.set=label']],
            ] as $i => $calls) {
                $e = $this->decide($this->systemMulticall($edited, $calls[0]));
                $p = $this->decide($this->systemMulticall($plain, $calls[0]));
                $this->assertEquals(self::renamed($p, $plain, $plain), self::renamed($e, $edited, $plain), $edited . ' as a member, case ' . $i . ', is decided as ' . $plain . ' is');
            }
        }
    }

    /** A decision with the edited name written as the plain one, wherever it appears. */
    private static function renamed($decision, $edited, $plain)
    {
        return json_decode(str_replace($edited, $plain, json_encode($decision)), true);
    }

    private function decide($xml)
    {
        return XMLRPCProxy::decide($xml, 'sanitize', $this->safe, false, $this->opts);
    }

    private function call($method, $params)
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>' . $method . '</methodName><params>';
        foreach ($params as $p) {
            $xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES) . '</string></value></param>';
        }
        return $xml . '</params></methodCall>';
    }

    private function systemMulticall($method, $params)
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName><params><param>'
            . '<value><array><data><value><struct><member><name>methodName</name><value><string>'
            . $method . '</string></value></member><member><name>params</name><value><array><data>';
        foreach ($params as $p) {
            $xml .= '<value><string>' . htmlspecialchars($p, ENT_NOQUOTES) . '</string></value>';
        }
        return $xml . '</data></array></value></member></struct></value></data></array></value></param></params></methodCall>';
    }
}
