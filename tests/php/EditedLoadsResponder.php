<?php

require_once(__DIR__ . '/RtorrentProbeFixture.php');

/**
 * RtorrentProbeResponder, with an answer to system.listMethods: the given
 * names, or a fault when there are none. Every other request is answered as
 * the probe fixture answers it.
 *
 * A separate file because the daemon worker loads it before it unserializes
 * its reply plan, and a *Test.php file would be run there as a test.
 */
class EditedLoadsResponder extends RtorrentProbeResponder
{
    private $methods;

    public function __construct($methods, $directory = '/tmp', $home = '/home/rtorrent')
    {
        parent::__construct(str_repeat('A', 40), '', $directory, '', $home);
        $this->methods = $methods;
    }

    public function __invoke($body)
    {
        $names = RtorrentProbeFixture::methodNames($body);
        if ($names === ['system.listMethods']) {
            return ($this->methods === null)
                ? FakeRtorrentDaemon::fault("Method 'system.listMethods' not defined", -506)
                : $this->methods;
        }
        return parent::__invoke($body);
    }
}
