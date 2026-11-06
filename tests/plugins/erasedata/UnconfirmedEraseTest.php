<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/LoadedDownloads.php');

/**
 * "Remove and delete data" is two steps in two processes. removewithdata.php
 * asks rtorrent to d.set_custom5, d.delete_tied and d.erase the download and,
 * once that is confirmed, publishes <hash>.list2 -- the files to unlink, the
 * base path, the multi-file flag and the deletion mode. update.php, started
 * by rtorrent's schedule every $garbageCheckInterval seconds, applies the
 * lists it finds and removes them.
 *
 * What this pins is that neither step deletes the data of a download rtorrent
 * still holds. The collector deletes what a list names once rtorrent no
 * longer lists the hash, so a list must only exist for an erase rtorrent
 * confirmed: the writer publishes nothing when the reply never came or was a
 * fault. And the collector asks rtorrent which downloads it holds, and about
 * their files, before each list it applies, because the same hash can be loaded
 * again before its pass or in the middle of one: such a list is withdrawn and
 * the data kept, and when rtorrent does not answer, the pass stops and the
 * lists wait. Another download can also hold the same files under another
 * hash -- cross-seeding, one file of a pack seeded on its own, a symlink --
 * and those files are kept (#3514).
 *
 * Each case drives the shipped writer against a daemon scripted for one
 * outcome, checks that the sequence really happened as described, and then
 * runs the shipped collector as production runs it: its own process, over the
 * real settings directory, against the same daemon.
 *
 * The daemon is a scripted SCGI listener (tests/php/FakeRtorrentDaemon.php); a
 * reply entry answers one request, and a system.multicall is one request. The
 * collector's questions are answered from the downloads a case says the daemon
 * holds (LoadedDownloads.php).
 */
class UnconfirmedEraseTest extends TestCase
{
    public const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
    /** Another download the daemon may hold; never the one being erased. */
    public const OTHER = '89ABCDEF0123456789ABCDEF0123456789ABCDEF';
    /** A third download, for a case that needs one. */
    public const THIRD = 'FEDCBA9876543210FEDCBA9876543210FEDCBA98';
    /** A fourth, for a case that needs one more. */
    public const FOURTH = '00112233445566778899AABBCCDDEEFF00112233';

    // What rtorrent answers the collector's three questions with, captured
    // over the socket by a recorder from a daemon holding the second torrent
    // of a season seeded twice and the season's e02.bin, seeded on its own
    // as well (testCapturedAnswersOfBothDaemonsAreRead()); downloads under
    // /tmp/lab/data. From rTorrent 0.16.27 (tinyxml2): the downloads it
    // holds, the sizes of their files joined by cat, and the paths of the
    // four files that can be ours.
    public const DOWNLOADS_016 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 582\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data><value><array><data>"
        . "<value><string>6C912091846E4F4DCC28E20111B21142E23675E8</string></value>"
        . "<value><string>/tmp/lab/data/s1/Show.S01</string></value><value><i8>3</i8></value>"
        . "<value><i8>150000</i8></value>"
        . "</data></array></value><value><array><data>"
        . "<value><string>6F2824DE04A7E216DDCD48D90C6923113649D716</string></value>"
        . "<value><string>/tmp/lab/data/s1/Show.S01</string></value><value><i8>1</i8></value>"
        . "<value><i8>50000</i8></value>"
        . "</data></array></value></data></array></value></param></params></methodResponse>";

    public const SIZES_016 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 447\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data><value><array><data>"
        . "<value><string>6C912091846E4F4DCC28E20111B21142E23675E8</string></value>"
        . "<value><string>40000/50000/60000/</string></value></data></array></value><value><array><data>"
        . "<value><string>6F2824DE04A7E216DDCD48D90C6923113649D716</string></value>"
        . "<value><string>50000/</string></value></data></array></value></data></array></value>"
        . "</param></params></methodResponse>";

    public const PATHS_016 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 457\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data><value><array><data>"
        . "<value><string>e01.bin</string></value></data></array></value><value><array><data>"
        . "<value><string>e02.bin</string></value></data></array></value><value><array><data>"
        . "<value><string>e03.bin</string></value></data></array></value><value><array><data>"
        . "<value><string>e02.bin</string></value></data></array></value></data></array></value>"
        . "</param></params></methodResponse>";

    // The same from rTorrent 0.9.8 (xmlrpc-c), asked as d.multicall2.
    public const DOWNLOADS_098 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 637\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
        . "<methodResponse>\r\n"
        . "<params>\r\n"
        . "<param><value><array><data>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>6C912091846E4F4DCC28E20111B21142E23675E8</string></value>\r\n"
        . "<value><string>/tmp/lab/data/s1/Show.S01</string></value>\r\n"
        . "<value><i8>3</i8></value>\r\n"
        . "<value><i8>150000</i8></value>\r\n"
        . "</data></array></value>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>6F2824DE04A7E216DDCD48D90C6923113649D716</string></value>\r\n"
        . "<value><string>/tmp/lab/data/s1/Show.S01</string></value>\r\n"
        . "<value><i8>1</i8></value>\r\n"
        . "<value><i8>50000</i8></value>\r\n"
        . "</data></array></value>\r\n"
        . "</data></array></value></param>\r\n"
        . "</params>\r\n"
        . "</methodResponse>\r\n";

    public const SIZES_098 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 494\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
        . "<methodResponse>\r\n"
        . "<params>\r\n"
        . "<param><value><array><data>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>6C912091846E4F4DCC28E20111B21142E23675E8</string></value>\r\n"
        . "<value><string>40000/50000/60000/</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>6F2824DE04A7E216DDCD48D90C6923113649D716</string></value>\r\n"
        . "<value><string>50000/</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "</data></array></value></param>\r\n"
        . "</params>\r\n"
        . "</methodResponse>\r\n";

    public const PATHS_098 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 512\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
        . "<methodResponse>\r\n"
        . "<params>\r\n"
        . "<param><value><array><data>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>e01.bin</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>e02.bin</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>e03.bin</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>e02.bin</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "</data></array></value></param>\r\n"
        . "</params>\r\n"
        . "</methodResponse>\r\n";

