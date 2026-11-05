<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');
require_once(__DIR__ . '/../../../php/Torrent.php');

/**
 * plugins/retrackers/update.php reads the torrent it adds trackers to from
 * the session directory, or else from the file the download is tied to, then
 * erases the download and loads the changed torrent in its place.
 *
 * rXMLRPCRequest hands back every string with its backslashes doubled and a
 * backslash put before each double quote. Both paths are paths on disk, so
 * they are looked for in their own bytes. Read as they arrive, the session
 * copy is not found: the download gets no tracker and nothing is logged --
 * it is started again if it was added started, and otherwise stays stopped
 * -- or, when it has a readable tied file, the trackers are added to that
 * file's torrent instead.
 *
 * The tied file is the user's own .torrent, typically in a watch directory.
 * The reload sends the changed bytes, and ruTorrent itself does not delete
 * that file. FakeRtorrentDaemon runs no erase handlers; rtorrent's default
 * one, ~_delete_tied, still deletes it on d.erase.
 *
 * update.php is a top level script that takes the hash on its command line,
 * so it is driven where it lives, in a process of its own, with
 * FakeRtorrentDaemon on the other end of its SCGI socket. What is asserted is
 * what that daemon was asked for, and whether the tied file is still on disk
 * afterwards. RetrackersUpdateSequenceTest covers the announce lists the
 * script builds.
 */
class RetrackersUpdateReloadTest extends TestCase
{
    use TorrentSequenceFixtures;

    public const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-retrackers-reload-' . getmypid();
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

    public function testTheTorrentIsFoundWhenItsPathHoldsAQuoteOrABackslash()
    {
        // A backslash before a quote also shows a second decoding.
        $name = 'x\\"y';
        $fromSession = [['http://one.test/announce'], ['http://added.test/announce']];

        $printed = $this->addTrackers('', $name . '/');
        $this->assertTrue(
            $this->reloaded($fromSession),
            'the session copy is found and reloaded with the tracker; calls: '
            . implode(', ', $this->daemon->calls()) . '; printed: ' . $printed,
        );

        $printed = $this->addTrackers($name . '.torrent');
        $this->assertTrue(
            $this->reloaded([['http://added.test/announce']]),
            'with no session directory the tied file is found; calls: '
            . implode(', ', $this->daemon->calls()) . '; printed: ' . $printed,
        );

        $printed = $this->addTrackers('tied.torrent', $name . '/');
        $this->assertTrue(
            $this->reloaded($fromSession),
            'the session copy is read, not the tied file beside it; calls: '
            . implode(', ', $this->daemon->calls()) . '; printed: ' . $printed,
        );
    }

    public function testTheTiedFileIsKept()
    {
        $printed = $this->addTrackers('xy.torrent');
        $this->assertTrue(
            $this->reloaded([['http://added.test/announce']]),
            'with no session directory the tracker is added to the tied file\'s torrent; calls: '
            . implode(', ', $this->daemon->calls()) . '; printed: ' . $printed,
        );
        $this->assertTrue(
            @file_get_contents($this->base . '/xy.torrent') === $this->trackerlessTorrent(),
            'and the tied file is still there, unchanged',
        );
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }

    /**
     * Runs plugins/retrackers/update.php for HASH with one tracker to add.
     * The seven values are the multicall it opens with: get_session,
     * d.get_custom4 (1: start the download), d.get_tied_to_file,
     * d.get_custom1, d.get_directory_base, d.is_private and d.get_name.
     *
     * The download is tied to $tied, a file under the case's directory that
     * holds a torrent with no tracker, and rtorrent's session directory is
     * $session there, with a copy that has one; '' leaves either out. The
     * reloaded bytes tell which of the two was read.
     */
    private function addTrackers($tied, $session = '')
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
            [$sessionPath, '1', $tiedPath, '', '', 0, 'name'],
            [0],
            [0],
            [0],
        ], $this->base . '/calls.log');

        $driver = $this->base . '/drive-update.php';
        file_put_contents($driver, "<?php\n"
            . '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1";' . "\n"
            . '$scgi_port = ' . $this->daemon->port() . ";\n"
            . '$rpcTimeOut = 10;' . "\n"
            . '$rpcLogCalls = false;' . "\n"
            . 'chdir(' . var_export($root . '/plugins/retrackers', true) . ");\n"
            . 'require_once(' . var_export($root . '/plugins/retrackers/retrackers.php', true) . ");\n"
            . '$trackers = new rRetrackers();' . "\n"
            . '$trackers->list = [["http://added.test/announce"]];' . "\n"
            . '$trackers->store();' . "\n"
            . 'require(' . var_export($root . '/plugins/retrackers/update.php', true) . ");\n");

        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 '
            . escapeshellarg($driver) . ' ' . escapeshellarg(self::HASH) . ' 2>&1', $output);
        return implode("\n", $output);
    }

    /**
     * Whether the run erased the download and then loaded it again with
     * $announceList, the tracker added to the torrent that was read.
     */
    private function reloaded($announceList)
    {
        $calls = $this->daemon->calls();
        $erase = array_search('d.erase', $calls, true);
        $load = -1;
        foreach ($calls as $index => $call) {
            if (strpos($call, 'load') === 0) {
                $load = $index;
            }
        }
        if (($erase === false) || ($load < $erase)) {
            return false;
        }
        foreach ($this->daemon->bodies() as $body) {
            if (preg_match('~<base64>([^<]+)</base64>~', $body, $match)) {
                $torrent = new Torrent(base64_decode($match[1]));
                return $torrent->announce_list() === $announceList;
            }
        }
        return false;
    }
}
