<?php
require_once __DIR__.'/../../php/TestCase.php';
require_once __DIR__.'/../../php/PhpProcess.php';

class DirectoryByteNamesTest extends TestCase
{
    private $root;
    private function assertSame($expected, $actual, $message) { $this->assertTrue($expected === $actual, $message); }
    public function setUp()
    {
        // Fixture modes must not depend on the caller's umask.
        umask(0077);
        $this->root = sys_get_temp_dir().'/getdir-'.getmypid().'-'.uniqid();
        mkdir($this->root.'/downloads/inside', 0700, true);
        mkdir($this->root.'/downloads-private/secret', 0700, true);
        file_put_contents($this->root.'/downloads/inside.txt', 'allowed');
        file_put_contents($this->root.'/downloads-private/secret.txt', 'private');
    }
    public function tearDown()
    {
        $this->removeTree($this->root);
    }
    private function removeTree($path)
    {
        if (!is_dir($path) || is_link($path)) { unlink($path); return; }
        foreach (new DirectoryIterator($path) as $entry)
            if (!$entry->isDot()) $this->removeTree($entry->getPathname());
        rmdir($path);
    }
    private function probe($script, $request)
    {
        $repo = dirname(__DIR__, 3);
        $top = $this->root.'/downloads';
        $code = 'chdir('.var_export($repo.'/php', true).'); '
            .'$_ENV["RU_PROFILE_PATH"] = '.var_export($this->root.'/profiles', true).'; '
            .'$_ENV["RU_PROFILE_MASK"] = "0700"; '
            .'require '.var_export($repo.'/php/settings.php', true).'; '
            .'$topDirectory = '.var_export($top, true).'; $checkUserPermissions = false; '
            .'$settings = (new ReflectionClass("rTorrentSettings"))->newInstanceWithoutConstructor(); '
            .'$settings->directory = '.var_export($top, true).'; $settings->uid = -1; '
            .'$property = new ReflectionProperty("rTorrentSettings", "theSettings"); '
            .'if(PHP_VERSION_ID < 80100) $property->setAccessible(true); $property->setValue(null, $settings); '
            .'$_REQUEST = '.var_export($request, true).'; '
            .'$source = file_get_contents('.var_export($repo.'/plugins/_getdir/'.$script, true).'); '
            .'$source = preg_replace("~^require_once[^\\n]*|^eval\\(FileUtil::getPluginConf[^\\n]*~m", "", $source); '
            .'$source = str_replace(\'CachedEcho::send(JSON::safeEncode($output),"application/json");\', '
            .'\'echo JSON::safeEncode($output);\', $source); '
            .'eval("?>".$source);';
        $process = PhpProcess::run(array('-d', 'display_errors=stderr', '-r', $code));
        $out = $process['out'];
        $this->assertEquals(0, $process['code'], 'actual directory controller executes: '.$process['err']);
        $this->assertEquals('', $process['err'], 'directory dependency failures do not escape as PHP diagnostics');
        $data = json_decode($out, true);
        $this->assertTrue(is_array($data), 'directory controller returns JSON: '.$out);
        return $data ?: array();
    }
    public function testLegacyDirectoryAndFileNamesKeepTheirRawIdentity()
    {
        $base = $this->root.'/downloads';
        mkdir($base."/\xFF", 0700);
        mkdir($base."/\xFF/child", 0700);
        file_put_contents($base."/\xFE.txt", 'legacy');
        mkdir($base.'/\\xFF', 0700);
        foreach (array('listdir.php', 'info.php') as $script) {
            $request = $script === 'listdir.php' ? array('dir'=>$base, 'withfiles'=>'1')
                : array('mode'=>'dirlist', 'basedir'=>$base);
            $data = $this->probe($script, $request);
            $entries = $data[$script === 'listdir.php' ? 'directories' : 'dirlist'] ?? array();
            $legacy = array_values(array_filter($entries, 'is_array'));
            $this->assertEquals(array(array('name'=>'\\xFF', 'pathKey'=>'%FF')), $legacy,
                'legacy bytes have a visible label and a reversible identity in '.$script);
            $this->assertTrue(in_array('\\xFF', $entries, true), 'literal backslash spelling stays a distinct scalar');
            if ($script === 'listdir.php')
                $this->assertTrue(in_array(array('name'=>'\\xFE.txt', 'pathKey'=>'%FE.txt'), $data['files'], true),
                    'file chooser keeps each raw file name');
            parse_str(($script === 'listdir.php' ? 'dir=' : 'mode=dirlist&basedir=').
                rawurlencode($base.'/').'%FF', $navigation);
            $next = $this->probe($script, $navigation);
            $path = $next[$script === 'listdir.php' ? 'path' : 'basedir'] ?? null;
            $raw = $base."/\xFF".($script === 'listdir.php' ? '/' : '');
            $this->assertEquals(array('name'=>str_replace("\xFF", '\\xFF', $raw), 'pathKey'=>rawurlencode($raw)),
                $path, 'returned directory path remains reversible after navigation in '.$script);
            $this->assertTrue(in_array('child', $next[$script === 'listdir.php' ? 'directories' : 'dirlist'], true),
                'navigation opened the raw-byte directory');
        }
    }
}
