<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

/**
 * plugins/source/action.php sends several torrents as one ZIP, each read from
 * the session directory or else from the file its download is tied to.
 *
 * rXMLRPCRequest hands back every string with its backslashes doubled and a
 * backslash put before each double quote. Both paths are paths on disk, so
 * they are looked for in their own bytes. Read as they arrive, no file is
 * found and the request ends in theUILang.cantFindTorrent, while the same
 * torrents one at a time, through rTorrent::getSource(), download.
 *
 * action.php is a top level script, so it is driven where it lives, in a
 * process of its own, with FakeRtorrentDaemon on the other end of its SCGI
 * socket. The zip extension is optional and not every PHP this suite runs on
 * has it: without it, a recorder of what action.php adds stands in for
 * ZipArchive, and with it, the archive action.php prints is read back.
 */
class SourceActionZipTest extends TestCase
{
    use TorrentSequenceFixtures;

    public const FIRST = '0123456789ABCDEF0123456789ABCDEF01234567';
    public const SECOND = '89ABCDEF0123456789ABCDEF0123456789ABCDEF';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-source-zip-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testTheTorrentsAreFoundWhenTheirPathsHoldAQuoteOrABackslash()
    {
        // A backslash before a quote also shows a second decoding.
        $name = 'x\\"y';
        $torrents = [
            self::FIRST => $this->announceOnlyTorrent(),
            self::SECOND => $this->announceListTorrent(),
        ];

        $this->assertTrue(
            array_values($this->zip($torrents, $name . '/', '')) === array_values($torrents),
            'both session copies are in the ZIP; calls: ' . implode(', ', $this->daemon->calls()),
        );
        $this->assertTrue(
            array_values($this->zip($torrents, '', $name)) === array_values($torrents),
            'with no session directory both tied files are in the ZIP; calls: '
            . implode(', ', $this->daemon->calls()),
        );
    }

    /**
     * A torrent whose file is in neither place is left out, and the ZIP still
     * holds the others.
     */
    public function testTheZipHoldsTheTorrentsFoundWhenOneIsNot()
    {
        $torrent = $this->announceListTorrent();

        $zipped = $this->zip([self::FIRST => null, self::SECOND => $torrent], 'session/', '');
        $this->assertTrue(
            array_values($zipped) === [$torrent],
            'the torrent that is found is in the ZIP without the one that is not; calls: '
            . implode(', ', $this->daemon->calls()),
        );
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }

    /**
     * Runs plugins/source/action.php for the hashes of $torrents, torrent
     * bytes by hash, and returns what went into the ZIP, file bytes by name in
     * the archive. Each torrent is put in $session, a directory under the
     * case's own, or, with $session '', in a file tied to its download whose
     * name starts with $tied. A torrent given as null has no file in either.
     */
    private function zip($torrents, $session, $tied)
    {
        $root = $this->repoRoot();
        if ($this->daemon !== null) {
            $this->daemon->stop();
        }
        TestTree::remove($this->base);
        @mkdir($this->base . '/profile/settings', 0700, true);
        $sessionPath = ($session === '') ? '' : $this->base . '/' . $session;
        if ($sessionPath !== '') {
            @mkdir($sessionPath, 0700);
        }
        // One get_session + d.get_tied_to_file request per hash.
        $replies = [];
        foreach ($torrents as $hash => $bytes) {
            $tiedPath = ($sessionPath === '') ? $this->base . '/' . $tied . $hash . '.torrent' : '';
            if ($bytes !== null) {
                file_put_contents(($sessionPath === '') ? $tiedPath : $sessionPath . $hash . '.torrent', $bytes);
            }
            $replies[] = [$sessionPath, $tiedPath];
        }
        // An install whose interface has been opened has a settings cache.
        // This script is driven against a profile of its own, so leave one
        // there rather than have it probe and spend the replies above.
        RtorrentProbeFixture::warmSettingsCache($this->base . '/profile');
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');

        $recorded = $this->base . '/recorded';
        $driver = $this->base . '/drive-source.php';
        file_put_contents($driver, "<?php\n"
            . '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1";' . "\n"
            . '$scgi_port = ' . $this->daemon->port() . ";\n"
            . '$rpcTimeOut = 10;' . "\n"
            . '$rpcLogCalls = false;' . "\n"
            . 'if (!class_exists("ZipArchive")) {' . "\n"
            . '    class ZipArchive' . "\n"
            . '    {' . "\n"
            . '        public const CREATE = 1;' . "\n"
            . '        private $added = [];' . "\n"
            . '        public function open($path, $flags) { return true; }' . "\n"
            . '        public function addFile($path, $name) { $this->added[$name] = file_get_contents($path); return true; }' . "\n"
            . '        public function close() { return file_put_contents(' . var_export($recorded, true)
            . ', serialize($this->added)) !== false; }' . "\n"
            . '    }' . "\n"
            . '}' . "\n"
            . '$_POST["hash"] = ' . var_export(implode(' ', array_keys($torrents)), true) . ";\n"
            . 'chdir(' . var_export($root . '/plugins/source', true) . ");\n"
            . 'require(' . var_export($root . '/plugins/source/action.php', true) . ");\n");

        $printed = $this->base . '/printed';
        exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' . escapeshellarg($driver)
            . ' > ' . escapeshellarg($printed) . ' 2>&1');
        if (is_file($recorded)) {
            return unserialize(file_get_contents($recorded));
        }
        $zipped = [];
        if (class_exists('ZipArchive') && (filesize($printed) > 0)) {
            $zip = new ZipArchive();
            if ($zip->open($printed) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zipped[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
                }
                $zip->close();
            }
        }
        return $zipped;
    }
}
