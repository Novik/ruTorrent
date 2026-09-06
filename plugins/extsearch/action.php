<?php

require_once(dirname(__FILE__) . "/../../php/util.php");
require_once(dirname(__FILE__) . "/../../php/rtorrent.php");
require_once("engines.php");

set_time_limit(0);
$em = engineManager::load();
if ($em === false) {
    $em = new engineManager();
    $em->obtain("./engines");
}

if (isset($_REQUEST['mode'])) {
    $cmd = $_REQUEST['mode'];
    switch ($cmd) {
        case "set":
            {
                $em->set();
                CachedEcho::send($em->get(), "application/javascript");
                break;
            }
        case "get":
            {
                CachedEcho::send(JSON::safeEncode($em->action($_REQUEST['eng'], $_REQUEST['what'], $_REQUEST['cat'])), "application/json");
                break;
            }
        case "loadtorrents":
            {
                if (!isset($HTTP_RAW_POST_DATA)) {
                    $HTTP_RAW_POST_DATA = file_get_contents("php://input");
                }
                if (isset($HTTP_RAW_POST_DATA)) {
                    $vars = explode('&', $HTTP_RAW_POST_DATA);
                    $lbl = null;
                    $dir = null;
                    $isStart = true;
                    $isAddPath = true;
                    $fast = false;
                    $urls = [];
                    $engs = [];
                    $ndx = [];
                    $teg = '';
                    foreach ($vars as $var) {
                        $parts = explode("=", $var);
                        if ($parts[0] == "torrents_start_stopped") {
                            $isStart = false;
                        } elseif ($parts[0] == "not_add_path") {
                            $isAddPath = false;
                        } elseif ($parts[0] == "dir_edit") {
                            $dir = rawurldecode($parts[1]);
                        } elseif ($parts[0] == "label") {
                            $lbl = rawurldecode($parts[1]);
                        } elseif ($parts[0] == "fast_resume") {
                            $fast = $parts[1];
                        } elseif ($parts[0] == "teg") {
                            $teg = $parts[1];
                        } elseif ($parts[0] == "url") {
                            $urls[] = rawurldecode($parts[1]);
                        } elseif ($parts[0] == "eng") {
                            $engs[] = $parts[1];
                        } elseif ($parts[0] == "ndx") {
                            $ndx[] = $parts[1];
                        }
                    }
                    $status = $em->getTorrents($engs, $urls, $isStart, $isAddPath, $dir, $lbl, $fast);
                    $ret = [ "teg" => $teg, "data" => [] ];
                    for ($i = 0; $i < count($status); $i++) {
                        $ret["data"][] = [ "hash" => $status[$i], "ndx" => $ndx[$i] ];
                    }
                    CachedEcho::send(JSON::safeEncode($ret), "application/json");
                }
                break;
            }
    }
}
