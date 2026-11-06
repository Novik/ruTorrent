<?php

require_once(__DIR__ . '/../../php/TestCase.php');

/**
 * The collector deletes what is inside the download and nothing else, whether
 * it is deleting the download's files or the whole base path.
 *
 * Every entry of a list is a path the collector unlinks, and with force
 * deletion the base path is removed whole with everything under it. What the
 * paths are made of came from the torrent -- from path elements its publisher
 * chose. Deciding whether one of them lies inside the download by looking at
 * the text of it is not enough: unlink(), rmdir() and the walk down a
 * directory are handed the path as one string and follow every symlink in it,
 * so one directory of the download being a link is a name inside the download
 * deciding what is deleted outside it. The force path is where that costs
 * most, because it visits nothing and simply empties whatever it reaches.
 *
 * tests/plugins/erasedata/ListCollectorTest.php covers the entries that leave
 * the download by their text. This covers the ones that leave it through the
 * filesystem, with force deletion enabled, which is the mode that removes a
 * directory rather than named files.
 *
 * update.php is copied rather than included: it runs its work at include time
 * and declares functions, so it can be entered once per process, and its
 * requires need answering by stubs -- the real php/util.php pulls in the
 * configuration, and the real php/xmlrpc.php a live rtorrent.
 */
class ForceDeleteContainmentTest extends TestCase
{
    /** A download the stub daemon holds; never one a list is for. */
    public const OTHER = '89ABCDEF0123456789ABCDEF0123456789ABCDEF';

    private $tree;
    private $listPath;
    private $log;

