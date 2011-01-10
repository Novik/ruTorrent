<?php
require_once( '../../php/xmlrpc.php' );
eval( getPluginConf( 'mediainfo' ) );

function mediaInfo() {
        if(isset($_REQUEST['hash']) && isset($_REQUEST['no']))
        {
                                $req = new rXMLRPCRequest( array(
                                        new rXMLRPCCommand( "d.get_base_path", $_REQUEST['hash'] ),
                                        new rXMLRPCCommand( "f.get_path", array($_REQUEST['hash'],intval($_REQUEST['no'])))));
                                if($req->success()) {
                                        $dir = $req->val[0];
                                        $filename = $req->val[1];
                                                if($dir=='') {
                                                        $req = new rXMLRPCRequest( array(
                                                                new rXMLRPCCommand( "d.open", $_REQUEST['hash'] ),
                                                                new rXMLRPCCommand( "d.get_base_path", $_REQUEST['hash'] ),
                                                                new rXMLRPCCommand( "d.close", $_REQUEST['hash'] )));
                                                        if($req->success())
                                                                $dir = $req->val[1];
                                                }
                                        return(shell_exec(getExternal("mediainfo").' --Output=HTML "'.$dir."/".$filename.'"'));
                                }
        }
return false;
}
echo mediaInfo();
?>