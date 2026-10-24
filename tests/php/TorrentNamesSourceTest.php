<?php

require_once(__DIR__.'/TorrentNamesFixture.php');
$source = file_get_contents(__DIR__.'/../../php/rtorrent.php');
$start = strpos($source, 'static public function getSource(');
$method = substr($source, $start, strpos($source, '// The session directory and tied file of every loaded torrent', $start)-$start);
eval('class TorrentNamesSourceOwner {'.$method.'}');

class TorrentNamesSourceTest extends TestCase
{
    use TorrentNamesAssertions;

    public function testKnownPathsUseTheExistingSessionThenTiedSourceOwner()
    {
        $directory = sys_get_temp_dir().'/torrent-names-source-'.getmypid();
        mkdir($directory, 0700);
        chmod($directory, 0700);
        $data = TorrentEncoder::raw(array('info'=>array('name'=>'session', 'length'=>1)));
        $torrent = new Torrent($data);
        $hash = $torrent->hash_info();
        $session = $directory.'/'.$hash.'.torrent';
        $tied = $directory.'/tied.torrent';
        file_put_contents($session, $data);
        file_put_contents($tied, TorrentEncoder::raw(array('info'=>array('name'=>'tied', 'length'=>1))));
        try {
            $owner = 'TorrentNamesSourceOwner';
        rXMLRPCRequest::reset();
            $result = $owner::getSource($hash, array($directory.'/', $tied));
            $this->assertTrue($result instanceof Torrent && $result->hash_info() === $hash, 'prefetched paths use the real Torrent reader');
            $this->assertSame('session', $result->info['name'], 'the existing readable session source keeps precedence');
            unlink($session);
            $result = $owner::getSource($hash, array($directory.'/', $tied));
            $this->assertSame('tied', $result->info['name'], 'missing session source retains tied-file fallback');
            $this->assertSame(array(), rXMLRPCRequest::$requests, 'prefetched metadata never opens another daemon request');
        } finally {
            foreach(glob($directory.'/*') as $file) unlink($file);
            rmdir($directory);
        }
    }

    public function testUnavailablePrefetchedPathsCannotFallBackToAnotherRpc()
    {
        $owner = 'TorrentNamesSourceOwner';
        rXMLRPCRequest::reset();
        foreach(array(false, array(), array('/session/'), array('/session/', null), 'invalid') as $paths) {
            $this->assertSame(false, $owner::getSource(str_repeat('A', 40), $paths),
                'an unavailable optional path result cannot fabricate a source');
        }
        $this->assertSame(array(), rXMLRPCRequest::$requests, 'explicit failed source paths never add a timeout');
        rXMLRPCRequest::queue(array('get_session', 'd.get_tied_to_file'), true, false, array('', ''));
        $this->assertSame(false, $owner::getSource(str_repeat('A', 40)), 'the ordinary source API still resolves its own paths');
        $this->assertSame(1, count(rXMLRPCRequest::$requests), 'omitted paths preserve the former source-owner request');
    }
}
