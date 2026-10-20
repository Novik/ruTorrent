<?php

// A complete download with no seedingtime takes one from its addtime. The
// hash_done key below covers downloads as they complete; the pass over the
// main view covers those already complete when the plugin starts, which no
// event reaches again. The inner branch keeps both writes to the gap: an
// existing seedingtime stays, and an incomplete download gets none.
$fillSeedingtime =
	getCmd('branch=').getCmd('d.get_complete=').',"'.
	getCmd('branch=').getCmd('d.get_custom').'=seedingtime,,\"'.
	getCmd('d.set_custom').'=seedingtime,$'.getCmd('d.get_custom').'=addtime\""';

$req = new rXMLRPCRequest( array(
	$theSettings->getOnFinishedCommand(array("seedingtime".User::getUser(),
		getCmd('d.set_custom').'=seedingtime,"$'.getCmd('execute_capture').'={date,+%s}"')),
	$theSettings->getOnInsertCommand(array("addtime".User::getUser(),
		getCmd('d.set_custom').'=addtime,"$'.getCmd('execute_capture').'={date,+%s}"')),

	$theSettings->getOnHashdoneCommand(array("seedingtimecheck".User::getUser(),
		$fillSeedingtime)),
	));

// Kept out of the request above: a daemon that refuses the pass must still
// get its event handlers and leave the plugin enabled.
$backfill = new rXMLRPCRequest( new rXMLRPCCommand("d.multicall",
	array("main", $fillSeedingtime)) );

if($req->success())
{
        $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
        $backfill->run();
}
else
        $jResult .= "plugin.disable(); noty('seedingtime: '+theUILang.pluginCantStart,'error');";
