<?php

require_once(__DIR__.'/utility/utf.php');

/** Display legacy metainfo names without changing daemon paths or file indices. */
final class TorrentFileNames
{
    public static function needsMetadata($rows)
    {
        foreach($rows as $row)
            if(isset($row[0]) && is_string($row[0]) && !UTF::isValid($row[0])) return true;
        return false;
    }

    public static function needsTitleMetadata($name)
    {
        return is_string($name) && !UTF::isValid($name);
    }

    private static function sourceMatches($source, $hash)
    {
        if(!($source instanceof Torrent)) return false;
        try {
            if($source->errors()) return false;
            $sourceHash = $source->hash_info();
            return is_string($hash) && is_string($sourceHash)
                && preg_match('/\A[0-9A-Fa-f]{40}\z/', $hash) === 1
                && preg_match('/\A[0-9A-Fa-f]{40}\z/', $sourceHash) === 1
                && strcasecmp($sourceHash, $hash) === 0;
        } catch(Throwable $error) {
            return false;
        }
    }

    /** Keep the original wire name for path comparisons; only name is a label. */
    public static function title($name, $hash, $source = false)
    {
        if(!self::needsTitleMetadata($name)) return $name;
        $encoding = null;
        $alias = false;
        if($source instanceof Torrent) {
            try {
                if(@self::sourceMatches($source, $hash)
                    && @is_array($source->info) && isset($source->info['name']) && $source->info['name'] === $name) {
                    $encoding = $source->encoding;
                    if(isset($source->info['name.utf-8']) && @self::component($source->info['name.utf-8']))
                        $alias = $source->info['name.utf-8'];
                }
            } catch(Throwable $error) {
                // An unreadable source cannot remove a live torrent from the list.
            }
        }
        return $alias === false ? self::fallback($name, $encoding) : $alias;
    }

    /** One display title. Paths are false when unknown, so no daemon lookup is made. */
    public static function displayTitle($name, $hash, $knownPaths)
    {
        if(!self::needsTitleMetadata($name)) return $name;
        require_once(__DIR__.'/rtorrent.php');
        $source = false;
        try {
            $source = rTorrent::getSource($hash, $knownPaths);
        } catch(Throwable $error) {
            // Unreadable optional metadata leaves visible byte escapes.
        }
        return self::title($name, $hash, $source);
    }

    /** Display titles for hash => daemon name pairs; source paths are read once, only when needed. */
    public static function titles($names)
    {
        $paths = null;
        foreach($names as $hash=>$name)
            if(self::needsTitleMetadata($name)) {
                if($paths === null) {
                    require_once(__DIR__.'/rtorrent.php');
                    $paths = rTorrent::getSourcePaths();
                }
                $names[$hash] = self::displayTitle($name, $hash, $paths[$hash] ?? false);
            }
        return $names;
    }

    private static function component($text)
    {
        return UTF::isValid($text) && $text !== '' && $text !== '.' && $text !== '..'
            && strpos($text, '/') === false && strpos($text, "\0") === false;
    }

    private static function alias($path, $width)
    {
        if(!is_array($path) || count($path) !== $width || array_keys($path) !== range(0, $width-1)) return false;
        foreach($path as $part) if(!self::component($part)) return false;
        return $path;
    }

