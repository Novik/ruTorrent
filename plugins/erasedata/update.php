<?php

if (count( $argv ) > 1) {
    $_SERVER['REMOTE_USER'] = $argv[1];
}

require_once(dirname(__FILE__)."/../../php/util.php");
eval(getPluginConf('erasedata'));

function eLog($str) {
    global $erasedebug_enabled;
    if ($erasedebug_enabled) {
        toLog("erasedata: " . $str );
    }
}

function unlinkAlt($file) {
    global $erasetest_enabled;
    if ($erasetest_enabled || @unlink($file)) {
        eLog('Successfully delete file '.$file);
    } else {
        eLog('FAIL delete file '.$file);
    }
}

function rmdirAlt($dir) {
    global $erasetest_enabled;
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != '.' && $object != '..') {
                if (filetype($dir . '/' . $object) == 'dir') {
                    rmdirAlt($dir . '/' . $object);
                } else {
                    unlinkAlt($dir . '/' . $object);
                }
            }
        }
        reset($objects);
        if ($erasetest_enabled || @rmdir($dir)) {
            eLog('Successfully delete dir '.$dir);
        } else {
            eLog('FAIL delete dir '.$dir);
        }
    }
}

function parseOneItem($item)
{
    global $erasetest_enabled;
    eLog('*** Parse item '.$item);
    if ($erasetest_enabled) {
        eLog('TEST enabled, no files will be deleted.');
    }
    
    $lines = file($item,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $cnt = count($lines);
    eLog('nb lines ' . $cnt);
    if ($cnt >= 3) {
        $dirs = array();
        $force_delete = intval($lines[$cnt-1]) == 2;
        $is_multi = intval($lines[$cnt-2]);
        $base_path = $lines[$cnt-3];
        eLog('force_delete: '. ($force_delete ? 'true' : 'false'));
        eLog('is_multi: '. $is_multi);
        eLog('base_path: '. $base_path);
        unset($lines[$cnt-3]);
        unset($lines[$cnt-2]);
        unset($lines[$cnt-1]);
        if (empty($base_path)) {
            return;
        }
        if (!$force_delete || !$is_multi) {
            if (empty($lines) && is_file($base_path)) {
                $lines[0] = $base_path;
            }
            foreach ($lines as $file) {
                unlinkAlt($file);
                if ($is_multi) {
                    $dir = $base_path;
                    $file = substr($file, strlen($base_path) + 1);
                    $pieces = explode('/', $file);
                    for ($i = 0; $i < count($pieces) - 1; $i++) {
                        $dir .= '/';
                        $dir .= $pieces[$i];
                        $dirs[] = $dir;
                    }
                }
            }
        }
        if ($is_multi) {
            eLog('multi');
            if ($force_delete) {
                rmdirAlt($base_path);
            } else {
                $dirs = array_unique($dirs);
                usort($dirs, create_function('$a,$b', 'return strrpos($b,"/")-strrpos($a,"/");'));
                foreach ($dirs as $dir) {
                    rmdirAlt($dir);
                }
                rmdirAlt($base_path);
            }
        }
    }
}

define('MAX_DURATION_OF_CHECK',3600);

$listPath = getSettingsPath()."/erasedata";
$lock = $listPath.'/scheduler.lock';
if (!is_file($lock) || (time()-filemtime($lock)>MAX_DURATION_OF_CHECK)) {
    touch($lock);
    $list = array();
    if($handle = @opendir($listPath)) {
        while(false !== ($file = readdir($handle))) {
            $fname = $listPath.'/'.$file;
            if ($file != "." && $file != ".." && is_file($fname) && (pathinfo($file,PATHINFO_EXTENSION) == "list")) {
                $list[] = $fname;
            }
        }
        closedir($handle);
    }
    foreach( $list as $item ) {
        parseOneItem($item);
        unlink($item);
    }
    unlink($lock);
} else {
    eLog('Busy, wait for next time.');
}
