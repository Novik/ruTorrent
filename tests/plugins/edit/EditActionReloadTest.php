<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');
require_once(__DIR__ . '/../../../php/Torrent.php');

/**
 * plugins/edit/action.php erases the torrent before it reloads it.
 *
 * Saving an edit is d.erase followed by a load of the edited bytes, with
 * nothing in between that could put the old one back: if the load does not
 * happen the download is gone, and action.php's only response is to record
 * theUILang.errorAddTorrent and move to the next hash. So the reload failing
 * is data loss, and the two calls have to be watched together to see it.
 *
 * rTorrent::ADDITION_COMMANDS is what made the reload fail. action.php passes
 * d.set_custom3 in its addition and sendTorrent() drops the whole add when one
 * addition is not on that list, so it returned false before building a
 * payload -- after the erase had already gone through.
 *
 * action.php is a top level script that reads php://input, so it is driven
 * where it lives, in a process of its own, with FakeRtorrentDaemon on the
 * other end of its SCGI socket. What is asserted is what the script printed,
 * what that daemon was asked for, and whether the tied file is still on disk
 * afterwards. tests/plugins/edit/EditActionSequenceTest.php covers
 * the bencoding the script produces, against a transcription of it; this
 * covers the call sequence, against the script itself.
 */
class EditActionReloadTest extends TestCase
{
    use TorrentSequenceFixtures;

    public const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-edit-reload-' . getmypid();
        @mkdir($this->base . '/profile/settings', 0700, true);
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    /**
     * The whole point: the torrent the script erased is loaded again in the
     * same run. Before the addition allowlist knew about d.set_custom3 the
     * erase went through and the load never did.
     */
    public function testAnEditedTorrentIsReloadedAfterItIsErased()
    {
        $printed = $this->saveAnEdit('hash=' . self::HASH . '&set_comment=1&comment='
            . rawurlencode('an edited comment'));

        $erase = $this->indexOf('d.erase');
        $load = $this->loadCallIndex();

        $this->assertTrue(
            $erase >= 0,
            'the script erases the torrent before reloading it; calls: '
            . implode(', ', $this->daemon->calls()),
        );
        $this->assertTrue(
            $load >= 0,
            'and loads it again -- a run that erases and does not load has lost the '
            . 'download; calls: ' . implode(', ', $this->daemon->calls()),
        );
        $this->assertTrue(
            $erase < $load,
            'the erase comes first, which is why a refused reload cannot be retried',
        );
        $this->assertTrue(
            strpos($printed, 'errorAddTorrent') === false,
            'and the script reports no add error: ' . $printed,
        );
        $this->assertTrue(
            strpos($printed, self::HASH) !== false,
            'the saved hash comes back in the response: ' . $printed,
        );
    }

    /**
     * The same run seen from the other side: the reload carries the commands
     * action.php builds as its addition, so they reached the daemon rather
     * than stopping the add.
     */
    public function testTheReloadCarriesTheEditPluginsAddition()
    {
        $this->saveAnEdit('hash=' . self::HASH . '&set_private=1&private=1');

        $this->assertTrue(
            $this->loadCallIndex() >= 0,
            'a private-flag edit reloads too; calls: ' . implode(', ', $this->daemon->calls()),
        );
    }

    /**
     * The throttle plugin is not registered in this fixture, so action.php
     * asks for nine values and never reads a throttle name. It used to put the
     * unset throttle into the addition list anyway, and a non-string entry
     * refuses the whole add.
     */
    public function testTheReloadHappensWithNoThrottlePluginRegistered()
    {
        $this->saveAnEdit('hash=' . self::HASH . '&set_comment=1&comment=x');

        $calls = $this->daemon->calls();
        $this->assertTrue(
            !in_array('d.get_throttle_name', $calls, true),
            'the fixture has no throttle plugin, so no throttle name is read',
        );
        $this->assertTrue(
            $this->loadCallIndex() >= 0,
            'and the reload still happens; calls: ' . implode(', ', $calls),
        );
    }

    /**
     * rXMLRPCRequest hands back every string with its backslashes doubled and
     * a backslash put before each double quote. The session directory and
     * the tied file are paths on disk, so they are looked for in their own
     * bytes. Read as they arrive, the session copy is not found: the edit is
     * refused with theUILang.cantFindTorrent, or, when the download has a
     * readable tied file, made from that file instead.
     */
    public function testTheTorrentIsFoundWhenItsPathHoldsAQuoteOrABackslash()
    {
        // A backslash before a quote also shows a second decoding.
        $name = 'x\\"y';
        $post = 'hash=' . self::HASH . '&set_comment=1&comment=x';

        $printed = $this->saveAnEdit($post, $name . '/');
        $this->assertTrue(
            $this->loadCallIndex() >= 0 && strpos($printed, 'cantFindTorrent') === false,
            'the session copy is found; printed: ' . $printed,
        );

        $printed = $this->saveAnEdit($post, '', $name . '.torrent');
        $this->assertTrue(
            $this->loadCallIndex() >= 0 && strpos($printed, 'cantFindTorrent') === false,
            'with no session directory the tied file is found; printed: ' . $printed,
        );

        $printed = $this->saveAnEdit($post, $name . '/', 'tied.torrent');
        $reloaded = $this->reloadedTorrent();
        $this->assertTrue(
            ($reloaded !== null) && ($reloaded->announce() === 'http://one.test/announce'),
            'the session copy is read, not the tied file beside it; printed: ' . $printed,
        );
    }