    /** A declared encoding is evidence; detector guesses and lossy conversions are not. */
    private static function converted($text, $encoding)
    {
        try {
            if(!is_string($encoding) || @preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $encoding) !== 1) return false;
        } catch(Throwable $error) {
            return false;
        }
        foreach(array('iconv', 'mb_convert_encoding') as $function) {
            try {
                if(!@function_exists($function)) continue;
                if($function === 'iconv') {
                    $result = @iconv($encoding, 'UTF-8', $text);
                    $reverse = is_string($result) ? @iconv('UTF-8', $encoding, $result) : false;
                } else {
                    $result = @mb_convert_encoding($text, 'UTF-8', $encoding);
                    $reverse = is_string($result) ? @mb_convert_encoding($result, $encoding, 'UTF-8') : false;
                }
                if(@self::component($result) && $reverse === $text) return $result;
            } catch(Throwable $error) {
                // PHP 8 throws for disabled functions and unknown encoding names.
            }
        }
        return false;
    }

    private static function fallback($text, $encoding)
    {
        $converted = self::converted($text, $encoding);
        return $converted === false ? UTF::display($text) : $converted;
    }

    /** Return source rows only after checking the requested torrent's identity. */
    private static function sourceFiles($source, $hash, $count, &$encoding)
    {
        $encoding = null;
        if(!($source instanceof Torrent)) return false;
        try {
            if(!self::sourceMatches($source, $hash)
                || !is_array($source->info)) return false;
            $info = $source->info;
            if(isset($info['files'])) $files = $info['files'];
            else {
                if(!isset($info['name'], $info['length']) || !is_string($info['name'])) return false;
                $files = array(array('path'=>array($info['name']), 'length'=>$info['length']));
                if(isset($info['name.utf-8'])) $files[0]['path.utf-8'] = array($info['name.utf-8']);
            }
            if(!is_array($files) || count($files) !== $count || array_keys($files) !== range(0, $count-1)) return false;
            $encoding = $source->encoding;
            return $files;
        } catch(Throwable $error) {
            return false;
        }
    }

    private static function matchingFile($file, $row, $path)
    {
        if(!is_array($file) || !isset($file['path'], $file['length'], $row[3]) || !is_array($file['path'])
            || $file['path'] !== $path || (!is_int($file['length']) && !is_string($file['length']))
            || (!is_int($row[3]) && !is_string($row[3]))) return false;
        return (string)$file['length'] === (string)$row[3];
    }

    /** Download label for a file whose name is not UTF-8, or null to keep the default. */
    public static function downloadName($hash, $index, $filename)
    {
        $leaf = FileUtil::getFileName($filename);
        if(UTF::isValid($leaf)) return null;
        require_once(__DIR__.'/rtorrent.php');
        $req = new rXMLRPCRequest(array(
            new rXMLRPCCommand('f.get_path', array($hash, $index)),
            new rXMLRPCCommand('f.get_size_bytes', array($hash, $index)),
            new rXMLRPCCommand('d.get_size_files', $hash)));
        if(!$req->success() || count($req->val) !== 3 || FileUtil::getFileName($req->val[0]) !== $leaf)
            return UTF::display($leaf);
        $source = false;
        try {
            $source = rTorrent::getSource(strtoupper($hash));
        } catch(Throwable $error) {
            // The payload stays downloadable under an escaped name.
        }
        return self::fileName($req->val[0], $index, $req->val[1], $req->val[2], $hash, $source);
    }

    /** A download label for one exact live file; never a replacement disk path. */
    public static function fileName($path, $index, $size, $count, $hash, $source = false)
    {
        $parts = explode('/', $path);
        $leaf = $parts[count($parts)-1];
        if(UTF::isValid($leaf)) return $leaf;
        $fallback = UTF::display($leaf);
        if(!is_int($index) || $index < 0 || (!is_int($count) && !is_string($count))
            || preg_match('/\A[0-9]+\z/', (string)$count) !== 1 || $index >= (int)$count) return $fallback;
        $files = self::sourceFiles($source, $hash, (int)$count, $encoding);
        if($files === false || !isset($files[$index])
            || !self::matchingFile($files[$index], array(3=>$size), $parts)) return $fallback;
        $alias = isset($files[$index]['path.utf-8'])
            ? self::alias($files[$index]['path.utf-8'], count($parts)) : false;
        return $alias === false ? self::fallback($leaf, $encoding) : $alias[count($parts)-1];
    }

    /**
     * Ordinary UTF-8 rows retain their wire format. Only legacy names receive
     * a display envelope; pathKey encodes raw components, never an alias.
     * Source acquisition belongs to rTorrent::getSource() in the caller.
     */
    public static function resolve($rows, $hash, $source = false)
    {
        if(!self::needsMetadata($rows) || !function_exists('rawurlencode')) return $rows;
        $files = self::sourceFiles($source, $hash, count($rows), $encoding);
        $names = array();
        $directories = array();
        foreach($rows as $index=>$row) {
            if(!isset($row[0]) || !is_string($row[0]) || UTF::isValid($row[0])) continue;
            $path = explode('/', $row[0]);
            $alias = false;
            $rowEncoding = null;
            if($files !== false && isset($files[$index]) && self::matchingFile($files[$index], $row, $path)) {
                $rowEncoding = $encoding;
                if(isset($files[$index]['path.utf-8'])) $alias = self::alias($files[$index]['path.utf-8'], count($path));
            }
            $display = array();
            $keys = array();
            foreach($path as $depth=>$part) {
                $key = rawurlencode($part);
                if(!is_string($key)) return $rows;
                $keys[] = $key;
                $label = UTF::isValid($part) ? $part
                    : ($alias !== false ? $alias[$depth] : self::fallback($part, $rowEncoding));
                $display[] = $label;
                if($depth < count($path)-1 && !UTF::isValid($part)) {
                    $prefix = implode('/', $keys);
                    if(!array_key_exists($prefix, $directories)) $directories[$prefix] = $label;
                    else if($directories[$prefix] !== $label) $directories[$prefix] = false;
                }
            }
            $names[$index] = array($path, $display, $keys);
        }
        foreach($names as $index=>$name) {
            list($path, $display, $keys) = $name;
            $prefix = array();
            foreach($path as $depth=>$part) {
                $prefix[] = $keys[$depth];
                if($depth < count($path)-1 && !UTF::isValid($part) && $directories[implode('/', $prefix)] === false)
                    $display[$depth] = UTF::display($part);
            }
            $rows[$index][0] = array('name'=>implode('/', $display), 'pathKey'=>implode('/', $keys));
        }
        return $rows;
    }
}