    private $base;
    private $daemon;
    private $port;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-unconfirmed-erase-' . getmypid();
        $this->removeTree($this->base);
        @mkdir($this->base . '/profile/settings', 0700, true);
    }

    public function tearDown()
    {
        $this->stopDaemon();
        $this->removeTree($this->base);
    }

    // -- the erase never reached the daemon -----------------------------------

    public function testNothingIsPublishedForAnEraseTheDaemonNeverAnswered()
    {
        $this->fresh();
        $download = $this->download();
        // One reply: the paths. The daemon's worker then closes its listener
        // and exits, so the erase request meets a refused connection -- a
        // daemon that stopped between the two requests. No timeout is
        // reached. To the writer, the download is still loaded.
        $this->startDaemon([$this->pathsReply($download)]);

        $verdict = $this->removeWithData(2);

        $this->assertTrue(
            $verdict === 'refused',
            'the writer tells the caller the removal did not happen: ' . $verdict,
        );
        $this->assertTrue(
            !$this->daemon->received('d.erase'),
            'and the daemon never saw the erase',
        );
        $this->assertTrue(
            !$this->listPublished(),
            'so no list authorises deleting the files',
        );
        $this->assertTrue(
            $this->listed() === [],
            'and nothing staged is left behind: ' . implode(' ', $this->listed()),
        );
        $this->assertTrue(
            strpos($this->logged(), 'did not confirm the erase of ' . self::HASH) !== false,
            'the log says the erase was not confirmed, and where the data is: ' . $this->logged(),
        );

        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        foreach ($download['files'] as $file) {
            $this->assertTrue(
                $this->isFile($file),
                'a file of a download the daemon still holds is still there: ' . basename($file),
            );
        }
    }

    // -- the daemon refused the erase -----------------------------------------

    public function testNothingIsPublishedForAnEraseTheDaemonRefused()
    {
        $this->fresh();
        $download = $this->download();
        // A fault makes the writer report failure. It cannot tell which of
        // the three calls ran; here none did. The daemon would tell a
        // collector that asked that it still holds the download; one with
        // nothing to apply does not ask.
        $this->startDaemon(
            [$this->pathsReply($download), FakeRtorrentDaemon::fault('Could not erase download.')],
            [$this->holding(self::HASH)],
        );

        $verdict = $this->removeWithData();

        $this->assertTrue(
            $verdict === 'refused',
            'the writer tells the caller the removal did not happen: ' . $verdict,
        );
        $this->assertTrue(
            $this->daemon->received('d.erase'),
            'the daemon saw the erase and answered it with a fault',
        );
        $this->assertTrue(
            !$this->listPublished(),
            'so no list authorises deleting the files',
        );
        $this->assertTrue(
            $this->listed() === [],
            'and nothing staged is left behind: ' . implode(' ', $this->listed()),
        );

        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            !$this->daemon->received('d.multicall'),
            'with nothing to apply, the collector does not ask the daemon',
        );
        foreach ($download['files'] as $file) {
            $this->assertTrue(
                $this->isFile($file),
                'a file of a download the daemon still holds is still there: ' . basename($file),
            );
        }
    }

    // -- the same hash is loaded again before the collector's pass -------------

    public function testAListWhoseHashIsLoadedAgainIsWithdrawnAndTheDataKept()
    {
        $this->fresh();
        $download = $this->download();
        // The erase succeeds and the list is published. Before the collector's
        // next pass -- up to $garbageCheckInterval seconds later, longer when
        // a pass is skipped as busy -- the torrent is added again to the same
        // directory: a removal noticed as a mistake, a watch directory, an
        // RSS rule. rtorrent finds the files on disk and lists the hash
        // again, and tells the collector so.
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->holding(self::OTHER, self::HASH)],
        );

        $verdict = $this->removeWithData();

        $this->assertTrue(
            $verdict === 'erased',
            'the writer tells the caller the removal happened: ' . $verdict,
        );
        $this->assertTrue($this->daemon->received('d.erase'), 'the daemon erased the download');
        $this->assertTrue($this->listPublished(), 'and the list is published');

        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            $this->daemon->received('d.multicall'),
            'the collector asked the daemon what it holds',
        );
        $this->assertTrue(!$this->listPublished(), 'and withdrew the list');
        foreach ($download['files'] as $file) {
            $this->assertTrue(
                $this->isFile($file),
                'a file of a download the daemon holds again is still there: ' . basename($file),
            );
        }
        $this->assertTrue(
            strpos(
                $this->logged(),
                'erasedata: ' . self::HASH . ' is loaded again; list withdrawn, data kept',
            ) !== false,
            'and the log says so: ' . $this->logged(),
        );
    }

    // -- the daemon does not answer the collector ------------------------------

    public function testNothingIsDeletedWhenTheDaemonDoesNotAnswerTheCollector()
    {
        $this->fresh();
        $download = $this->download();
        $this->startDaemon([$this->pathsReply($download), $this->eraseReply()]);

        $verdict = $this->removeWithData();

        $this->assertTrue(
            $verdict === 'erased',
            'the writer tells the caller the removal happened: ' . $verdict,
        );
        $this->assertTrue($this->listPublished(), 'and the list is published');
        // rtorrent is down when the collector's pass comes: restarting,
        // crashed, its socket refused. Whether the download has been loaded
        // again is unknown, and the collector may not guess.
        $this->stopDaemon();

        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            $this->listPublished(),
            'the list is kept for a pass the daemon answers',
        );
        foreach ($download['files'] as $file) {
            $this->assertTrue(
                $this->isFile($file),
                'and nothing is deleted on an unknown answer: ' . basename($file),
            );
        }
        $this->assertTrue(
            strpos($this->logged(), 'rtorrent did not answer, 1 list(s) kept') !== false,
            'and the log says the pass was skipped: ' . $this->logged(),
        );
    }

    public function testNothingIsDeletedWhenTheDaemonRefusesTheCollectorsQuestion()
    {
        $this->fresh();
        $download = $this->download();
        $this->startDaemon([$this->pathsReply($download), $this->eraseReply()]);

        $verdict = $this->removeWithData();

        $this->assertTrue(
            $verdict === 'erased',
            'the writer tells the caller the removal happened: ' . $verdict,
        );
        $this->assertTrue($this->listPublished(), 'and the list is published');

        // rtorrent answers one of the collector's questions with a fault --
        // what a daemon that does not know the command as spelled does, or
        // one that no longer holds a download it has just listed -- which is
        // as unknown an answer as none, and is told apart in the log so that
        // a line every pass can be read for what it is. Paths are asked for
        // two a request, and the last case refuses the second of three: the
        // download erased in between is the one that uses this one's files.
        $other = [self::OTHER => [$download['base'], ['one.bin' => 3]]];
        $refused = [
            'downloads' => FakeRtorrentDaemon::fault('Unsupported target type found.'),
            'sizes' => ['holds' => $other, 'sizes' => FakeRtorrentDaemon::fault('Could not find info-hash.')],
            'paths' => ['holds' => $other, 'paths' => FakeRtorrentDaemon::fault('Could not find info-hash.')],
            'paths, second of three' => ['holds' => [
                self::OTHER => [$this->base . '/elsewhere', ['a.bin' => 3, 'b.bin' => 3]],
                self::THIRD => [$download['base'], ['one.bin' => 3, 'sub/two.bin' => 3]],
                self::FOURTH => [$this->base . '/elsewhere', ['c.bin' => 3]],
            ], 'gone' => [self::THIRD]],
        ];
        $n = 0;
        foreach ($refused as $question => $state) {
            $this->stopDaemon();
            $this->startDaemon([], [$state]);

            $out = $this->collect("define('ERASEDATA_PATHS_PER_REQUEST', 2);\n");

            $n++;
            $this->assertTrue($out === '', $question . ': the collector runs without a diagnostic: ' . $out);
            $this->assertTrue(
                $this->listPublished(),
                $question . ': the list is kept for a pass the daemon answers',
            );
            foreach ($download['files'] as $file) {
                $this->assertTrue(
                    $this->isFile($file),
                    $question . ': and nothing is deleted on an unknown answer: ' . basename($file),
                );
            }
            $this->assertTrue(
                substr_count($this->logged(), 'rtorrent answered with a fault, 1 list(s) kept') === $n,
                $question . ': and the log says the question was refused: ' . $this->logged(),
            );
        }
        $this->assertTrue(
            $this->asked() === [['sizes', 1], ['paths', 2], ['paths', 2]],
            'the last pass stopped at the refused second request of paths: ' . json_encode($this->asked()),
        );
    }

    // -- a hash is loaded again in the middle of a pass -------------------------

    public function testAHashLoadedAgainBetweenTwoListsOfOnePassIsSeen()
    {
        $this->fresh();
        $first = $this->download('First');
        $second = $this->download('Second');
        // Two downloads erased, two lists. rtorrent is asked before each list,
        // not once per pass: it holds nothing when asked the first time, and
        // both hashes when asked the second time. The order the collector
        // reads the directory in is the filesystem's, so what is pinned is
        // that exactly one list is applied and the other withdrawn, and that
        // the withdrawn one is the download whose files are still there.
        $this->startDaemon(
            [$this->pathsReply($first), $this->eraseReply(), $this->pathsReply($second), $this->eraseReply()],
            [$this->holding(), $this->holding(self::HASH, self::OTHER)],
        );

        $this->assertTrue($this->removeWithData(60, self::HASH) === 'erased', 'the first removal happened');
        $this->assertTrue($this->removeWithData(60, self::OTHER) === 'erased', 'and the second');
        $this->assertTrue(
            $this->listPublished(self::HASH) && $this->listPublished(self::OTHER),
            'and both lists are published',
        );

        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            count(array_keys($this->daemon->calls(), 'd.multicall', true)) === 2,
            'the collector asked the daemon once per list: ' . implode(' ', $this->daemon->calls()),
        );
        $this->assertTrue(
            !$this->listPublished(self::HASH) && !$this->listPublished(self::OTHER),
            'both lists are gone',
        );
        $withdrawn = [];
        if (preg_match_all(
            '/erasedata: ([0-9A-F]{40}) is loaded again; list withdrawn, data kept/',
            $this->logged(),
            $m,
        )) {
            $withdrawn = $m[1];
        }
        $this->assertTrue(
            count($withdrawn) === 1,
            'exactly one list was withdrawn: ' . $this->logged(),
        );
        $kept = ($withdrawn === [self::OTHER]) ? $second : $first;
        $deleted = ($withdrawn === [self::OTHER]) ? $first : $second;
        foreach ($kept['files'] as $file) {
            $this->assertTrue(
                $this->isFile($file),
                'the download loaded again in the middle of the pass keeps its files: ' . basename($file),
            );
        }
        foreach ($deleted['files'] as $file) {
            $this->assertTrue(
                !$this->isFile($file),
                'and the one applied before the answer changed is deleted: ' . basename($file),
            );
        }
    }

    // -- the erase is confirmed and the hash stays gone -------------------------

    public function testAConfirmedEraseIsCollectedOnceTheHashIsGone()
    {
        $this->fresh();
        $download = $this->download();
        // At the collector's pass the daemon holds another download, not
        // this one.
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->holding(self::OTHER)],
        );

        $verdict = $this->removeWithData();

        $this->assertTrue(
            $verdict === 'erased',
            'the writer tells the caller the removal happened: ' . $verdict,
        );
        $this->assertTrue(
            $this->daemon->calls() === [
                'd.get_base_path', 'd.is_multi_file', 'f.multicall',
                'd.set_custom5', 'd.delete_tied', 'd.erase'],
            'the paths are read first, then the download is erased in one request: '
            . implode(' ', $this->daemon->calls()),
        );
        $this->assertTrue($this->listPublished(), 'and the list is published');

        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        foreach ($download['files'] as $file) {
            $this->assertTrue(
                !$this->isFile($file),
                'a file of the erased download is deleted: ' . basename($file),
            );
        }
        $this->assertTrue(!$this->isDir($download['base']), 'and so is its directory');
        $this->assertTrue(!$this->listPublished(), 'and the list is consumed');
        $this->assertTrue($this->logged() === '', 'with nothing to report: ' . $this->logged());
    }

    // -- another download holds the same files (#3514) -------------------------

    public function testTheFilesOfAnotherLoadedDownloadAreKept()
    {
        $this->fresh();
        $download = $this->download();
        // The same data seeded on two trackers: two torrents that differ in
        // their info hash only, on one directory. The other one is still
        // loaded when this one's list is applied, and has a file of its own
        // it has not downloaded yet.
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [
                $download['base'],
                ['one.bin' => 3, 'sub/two.bin' => 3, 'three.bin' => 3],
            ]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!$this->listPublished(), 'the list is consumed');
        foreach ($download['files'] as $file) {
            $this->assertTrue(
                $this->isFile($file),
                'a file the other download uses is still there: ' . basename($file),
            );
        }
        $this->assertTrue(
            strpos(
                $this->logged(),
                'erasedata: kept 2 file(s) of ' . self::HASH . ' that 1 other loaded torrent(s) still use',
            ) !== false,
            'and the log says what was kept: ' . $this->logged(),
        );
        $this->assertTrue(
            strpos($this->logged(), 'base path') === false,
            'and nothing about a base path, which only Delete Path removes whole: ' . $this->logged(),
        );
    }

    public function testOnlyTheFileAnotherDownloadUsesIsKept()
    {
        $this->fresh();
        $download = $this->download();
        // One file of the pack is also seeded on its own: a single-file
        // torrent whose directory is the pack's subdirectory. And one file
        // the list names is no longer there.
        $listed = $download;
        $listed['files'][] = $download['base'] . '/gone.bin';
        $this->startDaemon(
            [$this->pathsReply($listed), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$download['base'] . '/sub', ['two.bin' => 3]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue($this->isFile($download['files'][1]), 'the file the other download uses is kept');
        $this->assertTrue(!$this->isFile($download['files'][0]), 'and the one only this download used is deleted');
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: kept 1 file(s) of ' . self::HASH) !== false,
            'and the log says so: ' . $this->logged(),
        );
    }

    public function testAFileAnotherDownloadReachesThroughASymlinkIsKept()
    {
        $this->fresh();
        $download = $this->download();
        // The other download's file is a symlink to this one's: its path
        // matches nothing, its size and, through the link, its inode do. Its
        // names carry a double quote, which the RPC layer hands over escaped.
        @mkdir($this->base . '/data/Else"where', 0700, true);
        symlink($download['files'][0], $this->base . '/data/Else"where/o"ne.bin');
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$this->base . '/data/Else"where', ['o"ne.bin' => 3]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue($this->isFile($download['files'][0]), 'the file the symlink points at is kept');
        $this->assertTrue(!$this->isFile($download['files'][1]), 'and the other file is deleted');
    }

    public function testASymlinkAnotherDownloadReachesByItsNameIsKept()
    {
        $this->fresh();
        $download = $this->download('Linked');
        // A file of the download is a symlink into a library, and another
        // torrent of the same folder reaches its data through that very name:
        // unlinking it would leave that torrent without its file. What it
        // points at has a name of its own.
        $target = $this->base . '/data/library/clip-original.bin';
        @mkdir(dirname($target), 0700, true);
        file_put_contents($target, 'library');
        symlink($target, $download['base'] . '/clip.bin');
        $download['files'][] = $download['base'] . '/clip.bin';
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$download['base'], ['clip.bin' => 7]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(is_link($download['base'] . '/clip.bin'), 'the symlink the other torrent reaches is kept');
        $this->assertTrue(!$this->isFile($download['files'][0]), 'and a file nobody else uses is deleted');
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: kept 1 file(s) of ' . self::HASH) !== false,
            'and the log says so: ' . $this->logged(),
        );
    }

    public function testASymlinkOfThisDownloadIsUnlinkedAsTheNameItIs()
    {
        $this->fresh();
        $download = $this->download('Pointing');
        // S5 the other way round: this download's file is a symlink to the
        // other's file, which that one uses by its own path. And one symlink
        // points at nothing. Unlinking a symlink takes nothing from the file
        // it points at.
        $theirs = $this->base . '/data/s5a/movie.bin';
        @mkdir(dirname($theirs), 0700, true);
        file_put_contents($theirs, 'movie');
        symlink($theirs, $download['base'] . '/movie.bin');
        symlink($this->base . '/data/s5a/gone.bin', $download['base'] . '/gone.bin');
        $download['files'][] = $download['base'] . '/movie.bin';
        $download['files'][] = $download['base'] . '/gone.bin';
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [dirname($theirs), ['movie.bin' => 5]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            !is_link($download['base'] . '/movie.bin') && !is_link($download['base'] . '/gone.bin'),
            'the symlinks are unlinked',
        );
        $this->assertTrue(file_get_contents($theirs) === 'movie', 'and the file the other download uses is untouched');
        $this->assertTrue($this->logged() === '', 'with nothing kept: ' . $this->logged());
    }

    public function testAHardLinkOfAnotherDownloadOnlyLosesThisDownloadsName()
    {
        $this->fresh();
        $download = $this->download();
        // The setup #833 recommended: the other download has a hard link of
        // its own. Unlinking this name leaves it its name and its data.
        @mkdir($this->base . '/data/Elsewhere', 0700, true);
        $theirs = $this->base . '/data/Elsewhere/one.bin';
        link($download['files'][0], $theirs);
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$this->base . '/data/Elsewhere', ['one.bin' => 3]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        foreach ($download['files'] as $file) {
            $this->assertTrue(!$this->isFile($file), 'a file of the download is deleted: ' . basename($file));
        }
        $this->assertTrue(!$this->isDir($download['base']), 'and so is its directory');
        $this->assertTrue(
            $this->isFile($theirs) && (file_get_contents($theirs) === 'one'),
            'while the other download keeps its name and its data',
        );
        $this->assertTrue($this->logged() === '', 'with nothing kept: ' . $this->logged());
    }

    public function testAHardLinkOfAnotherNameInTheSameDirectoryIsNotInUse()
    {
        $this->fresh();
        $download = $this->download();
        // The other download's hard link lies beside this one's file, under
        // another name.
        link($download['files'][0], $download['base'] . '/one.copy.bin');
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$download['base'], ['one.copy.bin' => 3]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!$this->isFile($download['files'][0]), 'this download\'s name is unlinked');
        $this->assertTrue(
            file_get_contents($download['base'] . '/one.copy.bin') === 'one',
            'and the other download keeps its own name and its data',
        );
        $this->assertTrue($this->logged() === '', 'with nothing kept: ' . $this->logged());
    }

    public function testAFileAnotherDownloadHasWrittenIsKeptWhateverItsLinks()
    {
        $this->fresh();
        $download = $this->throughLink($this->download('pool/Download'));
        // The other download has this one's directory and names this very
        // path, but declares another size for it, and has started writing it:
        // libtorrent resized the file to that size when it opened it for
        // writing. The file also has a second hard link nobody seeds. Its size
        // makes it a candidate, and the shared name makes it in use. Both
        // name the directory through the symlink of the downloads directory.
        $fp = fopen($download['files'][0], 'r+');
        ftruncate($fp, 999);
        fclose($fp);
        @mkdir($this->base . '/data/Spare', 0700, true);
        link($download['files'][0], $this->base . '/data/Spare/one.bin');
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$download['base'], ['one.bin' => 999]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue($this->isFile($download['files'][0]), 'the file the other download writes is kept');
        $this->assertTrue(!$this->isFile($download['files'][1]), 'and the other file is deleted');
    }

    public function testAFileReachedByAPathRealpathCannotResolveIsKept()
    {
        $this->fresh();
        $download = $this->download();
        // The file has a second hard link nobody seeds, and the other download
        // names the download's directory padded with "/." to a path of
        // PHP_MAXPATHLEN - 1 bytes: stat() takes it, PHP's realpath() refuses
        // a path that long. Whether it reaches the file through this name
        // cannot be told, so the file is kept.
        @mkdir($this->base . '/data/Spare', 0700, true);
        link($download['files'][0], $this->base . '/data/Spare/one.bin');
        $pad = PHP_MAXPATHLEN - 1 - strlen($download['files'][0]);
        $directory = $download['base'] . str_repeat('/.', intdiv($pad, 2)) . str_repeat('/', $pad % 2);
        $path = $directory . '/one.bin';
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$directory, ['one.bin' => 3]]])],
        );
        $this->assertTrue(
            (strlen($path) === PHP_MAXPATHLEN - 1) && (@stat($path) !== false) && (realpath($path) === false),
            'stat() takes the path, realpath() does not',
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue($this->isFile($download['files'][0]), 'the file the long path reaches is kept');
        $this->assertTrue(!$this->isFile($download['files'][1]), 'and the other file is deleted');
    }

    public function testAFilePast2GiBIsComparedOnA32BitPhpToo()
    {
        $this->fresh();
        // A 32-bit PHP reads a size past 2 GiB from stat() wrapped, so it
        // cannot be matched against the size rtorrent declares. Written
        // sparse: one byte at the end.
        $base = $this->base . '/data/Big';
        @mkdir($base, 0700, true);
        $fp = fopen($base . '/big.bin', 'w');
        fseek($fp, 2147483647);
        fwrite($fp, 'x');
        fclose($fp);
        $download = ['base' => $base, 'files' => [$base . '/big.bin']];
        @mkdir($this->base . '/data/Elsewhere', 0700, true);
        symlink($base . '/big.bin', $this->base . '/data/Elsewhere/big.bin');
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$this->base . '/data/Elsewhere', ['big.bin' => 2147483648]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue($this->isFile($base . '/big.bin'), 'the file the symlink points at is kept');
    }

    public function testDownloadsRemovedTogetherLoseAllTheirFiles()
    {
        $this->fresh();
        $download = $this->download();
        // Both torrents on the same files are removed in one go: by the time
        // the collector runs, the daemon holds neither.
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply(), $this->pathsReply($download), $this->eraseReply()],
            [$this->holding()],
        );

        $this->assertTrue($this->removeWithData(60, self::HASH) === 'erased', 'the first removal happened');
        $this->assertTrue($this->removeWithData(60, self::OTHER) === 'erased', 'and the second');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            !$this->listPublished(self::HASH) && !$this->listPublished(self::OTHER),
            'both lists are consumed',
        );
        foreach ($download['files'] as $file) {
            $this->assertTrue(!$this->isFile($file), 'a file of both downloads is deleted: ' . basename($file));
        }
        $this->assertTrue(!$this->isDir($download['base']), 'and so is their directory');
        $this->assertTrue($this->logged() === '', 'with nothing kept: ' . $this->logged());
    }

    public function testARatioGroupKeepsTheFilesOfAnotherLoadedDownload()
    {
        $this->fresh();
        $download = $this->download();
        // A ratio group with "Remove data" runs erase.php, which queues the
        // request and drains the queue: it asks which hashes the daemon holds,
        // then reads the paths and erases, as the web UI does. The list goes to
        // the same collector.
        $other = [self::OTHER => [$download['base'], ['one.bin' => 3, 'sub/two.bin' => 3]]];
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::HASH => [$download['base'], ['one.bin' => 3, 'sub/two.bin' => 3]]] + $other),
                $this->loaded($other)],
        );

        $out = $this->eraseByRatio();

        $this->assertTrue($out === '', 'erase.php runs without a diagnostic: ' . $out);
        $this->assertTrue($this->daemon->received('d.erase'), 'the daemon erased the download');
        $this->assertTrue($this->listPublished(), 'and the list is published');

        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        foreach ($download['files'] as $file) {
            $this->assertTrue(
                $this->isFile($file),
                'a file the other download uses is still there: ' . basename($file),
            );
        }
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: kept 2 file(s) of ' . self::HASH) !== false,
            'and the log says what was kept: ' . $this->logged(),
        );
    }

    public function testAFileASymlinkOfAnotherNameReachesIsKeptDespiteItsOtherHardLink()
    {
        $this->fresh();
        $download = $this->download();
        // The file has a second hard link nobody seeds, and the other
        // download reaches it through a symlink of another name. Unlinking
        // this name would leave that symlink pointing at nothing.
        @mkdir($this->base . '/data/Spare', 0700, true);
        link($download['files'][0], $this->base . '/data/Spare/one.bin');
        @mkdir($this->base . '/data/Elsewhere', 0700, true);
        symlink($download['files'][0], $this->base . '/data/Elsewhere/renamed.bin');
        $this->startDaemon(
            [$this->pathsReply($download), $this->eraseReply()],
            [$this->loaded([self::OTHER => [$this->base . '/data/Elsewhere', ['renamed.bin' => 3]]])],
        );

        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue($this->isFile($download['files'][0]), 'the file the symlink reaches is kept');
        $this->assertTrue(!$this->isFile($download['files'][1]), 'and the other file is deleted');
    }

    public function testPathsAreAskedForInBatchesAndSizesOncePerPass()
    {
        $this->fresh();
        $first = $this->download('First');
        $second = $this->download('Second');
        // Two lists in one pass, and paths two a request. One download the
        // daemon holds uses the first download's files by their paths;
        // another has a file of their size elsewhere, and thirty thousand of
        // another size.
        $many = ['a.bin' => 3];
        for ($i = 0; $i < 30000; $i++) {
            $many['f' . $i . '.bin'] = 5;
        }
        $this->startDaemon(
            [$this->pathsReply($first), $this->eraseReply(), $this->pathsReply($second), $this->eraseReply()],
            [$this->loaded([
                self::OTHER => [$first['base'], ['sub/two.bin' => 3, 'one.bin' => 3]],
                self::THIRD => [$this->base . '/elsewhere', $many],
            ])],
        );

        $this->assertTrue($this->removeWithData(60, self::HASH) === 'erased', 'the first removal happened');
        $this->assertTrue($this->removeWithData(60, self::FOURTH) === 'erased', 'and the second');
        $out = $this->collect("define('ERASEDATA_PATHS_PER_REQUEST', 2);\n");

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        foreach ($first['files'] as $file) {
            $this->assertTrue($this->isFile($file), 'a file the other download uses is kept: ' . basename($file));
        }
        foreach ($second['files'] as $file) {
            $this->assertTrue(!$this->isFile($file), 'a file of the same size nobody uses is deleted: ' . basename($file));
        }
        $this->assertTrue(
            $this->asked() === [['sizes', 1], ['paths', 2], ['paths', 1], ['paths', 2], ['paths', 1]],
            'the sizes once, then for each list the paths of its three files of that size, two a request: '
            . json_encode($this->asked()),
        );
    }

    public function testTheSizesOfADownloadWhoseFilesChangedAreAskedForAgain()
    {
        $this->fresh();
        $first = $this->download('First');
        $second = $this->download('Second');
        // Between the two lists of one pass the other download goes from one
        // file to three -- a magnet link whose metadata arrived -- and two of
        // them are symlinks to the first file of each download. Whichever
        // list comes second, its file is then in use.
        @mkdir($this->base . '/data/Twin', 0700, true);
        symlink($first['files'][0], $this->base . '/data/Twin/a.bin');
        symlink($second['files'][0], $this->base . '/data/Twin/b.bin');
        $this->startDaemon(
            [$this->pathsReply($first), $this->eraseReply(), $this->pathsReply($second), $this->eraseReply()],
            [
                $this->loaded([self::OTHER => [$this->base . '/elsewhere', ['Twin.meta' => 999]]]),
                $this->loaded([self::OTHER => [$this->base . '/data/Twin', ['a.bin' => 3, 'b.bin' => 3, 'c.bin' => 3]]]),
            ],
        );

        $this->assertTrue($this->removeWithData(60, self::HASH) === 'erased', 'the first removal happened');
        $this->assertTrue($this->removeWithData(60, self::FOURTH) === 'erased', 'and the second');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            $this->isFile($first['files'][0]) !== $this->isFile($second['files'][0]),
            'the first file of the download applied second is kept, the other one is deleted',
        );
        $this->assertTrue(
            substr_count($this->logged(), ' other loaded torrent(s) still use') === 1,
            'and the log says so once: ' . $this->logged(),
        );
        $this->assertTrue(
            count(array_keys(array_column($this->asked(), 0), 'sizes')) === 2,
            'its sizes were asked for again: ' . json_encode($this->asked()),
        );
    }

    public function testTheSizesOfAMagnetLinkThatBecameATorrentOfOneFileAreAskedForAgain()
    {
        $this->fresh();
        $first = $this->download('First');
        // Two lists of one pass name the same files. While the first is
        // applied, a third download uses one.bin and the other is a magnet
        // link's stub: one file of one byte. By the second, the third is gone
        // and the stub has become a torrent of one file, one.bin. The number
        // of files stayed, so only the size tells that the sizes the pass
        // holds are the stub's.
        $this->startDaemon(
            [$this->pathsReply($first), $this->eraseReply(), $this->pathsReply($first), $this->eraseReply()],
            [
                $this->loaded([
                    self::OTHER => [$first['base'], [self::OTHER . '.meta' => 1]],
                    self::THIRD => [$first['base'], ['one.bin' => 3]],
                ]),
                $this->loaded([self::OTHER => [$first['base'], ['one.bin' => 3]]]),
            ],
        );

        $this->assertTrue($this->removeWithData(60, self::HASH) === 'erased', 'the first removal happened');
        $this->assertTrue($this->removeWithData(60, self::FOURTH) === 'erased', 'and the second');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue($this->isFile($first['files'][0]), 'the file of the torrent of one file is kept');
        $this->assertTrue(!$this->isFile($first['files'][1]), 'and the other file is deleted');
        $this->assertTrue(
            substr_count($this->logged(), ' that 1 other loaded torrent(s) still use') === 2,
            'and the log says so for each list: ' . $this->logged(),
        );
        $this->assertTrue(
            count(array_keys(array_column($this->asked(), 0), 'sizes')) === 2,
            'its sizes were asked for again: ' . json_encode($this->asked()),
        );
    }

    public function testAFatalErrorIsLoggedAndTheListsAreKept()
    {
        $this->fresh();
        $first = $this->download('First');
        $second = $this->download('Second');
        // Two lists in one pass. Whichever comes first is withdrawn, its hash
        // loaded again; by the second the daemon holds forty thousand
        // downloads, and PHP is given too little memory to read the list of
        // them.
        $downloads = [];
        for ($i = 0; $i < 40000; $i++) {
            $downloads[sprintf('%040X', $i)] = [$this->base . '/elsewhere', []];
        }
        $this->startDaemon(
            [$this->pathsReply($first), $this->eraseReply(), $this->pathsReply($second), $this->eraseReply()],
            [$this->holding(self::HASH, self::FOURTH), $this->loaded($downloads)],
        );

        $this->assertTrue($this->removeWithData(60, self::HASH) === 'erased', 'the first removal happened');
        $this->assertTrue($this->removeWithData(60, self::FOURTH) === 'erased', 'and the second');
        $out = $this->collect('', '-d memory_limit=16M', $code);

        // Its own words are not repeated here: tests/php-test.sh takes them
        // for a failure of this file. The collector's line comes from a
        // shutdown function, which some builds do not run after memory_limit
        // (PhpProcess::memoryExhaustion()): there the list waits without it.
        $php = PhpProcess::memoryExhaustion(['-d', 'display_errors=1']);
        $this->assertTrue(
            (strpos($out, 'Allowed memory size') !== false) && ($code === $php['code']),
            'the collector stops on PHP\'s memory_limit as this PHP ends a script on it (exit ' . $php['code']
            . ($php['shutdown'] ? ', shutdown functions run' : ', no shutdown function runs') . '): exit ' . $code,
        );
        $this->assertTrue(
            count(preg_grep('/\.list2$/', $this->listed())) === 1,
            'the list it stopped on is kept: ' . json_encode($this->listed()),
        );
        foreach (array_merge($first['files'], $second['files']) as $file) {
            $this->assertTrue($this->isFile($file), 'and nothing is deleted: ' . basename($file));
        }
        $this->assertTrue(
            $php['shutdown'] ? (strpos(
                $this->logged(),
                'erasedata: the collector stopped on a PHP fatal error (memory), 1 list(s) kept for a pass an hour later',
            ) !== false) : (substr_count($this->logged(), "\n") === 1),
            'and the log says so where PHP still runs shutdown functions, and has only the withdrawn list elsewhere: '
            . $this->logged(),
        );
    }

    public function testNothingIsDeletedWhenTheDaemonsAnswerCannotBeRead()
    {
        $this->fresh();
        $download = $this->download();
        $this->startDaemon([$this->pathsReply($download), $this->eraseReply()]);
        $this->assertTrue($this->removeWithData() === 'erased', 'the removal happened');

        // Answers without the shape asked for. About the downloads: a row cut
        // short after its hash, a row that does not start with a hash, a
        // number of files that is not a number. About the sizes: no row for a
        // download, a row cut short, one that does not start with a hash, a
        // size that is not a number, among as many sizes as the download has
        // files, one or two, or not, more sizes than it has files, another
        // size than the download's own for a download of one file. About the
        // paths: fewer than were asked for. Which files are in use is then
        // unknown.
        $other = [self::OTHER => [$download['base'], ['one.bin' => 3]]];
        $two = [self::OTHER => [$download['base'], ['one.bin' => 3, 'sub/two.bin' => 3]]];
        $answers = [
            [self::OTHER],
            ['not a hash', '/elsewhere', 0, 0],
            [self::OTHER, '/elsewhere', 'three', 3],
            ['holds' => $other, 'sizes' => []],
            ['holds' => $other, 'sizes' => [self::OTHER]],
            ['holds' => $other, 'sizes' => ['not a hash', '3/']],
            ['holds' => $other, 'sizes' => [self::OTHER, 'three/']],
            ['holds' => $other, 'sizes' => [self::OTHER, '3/three/']],
            ['holds' => $two, 'sizes' => [self::OTHER, '3/three/']],
            ['holds' => $other, 'sizes' => [self::OTHER, '3/3/']],
            ['holds' => $other, 'sizes' => [self::OTHER, '4/']],
            ['holds' => $other, 'paths' => []],
        ];
        foreach ($answers as $n => $answer) {
            $this->stopDaemon();
            $this->startDaemon([], [$answer]);

            $out = $this->collect();

            $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
            $this->assertTrue($this->listPublished(), 'the list is kept for a pass the daemon answers: ' . $n);
            foreach ($download['files'] as $file) {
                $this->assertTrue($this->isFile($file), 'and nothing is deleted: ' . $n . ' ' . basename($file));
            }
            $this->assertTrue(
                substr_count(
                    $this->logged(),
                    'rtorrent gave an answer that could not be read, 1 list(s) kept for the next pass',
                ) === $n + 1,
                'and the log says why: ' . $this->logged(),
            );
        }
    }

    public function testCapturedAnswersOfBothDaemonsAreRead()
    {
        foreach (['0.16.27' => [self::DOWNLOADS_016, self::SIZES_016, self::PATHS_016],
            '0.9.8' => [self::DOWNLOADS_098, self::SIZES_098, self::PATHS_098]] as $version => $captured) {
            $this->fresh();
            // S1 and S2 of #3514 at once: a season seeded twice, and one of its
            // files seeded on its own as well. The captures hold the second
            // torrent of the season and the single file; being removed is the
            // first torrent of the season.
            $show = $this->throughLink($this->download('pool/s1/Show.S01', [
                'e01.bin' => str_repeat('1', 40000),
                'e02.bin' => str_repeat('2', 50000),
                'e03.bin' => str_repeat('3', 60000),
            ]));
            $replies = [$this->pathsReply($show), $this->eraseReply()];
            foreach ($captured as $answer) {
                list($headers, $body) = explode("\r\n\r\n", $answer, 2);
                $this->assertTrue(
                    strpos($headers . "\r\n", 'Content-Length: ' . strlen($body) . "\r\n") !== false,
                    $version . ': the captured body is as long as its own Content-Length says',
                );
                // The captured downloads lie under /tmp/lab/data; here under
                // the test's own directory, through the symlink of the
                // downloads directory, reframed with the length that changes.
                $body = str_replace('/tmp/lab/data', $this->base . '/data/link', $body);
                $replies[] = FakeRtorrentDaemon::raw("Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: "
                    . strlen($body) . "\r\n\r\n" . $body);
            }
            $this->startDaemon($replies);

            $this->assertTrue($this->removeWithData() === 'erased', $version . ': the removal happened');
            $out = $this->collect();

            $this->assertTrue($out === '', $version . ': the collector runs without a diagnostic: ' . $out);
            foreach ($show['files'] as $file) {
                $this->assertTrue($this->isFile($file), $version . ': kept: ' . basename($file));
            }
            $this->assertTrue(
                strpos($this->logged(), 'erasedata: kept 3 file(s) of ' . self::HASH
                    . ' that 2 other loaded torrent(s) still use') !== false,
                $version . ': and the log counts both torrents: ' . $this->logged(),
            );
            $this->assertTrue(
                count($this->daemon->bodies()) === 5,
                $version . ': the collector asked its three questions once each',
            );
        }
    }

    private function stopDaemon()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
    }

    private function removeTree($path)
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path), ['.', '..']) as $name) {
            $entry = $path . '/' . $name;
            is_dir($entry) && !is_link($entry) ? $this->removeTree($entry) : @unlink($entry);
        }
        @rmdir($path);
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }

    private function listPath()
    {
        return $this->base . '/profile/settings/erasedata';
    }

    /**
     * setUp() runs once for the whole class, so every case starts from its
     * own download, an empty list directory and an empty log.
     */
    private function fresh()
    {
        $this->stopDaemon();
        $this->removeTree($this->listPath());
        $this->removeTree($this->base . '/data');
        @unlink($this->base . '/calls.log');
        @unlink($this->base . '/calls.log.bodies');
        @unlink($this->base . '/app.log');
    }

    /** A multi-file download on disk, as rtorrent would have written it. */
    private function download($name = 'Download', $contents = ['one.bin' => 'one', 'sub/two.bin' => 'two'])
    {
        $base = $this->base . '/data/' . $name;
        $files = [];
        foreach ($contents as $path => $content) {
            @mkdir(dirname($base . '/' . $path), 0700, true);
            file_put_contents($base . '/' . $path, $content);
            $files[] = $base . '/' . $path;
        }
        return ['base' => $base, 'files' => $files];
    }

    /**
     * $download as rtorrent names it when the downloads directory is a
     * symlink, data/link, to where it lies, data/pool: the usual layout.
     */
    private function throughLink($download)
    {
        @symlink($this->base . '/data/pool', $this->base . '/data/link');
        $named = [];
        foreach (array_merge([$download['base']], $download['files']) as $path) {
            $named[] = str_replace('/data/pool/', '/data/link/', $path);
        }
        return ['base' => array_shift($named), 'files' => $named];
    }

    /**
     * What the daemon answers the writer's first request -- the base path,
     * the multi-file flag and f.frozen_path of every file, in one multicall.
     */
    private function pathsReply($download)
    {
        return array_merge([$download['base'], 1], $download['files']);
    }

    /** What the daemon answers the erase request: one value per command. */
    private function eraseReply()
    {
        return [0, 0, 0];
    }

    /**
     * What the daemon holds when the collector asks: $downloads, hash =>
     * [directory, [path => size]] (LoadedDownloads.php).
     */
    private function loaded($downloads)
    {
        return ['holds' => $downloads];
    }

    /** The daemon holds downloads that share no file with any list. */
    private function holding(...$hashes)
    {
        $downloads = [];
        foreach ($hashes as $hash) {
            $downloads[$hash] = [$this->base . '/elsewhere', []];
        }
        return $this->loaded($downloads);
    }

    /**
     * A daemon that answers with $replies in order and, given $states, answers
     * the collector's questions from them instead (LoadedDownloads.php).
     */
    private function startDaemon($replies, $states = null)
    {
        $this->daemon = ($states === null)
            ? new FakeRtorrentDaemon($replies, $this->base . '/calls.log')
            : new FakeRtorrentDaemon(
                new LoadedDownloads($replies, $states),
                $this->base . '/calls.log',
                false,
                [__DIR__ . '/LoadedDownloads.php'],
            );
        // Kept apart from the daemon, so a collector can be pointed at the
        // address of a daemon that has since gone away.
        $this->port = $this->daemon->port();
    }

    /**
     * The collector's requests for sizes and for paths, in order: ['sizes',
     * 1] for one, ['paths', how many] for the other.
     */
    private function asked()
    {
        $asked = [];
        foreach ($this->daemon->bodies() as $body) {
            $sizes = 0;
            $paths = 0;
            foreach (LoadedDownloads::calls($body) as $call) {
                $method = str_replace('.get_', '.', $call[0]);
                $sizes += count(preg_grep('/f\.(get_)?size_bytes=/', $call[1])) ? 1 : 0;
                $paths += ($method === 'f.path') ? 1 : 0;
            }
            if ($sizes || $paths) {
                $asked[] = $sizes ? ['sizes', $sizes] : ['paths', $paths];
            }
        }
        return $asked;
    }

    /**
     * A script carrying the production configuration pointed at this test's
     * profile and daemon; the stub stands in for the settings cache that a
     * running instance would have filled from the daemon.
     */
    private function preamble()
    {
        $root = $this->repoRoot();
        return "<?php\n"
            . '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . '$_ENV[\'RU_LOG_FILE\'] = ' . var_export($this->base . '/app.log', true) . ";\n"
            . 'require_once(' . var_export($root . '/conf/config.php', true) . ");\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . (int) $this->port . ";\n"
            . '$rpcTimeOut = 5; $rpcLogCalls = false; $rpcLogFaults = false;' . "\n"
            . 'chdir(' . var_export($root . '/php', true) . "); require_once('xmlrpc.php');\n"
            . '$__r = new ReflectionClass("rTorrentSettings");'
            . ' $__s = $__r->newInstanceWithoutConstructor();'
            . ' $__s->iVersion = 0x904; $__s->aliases = array(); $__s->plugins = array();'
            . ' $__s->directory = ' . var_export($this->base, true) . '; $__s->linkExist = true;'
            . ' $__p = $__r->getProperty("theSettings");'
            // Before 8.1 a private static property cannot be written through
            // reflection without lifting its visibility first; from 8.1 the call
            // has no effect, and on 8.5 its deprecation notice would land in the
            // driver output the cases compare. The driver runs under PHP_BINARY,
            // so this process's version is the driver's.
            . (PHP_VERSION_ID < 80100 ? ' $__p->setAccessible(true);' : '')
            . ' $__p->setValue(null, $__s);' . "\n";
    }

    private function run_($driver, $options = '', &$code = null)
    {
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 ' . $options . ' '
            . escapeshellarg($driver) . ' 2>&1', $output, $code);
        return implode("\n", $output);
    }

    /**
     * erasedataRemoveWithData() for one hash, as plugins/httprpc/action.php
     * and plugins/erasedata/action.php call it for "Remove and delete data".
     * Answers "erased" or "refused": what the caller reports to the user.
     */
    private function removeWithData($socketTimeout = 60, $hash = self::HASH)
    {
        $root = $this->repoRoot();
        $driver = $this->base . '/drive-remove.php';
        file_put_contents($driver, $this->preamble()
            . 'require_once(' . var_export($root . '/plugins/erasedata/removewithdata.php', true) . ");\n"
            . '$__result = erasedataRemoveWithData(array(' . var_export($hash, true) . '), "1");' . "\n"
            . 'echo ($__result === false) ? "refused" : "erased";' . "\n");
        // rSCGITransport::sendConfigured() sets the transfer budget to
        // max(default_socket_timeout, $rpcTimeOut): config.php leaves
        // $rpcTransferTimeOut null, and the preamble sets $rpcTimeOut = 5.
        return $this->run_($driver, '-d default_socket_timeout=' . (int) $socketTimeout);
    }

    /**
     * plugins/erasedata/update.php, as rtorrent's schedule runs it: a process
     * of its own, with no argument on a single-user install. It talks to the
     * daemon too, so it carries the same configuration as the writer.
     * $prelude runs before it, $options are PHP's.
     */
    private function collect($prelude = '', $options = '', &$code = null)
    {
        $root = $this->repoRoot();
        $driver = $this->base . '/drive-collect.php';
        file_put_contents($driver, $this->preamble() . $prelude
            . 'require(' . var_export($root . '/plugins/erasedata/update.php', true) . ");\n");
        return $this->run_($driver, $options, $code);
    }

    /**
     * plugins/erasedata/erase.php for HASH, as the command a ratio group with
     * "Remove data" runs it (plugins/ratio/ratio.php): hash, force mode 1, and
     * no user on a single-user install.
     */
    private function eraseByRatio()
    {
        $root = $this->repoRoot();
        $driver = $this->base . '/drive-erase.php';
        file_put_contents($driver, $this->preamble()
            . '$argv = array("erase.php", ' . var_export(self::HASH, true) . ', "1", "");' . "\n"
            . 'require(' . var_export($root . '/plugins/erasedata/erase.php', true) . ");\n");
        return $this->run_($driver);
    }

    /**
     * is_file() for a path another process has created or removed since this
     * one last looked: PHP answers from its stat and realpath caches otherwise,
     * and on 7.4 and 8.1 that is a list the collector has already unlinked.
     */
    private function isFile($path)
    {
        clearstatcache(true, $path);
        return is_file($path);
    }

    private function isDir($path)
    {
        clearstatcache(true, $path);
        return is_dir($path);
    }

    private function listPublished($hash = self::HASH)
    {
        return $this->isFile($this->listPath() . '/' . $hash . '.list2');
    }

    /** Every file in the list directory, by name. */
    private function listed()
    {
        $names = [];
        foreach ((array) glob($this->listPath() . '/*') as $entry) {
            $names[] = basename($entry);
        }
        return $names;
    }

    private function logged()
    {
        $log = $this->base . '/app.log';
        return $this->isFile($log) ? file_get_contents($log) : '';
    }
}
