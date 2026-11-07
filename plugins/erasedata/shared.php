<?php

// The files of a download can also be the files of another download rtorrent
// holds: the same data seeded under another hash (cross-seeding, the same
// release uploaded to two trackers), one file of a pack seeded on its own, a
// symlink to it. Deleting the data of one then deletes the data of the other,
// which goes on showing as complete until its next hash check. The collector
// (update.php) keeps such files out of what it deletes.
//
// No answer here carries every path rtorrent holds. It is asked for its
// downloads; once a pass for the sizes of their files, as one string a
// download, about ten bytes a file; and then for the paths of only the files
// that can be ours, those of one of our sizes.
// Paths are asked for in batches, so one answer stays within a few megabytes,
// where rtorrent 0.9.8 aborts on an answer past 100 MiB and PHP's
// memory_limit is often 128 MB.
//
// The caller must have already loaded php/xmlrpc.php.

// At most this many paths in one request: a path takes at most six times its
// length once escaped, and a path a file can have is at most 4096 bytes long.
if (!defined('ERASEDATA_PATHS_PER_REQUEST')) {
    define('ERASEDATA_PATHS_PER_REQUEST', 256);
}

if (!function_exists('erasedataLoadedDownloads')) {
    // The values rtorrent answers $req with, or null when it will not say;
    // $why then tells which it was: "no-answer" (no connection, no reply, or a
    // reply the transport refused) or "fault" (it refused the question).
    function erasedataAnswer($req, &$why)
    {
        $req->important = false;
        if (!$req->success()) {
            $why = ($req->fault && ($req->transportFailure === null)) ? "fault" : "no-answer";
            return(null);
        }
        return($req->val);
    }

    // The downloads rtorrent holds: hash => [directory, number of files,
    // size]. Or null, with $why as erasedataAnswer() sets it, or "unreadable"
    // for an answer that does not have the shape asked for.
    function erasedataLoadedDownloads(&$why = null)
    {
        $val = erasedataAnswer(new rXMLRPCRequest(new rXMLRPCCommand("d.multicall", [
            "default",
            getCmd("d.get_hash="),
            getCmd("d.get_directory="),
            getCmd("d.get_size_files="),
            getCmd("d.get_size_bytes="),
        ])), $why);
        if ($val === null) {
            return(null);
        }
        // An answer without whole rows was not read from the shape asked
        // for. A row whose hash or number of files is wrong, or the size of
        // a download of one file, does not match the sizes
        // (erasedataClaimedFiles()), and is not read either.
        if (count($val) % 4) {
            $why = "unreadable";
            return(null);
        }
        $ret = [];
        foreach (array_chunk($val, 4) as $row) {
            // run() escapes what it reads for pasting into a command; this is
            // a path to stat().
            $ret[strtoupper($row[0])] = [rXMLRPCRequest::unescapeValue($row[1]), intval($row[2]), $row[3]];
        }
        return($ret);
    }

    // The sizes of the files of every download rtorrent holds, hash =>
    // "size/size/.../" in rtorrent's order of files. Or null, with $why as
    // erasedataLoadedDownloads() sets it.
    function erasedataFileSizes(&$why = null)
    {
        $val = erasedataAnswer(new rXMLRPCRequest(new rXMLRPCCommand("d.multicall", [
            "default",
            getCmd("d.get_hash="),
            getCmd("cat") . '="$' . getCmd("f.multicall=") . ',' . getCmd("f.get_size_bytes=") . ','
                . getCmd("cat=/") . '"',
        ])), $why);
        if ($val === null) {
            return(null);
        }
        if (count($val) % 2) {
            $why = "unreadable";
            return(null);
        }
        $ret = [];
        foreach (array_chunk($val, 2) as $row) {
            // Anything but digits and "/" was not read from the shape asked
            // for either.
            if (strspn($row[1], '0123456789/') != strlen($row[1])) {
                $why = "unreadable";
                return(null);
            }
            $ret[strtoupper($row[0])] = $row[1];
        }
        return($ret);
    }

    // What sizes are compared by: a size modulo 2^32, as a string. A 32-bit
    // PHP reads a size past 2 GiB from stat() as its low 32 bits, signed, so
    // that is all of it there is to compare; a size rtorrent reports, a
    // string, is brought to the same.
    function erasedataSizeKey($size)
    {
        return(sprintf('%.0f', fmod((float) $size + 4294967296.0, 4294967296.0)));
    }

    // Asks for the paths of $files, each [hash, index of the file], and adds
    // to $claimed which of $inodes they reach. False, with $why as
    // erasedataLoadedDownloads() sets it, when rtorrent will not say.
    function erasedataClaimPaths($files, $downloads, $inodes, &$claimed, &$why)
    {
        $req = new rXMLRPCRequest();
        foreach ($files as $file) {
            $req->addCommand(new rXMLRPCCommand(getCmd("f.get_path"), $file));
        }
        $val = erasedataAnswer($req, $why);
        if ($val === null) {
            return(false);
        }
        if (count($val) != count($files)) {
            $why = "unreadable";
            return(false);
        }
        foreach ($files as $n => $file) {
            $hash = $file[0];
            $path = $downloads[$hash][0] . '/' . rXMLRPCRequest::unescapeValue($val[$n]);
            // stat() for a symlink to one of ours, lstat() for one of ours
            // that is a symlink.
            $keys = [];
            foreach ([@stat($path), @lstat($path)] as $st) {
                if ($st !== false) {
                    $keys[$st['dev'] . ':' . $st['ino']] = true;
                }
            }
            foreach (array_keys($keys) as $key) {
                foreach ($inodes[$key] ?? [] as $own => $nlink) {
                    if (($nlink == 1) || erasedataSameName($path, $own)) {
                        $claimed[$own][$hash] = true;
                    }
                }
            }
        }
        return(true);
    }

    // Whether $path, which reaches the inode of $own, reaches it through
    // $own's own name rather than through another hard link: the same name in
    // the same directory once symlinks are resolved. The directory is told by
    // its inode, because realpath() gives a directory mounted twice two paths.
    function erasedataSameName($path, $own)
    {
        $real = realpath($path);
        if ($real === false) {
            // Gone since stat() found it, or a path PHP gives up on that the
            // system still takes: keep ours rather than guess.
            return(true);
        }
        $dir = @stat(dirname($real));
        $ownDir = @stat(dirname($own));
        return((basename($real) === basename($own)) && ($dir !== false) && ($ownDir !== false)
            && ($dir['dev'] == $ownDir['dev']) && ($dir['ino'] == $ownDir['ino']));
    }

    // Which of $ownPaths a download in $downloads still uses: own path =>
    // [hash => true] for each download that does. Or null, with $why as
    // erasedataLoadedDownloads() sets it, when rtorrent will not say.
    // $downloads is what erasedataLoadedDownloads() answers, without the
    // download being deleted. $sizes keeps what erasedataFileSizes() answered
    // from one call to the next, so a pass asks for it once: start it as
    // null. It is asked for again when a download holds another number of
    // files, or one file of another size.
    //
    // A file is in use when another download reaches its inode, whichever
    // path it takes: the same path, a symlink, a directory mounted twice. What
    // unlink() removes is the name, so a symlink of ours is in use only when
    // another download reaches the symlink itself.
    //
    // A hard link is not in use: unlinking our name leaves the other download
    // its own name and its data, which is how #833 suggested seeding the same
    // data twice. So a file with more than one link is in use only when the
    // other download reaches it through our very name.
    function erasedataClaimedFiles($ownPaths, $downloads, &$sizes, &$why = null)
    {
        $inodes = [];
        $ownSizes = [];
        foreach ($ownPaths as $own) {
            // The inode is the one unlink() removes; the size is the one a
            // download reading through the name sees, of a symlink the size
            // of what it points at.
            $st = @lstat($own);
            if ($st === false) {
                continue;
            }
            $inodes[$st['dev'] . ':' . $st['ino']][$own] = $st['nlink'];
            $seen = @stat($own);
            if ($seen !== false) {
                $ownSizes[erasedataSizeKey($seen['size'])] = true;
            }
        }

        // The sizes are asked for again, the old ones dropped first, when a
        // download is new, holds another number of files, or holds one file
        // of another size: a magnet link's stub holds one file until the
        // metadata arrives, and the torrent that replaces it under the same
        // hash can hold one file too. Still so in the new answer, it changed
        // between the two questions.
        for ($asked = false; ; $asked = true) {
            $stale = false;
            foreach ($downloads as $hash => $download) {
                if (!isset($sizes[$hash]) || (substr_count($sizes[$hash], '/') != $download[1])
                    || (($download[1] == 1) && ($sizes[$hash] !== $download[2] . '/'))) {
                    $stale = true;
                    break;
                }
            }
            if (!$stale) {
                break;
            }
            if ($asked) {
                $why = "unreadable";
                return(null);
            }
            $sizes = null;
            $sizes = erasedataFileSizes($why);
            if ($sizes === null) {
                return(null);
            }
        }

        // A candidate is a file of one of our sizes. A file another download
        // has written to has the size that download declares: libtorrent
        // resizes a file to it when it first opens it for writing.
        $claimed = [];
        $files = [];
        foreach (array_keys($downloads) as $hash) {
            $list = $sizes[$hash];
            for ($i = 0, $at = 0; ($end = strpos($list, '/', $at)) !== false; $i++, $at = $end + 1) {
                if (!isset($ownSizes[erasedataSizeKey(substr($list, $at, $end - $at))])) {
                    continue;
                }
                $files[] = [$hash, $i];
                if (count($files) == ERASEDATA_PATHS_PER_REQUEST) {
                    if (!erasedataClaimPaths($files, $downloads, $inodes, $claimed, $why)) {
                        return(null);
                    }
                    $files = [];
                }
            }
        }
        if (count($files) && !erasedataClaimPaths($files, $downloads, $inodes, $claimed, $why)) {
            return(null);
        }
        return($claimed);
    }

    // Whether $path is $dir or lies under it; $dir without a trailing slash.
    function erasedataInside($path, $dir)
    {
        return(strpos($path . '/', $dir . '/') === 0);
    }

    // The downloads of $downloads that lie under the base path of the one
    // being deleted, hash => true: $real is that base as it is on disk,
    // $listed as its list names it. Such a download can have files there of
    // no size of ours, or none yet, so it is told by its directory, as
    // rtorrent names it -- a path that runs through a symlink under the base
    // goes with the base all the same -- and resolved. A relative directory,
    // which only rtorrent's own working directory places, counts as under it.
    function erasedataDownloadsUnder($real, $listed, $downloads)
    {
        $listed = rtrim($listed, '/');
        $under = [];
        foreach ($downloads as $hash => $download) {
            $dir = $download[0];
            if (($dir === '') || ($dir[0] != '/') || erasedataInside($dir, $listed) || erasedataInside($dir, $real)
                || erasedataInside((string) realpath($dir), $real)) {
                $under[$hash] = true;
            }
        }
        return($under);
    }
}
