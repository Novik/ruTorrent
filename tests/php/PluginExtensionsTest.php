<?php

require_once(__DIR__ . '/TestCase.php');

define('RUTORRENT_REQUIREMENTS_LIB', true);
require_once(__DIR__ . '/../../env_check.php');

/**
 * What a plugin declares in its plugin.info is the only record of the PHP
 * extensions it needs, and nothing read it outside the plugin loader -- so an
 * installer had no way to learn that a missing extension was why a plugin was
 * absent. env_check reads those declarations now; these cover the reading and
 * the verdict, which is the part with no network and no filesystem in it.
 */
class PluginExtensionsTest extends TestCase
{
    private function info($lines)
    {
        return implode("\n", $lines) . "\n";
    }

    public function testItReadsBothSeverities()
    {
        $needs = Requirements::pluginExtensions($this->info([
            'plugin.author: someone',
            'php.extensions.error: json,ctype',
            'php.extensions.warning: zip',
        ]));
        $this->assertEquals(['json', 'ctype'], $needs['error'], 'the error list is read');
        $this->assertEquals(['zip'], $needs['warning'], 'and the warning list');
    }

    public function testItToleratesSpacingAndRepeats()
    {
        $needs = Requirements::pluginExtensions($this->info([
            'php.extensions.error:   json ,  ctype ,json ',
        ]));
        $this->assertEquals(
            ['json', 'ctype'],
            $needs['error'],
            'spacing is trimmed and a repeat is not listed twice',
        );
    }

    public function testAPluginInfoWithoutExtensionsAsksForNothing()
    {
        $needs = Requirements::pluginExtensions($this->info(['plugin.version: 5.1']));
        $this->assertEquals([], $needs['error'], 'no error list');
        $this->assertEquals([], $needs['warning'], 'no warning list');
    }

    public function testAnUnreadableInfoAsksForNothing()
    {
        // file_get_contents() answers false for a plugin without a plugin.info.
        $needs = Requirements::pluginExtensions(false);
        $this->assertEquals([], $needs['error'], 'a missing plugin.info is not a demand');
    }

    public function testItReportsOnlyWhatIsNotLoaded()
    {
        $missing = Requirements::missingPluginExtensions(
            ['alpha' => ['error' => ['json'], 'warning' => []]],
            ['json', 'pcre'],
        );
        $this->assertEquals([], $missing, 'an extension that is loaded is not reported');
    }

    public function testItSaysWhatEachAbsenceCosts()
    {
        $missing = Requirements::missingPluginExtensions([
            'alpha' => ['error' => ['ctype'], 'warning' => []],
            'beta'  => ['error' => [], 'warning' => ['ctype']],
        ], ['json']);
        $this->assertTrue(isset($missing['ctype']), 'the missing extension is reported');
        $this->assertEquals(
            ['alpha'],
            $missing['ctype']['disables'],
            'a plugin that declared it an error is disabled',
        );
        $this->assertEquals(
            ['beta'],
            $missing['ctype']['limits'],
            'a plugin that declared it a warning is limited',
        );
    }

    /** The stronger claim wins when one plugin says both. */
    public function testErrorOutranksWarningForTheSamePlugin()
    {
        $missing = Requirements::missingPluginExtensions(
            ['alpha' => ['error' => ['zip'], 'warning' => ['zip']]],
            [],
        );
        $this->assertEquals(['alpha'], $missing['zip']['disables'], 'it counts as disabling');
        $this->assertEquals([], $missing['zip']['limits'], 'and not also as limiting');
    }

    /**
     * get_loaded_extensions() answers with the name each extension registered,
     * which is not how plugin.info spells them.
     */
    public function testItMatchesTheNameCasePhpReports()
    {
        $missing = Requirements::missingPluginExtensions(
            ['geoip' => ['error' => ['phar'], 'warning' => ['sqlite3']]],
            ['Phar', 'SimpleXML', 'sqlite3'],
        );
        $this->assertEquals(
            [],
            $missing,
            'Phar answers for phar, however either side spells it',
        );
    }

    /** Every plugin.info that ships must parse, and name real extensions. */
    public function testEveryShippedDeclarationIsReadable()
    {
        $root = dirname(dirname(__DIR__));
        $declared = [];
        foreach (scandir($root . '/plugins') as $entry) {
            if (($entry === '.') || ($entry === '..')) {
                continue;
            }
            $path = $root . '/plugins/' . $entry . '/plugin.info';
            if (!is_file($path)) {
                continue;
            }
            $needs = Requirements::pluginExtensions(file_get_contents($path));
            foreach (array_merge($needs['error'], $needs['warning']) as $ext) {
                $declared[$ext] = true;
            }
        }
        $this->assertTrue(count($declared) > 0, 'the shipped plugins declare extensions');
        foreach (array_keys($declared) as $ext) {
            $this->assertTrue(
                (bool) preg_match('/^[a-z0-9_]+$/', $ext),
                'a declared extension name is a plain extension name: ' . var_export($ext, true),
            );
        }
    }
}
