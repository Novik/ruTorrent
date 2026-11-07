<?php

/**
 * What rtorrent tells erasedata's collector about the downloads it holds.
 *
 * Before each list update.php asks which downloads rtorrent holds (d.multicall
 * of d.hash=, d.directory=, d.size_files= and d.size_bytes=), once a pass the
 * sizes of their files (d.multicall of d.hash= and a cat of an f.multicall of
 * f.size_bytes=, "size/size/.../"), then the paths of some of those files
 * (f.path of <hash>:f<index>). answer() answers the three from downloads given
 * as hash => [directory, [path => size]], the way rtorrent would:
 * tests/plugins/erasedata/UnconfirmedEraseTest.php checks the shapes against
 * answers captured from rtorrent 0.16.27 and 0.9.8.
 *
 * As a FakeRtorrentDaemon responder it answers those questions from one state
 * per question about the downloads -- any d.multicall of d.hash=, so also the
 * queue's in pending.php, and the one master's collector asks -- and any other
 * request, the writer's, with the next entry of a script, as a plain
 * FakeRtorrentDaemon would. A state
 * is ['holds' => downloads], optionally with 'sizes' or 'paths' set to what the
 * daemon answers those questions with instead, or 'gone' listing hashes erased
 * after it answered about the downloads, whose paths it then cannot find;
 * anything else is what it answers the question about the downloads with: a
 * list of values, FakeRtorrentDaemon::fault() or raw(). The last state stands
 * for every later question.
 */
class LoadedDownloads
{
    private $script;
    private $states;
    private $asked = 0;
    private $state = ['holds' => []];

    public function __construct($script, $states)
    {
        $this->script = $script;
        $this->states = $states;
    }

    public function __invoke($body, $methods = [])
    {
        $calls = self::calls($body);
        $question = self::question($calls);
        if ($question === null) {
            return array_shift($this->script);
        }
        if ($question === 'downloads') {
            $state = $this->states[min($this->asked++, count($this->states) - 1)];
            if (!isset($state['holds'])) {
                return $state;
            }
            $this->state = $state;
        }
        if (isset($this->state[$question])) {
            return $this->state[$question];
        }
        $holds = $this->state['holds'];
        if ($question === 'paths') {
            $holds = array_diff_key($holds, array_flip($this->state['gone'] ?? []));
        }
        $values = self::answer($holds, $calls);
        return ($values === null) ? FakeRtorrentDaemon::fault('Could not find info-hash.') : $values;
    }

    /**
     * The values rtorrent answers $calls with, each [method, [parameter...]],
     * from $holds; null for a download or a file it does not hold. Command
     * names come as getCmd() leaves them in the tests that stub it out
     * ("d.get_hash="), or as rtorrent knows them.
     */
    public static function answer($holds, $calls)
    {
        $values = [];
        foreach ($calls as $call) {
            list($method, $params) = $call;
            $method = str_replace('.get_', '.', $method);
            if (($method === 'd.multicall') || ($method === 'd.multicall2')) {
                foreach ($holds as $hash => $download) {
                    foreach ($params as $field) {
                        $field = str_replace('.get_', '.', $field);
                        if ($field === 'd.hash=') {
                            $values[] = $hash;
                        } elseif ($field === 'd.directory=') {
                            $values[] = $download[0];
                        } elseif ($field === 'd.size_files=') {
                            $values[] = count($download[1]);
                        } elseif ($field === 'd.size_bytes=') {
                            $values[] = array_sum($download[1]);
                        } elseif (strpos($field, 'f.size_bytes=') !== false) {
                            $values[] = implode('', array_map(function ($size) {
                                return $size . '/';
                            }, $download[1]));
                        }
                    }
                }
                continue;
            }
            // f.path takes <hash>:f<index>; a stubbed request leaves the hash
            // and the index apart.
            $target = (count($params) > 1) ? $params : explode(':f', $params[0]);
            $paths = isset($holds[$target[0]]) ? array_keys($holds[$target[0]][1]) : [];
            if (($method !== 'f.path') || !isset($paths[$target[1]])) {
                return null;
            }
            $values[] = (string) $paths[$target[1]];
        }
        return $values;
    }

    /** The calls of an XMLRPC request body, each [method, [parameter...]]. */
    public static function calls($body)
    {
        if (preg_match('|<methodName>(.*?)</methodName>\s*<params>(.*)</params>|s', $body, $single)
            && ($single[1] !== 'system.multicall')) {
            return [[$single[1], self::values($single[2])]];
        }
        preg_match_all(
            '|<name>methodName</name><value><string>(.*?)</string></value></member>'
            . '<member><name>params</name><value><array><data>(.*?)</data></array></value>|s',
            $body,
            $members,
            PREG_SET_ORDER,
        );
        $calls = [];
        foreach ($members as $member) {
            $calls[] = [$member[1], self::values($member[2])];
        }
        return $calls;
    }

    /** Which of the collector's questions $calls are, or null for another request. */
    private static function question($calls)
    {
        $methods = [];
        $params = [];
        foreach ($calls as $call) {
            $methods[] = str_replace('.get_', '.', $call[0]);
            $params = array_merge($params, array_map(function ($p) {
                return str_replace('.get_', '.', $p);
            }, $call[1]));
        }
        if (count(preg_grep('/f\.size_bytes=/', $params))) {
            return 'sizes';
        }
        if (in_array('d.hash=', $params, true)) {
            return 'downloads';
        }
        if (count($methods) && (array_unique($methods) === ['f.path'])) {
            return 'paths';
        }
        return null;
    }

    private static function values($xml)
    {
        preg_match_all('#<value><(string|i4|i8)>(.*?)</\1></value>#s', $xml, $m);
        return array_map(function ($value) {
            return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }, $m[2]);
    }
}
