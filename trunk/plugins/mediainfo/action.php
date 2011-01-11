<?php
require_once( '../../php/xmlrpc.php' );
eval( getPluginConf( 'mediainfo' ) );

function mediaInfo() {
	if(isset($_REQUEST['hash']) && isset($_REQUEST['no'])) {
    	$req = new rXMLRPCRequest( 
			new rXMLRPCCommand( "f.get_frozen_path", array($_REQUEST['hash'],intval($_REQUEST['no']))) );
	if($req->success())
	{
		$filename = $req->val[0];
		if($filename=='')
		{
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand( "d.open", $_REQUEST['hash'] ),
				new rXMLRPCCommand( "f.get_frozen_path", array($_REQUEST['hash'],intval($_REQUEST['no'])) ),
				new rXMLRPCCommand( "d.close", $_REQUEST['hash'] ) ) );
			if($req->success())
				$filename = $req->val[1];
		}
        return(shell_exec(getExternal("mediainfo").' --Output=HTML "'.$filename.'"'));
      }
	}
return false;
}
echo mediaInfo();
?>