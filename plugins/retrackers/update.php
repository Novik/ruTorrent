<?php

if(count($argv)>2)
	$_SERVER['REMOTE_USER'] = $argv[2];

require_once( 'retrackers.php' );
require_once( $rootPath.'/php/xmlrpc.php' );
require_once( $rootPath.'/php/rtorrent.php' );

function clearTracker($addition,$tracker)
{
	foreach( $addition as $kg=>$group )
	{
		foreach( $group as $kt=>$trk )
		{
			if($trk==$tracker)
				unset($addition[$kg][$kt]);
		}
		if(!count($addition[$kg]))
			unset($addition[$kg]);
	}
	return($addition);
}

function deleteTrackers(&$lst,$todelete)
{
	$ret = false;
	foreach( $lst as $kg=>$group )
	{
		foreach( $group as $kt=>$trk )
		{
			if(in_array($trk,$todelete))
			{
				unset($lst[$kg][$kt]);
				if(!count($lst[$kg]))
					unset($lst[$kg]);
				$ret = true;
			}
		}
	}
	return($ret);
}

$processed = false;
$trks = rRetrackers::load();
if(count($argv)>1)
{
	$hash = $argv[1];
	$req = new rXMLRPCRequest( array(		
		new rXMLRPCCommand("get_session"),
		new rXMLRPCCommand("d.get_custom4",$hash),
		new rXMLRPCCommand("d.get_tied_to_file",$hash),
		new rXMLRPCCommand("d.get_custom1",$hash),
		new rXMLRPCCommand("d.get_directory_base",$hash),
		new rXMLRPCCommand("d.is_private",$hash),
		new rXMLRPCCommand("d.get_name",$hash),
		) );
	if($req->success())
	{
		$isStart = ($req->val[1]!=0);
		if((count($trks->list) || count($trks->todelete)) && !($req->val[5] && $trks->dontAddPrivate) &&
			($req->val[6]!=$hash.".meta"))
		{
			$fname = $req->val[0].$hash.".torrent";
			if(empty($req->val[0]) || !is_readable($fname))
			{
				if(strlen($req->val[2]) && is_readable($req->val[2]))
					$fname = $req->val[2];
				else
					$fname = null;
			}
			if($fname)
			{
				$torrent = new Torrent( $fname );		
				if( !$torrent->errors() )
				{
				        $needToProcessed = true;
					$lst = $torrent->announce_list();
					if(!$lst)
					{
						if(count($trks->list))
						{
							if($torrent->announce())
								$torrent->announce_list($trks->addToBegin ? array_merge($trks->list, array(array($torrent->announce()))) : 
									array_merge(array(array($torrent->announce())),$trks->list));
							else
							{
								$torrent->announce($trks->list[0][0]);
								$torrent->announce_list($trks->list);
							}
						} 
						else
							$needToProcessed = false;
					}
					else
					{
						$addition = $trks->list;
						foreach( $lst as $group )
							foreach( $group as $tracker )
								$addition = clearTracker($addition,$tracker);
						if(count($addition))
							$torrent->announce_list($trks->addToBegin ? array_merge($addition,$lst) : array_merge($lst,$addition));
						else
							$needToProcessed = false;
					}

					$lst = $torrent->announce_list();
					if($lst && count($trks->todelete))
					{
						$needToProcessed = deleteTrackers($lst,$trks->todelete);
						if($needToProcessed)
							$torrent->announce_list($lst);
					}

					if($needToProcessed)
					{
						if(isset($torrent->{'rtorrent'}))
							unset($torrent->{'rtorrent'});
						$eReq = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
						if($eReq->success())
						{
							$label = rawurldecode($req->val[3]);
							rTorrent::sendTorrent($torrent, $isStart, false, $req->val[4], $label, false, false, false,
							        array(getCmd("d.set_custom3")."=1") );
							$processed = true;
						}
					}
				}
			}
		}
		if(!$processed && $isStart)
		{
			$req = new rXMLRPCRequest( new rXMLRPCCommand("d.start", $hash ) );
			$req->run();
		}
	}
}
