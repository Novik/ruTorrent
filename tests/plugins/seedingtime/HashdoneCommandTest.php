<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/SeedingtimeCommandFixtures.php');

class HashdoneCommandTest extends TestCase
{
    use SeedingtimeCommandFixtures;

    public function testHashdoneEmitsNestedConditions()
    {
        // rTorrent 0.16 evaluates one condition/then/else per branch. A
        // five-argument chain only reads seedingtime instead of setting it.
        // Capture the emitted command, including PHP's nested quote escaping.
        foreach ([0x908, 0x1017] as $iVersion) {
            $this->assertEquals(
                'branch=d.complete=,"branch=d.custom=seedingtime,,\"branch=d.timestamp.finished=,\\\\\\"d.custom.set=seedingtime,$cat=$d.timestamp.finished=\\\\\\",\\\\\\"d.custom.set=seedingtime,$d.custom=addtime\\\\\\"\""',
                $this->eventCommand($iVersion, 'event.download.hash_done'),
                sprintf('complete data sets a missing time through quoted inner branches; rTorrent iVersion 0x%x', $iVersion),
            );
        }
    }

    public function testHashdoneTakesTheFinishedTimeFromItsFirstVersion()
    {
        // d.timestamp.finished is there from 0.9.0. Before it the time can
        // only come from addtime. Below 0.9.4 no alias map is loaded, so the
        // older names go out as they are.
        $this->assertEquals(
            'branch=d.get_complete=,"branch=d.get_custom=seedingtime,,\"branch=d.timestamp.finished=,\\\\\\"d.set_custom=seedingtime,$cat=$d.timestamp.finished=\\\\\\",\\\\\\"d.set_custom=seedingtime,$d.get_custom=addtime\\\\\\"\""',
            $this->eventCommand(0x900, 'event.download.hash_done'),
            'rTorrent 0.9.0 takes the finished time, else addtime',
        );
        $this->assertEquals(
            'branch=d.get_complete=,"branch=d.get_custom=seedingtime,,\"d.set_custom=seedingtime,$d.get_custom=addtime\""',
            $this->eventCommand(0x809, 'event.download.hash_done'),
            'rTorrent 0.8.9 takes addtime',
        );
    }
}
