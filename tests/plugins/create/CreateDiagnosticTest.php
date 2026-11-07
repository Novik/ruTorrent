<?php

require_once(__DIR__ . '/../../php/TestCase.php');

// init.php runs inside the plugin loader. These stand in for the parts of it
// the diagnostic touches, so the resolution it performs can be observed.

function findEXE($exe)
{
    $GLOBALS['findEXE_asked'][] = $exe;
    return (in_array($exe, $GLOBALS['findEXE_present']) ? '/usr/bin/' . $exe : false);
}

class FileUtil
{
    public static $conf = '';
    public static function getPluginConf($name)
    {
        return self::$conf;
    }
}

class LoaderSettings
{
    public $registered = [];
    public function registerPlugin($name, $perms)
    {
        $this->registered[] = $name;
    }
}

/**
 * The create plugin disables itself when it cannot find its external program.
 * The name it looks up, and the name it puts in front of the operator, have to
 * be the program's -- a message naming the backend key sends them looking for
 * a command that does not exist.
 */
class CreateDiagnosticTest extends TestCase
{
    public function testItLooksUpTheProgramNameNotTheBackendKey()
    {
        $r = $this->loadInit('transmissioncreate', []);
        $this->assertEquals(
            ['transmission-create'],
            $r['asked'],
            'findEXE is asked for the program Transmission installs',
        );
    }

    public function testTheErrorNamesTheProgramTheOperatorMustInstall()
    {
        $r = $this->loadInit('transmissioncreate', []);
        $this->assertTrue(
            strpos($r['js'], 'plugin.disable();') !== false,
            'the plugin disables itself when the program is absent',
        );
        $this->assertTrue(
            strpos($r['js'], '(transmission-create)') !== false,
            'the message names transmission-create: ' . $r['js'],
        );
        $this->assertEquals([], $r['registered'], 'and the plugin is not registered');
    }

    public function testThePluginLoadsWhenTheProgramIsInstalled()
    {
        $r = $this->loadInit('transmissioncreate', ['transmission-create']);
        $this->assertEquals(
            ['create'],
            $r['registered'],
            'a box with transmission-create on PATH gets the plugin',
        );
        $this->assertTrue(
            strpos($r['js'], 'plugin.disable();') === false,
            'and no disable was emitted',
        );
    }

    public function testAnExplicitPathStillSkipsTheLookupEntirely()
    {
        $r = $this->loadInit('transmissioncreate', [], '/opt/bin/transmission-create');
        $this->assertEquals([], $r['asked'], 'a configured path is not second-guessed');
        $this->assertEquals(['create'], $r['registered'], 'and the plugin loads');
    }

    public function testABackendSpelledLikeItsProgramIsUnaffected()
    {
        $r = $this->loadInit('mktorrent', ['mktorrent']);
        $this->assertEquals(['mktorrent'], $r['asked'], 'mktorrent is looked up as itself');
        $this->assertEquals(['create'], $r['registered'], 'and the plugin loads');
    }
    private function loadInit($useExternal, $present, $pathToCreatetorrent = '')
    {
        $GLOBALS['findEXE_asked'] = [];
        $GLOBALS['findEXE_present'] = $present;
        FileUtil::$conf = '$useExternal = ' . var_export($useExternal, true) . ';'
            . ' $pathToCreatetorrent = ' . var_export($pathToCreatetorrent, true) . ';'
            . ' $useInternalHybrid = true;';

        $plugin = ['name' => 'create'];
        $pInfo = ['perms' => 0];
        $do_diagnostic = true;
        $jResult = '';
        $theSettings = new LoaderSettings();

        include(dirname(dirname(dirname(__DIR__))) . '/plugins/create/init.php');

        return [
            'js' => $jResult,
            'asked' => $GLOBALS['findEXE_asked'],
            'registered' => $theSettings->registered,
        ];
    }
}
