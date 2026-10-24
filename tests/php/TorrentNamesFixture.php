<?php

require_once(__DIR__.'/TestCase.php');
define('TESTLIB_HANDLER_STUBS', true);
require_once(__DIR__.'/../plugins/rutracker_check/TestLib.php');

trait TorrentNamesAssertions
{
    private function assertSame($expected, $actual, $message)
    {
        $this->assertTrue($expected === $actual, $message);
    }
}

function getCmd($command) { return $command; }