    public function setUp()
    {
        $this->tree = sys_get_temp_dir() . '/rutorrent-forcedelete-' . getmypid();
        $this->wipe();
        mkdir($this->tree . '/plugins/erasedata', 0777, true);
        mkdir($this->tree . '/php', 0777, true);
        mkdir($this->tree . '/settings/erasedata', 0777, true);
        $this->listPath = $this->tree . '/settings/erasedata';
        $this->log = $this->tree . '/collector.log';

        $source = dirname(dirname(dirname(__DIR__))) . '/plugins/erasedata/update.php';
        $target = $this->tree . '/plugins/erasedata/update.php';
        copy($source, $target);
        if (hash_file('sha256', $source) !== hash_file('sha256', $target)) {
            throw new Exception('could not copy update.php');
        }

        // update.php asks rtorrent which downloads it holds, and about their
        // files, before it applies a list, through shared.php, which is copied
        // beside it. The daemon this stands for holds none unless a case says
        // otherwise (loaded()), and answers as LoadedDownloads.php does; what
        // the collector does when the daemon holds the same hash, or does not
        // answer, is pinned in UnconfirmedEraseTest.php against the real RPC
        // layer.
        $source = dirname(dirname(dirname(__DIR__))) . '/plugins/erasedata/shared.php';
        $target = $this->tree . '/plugins/erasedata/shared.php';
        copy($source, $target);
        if (hash_file('sha256', $source) !== hash_file('sha256', $target)) {
            throw new Exception('could not copy shared.php');
        }
        file_put_contents($this->tree . '/php/xmlrpc.php', '<?php
			require_once(' . var_export(__DIR__ . '/LoadedDownloads.php', true) . ');
			class rXMLRPCCommand
			{
				public $command; public $params;
				public function __construct($c, $p = null) { $this->command = $c; $this->params = (array) $p; }
			}
			class rXMLRPCRequest
			{
				public $val = array(); public $important = true; private $calls = array();
				public function __construct($cmd = null) { if ($cmd) { $this->addCommand($cmd); } }
				public function addCommand($cmd) { $this->calls[] = array($cmd->command, $cmd->params); }
				public function success()
				{
					$holds = json_decode((string) @file_get_contents(getenv("ERASEDATA_TEST_LOADED")), true) ?: array();
					$this->val = array_map("strval", LoadedDownloads::answer($holds, $this->calls) ?: array());
					return(true);
				}
				public static function unescapeValue($v) { return($v); }
			}
			function getCmd($c) { return($c); }
		');

        // Force deletion is off by default and is what these cases are about,
        // so the stub takes it from the environment rather than pinning it.
        file_put_contents($this->tree . '/php/util.php', '<?php
			class FileUtil
			{
				public static function getSettingsPath()
				{
					return(getenv("ERASEDATA_TEST_SETTINGS"));
				}
				public static function makeDirectory($dir)
				{
					return(@mkdir($dir, 0777, true));
				}
				public static function getPluginConf($name)
				{
					return(\'$erasedebug_enabled = \'.(getenv("ERASEDATA_TEST_DEBUG") === "0" ? "false" : "true")
						.\'; $enableForceDeletion = \'.
						(getenv("ERASEDATA_TEST_FORCE") === "1" ? "true" : "false").\';\');
				}
				public static function toLog($msg)
				{
					@file_put_contents(getenv("ERASEDATA_TEST_LOG"), $msg."\n", FILE_APPEND);
				}
			}
		');
    }

    public function tearDown()
    {
        $this->wipe();
    }

    // -- the control ---------------------------------------------------------

    /**
     * Force deletion still does what it is for. Without this every refusal
     * below could be a collector that deletes nothing at all.
     */
    public function testForceDeletionStillRemovesTheDownloadWhole()
    {
        $this->reset();
        $base = $this->tree . '/data/GoodPack';
        $own = $this->file($base . '/sub/one.txt');
        // Force deletion removes everything under the base, including what the
        // list does not name -- that is the whole point of the mode.
        $unlisted = $this->file($base . '/sub/left_over.part');
        $neighbour = $this->file($this->tree . '/data/Neighbour/keep.txt', 'must survive');

        $this->queue('A', [$own], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!is_dir($base), 'the download directory is gone');
        $this->assertTrue(!is_file($unlisted), 'and what was in it that the list did not name');
        $this->assertTrue(is_file($neighbour), 'and the directory beside it is untouched');
    }

    // -- what the force path must not reach ----------------------------------

    /**
     * A directory of the download that is a symlink. The recursion must remove
     * the link as the name it is and leave what it points at.
     */
    public function testForceDeletionDoesNotEmptyWhatASymlinkPointsAt()
    {
        $this->reset();
        $base = $this->tree . '/data/LinkPack';
        $own = $this->file($base . '/one.txt');
        $outside = $this->tree . '/elsewhere';
        $sentinel = $this->file($outside . '/sentinel.txt', 'must survive');
        symlink($outside, $base . '/away');
        // Another loaded download uses that file. The link is removed as a
        // name, so it does not keep the base from being removed whole.
        $this->loaded([self::OTHER => [$outside, ['sentinel.txt' => 12]]]);

        $this->queue('B', [$own], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            is_file($sentinel),
            'a file where a directory of the download points is still there',
        );
        $this->assertTrue(
            is_dir($outside),
            'and so is the directory it is in',
        );
        $this->assertTrue(
            !is_dir($base) && !is_link($base . '/away'),
            'while the download, the symlink included, is gone',
        );
    }

    /**
     * A base path that is itself a symlink. It is removed whole in this mode,
     * and a link is a name that belongs to whatever it points at.
     */
    public function testABasePathThatIsASymlinkIsRefused()
    {
        $this->reset();
        $outside = $this->tree . '/pointed_at';
        $sentinel = $this->file($outside . '/sentinel.txt', 'must survive');
        $base = $this->tree . '/data/LinkedBase';
        @mkdir(dirname($base), 0777, true);
        symlink($outside, $base);

        $this->queue('C', [$base . '/sentinel.txt'], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(is_file($sentinel), 'what the base points at is still there');
        $this->assertTrue(is_link($base), 'and the link itself is left alone');
        $this->assertTrue(
            strpos($this->logged(), 'REFUSED') !== false,
            'and the collector says it refused the list',
        );
    }

    /**
     * Force deletion visits none of the entries, so the entries are what ties
     * the base a list declares to the download it claims to be. A list whose
     * entries are not files of its own base is not acted on at all.
     */
    public function testForceDeletionIsAbandonedWhenAnEntryIsNotUnderTheBase()
    {
        $this->reset();
        $base = $this->tree . '/data/MixedPack';
        $own = $this->file($base . '/one.txt');
        $victim = $this->file($this->tree . '/victim/DELETE_ME', 'must survive');

        $this->queue('D', [$own, $victim], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(is_file($victim), 'the entry outside the download is still there');
        $this->assertTrue(
            is_dir($base) && is_file($own),
            'and the download is not removed either -- the whole list is refused',
        );
        $this->assertTrue(
            strpos($this->logged(), 'force delete of ' . $base . ' abandoned') !== false,
            'and the collector says why',
        );
    }

    /** A list naming no file of its own base gives the base nothing to stand on. */
    public function testForceDeletionIsRefusedWhenNoEntryIsUnderTheBase()
    {
        $this->reset();
        $base = $this->tree . '/data/EmptyClaim';
        $this->file($base . '/one.txt');

        // Only the base line names the download; the entry is somebody else's.
        $victim = $this->file($this->tree . '/victim/OTHER', 'must survive');
        $this->queue('E', [$victim], $base, '1');
        $this->collect();

        $this->assertTrue(is_file($victim), 'the entry outside the download is still there');
        $this->assertTrue(is_dir($base), 'and the base it declared is still there');
    }

    // -- what the ordinary path must not reach -------------------------------

    /**
     * Without force deletion the entries are unlinked one at a time, and an
     * entry reached through a symlinked directory of the download is outside
     * it however the text of the path reads.
     */
    public function testAnEntryReachedThroughASymlinkIsNotUnlinked()
    {
        $this->reset();
        $base = $this->tree . '/data/SlipPack';
        $own = $this->file($base . '/one.txt');
        $outside = $this->tree . '/next_door';
        $sentinel = $this->file($outside . '/secret.txt', 'must survive');
        symlink($outside, $base . '/away');

        // The text reads as a file of the download, and it is not one.
        $this->queue('F', [$own, $base . '/away/secret.txt'], $base, '1', '1');
        $out = $this->collect(false);

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            is_file($sentinel),
            'the file the symlink leads to is still there',
        );
        $this->assertTrue(
            strpos($this->logged(), 'REFUSED, not under ' . $base) !== false,
            'and the collector says it refused it',
        );
        $this->assertTrue(!is_file($own), 'while the download\'s own file is deleted');
    }

    /**
     * A directory of the download that is a regular file has nothing under it,
     * so an entry claiming to be under it is not a file of the download.
     */
    public function testAnEntryUnderARegularFileIsNotUnlinked()
    {
        $this->reset();
        $base = $this->tree . '/data/FlatPack';
        $own = $this->file($base . '/one.txt');
        $blocker = $this->file($base . '/sub', 'a regular file, not a directory');

        $this->queue('G', [$own, $base . '/sub/two.txt'], $base, '1', '1');
        $this->collect(false);

        $this->assertTrue(
            strpos($this->logged(), 'REFUSED, not under ' . $base) !== false,
            'an entry under a regular file is refused',
        );
        $this->assertTrue(!is_file($own), 'while the download\'s own file is deleted');
        $this->assertTrue(
            file_get_contents($blocker) === 'a regular file, not a directory',
            'and the file that is in the way is left as it is',
        );
    }

    // -- what another loaded download still uses -----------------------------

    /**
     * Another download rtorrent holds lies inside this one's directory -- a
     * torrent of the extras saved into the season's folder -- and has a file
     * there. Removing the base whole would take that file with it, so only the
     * download's own files are deleted. Its directory decides, not its size.
     */
    public function testDeletePathLeavesAnotherLoadedDownloadsFileUnderTheBase()
    {
        $this->reset();
        $base = $this->tree . '/data/Folder';
        $own = [$this->file($base . '/e01.bin', 'one'), $this->file($base . '/e02.bin', 'two')];
        $theirs = $this->file($base . '/extras/e03.bin', 'three');
        // It has not finished the file, which is not yet the size it declares.
        $this->loaded([self::OTHER => [$base . '/extras', ['e03.bin' => 999]]]);

        $this->queue('C', $own, $base, '1');
        // Debug logging off, as it ships: the operator is told all the same.
        $out = $this->collect(true, false);

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            !is_file($own[0]) && !is_file($own[1]),
            'the files of the download are deleted',
        );
        $this->assertTrue(is_file($theirs), 'the file of the other download is still there');
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: 1 other loaded torrent(s) lie in the base path of C'
                . ' or use its files; only its own files are deleted') !== false,
            'and the log says why the base was not removed: ' . $this->logged(),
        );
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: kept ') === false,
            'no file of the download itself was kept: ' . $this->logged(),
        );
    }

