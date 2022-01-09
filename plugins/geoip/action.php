<?php

require_once( '../../php/util.php' );
eval( FileUtil::getPluginConf( 'geoip' ) );
require_once( 'ip_db.php' );

$db = new ipDB();
$db->add($_REQUEST["ip"],$_REQUEST["comment"]);

CachedEcho::send( JSON::safeEncode( array( "ip"=>$_REQUEST["ip"], "comment"=>$_REQUEST["comment"] ) ), "application/json" );
