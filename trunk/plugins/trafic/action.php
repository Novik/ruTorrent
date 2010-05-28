<?php
	require_once( '../../php/xmlrpc.php' );
	require_once( './stat.php' );
		
	$req = new rXMLRPCRequest( 
		new rXMLRPCCommand("d.multicall", array("main",getCmd("d.get_hash="))) );
	$ret = '{';
	if($req->run() && !$req->fault)
	{
		$tm = time();
		for( $i=0; $i<count($req->val); $i++ )
		{
			$st = new rStat("torrents/".$req->val[$i].".csv");
			$ratios = $st->getRatios( $tm );
			if($ret!='{')
				$ret.=',';
			$ret.=('"'.$req->val[$i].'": ['.$ratios[0].','.$ratios[1].','.$ratios[2].']');
		}
	}
	cachedEcho($ret.'}',"application/json");
?>