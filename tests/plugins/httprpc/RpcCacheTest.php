<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/httprpc/rpccache.php');

// The cache keeps its state files in a directory taken from the profile path.
// Point it at a scratch directory instead, and let a test take that directory
// away to stand in for an evicted or unwritable cache.
class TestRpcCache extends rpcCache
{
    public function useDirectory($dir)
    {
        $this->dir = $dir;
        @mkdir($dir, 0777, true);
    }
}

class RpcCacheTest extends TestCase
{
    private $dir;
    private $cache;

    public function setUp()
    {
        $this->dir = sys_get_temp_dir() . '/rpccache-test-' . getmypid();
        $this->cache = new TestRpcCache();
        $this->cache->useDirectory($this->dir);
    }

    public function tearDown()
    {
        foreach (glob($this->dir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function torrents($hashes)
    {
        $ret = [];
        foreach ($hashes as $hash) {
            $ret[$hash] = ["1", "name of " . $hash, "0"];
        }
        return($ret);
    }

    private function difference($cid, $hashes)
    {
        $torrents = $this->torrents($hashes);
        $deleted = [];
        $hadPrevious = $this->cache->calcDifference($cid, $torrents, $deleted);
        return(["cid" => $cid, "changed" => $torrents, "deleted" => $deleted, "hadPrevious" => $hadPrevious]);
    }

    public function testFirstAnswerIsFullAndSaysSo()
    {
        $first = $this->difference(0, ["A", "B"]);
        $this->assertTrue(!$first["hadPrevious"], 'nothing to diff against on the first request');
        $this->assertEquals(["A","B"], array_keys($first["changed"]), 'the whole list is answered');
        $this->assertEquals([], $first["deleted"], 'and it carries no deletions');
    }

    public function testDeletionIsReportedAgainstThePreviousState()
    {
        $first = $this->difference(0, ["A", "B"]);
        $second = $this->difference($first["cid"], ["A"]);
        $this->assertTrue($second["hadPrevious"], 'the previous state was found');
        $this->assertEquals(["B"], $second["deleted"], 'B is reported as deleted');
    }

    // The state files are a ring of ten, so a client that polls slowly while
    // another one polls fast can come back with a cid that is gone -- as can any
    // client if the directory cannot be written at all.
    public function testALostStateIsAnnouncedAsFullRatherThanLosingTheDeletion()
    {
        $first = $this->difference(0, ["A", "B"]);
        foreach (glob($this->dir . '/*') as $f) {
            @unlink($f);
        }

        $second = $this->difference($first["cid"], ["A"]);

        $this->assertTrue(!$second["hadPrevious"], 'the state the client named is gone');
        $this->assertEquals([], $second["deleted"], 'so no deletion can be derived');
        $this->assertEquals(
            ["A"],
            array_keys($second["changed"]),
            'and the answer is the whole list, which is what lets the client drop B itself',
        );
    }
}
