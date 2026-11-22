<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * rtorrent registers some commands under a second name that redirects to the
 * first (RtorrentCommandRedirectsFixture.php lists them, from the daemon's own
 * source). The two names are one command, so the proxy has to give them one
 * answer: a refused command refused under both, a directory setter confined
 * under both, and everything else forwarded under both.
 *
 * Below 0.16.9 rtorrent reads the untrusted header and ignores it, so a name
 * the proxy forwards is a name the daemon runs. A redirect the proxy does not
 * know is a way around every refusal it makes.
 *
 * php/methods-*.php is the other source of spellings; that one is
 * XMLRPCProxyLegacySpellingTest.php.
 */
class XMLRPCProxyDaemonRedirectTest extends TestCase
{
    public function testTheRedirectTableCoversBothDaemonSeries()
    {
        $versions = [];
        foreach ($this->redirects() as $row) {
            $versions[$row['rtorrent']] = ($versions[$row['rtorrent']] ?? 0) + 1;
        }
        ksort($versions);
        $this->assertTrue(
            ($versions['0.9.8'] ?? 0) > 50 && ($versions['0.16.28'] ?? 0) > 50,
            'the table lists the redirects of rtorrent 0.9.8 and 0.16.28 ('
            . json_encode($versions) . ')',
        );
    }

    /**
     * The same command gets the same answer whichever name it is called by,
     * in every position a command reaches rtorrent from: a call of its own, a
     * command parameter of a multicall, a system.multicall member, a command
     * inside one, a command argument of an evaluator and a load call's
     * command parameter -- with and without a directory boundary.
     */
    public function testARedirectGetsTheSameAnswerAsTheCommandItNames()
    {
        $differ = [];
        foreach ($this->redirects() as $row) {
            $alias = $this->verdicts($row['name']);
            $target = $this->verdicts($row['target']);
            if ($alias === $target) {
                continue;
            }
            $where = [];
            foreach ($alias as $shape => $verdict) {
                if ($verdict !== $target[$shape]) {
                    $where[] = $shape . ' ' . $verdict . ' vs ' . $target[$shape];
                }
            }
            $differ[] = $row['name'] . ' -> ' . $row['target'] . ' (rtorrent '
                . $row['rtorrent'] . ' ' . $row['at'] . '): ' . implode(', ', $where);
        }
        $this->assertTrue(
            count($differ) === 0,
            'every rtorrent redirect is judged as the command it names'
            . (count($differ) ? ', differ: ' . implode('; ', $differ) : ''),
        );
    }

    /**
     * The redirects that land on a refused command, named, so a table that
     * lost them is a failure rather than a smaller loop.
     */
    public function testTheOldNamesOfRefusedSettersAreRefused()
    {
        foreach (['directory', 'session', 'scgi_port', 'scgi_local'] as $name) {
            $this->assertTrue(
                XMLRPCProxy::refusedCommandName($name . '=/tmp') !== null,
                $name . ' names a refused command',
            );
            $decision = $this->decide($this->call($name, ['', '/tmp']));
            $this->assertTrue(
                $decision['action'] === 'reject',
                $name . ' is rejected at the raw door',
            );
        }
    }

    /**
     * d.directory.base.set is what rtorrent 0.16 registers, and
     * d.directory_base.set redirects to it there; 0.9.8 has only the second.
     */
    public function testBothSpellingsOfTheBaseDirectorySetterAreConfined()
    {
        $hash = str_repeat('A', 40);
        foreach (['d.directory_base.set', 'd.directory.base.set'] as $name) {
            $decision = $this->decideConfined($this->call($name, [$hash, '/var/www/html']));
            $this->assertTrue(
                $decision['action'] === 'reject',
                $name . ' outside the boundary is refused',
            );
            $decision = $this->decideConfined($this->call($name, [$hash, '/torrents1/x']));
            $this->assertTrue(
                $decision['action'] === 'send',
                $name . ' inside the boundary is forwarded',
            );
        }
    }

    /**
     * What the proxy does with $name in each position, as "reject" or "send",
     * and "+path" when an outside path it was handed reached the payload.
     */
    private function verdicts($name)
    {
        $hash = str_repeat('A', 40);
        $outside = '/var/www/html';
        $shapes = [
            'call' => [false, $this->call($name, ['x'])],
            'multicall parameter' => [false, $this->call('d.multicall2', ['', 'main', $name . '=x'])],
            'system.multicall member' => [false, $this->systemMulticall($name, ['x'])],
            'inside a system.multicall member' => [false, $this->systemMulticall(
                'd.multicall2',
                ['', 'main', $name . '=x'],
            )],
            'evaluator argument' => [false, $this->call('branch', ['', 'false=', $name . '=x'])],
            'load parameter' => [false, $this->call('load.start', ['', 'http://example.test/x.torrent', $name . '=x'])],
            'confined call' => [true, $this->call($name, [$hash, $outside])],
            'confined multicall parameter' => [true, $this->call('d.multicall2', ['', 'main', $name . '=' . $outside])],
            'confined system.multicall member' => [true, $this->systemMulticall($name, [$hash, $outside])],
            'confined load parameter' => [true, $this->call('load.start', ['', 'http://example.test/x.torrent', $name . '=' . $outside])],
        ];
        $out = [];
        foreach ($shapes as $shape => $spec) {
            list($confined, $request) = $spec;
            $decision = $confined ? $this->decideConfined($request) : $this->decide($request);
            $verdict = $decision['action'];
            if (isset($decision['payload']) && is_string($decision['payload'])
                && strpos($decision['payload'], $outside) !== false) {
                $verdict .= '+path';
            }
            $out[$shape] = $verdict;
        }
        return $out;
    }

    private function redirects()
    {
        return require(__DIR__ . '/RtorrentCommandRedirectsFixture.php');
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

    private function systemMulticall($method, $params)
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall'
            . '</methodName><params><param><value><array><data>'
            . '<value><struct><member><name>methodName</name><value><string>'
            . htmlspecialchars($method, ENT_NOQUOTES) . '</string></value></member>'
            . '<member><name>params</name><value><array><data>';
        foreach ($params as $p) {
            $xml .= '<value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
                . '</string></value>';
        }
        return $xml . '</data></array></value></member></struct></value>'
            . '</data></array></value></param></params></methodCall>';
    }

    private function decide($xml)
    {
        return XMLRPCProxy::decide(
            $xml,
            'sanitize',
            ['d.custom1.set', 'd.directory.set'],
            false,
            ['directory' => ['root' => '/', 'resolve' => null]],
        );
    }

    private function decideConfined($xml)
    {
        return XMLRPCProxy::decide(
            $xml,
            'sanitize',
            ['d.custom1.set', 'd.directory.set', 'd.directory_base.set'],
            false,
            ['directory' => ['root' => '/torrents1', 'resolve' => null]],
        );
    }
}
