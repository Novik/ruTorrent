<?php
	require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
	require_once( dirname(__FILE__).'/stat.php' );

	function getRatiosStat()
	{
		$req = new rXMLRPCRequest( 
			new rXMLRPCCommand("d.multicall", array("main",getCmd("d.get_hash="))) );
		$ret = 'theWebUI.ratiosStat = {';
		if($req->run() && !$req->fault)
		{
			$tm = time();
			for( $i=0; $i<count($req->val); $i++ )
			{
				$st = new rStat("torrents/".$req->val[$i].".csv");
				$ratios = $st->getRatios( $tm );
				if($ret!='theWebUI.ratiosStat = {')
					$ret.=',';
				$ret.=('"'.$req->val[$i].'": ['.$ratios[0].','.$ratios[1].','.$ratios[2].']');
			}
		}
		return($ret.'}; ');
	}