    /**
     * The other download reaches a file under the base by another path -- its
     * directory is a symlink to the base. As written its directory is
     * elsewhere; resolved, it is the base.
     */
    public function testDeletePathSeesAFileAnotherDownloadReachesByAnotherPath()
    {
        $this->reset();
        $base = $this->tree . '/data/AliasedFolder';
        $own = $this->file($base . '/e01.bin', 'one');
        $theirs = $this->file($base . '/e03.bin', 'three');
        symlink($base, $this->tree . '/data/Alias');
        $this->loaded([self::OTHER => [$this->tree . '/data/Alias', ['e03.bin' => 5]]]);

        $this->queue('D', [$own], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!is_file($own), 'the file of the download is deleted');
        $this->assertTrue(is_file($theirs), 'the file the other download reaches is still there');
    }

    /**
     * A hard link is not a file in use: the other download keeps its own
     * name, so the base is still removed whole. Its directory, beside the
     * base, has a name the base's own begins with.
     */
    public function testDeletePathStillRemovesTheBaseWhenAnotherDownloadHasAHardLink()
    {
        $this->reset();
        $base = $this->tree . '/data/LinkedFolder';
        $own = $this->file($base . '/e01.bin', 'one');
        $theirs = $this->tree . '/data/LinkedFolder2/e01.bin';
        @mkdir(dirname($theirs), 0777, true);
        link($own, $theirs);
        $this->loaded([self::OTHER => [dirname($theirs), ['e01.bin' => 3]]]);

        $this->queue('E', [$own], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!is_dir($base), 'the download directory is gone');
        $this->assertTrue(
            is_file($theirs) && (file_get_contents($theirs) === 'one'),
            'and the other download keeps its name and its data',
        );
        $this->assertTrue(
            strpos($this->logged(), 'other loaded torrent') === false,
            'with nothing kept: ' . $this->logged(),
        );
    }

