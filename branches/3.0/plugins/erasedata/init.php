<?php

require_once( 'xmlrpc.php' );

$needStart = true;
if($do_diagnostic)
{
	@chmod($rootPath.'/plugins/erasedata/cleanup.sh',0755);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/erasedata/cleanup.sh',0x0005))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.erasedataRunNotAvailable');";
		$needStart = false;
	}
}
if($needStart)
{
	if( $theSettings->iVersion < 0x804 )
        	$cmd = new rXMLRPCCommand("on_erase");
	else
        	$cmd = new rXMLRPCCommand("system.method.set_key", "event.download.erased");
	$cmd->addParameters( array('erasedata',
		'branch=d.get_custom5=,cat=$d.open=,"$f.multicall=default,\"execute={rm,-rf,--,$f.get_frozen_path=}\"" ; execute={'.$rootPath.'/plugins/erasedata/cleanup.sh,$d.get_base_path=}'));
	$req = new rXMLRPCRequest( $cmd );
	if($req->run() && !$req->fault)
		$theSettings->registerPlugin( "erasedata" );
	else
		$jResult.="plugin.disable(); log('erasedata: '+theUILang.pluginCantStart);";
}
?>