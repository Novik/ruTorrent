<?php

// A complete download with no seedingtime takes one. The hash_done key below
// covers downloads as they complete; the pass over the main view covers those
// already complete when the plugin starts, which no event reaches again. The
// inner branch keeps both writes to the gap: an existing seedingtime stays,
// and an incomplete download gets none.
$fromAddtime = getCmd('d.set_custom') . '=seedingtime,$' . getCmd('d.get_custom') . '=addtime';

// rTorrent stamps d.timestamp.finished itself when a download finishes, from
// 0.9.0, and when a hash check finds it complete, from 0.9.7. A download with
// no addtime then gets a time too. It reads 0 when never stamped, and then
// addtime is taken. The value is a number, which d.custom.set refuses, hence
// cat.
if ($theSettings->iVersion >= 0x900) {
    $fill = getCmd('branch=') . 'd.timestamp.finished=,\\\\\\"'
        . getCmd('d.set_custom') . '=seedingtime,$' . getCmd('cat=') . '$d.timestamp.finished=\\\\\\",\\\\\\"'
        . $fromAddtime . '\\\\\\"';
} else {
    $fill = $fromAddtime;
}

$fillSeedingtime
    = getCmd('branch=') . getCmd('d.get_complete=') . ',"'
    . getCmd('branch=') . getCmd('d.get_custom') . '=seedingtime,,\"'
    . $fill . '\""';

$req = new rXMLRPCRequest([
    $theSettings->getOnFinishedCommand(["seedingtime" . User::getUser(),
        getCmd('d.set_custom') . '=seedingtime,"$' . getCmd('execute_capture') . '={date,+%s}"']),
    $theSettings->getOnInsertCommand(["addtime" . User::getUser(),
        getCmd('d.set_custom') . '=addtime,"$' . getCmd('execute_capture') . '={date,+%s}"']),

    $theSettings->getOnHashdoneCommand(["seedingtimecheck" . User::getUser(),
        $fillSeedingtime]),
]);

// Kept out of the request above: a daemon that refuses the pass must still
// get its event handlers and leave the plugin enabled.
$backfill = new rXMLRPCRequest(new rXMLRPCCommand(
    "d.multicall",
    ["main", $fillSeedingtime],
));

if ($req->success()) {
    $theSettings->registerPlugin($plugin["name"], $pInfo["perms"]);
    $backfill->run();
} else {
    $jResult .= "plugin.disable(); noty('seedingtime: '+theUILang.pluginCantStart,'error');";
}
