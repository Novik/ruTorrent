<?php

require_once(__DIR__.'/../../php/TestCase.php');
require_once(__DIR__.'/../../php/PhpProcess.php');
require_once(__DIR__.'/../../../php/rtorrent.php');
require_once(__DIR__.'/../../../plugins/history/history.php');

class HistoryDisplayTorrent extends Torrent
{
    public static function encoded($data) { return self::encode($data); }
}

// Only the transport is replaced: the production hook and notification builders
// below are evaluated intact and still use real command/settings/JSON owners.
class HistoryDisplayRequest
{
    public static $commands;
    public function __construct($commands) { self::$commands = $commands; }
    public function success() { return true; }
}

class HistoryDisplayNotification
{
    public $user;
    public static $sent;
    public function fetch($url, $method, $type, $body) { self::$sent = array($url, $method, $type, $body); }
}

class HistoryDisplayTest extends TestCase
{
    private function assertSame($expected, $actual, $message)
    {
        $this->assertTrue($expected === $actual, $message);
    }
    private function event($name, $time = 31)
    {
        return array('name'=>$name, 'label'=>"label\xFE", 'tracker'=>"https://tracker.test/\xFD",
            'hash'=>md5($name), 'action'=>1, 'action_time'=>$time, 'size'=>123, 'ratio'=>0);
    }

    private function projected($event)
    {
        $history = new rHistoryData();
        $history->data = array($event['hash']=>$event);
        return $history->get(0)['items'][0];
    }

    private function manager()
    {
        if(!class_exists('HistoryDisplayManager', false)) {
            $source = file_get_contents(__DIR__.'/../../../plugins/history/history.php');
            $source = substr($source, strpos($source, "class rHistory\n"));
            $source = str_replace(array("class rHistory\n", 'new rXMLRPCRequest(', 'new Snoopy()'),
                array("class HistoryDisplayManager\n", 'new HistoryDisplayRequest(', 'new HistoryDisplayNotification()'), $source);
            eval($source);
        }
        return (new ReflectionClass('HistoryDisplayManager'))->newInstance();
    }

    private function withSettings($callback)
    {
        $class = new ReflectionClass('rTorrentSettings');
        $property = $class->getProperty('theSettings');
        if(PHP_VERSION_ID < 80100) $property->setAccessible(true);
        $previous = $property->getValue();
        $settings = $class->newInstanceWithoutConstructor();
        $settings->iVersion = 0x101A;
        $property->setValue(null, $settings);
        try { $callback(); }
        finally { $property->setValue(null, $previous); }
    }

    public function testOldRecordsDisplayUnknownBytesWithoutChangingStoredEvents()
    {
        $event = $this->event("episode\xFF.mkv");
        $old = $this->event('older', 10);
        $history = new rHistoryData();
        $history->data = array($event['hash']=>$event, $old['hash']=>$old);
        $stored = serialize($history);
        $first = $history->get(20);
        $this->assertSame('episode\\xFF.mkv', $first['items'][0]['name'], 'old records have visible original bytes instead of guessed encoding');
        $this->assertSame('label\\xFE', $first['items'][0]['label'], 'labels use the same display boundary');
        $this->assertSame('https://tracker.test/\\xFD', $first['items'][0]['tracker'], 'display-only tracker text retains its unknown bytes');
        $this->assertSame(array($event['hash']), array_column($first['items'], 'hash'), 'incremental history still uses the original time filter and event key');
        $this->assertSame($first, $history->get(20), 'repeated polling does not escape the projection twice');
        $this->assertSame($stored, serialize($history), 'projection never mutates serialized history or event identity');
        $wire = JSON::safeEncode($first);
        $this->assertSame(false, strpos($wire, '\\ufffd'), 'the JSON response no longer substitutes unknown history bytes');
    }

    public function testValidUnicodeAndInvalidStoredAliasesKeepTheirOriginalMeaning()
    {
        $event = $this->event('映画 / Серия 🙂');
        $event['label'] = 'valid'; $event['tracker'] = 'https://tracker.test/';
        $this->assertSame($event, $this->projected($event), 'valid Unicode remains byte-identical');
        $event['name'] = "raw\xFF";
        foreach(array(null, false, array('alias'), "bad\xFF", '') as $alias) {
            $event['display_name'] = $alias;
            $this->assertSame('raw\\xFF', $this->projected($event)['name'], 'malformed optional display snapshots never replace the fallback');
        }
        $event['display_name'] = 'Stored label';
        $this->assertSame('Stored label', $this->projected($event)['name'], 'a previously attested Unicode label is display-only');
        $event['name'] = 'Already Unicode';
        $this->assertSame('Already Unicode', $this->projected($event)['name'], 'a stored display field cannot replace an already valid title');
    }