    /**
     * With no session copy to read, the edit is made from the file the
     * download is tied to: the user's own .torrent, typically in a watch
     * directory. The reload sends the edited bytes, and ruTorrent itself
     * does not delete that file. FakeRtorrentDaemon runs no erase handlers;
     * rtorrent's default one, ~_delete_tied, still deletes it on d.erase.
     */
    public function testTheTiedFileIsKept()
    {
        $post = 'hash=' . self::HASH . '&set_comment=1&comment=x';
        foreach (['xy.torrent', 'x\\"y.torrent'] as $tied) {
            $printed = $this->saveAnEdit($post, '', $tied);
            $this->assertTrue(
                $this->loadCallIndex() >= 0,
                'the edit of ' . $tied . ' is reloaded; printed: ' . $printed,
            );
            $this->assertTrue(
                @file_get_contents($this->base . '/' . $tied) === $this->trackerlessTorrent(),
                'and ' . $tied . ' is still there, unchanged',
            );
        }
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }

    /**
     * Runs plugins/edit/action.php against the daemon and returns what it
     * printed. The nine values are the multicall action.php opens with:
     * get_session, d.is_open, d.is_active, d.get_state, d.get_tied_to_file,
     * d.get_custom1, d.get_directory_base, d.get_connection_seed and
     * d.get_complete. d.get_complete is 0 so the reload does not take the
     * fast resume branch, and the directory is empty so the add does not need
     * a directory to be approved.
     *
     * The torrent is put in $session, a directory under the case's own, and
     * in the file $tied names there; '' leaves either out. The two hold
     * different torrents -- the tied one has no tracker -- so the reloaded
     * bytes tell which was read.
     */
    private function saveAnEdit($post, $session = 'session/', $tied = '')
    {
        $root = $this->repoRoot();
        if ($this->daemon !== null) {
            $this->daemon->stop();
        }
        $sessionPath = ($session === '') ? '' : $this->base . '/' . $session;
        $tiedPath = ($tied === '') ? '' : $this->base . '/' . $tied;
        if ($sessionPath !== '') {
            @mkdir($sessionPath, 0700);
            file_put_contents($sessionPath . self::HASH . '.torrent', $this->announceOnlyTorrent());
        }
        if ($tiedPath !== '') {
            file_put_contents($tiedPath, $this->trackerlessTorrent());
        }
        // An install whose interface has been opened has a settings cache.
        // This script is driven against a profile of its own, so leave one
        // there rather than have it probe and spend the replies below.
        RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
        $this->daemon = new FakeRtorrentDaemon([
            [$sessionPath, 1, 1, 1, $tiedPath, '', '', 'seed', 0],
            [0],
            [0],
            [0],
        ], $this->base . '/calls.log');

        $driver = $this->base . '/drive-edit.php';
        file_put_contents($driver, "<?php\n"
            . '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1";' . "\n"
            . '$scgi_port = ' . $this->daemon->port() . ";\n"
            . '$rpcTimeOut = 10;' . "\n"
            . '$rpcLogCalls = false;' . "\n"
            . '$saveUploadedTorrents = false;' . "\n"
            . '$HTTP_RAW_POST_DATA = ' . var_export($post, true) . ";\n"
            . 'chdir(' . var_export($root . '/plugins/edit', true) . ");\n"
            . 'require(' . var_export($root . '/plugins/edit/action.php', true) . ");\n");

        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 '
            . escapeshellarg($driver) . ' 2>&1', $output);
        return implode("\n", $output);
    }

    /** The torrent the reload sent, or null: the load is the only call with a base64 value. */
    private function reloadedTorrent()
    {
        foreach ($this->daemon->bodies() as $body) {
            if (preg_match('`<base64>([^<]*)</base64>`', $body, $match)) {
                return new Torrent(base64_decode($match[1]));
            }
        }
        return null;
    }

    /** The index of the first call to $method, or -1. */
    private function indexOf($method)
    {
        $calls = $this->daemon->calls();
        $at = array_search($method, $calls, true);
        return $at === false ? -1 : $at;
    }

    /** The load command, whatever the daemon in front of it spells it. */
    private function loadCallIndex()
    {
        foreach ($this->daemon->calls() as $index => $call) {
            if (strpos($call, 'load') === 0 || strpos($call, 'load.') === 0) {
                return $index;
            }
        }
        return -1;
    }
}
