<?php

// The autotools settings page posts an application/x-www-form-urlencoded body
// that rAutoTools::set() reads by splitting on '&' and then on '='. Every
// value in it therefore has to arrive escaped, and be unescaped here.
//
// tests/fixtures/autotools-form-values.json holds one value per row with the
// form it travels as. tests/plugins/autotools/form-encoding.spec.js asserts
// that plugins/autotools/init.js writes that form; this asserts that set()
// reads the same form back as the value. Neither side can be changed alone.

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-autotools-encoding-test-' . getmypid();

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/autotools/autotools.php');

// set() re-registers the rtorrent commands and writes the cache file. Neither
// is what is under test.
class AutoToolsFormEncodingProbe extends rAutoTools
{
    public function store()
    {
        return true;
    }
    public function setHandlers()
    {
        return true;
    }
}

class AutoToolsFormEncodingTest extends TestCase
{
    public const MARKER = 'formEncoding=percent-v1';

    private $values;
    private $previous;

    public function setUp()
    {
        $this->values = json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/autotools-form-values.json'),
            true,
        );
        $this->assertTrue(
            is_array($this->values) && count($this->values) > 0,
            'read a table with values in it',
        );
        // The directory fields are checked against $topDirectory through the
        // cached rtorrent settings; give it one, so nothing asks rtorrent.
        $settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
        $settings->linkExist = true;
        $settings->iVersion = 0x1000;
        $settings->directory = '/';
        $this->previous = self::swapSettings($settings);
    }

    public function tearDown()
    {
        self::swapSettings($this->previous);
        @rmdir($_ENV['RU_PROFILE_PATH'] . '/settings');
        @rmdir($_ENV['RU_PROFILE_PATH']);
    }

    public function testTheLabelTemplateArrivesAsItWasTyped()
    {
        foreach ($this->values as $row) {
            $at = $this->saved($this->body('label_template', $row['encoded']));
            $this->assertTrue(
                $at->label_template === $row['value'],
                'label template ' . json_encode($row['value']) . ' (' . $row['why'] . '): '
                . var_export($at->label_template, true),
            );
        }
    }

    // A filter that does not compile is replaced by the default, so a value
    // cut short at a separator was not just shortened but silently lost.
    public function testTheFiltersArriveAsTheyWereTyped()
    {
        foreach (['/^(?=tv)t/', '/a&b|c=d/', '/100%|x+y/', '/caf\x{e9}|é/'] as $filter) {
            $at = $this->saved($this->body('automove_filter', rawurlencode($filter)));
            $this->assertTrue(
                $at->automove_filter === $filter,
                'move filter ' . json_encode($filter) . ': ' . var_export($at->automove_filter, true),
            );
            $at = $this->saved($this->body('skip_move_for_files', rawurlencode($filter)));
            $this->assertTrue(
                $at->skip_move_for_files === $filter,
                'skip pattern ' . json_encode($filter) . ': ' . var_export($at->skip_move_for_files, true),
            );
        }
    }

    public function testADirectoryNameArrivesAsItWasTyped()
    {
        $typed = '/downloads/Movies & TV=1 100%+ü/';
        $expected = $typed;
        rTorrentSettings::get()->correctDirectory($expected);
        $at = $this->saved($this->body('path_to_finished', rawurlencode($typed))
            . '&path_to_watch=' . rawurlencode($typed));
        $this->assertTrue(
            $at->path_to_finished === $expected,
            'the finished directory: ' . var_export($at->path_to_finished, true),
        );
        $this->assertTrue(
            $at->path_to_watch === $expected,
            'the watch directory: ' . var_export($at->path_to_watch, true),
        );
    }

    // encodeURIComponent() writes a space as "%20" and a plus as "%2B", so
    // nothing it escapes comes out as a bare plus. One in the body is data.
    public function testAPlusInAMarkedBodyIsAPlus()
    {
        $at = $this->saved($this->body('label_template', 'a+b'));
        $this->assertTrue($at->label_template === 'a+b', 'a plus stays a plus: ' . var_export($at->label_template, true));
    }

    // Once, and once only. Somebody who types the five characters "p%26s"
    // has them escaped to "p%2526s" and gets those five back.
    public function testAMarkedValueIsUnescapedExactlyOnce()
    {
        $at = $this->saved($this->body('label_template', 'p%2526s'));
        $this->assertTrue($at->label_template === 'p%26s', 'unescaped once: ' . var_export($at->label_template, true));
    }

    // A settings page from before the word escaped nothing, so nothing it
    // sends is unescaped: what arrived is what somebody typed.
    public function testAnUnmarkedBodyIsNotUnescaped()
    {
        foreach (['100%25', 'p%2Bq', 'p+q', 'a%26b', '{DIR}'] as $typed) {
            $at = $this->saved($this->legacyBody('label_template', $typed));
            $this->assertTrue(
                $at->label_template === $typed,
                'unmarked ' . json_encode($typed) . ' stays itself: ' . var_export($at->label_template, true),
            );
        }
    }

    // What follows the first "=" is the value, in either form.
    public function testAnUnmarkedValueIsKeptPastItsFirstEqualsSign()
    {
        $at = $this->saved($this->legacyBody('automove_filter', '/^(?=tv)t/'));
        $this->assertTrue(
            $at->automove_filter === '/^(?=tv)t/',
            'the whole filter: ' . var_export($at->automove_filter, true),
        );
    }

    // An old page escaped nothing, so an ampersand in any value can make the
    // text after it look like another field, the word included. Only a body
    // that opens with it is unescaped.
    public function testAMarkerThatDoesNotOpenTheBodyDecodesNothing()
    {
        $at = $this->saved($this->legacyBody('label_template', 'a%26b') . '&' . self::MARKER);
        $this->assertTrue(
            $at->label_template === 'a%26b',
            'a later marker leaves legacy text as it arrived: ' . var_export($at->label_template, true),
        );
    }

    public function testTheCheckboxesAndTheOperationStillArrive()
    {
        $body = self::MARKER . '&enable_label=1&label_template=%7BDIR%7D&enable_move=1&path_to_finished='
            . '&skip_move_for_files=&fileop_type=Copy&enable_watch=1&add_label=1&add_name=1&path_to_watch='
            . '&automove_filter=%2F.*%2F&watch_start=1';
        $at = $this->saved($body);
        $this->assertTrue(
            [$at->enable_label, $at->enable_move, $at->enable_watch, $at->addLabel, $at->addName, $at->watch_start, $at->fileop_type]
            === ['1', '1', '1', '1', '1', '1', 'Copy'],
            'every checkbox and the file operation: ' . json_encode(get_object_vars($at)),
        );
    }

    private function saved($body)
    {
        $at = new AutoToolsFormEncodingProbe();
        $at->set($body);
        return $at;
    }

    private static function swapSettings($settings)
    {
        return Closure::bind(function ($settings) {
            $previous = rTorrentSettings::$theSettings;
            rTorrentSettings::$theSettings = $settings;
            return $previous;
        }, null, 'rTorrentSettings')->__invoke($settings);
    }

    // What the settings page sends: the word that says its values are
    // escaped, and then one field each.
    private function body($field, $encoded)
    {
        $fields = self::fields();
        $fields[$field] = $encoded;
        $pairs = [self::MARKER];
        foreach ($fields as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        return implode('&', $pairs);
    }

    // What a settings page from before that word sends: no word, and
    // whatever was typed put on the wire as it stands.
    private function legacyBody($field, $raw)
    {
        $fields = self::fields();
        $fields[$field] = $raw;
        $pairs = [];
        foreach ($fields as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        return implode('&', $pairs);
    }

    private static function fields()
    {
        return ['enable_label' => '1', 'label_template' => '{DIR}', 'enable_move' => '1', 'path_to_finished' => '',
            'skip_move_for_files' => '', 'fileop_type' => 'Move', 'enable_watch' => '1', 'add_label' => '0',
            'add_name' => '0', 'path_to_watch' => '', 'automove_filter' => '/.*/', 'watch_start' => '0'];
    }
}
