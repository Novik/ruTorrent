<?php

/**
 * LogFileModeTest's child requires this file in place of fileutil.php. It
 * loads FileUtil and then stands in for PHP's plain-file wrapper, so that a
 * test without a second Unix user or a fork can refuse an open the way
 * fs.protected_regular does, or append another writer's line between
 * FileUtil::toLog()'s open and its write. Everything else reaches the real file
 * through a real handle, its real flock() included: flock() reads
 * EWOULDBLOCK from errno, which the real flock() is the last call to set.
 * stream_seek() only seeks for SEEK_END, the one seek toLog() asks for.
 *
 * Options, as JSON in RUTORRENT_LOG_WRAPPER: 'refuse', the fopen() modes to
 * refuse; 'beforeWrite', text that error_log(.., 3, ..) appends just before
 * the write.
 */
require_once(__DIR__ . '/../../php/utility/fileutil.php');

class LogFileWrapperFixture
{
    public $context;
    private static $options;
    private $path;
    private $real;

    public static function install($options)
    {
        self::$options = $options + ['refuse' => [], 'beforeWrite' => ''];
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', __CLASS__);
    }

    public function url_stat($path, $flags)
    {
        return self::withPlainFiles(function () use ($path) {
            return @stat($path);
        });
    }

    public function stream_open($path, $mode, $options, &$openedPath)
    {
        if (in_array($mode, self::$options['refuse'], true)) {
            return false;
        }
        $this->path = $path;
        $this->real = self::withPlainFiles(function () use ($path, $mode) {
            return fopen($path, $mode);
        });
        return (bool) $this->real;
    }

    public function stream_lock($operation)
    {
        return flock($this->real, $operation);
    }

    // PHP also asks an append stream for its position after opening it.
    public function stream_seek($offset, $whence)
    {
        return ($whence !== SEEK_END) || fseek($this->real, $offset, $whence) === 0;
    }

    public function stream_tell()
    {
        return ftell($this->real);
    }

    public function stream_write($data)
    {
        if (self::$options['beforeWrite'] !== '') {
            $other = self::$options['beforeWrite'];
            $path = $this->path;
            self::withPlainFiles(function () use ($other, $path) {
                error_log($other, 3, $path);
            });
        }
        return fwrite($this->real, $data);
    }

    public function stream_close()
    {
        fclose($this->real);
    }

    private static function withPlainFiles($call)
    {
        stream_wrapper_restore('file');
        try {
            return $call();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }
    }
}

LogFileWrapperFixture::install(json_decode(getenv('RUTORRENT_LOG_WRAPPER'), true));