    public function testNewEventsUseExactSourceEvidenceAndKeepRawNames()
    {
        $directory = sys_get_temp_dir().'/history-display-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        chmod($directory, 0700);
        $raw = "episode\xFF";
        $meta = array('info'=>array('name'=>$raw, 'name.utf-8'=>'Серия', 'length'=>123));
        $bytes = HistoryDisplayTorrent::encoded($meta);
        $source = (new Torrent($bytes));
        $hash = $source->hash_info();
        $file = $directory.'/'.$hash.'.torrent';
        file_put_contents($file, $bytes);
        try {
            $event = $this->event($raw);
            $record = rHistoryData::captureTorrentName($event, $hash, array($directory.'/', ''));
            $this->assertSame($raw, $record['name'], 'metadata never rewrites the stored original title');
            $this->assertSame($event['hash'], $record['hash'], 'display capture leaves the existing event identity untouched');
            $this->assertSame('Серия', $this->projected($record)['name'], 'exact hash and raw name attest the UTF-8 alias');
            $record = rHistoryData::captureTorrentName($this->event("other\xFF"), $hash, array('', $file));
            $this->assertSame('other\\xFF', $this->projected($record)['name'], 'same torrent hash does not attest a different raw name');
            $record = rHistoryData::captureTorrentName($event, str_repeat('A', 40), array('', $file));
            $this->assertSame('episode\\xFF', $this->projected($record)['name'], 'an unrelated source hash cannot supply a label');
            unlink($file);
            foreach(array(false, null, array(), array($directory.'/'), array($directory.'/', $file)) as $paths) {
                $record = rHistoryData::captureTorrentName($event, $hash, $paths);
                $this->assertSame('episode\\xFF', $this->projected($record)['name'], 'missing or partial metadata, including source removed after an erase hook, retains the event');
                $this->assertSame($raw, $record['name'], 'source failure never rewrites the original bytes');
            }
        } finally {
            if(is_file($file)) unlink($file);
            rmdir($directory);
        }
    }

    public function testHooksAppendSourceIdentityAfterTheExistingUserArgument()
    {
        $this->withSettings(function() {
            $this->assertSame(true, $this->manager()->setHandlers(), 'registration uses its original request success result');
            $commands = HistoryDisplayRequest::$commands;
            $this->assertSame(3, count($commands), 'all three hooks are registered in one existing request');
            $ending = ',u:'.User::getUser().',$'.getCmd('d.get_hash').'=,$'.getCmd('get_session').'=,$'.getCmd('d.get_tied_to_file').'=}';
            foreach($commands as $index=>$command) {
                $xml = end($command->params)->value;
                $this->assertSame(true, strpos($xml, ','.($index+1).',$'.getCmd('d.get_name').'=,') !== false, 'each callback keeps its original action and raw-name slot');
                $this->assertSame(true, strpos($xml, $ending) !== false, 'source fields follow the original user without shifting prior hook arguments');
            }
        });
    }

    public function testActualUpdateHookStoresTheRawNameAndDisplaySnapshot()
    {
        $directory = sys_get_temp_dir().'/history-hook-display-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        chmod($directory, 0700);
        $raw = "episode\xFF";
        $bytes = HistoryDisplayTorrent::encoded(array('info'=>array('name'=>$raw, 'name.utf-8'=>'Серия', 'length'=>123)));
        $hash = (new Torrent($bytes))->hash_info();
        $file = $directory.'/'.$hash.'.torrent';
        file_put_contents($file, $bytes);
        $runner = '$_ENV["RU_PROFILE_PATH"]=$argv[1];$script=$argv[2];$argv=array_slice($argv,2);include $script;'
            .'$history=rHistoryData::load();$rows=array_values($history->data);'
            .'echo json_encode(array("raw"=>array_map("base64_encode",array_column($rows,"name")),"view"=>$history->get(0),"user"=>$_SERVER["REMOTE_USER"]));';
        $args = array_merge(PhpProcess::sameIni(), array('-r', $runner, '--', $directory.'/profile',
            realpath(__DIR__.'/../../../plugins/history/update.php'), '1', $raw, '123', '0', '0', '0', '1', '2', '3',
            'https://tracker.test/#', 'label', '1', 'u:history-display-test', $hash, $directory.'/', ''));
        try {
            $result = PhpProcess::run($args);
            $this->assertSame(0, $result['code'], 'the actual CLI hook finishes successfully');
            $this->assertSame('', $result['err'], 'hook metadata capture emits no diagnostics');
            $payload = json_decode($result['out'], true);
            $this->assertSame(array(base64_encode($raw)), $payload['raw'], 'serialized history keeps exact original bytes');
            $this->assertSame('history-display-test', $payload['user'], 'appended source fields cannot change the existing profile user');
            $this->assertSame('Серия', $payload['view']['items'][0]['name'], 'the later history read uses the captured source alias');
            unlink($file);
            $args[array_search(realpath(__DIR__.'/../../../plugins/history/update.php'), $args, true)+1] = '3';
            $result = PhpProcess::run($args);
            $this->assertSame(0, $result['code'], 'a detached erase callback still records an event after source removal');
            $this->assertSame('', $result['err'], 'source removal has no warning or retry');
            $payload = json_decode($result['out'], true);
            $names = array_column($payload['view']['items'], 'name', 'action');
            ksort($names);
            $this->assertSame(array(1=>'Серия', 3=>'episode\\xFF'), $names, 'addition keeps its snapshot while source-less erase has visible original bytes');
        } finally {
            FileUtil::deleteDirectory($directory);
        }
    }

