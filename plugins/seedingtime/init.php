<?php

$req = new rXMLRPCRequest([
    $theSettings->getOnFinishedCommand(["seedingtime" . User::getUser(),
        getCmd('d.set_custom') . '=seedingtime,"$' . getCmd('execute_capture') . '={date,+%s}"']),
    $theSettings->getOnInsertCommand(["addtime" . User::getUser(),
        getCmd('d.set_custom') . '=addtime,"$' . getCmd('execute_capture') . '={date,+%s}"']),

    $theSettings->getOnHashdoneCommand(["seedingtimecheck" . User::getUser(),
        getCmd('branch=') . '$' . getCmd('not=') . '$' . getCmd('d.get_complete=') . ',,'
        . getCmd('d.get_custom') . '=seedingtime,,"' . getCmd('d.set_custom') . '=seedingtime,$' . getCmd('d.get_custom') . '=addtime' . '"']),
]);
if ($req->success()) {
    $theSettings->registerPlugin($plugin["name"], $pInfo["perms"]);
} else {
    $jResult .= "plugin.disable(); noty('seedingtime: '+theUILang.pluginCantStart,'error');";
}
