<?php

/**
 * A realpath() replacement
 *
 * This function behaves similar to PHP's realpath() but does not resolve
 * symlinks or accesses upper directories
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author <richpageau at yahoo dot co dot uk>
 * @link   http://de3.php.net/manual/en/function.realpath.php#75992
 */

function fullpath($path,$exists=false){
    static $run = 0; 
    $root  = '';
    $iswin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || @$GLOBALS['DOKU_UNITTEST_ASSUME_WINDOWS']);

    // find the (indestructable) root of the path - keeps windows stuff intact
    if($path{0} == '/'){
        $root = '/';
    }elseif($iswin){
        // match drive letter and UNC paths
        if(preg_match('!^([a-zA-z]:)(.*)!',$path,$match)){
            $root = $match[1].'/';
            $path = $match[2];
        }else if(preg_match('!^(\\\\\\\\[^\\\\/]+\\\\[^\\\\/]+[\\\\/])(.*)!',$path,$match)){
            $root = $match[1];
            $path = $match[2];
        }
    }
    $path = str_replace('\\','/',$path);

    // if the given path wasn't absolute already, prepend the script path and retry
    if(!$root){
        $base = dirname($_SERVER['SCRIPT_FILENAME']);
        $path = $base.'/'.$path;
        if($run == 0){ // avoid endless recursion when base isn't absolute for some reason
            $run++;
            return fullpath($path,$exists);
        }
    }
    $run = 0;

    // canonicalize
    $path=explode('/', $path);
    $newpath=array();
    foreach($path as $p) {
        if ($p === '' || $p === '.') continue;
           if ($p==='..') {
              array_pop($newpath);
              continue;
        }
        array_push($newpath, $p);
    }
    $finalpath = $root.implode('/', $newpath);

    // check for existance when needed (except when unit testing)
    if($exists && !defined('DOKU_UNITTEST') && !@file_exists($finalpath)) {
        return false;
    }
    return $finalpath;
}

?>
