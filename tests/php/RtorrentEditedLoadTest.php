<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/EditedLoadsResponder.php');
require_once(__DIR__ . '/TestTree.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/** The settings object, minus the cache file obtain() ends by writing. */
class EditedLoadSettings extends rTorrentSettings
{
    public function store()
    {
        return(true);
    }

    /** The alias tables obtain() reads for a 0.16.20 daemon, read again. */
    public function readTables()
    {
        $this->aliases = [
            "d.set_peer_exchange" => [ "name" => "d.peer_exchange.set", "prm" => 0 ],
            "d.set_connection_seed" => [ "name" => "d.connection_seed.set", "prm" => 0 ],
        ];
        foreach (['0.9.4', '0.10.2', '0.16.0', '0.16.16', '0.16.18'] as $version) {
            require(__DIR__ . '/../../php/methods-' . $version . '.php');
        }
    }
}

/**
 * rtorrent 0.16.29 erases the libtorrent_resume progress of every torrent a
 * load.* command adds, unless it is added with one of the eight *_edited
 * commands that release introduced. A fast resume is that progress, written
 * by rTorrent::fastResume() from the files on disk, so on 0.16.29 the plain
 * load throws it away and the download is hash checked from the start.
 *
 * - obtain() asks the daemon whether it has the commands (system.listMethods),
 *   because a version number cannot say: a build can carry them under an
 *   older version, and naming one a daemon does not have fails the load.
 * - sendTorrent() uses the *_edited form of the load it would have sent, and
 *   only for a torrent whose resume data fastResume() has just written.
 * - Should the daemon turn out not to have the command after all (it was
 *   swapped for an older one since it was probed), the load is sent again
 *   under the plain name rather than lost.
 */
class RtorrentEditedLoadTest extends TestCase
{
    use TorrentSequenceFixtures;

    private const PLAIN = [
        'load.normal', 'load.start', 'load.verbose', 'load.start_verbose',
        'load.raw', 'load.raw_start', 'load.raw_verbose', 'load.raw_start_verbose',
    ];
    private const EDITED = [
        'load.edited', 'load.start_edited', 'load.verbose_edited', 'load.start_verbose_edited',
        'load.raw_edited', 'load.raw_start_edited', 'load.raw_verbose_edited',
        'load.raw_start_verbose_edited',
    ];

    private $previousSettings;
    private $previousGlobals;
    private $hadTopDirectory;
    private $previousTopDirectory;
    private $base;
    private $top;
    private $daemon;

    public function setUp()
    {
        $this->previousGlobals = GlobalSettingsFixture::scgi();
        $this->hadTopDirectory = array_key_exists('topDirectory', $GLOBALS);
        $this->previousTopDirectory = $GLOBALS['topDirectory'] ?? null;

        $this->base = sys_get_temp_dir() . '/rutorrent-edited-load-' . uniqid('', true);
        @mkdir($this->base . '/top', 0700, true);
        $this->base = realpath($this->base);
        $this->top = $this->base . '/top';
        file_put_contents($this->top . '/test.data', str_repeat('x', 1024));

        $this->previousSettings = self::swapSettings(null);
        $GLOBALS['topDirectory'] = $this->top . '/';
        $GLOBALS['scgi_host'] = '127.0.0.1';
        $GLOBALS['rpcTimeOut'] = 5;
        $GLOBALS['rpcTransferTimeOut'] = 5;
        unset($GLOBALS['rpcMaxResponseBytes']);
        $GLOBALS['rpcLogCalls'] = false;
        $GLOBALS['rpcLogFaults'] = false;
        $GLOBALS['log_file'] = $this->base . '/errors.log';
        $GLOBALS['tempDirectory'] = $this->base;
        $GLOBALS['tempDirectory_init_done'] = false;
    }

    public function tearDown()
    {
        try {
            self::swapSettings($this->previousSettings);
            $this->stopDaemon();
            TestTree::remove($this->base);
        } finally {
            $this->previousGlobals->restore();
            if ($this->hadTopDirectory) {
                $GLOBALS['topDirectory'] = $this->previousTopDirectory;
            } else {
                unset($GLOBALS['topDirectory']);
            }
        }
    }

    // ---- the probe ---------------------------------------------------------

    public function testADaemonListingTheEditedLoadsHasThem()
    {
        $settings = $this->probe(array_merge(self::PLAIN, self::EDITED, ['system.listMethods']));
        $this->assertTrue($settings->linkExist, 'the probe reached the daemon');
        $this->assertTrue($settings->editedLoads === true, 'and found the *_edited loads');
        $this->assertTrue(
            in_array('system.listMethods', $this->daemon->calls(), true),
            'by asking system.listMethods: ' . implode(', ', $this->daemon->calls()),
        );
    }

    public function testADaemonWithoutThemHasNone()
    {
        $settings = $this->probe(array_merge(self::PLAIN, ['system.listMethods']));
        $this->assertTrue($settings->linkExist && ($settings->editedLoads === false), 'a 0.16.28 listing has none');
    }

    public function testAListingWithOnlySomeOfThemCountsAsNone()
    {
        // sendTorrent() may need any of the four; a daemon that has three is
        // not one to send them to.
        $listing = array_merge(self::PLAIN, array_values(array_diff(self::EDITED, ['load.edited'])));
        $settings = $this->probe($listing);
        $this->assertTrue($settings->editedLoads === false, 'three of the four are not enough');
    }

    public function testAListingThatFailsLeavesThemOffAndTheProbeStanding()
    {
        $settings = $this->probe(null);
        $this->assertTrue($settings->editedLoads === false, 'no listing, no *_edited loads');
        $this->assertTrue(
            $settings->linkExist && ($settings->version === RtorrentProbeFixture::VERSION)
                && ($settings->directory === $this->top),
            'and the rest of the probe is still read',
        );
    }

    public function testProbingAgainFollowsTheDaemon()
    {
        $settings = $this->probe(array_merge(self::PLAIN, self::EDITED));
        $this->assertTrue($settings->editedLoads === true, 'first daemon has them');
        $settings = $this->probe(self::PLAIN, $settings);
        $this->assertTrue($settings->editedLoads === false, 'the daemon that replaced it does not');
    }

    // ---- sendTorrent() -----------------------------------------------------

    public function testAFastResumedStartIsLoadedEdited()
    {
        $this->settings(true);
        $load = $this->sendFast(true);
        $this->assertTrue($load['method'] === 'load.raw_start_edited', 'load.raw_start becomes load.raw_start_edited: ' . $load['method']);
        $this->assertTrue($load['target'] === '' && $load['resumed'], 'with the target and the resume data');
    }

    public function testAFastResumedStoppedLoadIsLoadedEdited()
    {
        $this->settings(true);
        $load = $this->sendFast(false);
        $this->assertTrue($load['method'] === 'load.raw_edited', 'load.raw becomes load.raw_edited: ' . $load['method']);
        $this->assertTrue($load['target'] === '' && $load['resumed'], 'with the target and the resume data');
    }

    public function testAFastResumedStartOverThePacketLimitIsLoadedEditedFromItsFile()
    {
        $this->settings(true);
        $load = $this->sendFast(true, true);
        $this->assertTrue($load['method'] === 'load.start_edited', 'load.start becomes load.start_edited: ' . $load['method']);
        $this->assertTrue($load['target'] === '' && $load['resumed'], 'the file it names holds the resume data');
    }

    public function testAFastResumedStoppedLoadOverThePacketLimitIsLoadedEditedFromItsFile()
    {
        $this->settings(true);
        $load = $this->sendFast(false, true);
        $this->assertTrue($load['method'] === 'load.edited', 'load.normal becomes load.edited: ' . $load['method']);
        $this->assertTrue($load['target'] === '' && $load['resumed'], 'the file it names holds the resume data');
    }

    public function testADaemonWithoutThemIsSentThePlainLoads()
    {
        $this->settings(false);
        $expected = [[true, false, 'load.raw_start'], [false, false, 'load.raw'],
            [true, true, 'load.start'], [false, true, 'load.normal']];
        foreach ($expected as $case) {
            $load = $this->sendFast($case[0], $case[1]);
            $this->assertTrue(
                ($load['method'] === $case[2]) && $load['resumed'] && ($load['target'] === ''),
                $case[2] . ' is sent as before, with the resume data: ' . $load['method'],
            );
        }
    }

    public function testAnAddWithoutFastResumeIsSentThePlainLoad()
    {
        $this->settings(true);
        $this->daemon([[0], [0]]);
        $torrent = new Torrent($this->announceOnlyTorrent());
        $hash = rTorrent::sendTorrent($torrent, true, false, $this->top, '', false, false);
        $load = $this->lastLoad();
        $this->assertTrue($hash === $torrent->hash_info(), 'the torrent is added');
        $this->assertTrue($load['method'] === 'load.raw_start' && !$load['resumed'], 'with the plain load: ' . $load['method']);
    }

    public function testAFastResumeThatFindsNoDataIsSentThePlainLoad()
    {
        $this->settings(true);
        rename($this->top . '/test.data', $this->top . '/aside');
        try {
            $this->daemon([[0], [0]]);
            $torrent = new Torrent($this->announceOnlyTorrent());
            rTorrent::sendTorrent($torrent, true, false, $this->top, '', false, true);
        } finally {
            // setUp() runs once for the whole class.
            rename($this->top . '/aside', $this->top . '/test.data');
        }
        $load = $this->lastLoad();
        $this->assertTrue($load['method'] === 'load.raw_start' && !$load['resumed'], 'nothing certified, nothing edited: ' . $load['method']);
    }

    public function testAReloadKeepingItsOwnResumeDataIsSentThePlainLoad()
    {
        // Resume data the torrent already carried was not written from the
        // files just now, so it is not vouched for.
        $this->settings(true);
        $this->daemon([[0, 0]]);
        $torrent = new Torrent($this->sessionTorrent());
        rTorrent::sendTorrent($torrent, true, false, $this->top, '', false, false, false);
        $load = $this->lastLoad();
        $this->assertTrue($load['method'] === 'load.raw_start' && $load['resumed'], 'sent as it came, plainly: ' . $load['method']);
    }

    public function testAFastResumedReloadIsLoadedEdited()
    {
        // plugins/datadir and plugins/edit: isNew false, the mkdir goes in
        // the same request as the load.
        $this->settings(true);
        $this->daemon([[0, 0]]);
        $torrent = new Torrent($this->sessionTorrent());
        $hash = rTorrent::sendTorrent($torrent, true, false, $this->top, '', false, true, false);
        $this->assertTrue($hash === $torrent->hash_info(), 'the reload is sent');
        $this->assertEquals(['execute', 'load.raw_start_edited'], $this->daemon->calls(), 'with the mkdir, edited: ' . json_encode($this->daemon->calls()));
    }

    public function testAnEditedLoadTheDaemonDoesNotKnowIsSentAgainPlain()
    {
        $this->settings(true);
        $this->daemon([[0], FakeRtorrentDaemon::fault("Method 'load.raw_start_edited' not defined", -506), [0]]);
        $torrent = new Torrent($this->announceOnlyTorrent());
        $hash = rTorrent::sendTorrent($torrent, true, false, $this->top, '', false, true);
        $this->assertTrue($hash === $torrent->hash_info(), 'the torrent is still added');
        $this->assertEquals(['execute', 'load.raw_start_edited', 'load.raw_start'], $this->daemon->calls(), 'by the plain load: ' . json_encode($this->daemon->calls()));
        $load = $this->lastLoad();
        $bodies = $this->daemon->bodies();
        $this->assertTrue(
            $load['resumed'] && (str_replace('load.raw_start_edited', 'load.raw_start', $bodies[1]) === $bodies[2]),
            'carrying exactly what the edited load carried, the target once: ' . $bodies[2],
        );
    }

    public function testAnEditedLoadRefusedForAnotherReasonIsNotSentAgain()
    {
        $this->settings(true);
        // A third answer, so a retry would be seen and would succeed.
        $this->daemon([[0], FakeRtorrentDaemon::fault('Could not create download'), [0]]);
        $torrent = new Torrent($this->announceOnlyTorrent());
        $hash = rTorrent::sendTorrent($torrent, true, false, $this->top, '', false, true);
        $this->assertTrue($hash === false, 'the add failed');
        $this->assertEquals(['execute', 'load.raw_start_edited'], $this->daemon->calls(), 'and was not tried again: ' . json_encode($this->daemon->calls()));
    }

    public function testThePlainNameIsNotSentAgainWhenItIsTheOneNotDefined()
    {
        $this->settings(false);
        $this->daemon([[0], FakeRtorrentDaemon::fault("Method 'load.raw_start' not defined", -506), [0]]);
        $torrent = new Torrent($this->announceOnlyTorrent());
        rTorrent::sendTorrent($torrent, true, false, $this->top, '', false, true);
        $this->assertEquals(['execute', 'load.raw_start'], $this->daemon->calls(), 'one load, no retry: ' . json_encode($this->daemon->calls()));
    }

    // ---- helpers -----------------------------------------------------------

    private function probe($methods, $settings = null)
    {
        if ($settings === null) {
            $settings = (new ReflectionClass('EditedLoadSettings'))->newInstanceWithoutConstructor();
        }
        self::swapSettings($settings);
        $this->stopDaemon();
        $this->daemon = new FakeRtorrentDaemon(
            new EditedLoadsResponder($methods, $this->top, $this->base),
            $this->base . '/calls.log',
            false,
            [__DIR__ . '/EditedLoadsResponder.php'],
        );
        $GLOBALS['scgi_port'] = $this->daemon->port();
        $settings->obtain();
        return($settings);
    }

    private function settings($edited)
    {
        $settings = (new ReflectionClass('EditedLoadSettings'))->newInstanceWithoutConstructor();
        $settings->readTables();
        $settings->iVersion = 0x1014;
        $settings->apiVersion = 11;
        $settings->directory = $this->top;
        $settings->home = $this->base;
        $settings->editedLoads = $edited;
        self::swapSettings($settings);
    }

    /** A fast-resumed add of a new torrent whose data is in place. */
    private function sendFast($isStart, $big = false)
    {
        $this->daemon([[0], [0]]);
        $torrent = new Torrent($this->announceOnlyTorrent());
        if ($big) {
            $torrent->comment(str_repeat('c', rTorrent::RTORRENT_PACKET_LIMIT));
        }
        $hash = rTorrent::sendTorrent($torrent, $isStart, false, $this->top, '', false, true);
        $this->assertTrue($hash === $torrent->hash_info(), 'the torrent is added');
        return($this->lastLoad());
    }

    /** What the last request the daemon was sent loads, and how. */
    private function lastLoad()
    {
        $bodies = $this->daemon->bodies();
        $body = end($bodies);
        $out = ['method' => null, 'target' => null, 'resumed' => false, 'params' => 0];
        if (preg_match('|<methodName>(.*)</methodName>|Us', $body, $m)) {
            $out['method'] = $m[1];
        }
        if (preg_match_all('~<value><(string|base64)>(.*)</\1></value>~Us', $body, $p, PREG_SET_ORDER)) {
            // A multicall's first member is the mkdir; the load is the last.
            if ($out['method'] === 'system.multicall'
                && preg_match_all('|<name>methodName</name><value><string>(.*)</string>|Us', $body, $n)) {
                $out['method'] = end($n[1]);
                $p = array_slice($p, count($p) - $this->loadParamCount($body));
            }
            $out['params'] = count($p);
            $out['target'] = $p[0][2];
            $data = ($p[1][1] === 'base64') ? base64_decode($p[1][2]) : @file_get_contents($p[1][2]);
            $loaded = is_string($data) ? new Torrent($data) : null;
            $out['resumed'] = ($loaded !== null) && !$loaded->errors()
                && isset($loaded->libtorrent_resume['bitfield']);
        }
        return($out);
    }

    private function loadParamCount($body)
    {
        $members = explode('<name>methodName</name>', $body);
        return(preg_match_all('~<value><(string|base64)>~', end($members)) - 1);
    }

    private function daemon($replies)
    {
        $this->stopDaemon();
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
        $GLOBALS['scgi_port'] = $this->daemon->port();
    }

    private function stopDaemon()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
    }

    private static function swapSettings($settings)
    {
        return Closure::bind(function ($settings) {
            $previous = rTorrentSettings::$theSettings;
            rTorrentSettings::$theSettings = $settings;
            return $previous;
        }, null, 'rTorrentSettings')->__invoke($settings);
    }
}
