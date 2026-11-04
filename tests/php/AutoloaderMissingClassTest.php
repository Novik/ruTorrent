<?php

require_once(__DIR__ . "/TestCase.php");
require_once(__DIR__ . "/../../php/util.php");

/**
 * The core autoloader is consulted for every name any code hands to
 * class_exists(), new, or instanceof, including names it was never meant to
 * place: plugin classes, classes a later autoloader in the chain owns, and
 * names that are simply wrong. Being asked for one of those is the normal
 * case, not a fault, so it has to be silent.
 */
class AutoloaderMissingClassTest extends TestCase
{
    protected $warnings = [];

    public function testAnUnknownClassResolvesWithoutADiagnostic()
    {
        list($found, $warnings) = $this->warningsWhileResolving("NoSuchUtilityClass");
        $this->assertTrue(!$found, "An unknown class is reported as absent");
        $this->assertEquals(
            [],
            $warnings,
            "Asking for an unknown class emits nothing: " . json_encode($warnings),
        );
    }

    public function testANamespacedUnknownClassResolvesWithoutADiagnostic()
    {
        list($found, $warnings) = $this->warningsWhileResolving("Vendor\\\\Package\\\\NoSuchUtilityClass");
        $this->assertTrue(!$found, "An unknown namespaced class is reported as absent");
        $this->assertEquals(
            [],
            $warnings,
            "Asking for an unknown namespaced class emits nothing: " . json_encode($warnings),
        );
    }

    public function testAShippedUtilityClassIsStillLoaded()
    {
        $this->assertTrue(
            !class_exists("TimeFormat", false),
            "TimeFormat is not already declared, so the autoloader is what resolves it",
        );
        list($found, $warnings) = $this->warningsWhileResolving("TimeFormat");
        $this->assertTrue($found, "A class under php/utility/ loads through the autoloader");
        $this->assertEquals(
            [],
            $warnings,
            "Loading a utility class emits nothing: " . json_encode($warnings),
        );
    }

    protected function warningsWhileResolving($class, $autoload = true)
    {
        $this->warnings = [];
        set_error_handler(function ($no, $str) {
            $this->warnings[] = $str;
            return true;
        });
        $found = class_exists($class, $autoload);
        restore_error_handler();
        return [$found, $this->warnings];
    }
}
