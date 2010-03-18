<?php

require_once( 'xmlrpc.php' );

$needStart = true;
if($do_diagnostic)
{
	@chmod($rootPath.'/plugins/erasedata/cleanup.sh',0755);
	if(isLocalMode() && !isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/erasedata/cleanup.sh',0x0005))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.erasedataRunNotAvailable');";
		$needStart = false;
	}
}
if($needStart)
{
	$params = array(
	    'branch=d.get_custom5=,d.open=',
	    'branch=d.get_custom5=,"f.multicall=default,\"execute={rm,-rf,--,$f.get_frozen_path=}\""'
	);
	if(isLocalMode())
		$params[] = 'branch=d.get_custom5=,"execute={'.$rootPath.'/plugins/erasedata/cleanup.sh,$d.get_base_path=}"';
	$req = new rXMLRPCRequest();
	foreach( $params as $i=>$prm )
		$req->addCommand($theSettings->getOnEraseCommand(array('erasedata'.$i.getUser(), $prm )));
	if($req->run() && !$req->fault)
		$theSettings->registerPlugin( "erasedata" );
	else
		$jResult.="plugin.disable(); log('erasedata: '+theUILang.pluginCantStart);";
}
?>