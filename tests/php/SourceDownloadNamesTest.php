<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/PhpProcess.php');

class SourceDownloadNamesTest extends TestCase
{
    private function same($expected, $actual, $message)
    {
        $this->assertTrue($expected === $actual, $message);
    }

    private function metainfo($name, $alias = null)
    {
        $info = 'd6:lengthi1e4:name'.strlen($name).':'.$name;
        if($alias !== null) $info .= '10:name.utf-8'.strlen($alias).':'.$alias;
        return 'd4:info'.$info.'12:piece lengthi16384e6:pieces20:'.str_repeat('x', 20).'ee';
    }

    private function request($sources)
    {
        $directory = sys_get_temp_dir().'/source-download-names-'.bin2hex(random_bytes(5));
        if(!mkdir($directory, 0700) || !chmod($directory, 0700)) throw new RuntimeException('Cannot create source fixture directory');
        $paths = array();
        try
        {
            foreach($sources as $index=>$source)
            {
                $paths[] = $directory.'/'.$source[0];
                file_put_contents($paths[$index], $source[1]);
            }
            $root = dirname(__DIR__, 2);
            // Keep the request parser and source controller intact. Replace only
            // SCGI transport and archive/download sinks, so no server or zip
            // extension is needed and raw paths can be asserted directly.
            $request = file_get_contents($root.'/php/xmlrpc.php');
            $begin = strpos($request, 'class rXMLRPCRequest');
            $request = substr($request, $begin, strpos($request, "\nfunction getCmd", $begin)-$begin);
            $request = str_replace(array('class rXMLRPCRequest', 'self::send(', '__'.'DIR__'),
                array('class SourceDownloadRequest', 'SourceDownloadTransport::send(', var_export($root.'/php', true)), $request);
            $action = file_get_contents($root.'/plugins/source/action.php');
            $action = str_replace(array('new rXMLRPCRequest', "class_exists('ZipArchive')", 'new ZipArchive',
                'ZipArchive::CREATE', 'SendFile::send('), array('new SourceDownloadRequest',
                "class_exists('SourceDownloadZip')", 'new SourceDownloadZip', 'SourceDownloadZip::CREATE',
                'SourceDownloadSend::send('), substr($action, 5));
            $code = '$_ENV["RU_PROFILE_PATH"]='.var_export($directory.'/profile', true).';'
                .'require '.var_export($root.'/php/rtorrent.php', true).';'
                .'$reflection=new ReflectionClass("rTorrentSettings");$property=$reflection->getProperty("theSettings");'
                .'if(PHP_VERSION_ID<80100)$property->setAccessible(true);'
                .'$settings=$reflection->newInstanceWithoutConstructor();$settings->iVersion=0x101A;'
                .'$property->setValue(null,$settings);'.$request;
            $code .= <<<'PHP'
class SourceDownloadTransport
{
    public static $paths;
    public static $calls = 0;
    public static function send($body, $trusted, &$failure)
    {
        $path = self::$paths[self::$calls++];
        $path = strtr($path, array('&'=>'&amp;', '<'=>'&lt;', '>'=>'&gt;'));
        $xml = '<?xml version="1.0"?><methodResponse><params><param><value><array><data>'
            .'<value><array><data><value><string></string></value></data></array></value>'
            .'<value><array><data><value><string>'.$path.'</string></value></data></array></value>'
            .'</data></array></value></param></params></methodResponse>';
        return "Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($xml)."\r\n\r\n".$xml;
    }
}
class SourceDownloadZip
{
    const CREATE = 1;
    public static $entries = array();
    public function open($path, $flags) { file_put_contents($path, 'zip fixture'); return true; }
    public function addFile($path, $name)
    {
        self::$entries[] = array('path'=>base64_encode($path), 'name'=>base64_encode($name),
            'bytes'=>base64_encode(file_get_contents($path)));
        return true;
    }
    public function close() { return true; }
}
class SourceDownloadSend
{
    public static function send($path, $type, $name, $cache)
    {
        echo json_encode(array('entries'=>SourceDownloadZip::$entries, 'calls'=>SourceDownloadTransport::$calls));
        return true;
    }
}
PHP;
            $code .= 'SourceDownloadTransport::$paths='.var_export($paths, true).';'
                .'$_POST["hash"]='.var_export(str_repeat('A', 40).' '.str_repeat('B', 40), true).';'
                .'chdir('.var_export($root.'/plugins/source', true).');'.$action;
            $result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-r', $code)));
            $this->same(0, $result['code'], 'source controller finishes normally');
            $this->same('', $result['err'], 'source controller emits no diagnostics');
            $payload = json_decode($result['out'], true);
            $this->same(2, $payload['calls'] ?? null, 'source labels do not add RPC requests to the two existing lookups');
            $entries = $payload['entries'] ?? array();
            $this->same(count($sources), count($entries), 'every readable raw source reaches the archive');
            foreach($entries as $index=>$entry)
            {
                $this->same($paths[$index], base64_decode($entry['path'], true), 'archive reads the exact raw source path');
                $this->same($sources[$index][1], base64_decode($entry['bytes'], true), 'archive payload retains original metainfo bytes');
            }
            return array_map(function($entry) { return base64_decode($entry['name'], true); }, $entries);
        }
        finally
        {
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,
                FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry)
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            rmdir($directory);
        }
    }

    public function testLegacySourcePathsStayExactWhileArchiveLabelsUseAliases()
    {
        $names = $this->request(array(
            array("raw \xFF %41.torrent", $this->metainfo("cp\xFF", 'Серия "q" \\b %41')),
            array('plain.torrent', $this->metainfo('映画 🎬', 'ignored alias')),
        ));
        $this->same(array('Серия "q" _b %41-1.torrent', '映画 🎬-2.torrent'), $names,
            'archive labels use verified aliases only for legacy titles and preserve valid Unicode');
    }

    public function testArchiveLabelsStaySingleComponentsOnBothSlashConventions()
    {
        $names = $this->request(array(
            array('first.torrent', $this->metainfo("raw\xFF", '..\\outside\\name')),
            array('second.torrent', $this->metainfo('../outside/name', 'ignored alias')),
        ));
        $this->same(array('.._outside_name-1.torrent', '.._outside_name-2.torrent'), $names,
            'neither raw Unicode labels nor metadata aliases create directory traversal members');
    }
}
