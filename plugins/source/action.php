<?php

require_once('../../php/rtorrent.php');

if (isset($_REQUEST['result'])) {
    CachedEcho::send('noty(theUILang.cantFindTorrent,"error");', "text/html");
}
if (isset($_POST['hash'])) {
    $query = urldecode($_POST['hash']);
    $hashes = explode(" ", $query);
    if (count($hashes) == 1) {
        $torrent = rTorrent::getSource($_POST['hash']);
        if ($torrent) {
            $torrent->send();
        }
    } else {
        if (!class_exists('ZipArchive')) {
            CachedEcho::send('noty("PHP module \'zip\' is not installed.","error");', "text/html");
        }
        foreach ($hashes as $hash) {
            $fname = rTorrent::getSourceFile($hash);
            if ($fname) {
                $filepaths[] = $fname;
                $files[] = new Torrent($fname);
            }
        }
        if (isset($files)) {
            ignore_user_abort(true);
            set_time_limit(0);

            $fn = 1;
            $zippath = FileUtil::getTempFilename('source', 'zip');

            $zip = new ZipArchive();
            $zip->open($zippath, ZipArchive::CREATE);
            foreach (array_combine($filepaths, $files) as $filepath => $file) {
                $filename = $file->info['name'] . "-" . $fn . ".torrent";
                $zip->addFile($filepath, $filename);

                $fn++;
            }
            $zip->close();

            if (SendFile::send($zippath, "application/zip", null, false)) {
                unlink($zippath);
            }

            exit();
        }
    }
}
header("HTTP/1.0 302 Moved Temporarily");
header("Location: action.php?result=0");