    /**
     * A single-file download is its file, so Delete Path has no directory to
     * remove whole: the file another download uses is kept as in the default
     * mode.
     */
    public function testDeletePathKeepsTheFileOfASingleFileDownloadInUse()
    {
        $this->reset();
        $own = $this->file($this->tree . '/data/single.bin', 'single');
        @mkdir($this->tree . '/data/Twin', 0777, true);
        symlink($own, $this->tree . '/data/Twin/single.bin');
        $this->loaded([self::OTHER => [$this->tree . '/data/Twin', ['single.bin' => 6]]]);

        $this->queue('J', [$own], $own, '0');
        $out = $this->collect(true, false);

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(is_file($own), 'the file the other download uses is kept');
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: kept 1 file(s) of J that 1 other loaded torrent(s) still use') !== false,
            'and the log says so: ' . $this->logged(),
        );
        $this->assertTrue(strpos($this->logged(), 'base path') === false, 'and nothing about a base path');
    }

    /**
     * A symlink under the base is removed as the name it is. A download that
     * uses what it points at, by that file's own path, keeps its file, so the
     * link does not stop the base from being removed whole.
     */
    public function testDeletePathRemovesASymlinkWhoseTargetAnotherDownloadUses()
    {
        $this->reset();
        $base = $this->tree . '/data/Linking';
        $own = $this->file($base . '/e01.bin', 'one');
        $target = $this->file($this->tree . '/library/movie.bin', 'target');
        symlink($target, $base . '/movie.bin');
        // And one that points at nothing.
        symlink($this->tree . '/library/gone.bin', $base . '/gone.bin');
        $this->loaded([self::OTHER => [$this->tree . '/library', ['movie.bin' => 6]]]);

        $this->queue('K', [$own], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!is_dir($base), 'the download directory is gone, the symlink with it');
        $this->assertTrue(
            is_file($target) && (file_get_contents($target) === 'target'),
            'and the file it pointed at, which the other download uses, is still there',
        );
        $this->assertTrue(
            strpos($this->logged(), 'other loaded torrent') === false,
            'with nothing kept: ' . $this->logged(),
        );
    }

    /**
     * A download whose directory is a symlink to the base reaches a symlink
     * under the base by its name: its directory, resolved, is the base.
     */
    public function testDeletePathKeepsASymlinkAnotherDownloadReachesThroughAnAliasOfTheBase()
    {
        $this->reset();
        $base = $this->tree . '/data/Pooled';
        $own = $this->file($base . '/e01.bin', 'one');
        $target = $this->file($this->tree . '/library/clip.bin', 'library');
        symlink($target, $base . '/clip.bin');
        symlink($base, $this->tree . '/data/PoolAlias');
        $this->loaded([self::OTHER => [$this->tree . '/data/PoolAlias', ['clip.bin' => 7]]]);

        $this->queue('L', [$own], $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!is_file($own), 'the file of the download is deleted');
        $this->assertTrue(is_link($base . '/clip.bin'), 'the symlink the other download reaches is kept');
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: 1 other loaded torrent(s) lie in the base path of L'
                . ' or use its files; only its own files are deleted') !== false,
            'and the log says why the base was not removed: ' . $this->logged(),
        );
    }

    /**
     * The downloads directory is a symlink into a storage pool, the usual
     * layout, and rtorrent names every directory through it. Another torrent
     * of the same folder has a file there it has not started writing: it is
     * not the size it declares, and it is not in the list.
     */
    public function testDeletePathUnderASymlinkedRootLeavesAnotherDownloadsUnfinishedFile()
    {
        $this->reset();
        @mkdir($this->tree . '/pool', 0777, true);
        symlink($this->tree . '/pool', $this->tree . '/downloads');
        $base = $this->tree . '/downloads/Folder';
        $own = [$this->file($base . '/e01.bin', 'one'), $this->file($base . '/e02.bin', 'two')];
        $theirs = $this->file($base . '/e03.bin', '');
        $this->loaded([self::OTHER => [$base, ['e03.bin' => 999]]]);

        $this->queue('M', $own, $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(!is_file($own[0]) && !is_file($own[1]), 'the files of the download are deleted');
        $this->assertTrue(is_file($theirs), 'the file of the other download is still there');
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: 1 other loaded torrent(s) lie in the base path of M') !== false,
            'and the log says why the base was not removed: ' . $this->logged(),
        );
    }

    /**
     * Each of these directories counts as lying in the base, so the base is
     * not removed whole and what else is in it stays. Two run through a
     * symlink under the base, named through the downloads directory's
     * symlink and through the pool it points at: resolved, they lie
     * elsewhere, but removing the base would take the link, and with it the
     * download's way to its files. Two cannot be placed: a directory
     * relative to rtorrent's own working directory, and none at all.
     */
    public function testDeletePathSeesADownloadWhoseDirectoryRunsThroughTheBase()
    {
        @mkdir($this->tree . '/pool2', 0777, true);
        symlink($this->tree . '/pool2', $this->tree . '/downloads2');
        $directories = [
            $this->tree . '/downloads2/Folder/away/Theirs',
            $this->tree . '/pool2/Folder/away/Theirs',
            'Folder/extras',
            '',
        ];
        foreach ($directories as $n => $directory) {
            $this->reset();
            $base = $this->tree . '/downloads2/Folder';
            $own = $this->file($base . '/e01.bin', 'one');
            $notes = $this->file($base . '/notes.txt', 'notes');
            $theirs = $this->file($this->tree . '/away2/Theirs/e03.bin', 'three');
            @symlink($this->tree . '/away2', $base . '/away');
            $this->loaded([self::OTHER => [$directory, ['e03.bin' => 5]]]);

            // The list may name its base with a trailing slash, which the
            // collector reads as without.
            $this->queue('N', [$own], $base . '/', '1');
            $out = $this->collect();

            $this->assertTrue($out === '', $n . ': the collector runs without a diagnostic: ' . $out);
            $this->assertTrue(!is_file($own), $n . ': the file of the download is deleted');
            $this->assertTrue(
                is_file($notes) && is_link($base . '/away') && is_file($theirs),
                $n . ': and the rest of the base is still there, the symlink with it',
            );
            $this->assertTrue(
                strpos($this->logged(), 'erasedata: 1 other loaded torrent(s) lie in the base path of N') !== false,
                $n . ': and the log says why: ' . $this->logged(),
            );
        }
    }

    /**
     * A season pack above the base uses this season's files: they are kept,
     * and so is the base around them.
     */
    public function testDeletePathKeepsTheFilesADownloadAboveTheBaseUses()
    {
        $this->reset();
        $base = $this->tree . '/data/Show/Season1';
        $own = [$this->file($base . '/e01.bin', 'one'), $this->file($base . '/e02.bin', 'two')];
        $this->loaded([self::OTHER => [$this->tree . '/data/Show', ['Season1/e01.bin' => 3, 'extras.nfo' => 9]]]);

        $this->queue('O', $own, $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(is_file($own[0]), 'the file the season pack uses is kept');
        $this->assertTrue(!is_file($own[1]), 'and the other is deleted');
    }

    /**
     * The log counts torrents, not files: here four files are kept, used by
     * two torrents -- the same release on another tracker, and a season pack
     * above the base -- and a third torrent lies in the base.
     */
    public function testDeletePathCountsTheTorrentsThatKeepTheBase()
    {
        $this->reset();
        $base = $this->tree . '/data/Counted/Season1';
        $own = [];
        $twin = [];
        foreach (['e01', 'e02', 'e03', 'e04'] as $name) {
            $own[] = $this->file($base . '/' . $name . '.bin', 'abc');
            $twin[$name . '.bin'] = 3;
        }
        $this->loaded([
            self::OTHER => [$base, $twin],
            '0123456789ABCDEF0123456789ABCDEF01234567' => [$base . '/extras', ['x.bin' => 7]],
            'FEDCBA9876543210FEDCBA9876543210FEDCBA98' => [$this->tree . '/data/Counted', ['Season1/e01.bin' => 3]],
        ]);

        $this->queue('P', $own, $base, '1');
        $out = $this->collect();

        $this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: kept 4 file(s) of P that 2 other loaded torrent(s) still use') !== false,
            'the log counts the files kept and the torrents that use them: ' . $this->logged(),
        );
        $this->assertTrue(
            strpos($this->logged(), 'erasedata: 3 other loaded torrent(s) lie in the base path of P'
                . ' or use its files; only its own files are deleted') !== false,
            'and the torrents that keep the base: ' . $this->logged(),
        );
    }

    /** Every list is still consumed, refused or not. */
    public function testEveryListFileIsStillConsumed()
    {
        $this->reset();
        $base = $this->tree . '/data/Consumed';
        $this->file($base . '/a');
        $this->queue('H', [$base . '/a'], $base, '1');
        $this->queue('I', [$this->tree . '/victim/elsewhere'], '/', '1');
        $this->collect();

        $this->assertTrue(
            count(glob($this->listPath . '/*.list2')) === 0,
            'a refused list is removed from the queue rather than retried forever',
        );
    }

    private function wipe()
    {
        if (!is_dir($this->tree)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tree, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->tree);
    }

    // -- helpers ------------------------------------------------------------

    private function reset()
    {
        foreach (glob($this->listPath . '/*') as $f) {
            @unlink($f);
        }
        @unlink($this->log);
        @unlink($this->tree . '/loaded.json');
    }

    /** The downloads the stub daemon holds, hash => [directory, [path => size]]. */
    private function loaded($downloads)
    {
        file_put_contents($this->tree . '/loaded.json', json_encode($downloads));
    }

    private function file($path, $content = 'x')
    {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
        return $path;
    }

    /** Write a list file exactly as removewithdata.php writes one. */
    private function queue($hash, $files, $base, $multi, $force = '2')
    {
        $lines = $files;
        $lines[] = $base;
        $lines[] = $multi;
        $lines[] = $force;
        file_put_contents($this->listPath . '/' . $hash . '.list2', implode("\n", $lines) . "\n");
    }

    /** Run the shipped collector the way production runs it: its own process. */
    private function collect($force = true, $debug = true)
    {
        $command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 update.php';
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tree . '/plugins/erasedata',
            [
                'ERASEDATA_TEST_SETTINGS' => $this->tree . '/settings',
                'ERASEDATA_TEST_LOG' => $this->log,
                'ERASEDATA_TEST_FORCE' => $force ? '1' : '0',
                'ERASEDATA_TEST_DEBUG' => $debug ? '1' : '0',
                'ERASEDATA_TEST_LOADED' => $this->tree . '/loaded.json',
                'PATH' => getenv('PATH'),
            ],
        );
        if (!is_resource($process)) {
            throw new Exception('could not start the collector');
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return $out;
    }

    private function logged()
    {
        return is_file($this->log) ? file_get_contents($this->log) : '';
    }
}
