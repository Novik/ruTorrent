<?php

require_once(dirname(__FILE__) . '/../_task/task.php');
eval(FileUtil::getPluginConf('mediainfo'));

class mediainfoSettings
{
    public $hash = "mediainfo.dat";
    public $modified = false;
    public $data = [];
    public static function load()
    {
        $cache = new rCache();
        $rt = new mediainfoSettings();
        return($cache->get($rt) ? $rt : null);
    }
}

$ret = [ "status" => 255, "errors" => ["Can't retrieve information"] ];

if (isset($_REQUEST['hash'])
    && isset($_REQUEST['no'])
    && isset($_REQUEST['cmd'])) {
    switch ($_REQUEST['cmd']) {
        case "mediainfo":
            {
                $req = new rXMLRPCRequest(new rXMLRPCCommand("f.get_frozen_path", [$_REQUEST['hash'],intval($_REQUEST['no'])]));
                if ($req->success()) {
                    $filename = $req->val[0];
                    if ($filename == '') {
                        $req = new rXMLRPCRequest([
                            new rXMLRPCCommand("d.open", $_REQUEST['hash']),
                            new rXMLRPCCommand("f.get_frozen_path", [$_REQUEST['hash'],intval($_REQUEST['no'])]),
                            new rXMLRPCCommand("d.close", $_REQUEST['hash']) ]);
                        if ($req->success()) {
                            $filename = $req->val[1];
                        }
                    }
                    if ($filename !== '') {
                        $commands = [];
                        $flags = '';
                        $st = mediainfoSettings::load();
                        $task = new rTask(
                            [
                                'arg' => FileUtil::getFileName($filename),
                                'requester' => 'mediainfo',
                                'name' => 'mediainfo',
                                'hash' => $_REQUEST['hash'],
                                'no' => $_REQUEST['no'],
                            ],
                        );
                        if ($st && !empty($st->data["mediainfousetemplate"])) {
                            $randName = $task->makeDirectory() . "/opts";
                            file_put_contents($randName, $st->data["mediainfotemplate"]);
                            $flags = "--Inform=file://" . escapeshellarg($randName);
                        }
                        $commands[] = Utility::getExternal("mediainfo") . " " . $flags . " " . escapeshellarg($filename);
                        $ret = $task->start($commands, rTask::FLG_WAIT);
                    }
                }
                break;
            }
    }
}

CachedEcho::send(JSON::safeEncode($ret), "application/json");