    public function testNotificationProjectsTextWithoutChangingItsInputOrSendingNetworkTraffic()
    {
        global $pushBulletNotifications, $pushBulletEndpoint;
        $saved = $pushBulletNotifications;
        $pushBulletNotifications['addition'] = array('title'=>'{name}', 'body'=>'{label} {tracker}');
        $event = $this->event("episode\xFF") + array('downloaded'=>0, 'uploaded'=>0, 'creation'=>1, 'added'=>2, 'finished'=>3);
        $before = serialize($event);
        try {
            $this->withSettings(function() use ($event, $pushBulletEndpoint) {
                $manager = $this->manager();
                $manager->pushBulletNotify($event);
                $sent = HistoryDisplayNotification::$sent;
                $this->assertSame(array($pushBulletEndpoint, 'POST', 'application/json'), array_slice($sent, 0, 3), 'notification keeps the existing endpoint and request format');
                $this->assertSame(array('type'=>'note', 'title'=>'episode\\xFF', 'body'=>'label\\xFE https://tracker.test/\\xFD'),
                    json_decode($sent[3], true), 'notification templates use the same lossless display text as history');
            });
            $this->assertSame($before, serialize($event), 'notification formatting cannot mutate the stored event');
        } finally {
            $pushBulletNotifications = $saved;
        }
    }

    public function testOldAndAnonymousHookUsersKeepTheirProfiles()
    {
        $directory = sys_get_temp_dir().'/history-hook-users-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        chmod($directory, 0700);
        $runner = '$_ENV["RU_PROFILE_PATH"]=$argv[1];$script=$argv[2];$argv=array_slice($argv,2);include $script;'
            .'echo json_encode(array("user"=>$_SERVER["REMOTE_USER"],"items"=>rHistoryData::load()->get(0)["items"]));';
        try {
            foreach(array(array('legacy-user'), array('u:', str_repeat('A',40), '', ''),
                array('u:partial-user', str_repeat('A',40))) as $tail) {
                $args = array_merge(PhpProcess::sameIni(), array('-r', $runner, '--', $directory,
                    realpath(__DIR__.'/../../../plugins/history/update.php'), '1', 'Unicode title', '123', '0', '0', '0',
                    '1', '2', '3', 'https://tracker.test/#', 'label', '1'), $tail);
                $result = PhpProcess::run($args);
                $this->assertSame(0, $result['code'], 'old and anonymous hooks still finish successfully');
                $this->assertSame('', $result['err'], 'hook user compatibility emits no diagnostics');
                $payload = json_decode($result['out'], true);
                $this->assertSame(count($tail) === 1 ? 'legacy-user' : substr($tail[0], 2), $payload['user'], 'old users, an explicitly empty user and a partial new hook retain their profile identity');
                $this->assertSame('Unicode title', $payload['items'][0]['name'], 'user argument compatibility keeps the event visible');
            }
        } finally {
            FileUtil::deleteDirectory($directory);
        }
    }

    public function testMalformedNewHookCannotChooseAProfile()
    {
        $runner = '$script=$argv[1];$argv=array_slice($argv,1);include $script;'
            .'echo json_encode(array("loaded"=>class_exists("rHistoryData",false),"user"=>$_SERVER["REMOTE_USER"]??null));';
        $args = array_merge(PhpProcess::sameIni(), array('-r', $runner, '--',
            realpath(__DIR__.'/../../../plugins/history/update.php'), '1', 'title', '123', '0', '0', '0',
            '1', '2', '3', 'https://tracker.test/#', 'label', '1', 'untagged-new-user', str_repeat('A',40)));
        $result = PhpProcess::run($args);
        $this->assertSame(0, $result['code'], 'malformed appended hook arguments are refused cleanly');
        $this->assertSame('', $result['err'], 'malformed-hook refusal is quiet');
        $this->assertSame(array('loaded'=>false,'user'=>null), json_decode($result['out'], true), 'an untagged new hook cannot bootstrap or write a different profile');
    }

    public function testOptionalMetadataFailuresNeverDiscardTheEvent()
    {
        $directory = sys_get_temp_dir().'/history-source-failure-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        chmod($directory, 0700);
        file_put_contents($directory.'/broken.torrent', 'not a torrent');
        try {
            $event = $this->event("raw\xFF");
            // false means no paths: getSource() then makes no daemon request.
            foreach(array(false, array($directory.'/', ''), array('', $directory.'/broken.torrent'), array($directory.'/')) as $paths) {
                $record = rHistoryData::captureTorrentName($event, str_repeat('A', 40), $paths);
                $this->assertSame($event, $record, 'a missing, unreadable or partial source leaves the event as it was');
                $this->assertSame('raw\\xFF', $this->projected($record)['name'], 'the event still shows its bytes');
            }
        } finally {
            FileUtil::deleteDirectory($directory);
        }
    }
}
