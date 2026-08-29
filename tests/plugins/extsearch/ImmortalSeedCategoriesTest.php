<?php

/**
 * The category maps of the extsearch engines.
 *
 * A search engine's $categories is both the list the WebUI shows and the
 * lookup the search itself goes through: the label the user picked comes back
 * as $cat and action() reads $categories[$cat] to get the query fragment. Two
 * entries sharing a label are therefore not a cosmetic duplicate -- PHP keeps
 * the last value for the key, so the earlier categories vanish from the list
 * and can never be searched. ImmortalSeed had four "|-- Non-English"
 * sub-categories, one under each Movies parent, and only the last survived.
 *
 * The engine files are loaded the way KinozalAccountTest loads an account: with
 * a minimal commonEngine base, so the code under test is the real one without
 * dragging in engines.php's plugin config and Snoopy.
 */

require_once(__DIR__ . '/../rutracker_check/TestLib.php');

class commonEngine
{
    public $defaults = array('public' => true, 'page_size' => 100);
    public $categories = array('All' => '');

    public function makeClient($url)
    {
        return new stdClass();
    }
}

require_once(testFindRepoRoot() . '/plugins/extsearch/engines/ImmortalSeed.php');

/**
 * The string keys of every array literal in $file that appears twice in the
 * same literal. Keys are grouped per bracket pair, so a label repeated in two
 * different maps is not reported.
 */
function extsearchDuplicateArrayKeys($file)
{
    $tokens = token_get_all(file_get_contents($file));
    $stack = array(array());
    $duplicates = array();
    $total = count($tokens);
    for ($i = 0; $i < $total; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            if ($token === '(' || $token === '[' || $token === '{') {
                $stack[] = array();
                continue;
            }
            if ($token === ')' || $token === ']' || $token === '}') {
                if (count($stack) > 1) {
                    array_pop($stack);
                }
                continue;
            }
            continue;
        }
        if ($token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        // The next significant token decides whether this string is a key.
        for ($j = $i + 1; $j < $total; $j++) {
            $next = $tokens[$j];
            if (is_array($next) && in_array($next[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            if (is_array($next) && $next[0] === T_DOUBLE_ARROW) {
                $key = substr($token[1], 1, -1);
                $top = count($stack) - 1;
                if (isset($stack[$top][$key])) {
                    $duplicates[] = $key;
                } else {
                    $stack[$top][$key] = true;
                }
            }
            break;
        }
    }
    return array_values(array_unique($duplicates));
}

$suite = new StrictTestSuite();

$suite->test('every ImmortalSeed movie sub-category is reachable', function () {
    $engine = new ImmortalSeedEngine();
    $categories = $engine->categories;
    // One "Non-English" per Movies parent, with the site's own category id.
    $expected = array(
        'Movies-4k' => '&selectedcats2=60',
        'Movies-HD' => '&selectedcats2=18',
        'Movies-Low Def' => '&selectedcats2=34',
        'Movies-SD' => '&selectedcats2=33',
    );
    foreach ($expected as $parent => $query) {
        strictAssertTrue(array_key_exists($parent, $categories),
            'the parent category ' . $parent . ' is gone');
        strictAssertSame(1, count(array_keys($categories, $query, true)),
            'exactly one label must search ' . $query . ', saw '
            . var_export(array_keys($categories, $query, true), true));
    }
});

$suite->test('no two ImmortalSeed labels search the same categories', function () {
    $categories = (new ImmortalSeedEngine())->categories;
    foreach ($categories as $label => $query) {
        strictAssertSame(1, count(array_keys($categories, $query, true)),
            'more than one label searches ' . var_export($query, true));
    }
});

$suite->test('the ImmortalSeed map keeps every entry its source declares', function () {
    // What the browser sends back is a label, and action() reads
    // $categories[$label]; an entry the parser dropped is a label the user can
    // never pick again.
    $source = testFindRepoRoot() . '/plugins/extsearch/engines/ImmortalSeed.php';
    $declared = preg_match_all("/^\t\t'.*'=>'[^']*',?$/m", file_get_contents($source));
    strictAssertTrue($declared > 1, 'no category lines found in ' . $source);
    strictAssertSame($declared, count((new ImmortalSeedEngine())->categories),
        'the parsed map is smaller than the list the file declares');
});

$suite->test('no search engine hides a category behind a repeated label', function () {
    // Source-level, because a duplicate key is resolved when the file is
    // parsed: by the time the array exists the lost entries are unrecoverable.
    $engines = glob(testFindRepoRoot() . '/plugins/extsearch/engines/*.php');
    strictAssertTrue(count($engines) > 0, 'no engine files found');
    foreach ($engines as $engine) {
        $duplicates = extsearchDuplicateArrayKeys($engine);
        strictAssertSame(array(), $duplicates,
            basename($engine) . ' repeats array key(s) ' . implode(', ', $duplicates)
            . ' -- every entry but the last is dropped');
    }
});

exit($suite->run());
