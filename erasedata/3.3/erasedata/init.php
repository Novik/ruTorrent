<?php

require_once( 'xmlrpc.php' );

$params = array(
    getCmd('branch=').getCmd('d.get_custom5').'=,'.getCmd('d.open='),
    getCmd('branch=').getCmd('d.get_custom5').'=,"'.getCmd('f.multicall').'=,\"'.getCmd('execute').'={rm,-rf,--,$'.getCmd('f.get_frozen_path').'=}\""'
);
if(isLocalMode())
	$params[] = getCmd('branch=').getCmd('d.get_custom5').'=,"'.getCmd('execute').'={sh,'.$rootPath.'/plugins/erasedata/cleanup.sh,$'.getCmd('d.get_base_path').'=}"';
//	$params[] = getCmd('branch=').getCmd('d.get_custom5').'=$'.getCmd('and').'=$'.getCmd('d.is_multi_file=').',"'.getCmd('execute').'={rm,-rfd,--,$'.getCmd('d.get_base_path').'=}"';
$req = new rXMLRPCRequest();
foreach( $params as $i=>$prm )
	$req->addCommand($theSettings->getOnEraseCommand(array('erasedata'.$i.getUser(), $prm )));
if($req->run() && !$req->fault)
	$theSettings->registerPlugin( "erasedata" );
else
	$jResult.="plugin.disable(); log('erasedata: '+theUILang.pluginCantStart);";

?>