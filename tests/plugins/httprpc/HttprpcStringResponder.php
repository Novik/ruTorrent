<?php

require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');

/**
 * Answers each request plugins/httprpc/action.php sends with the values a
 * test gave for its first method name, and the settings probe as
 * RtorrentProbeResponder does. The d.multicall that carries a t.multicall
 * command, which mode trkall sends without a hash, is answered from the key
 * "d.multicall/t.multicall".
 *
 * Serializable, so that FakeRtorrentDaemon can run it in a worker process.
 */
class HttprpcStringResponder extends RtorrentProbeResponder
{
    private $answers;

    public function __construct($hash, $answers)
    {
        parent::__construct($hash);
        $this->answers = $answers;
    }

    public function __invoke($body)
    {
        $names = RtorrentProbeFixture::methodNames($body);
        $key = (count($names) > 0) ? $names[0] : '';
        if (($key === 'd.multicall') && (strpos($body, 't.multicall') !== false)) {
            $key = 'd.multicall/t.multicall';
        }
        if (array_key_exists($key, $this->answers)) {
            return $this->answers[$key];
        }
        return parent::__invoke($body);
    }
}
