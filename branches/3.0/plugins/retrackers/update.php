<?php

if(count($argv)>1)
	$_SERVER['REMOTE_USER'] = $argv[1];

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

$trks = rRetrackers::load();
if(count($trks->list) && (count($argv)>2))
{
	$hash = $argv[2];
	$req = new rXMLRPCRequest( array(		
		new rXMLRPCCommand("get_session"),
		new rXMLRPCCommand("d.is_open",$hash),
		new rXMLRPCCommand("d.is_active",$hash),
		new rXMLRPCCommand("d.get_state",$hash),
		new rXMLRPCCommand("d.get_tied_to_file",$hash),
		new rXMLRPCCommand("d.get_custom1",$hash),
		new rXMLRPCCommand("d.get_directory_base",$hash),
		new rXMLRPCCommand("d.is_private",$hash)
		) );

	if($req->success())
	{
		if($req->val[7] && $trks->dontAddPrivate)
			return;
		$isStart = (($req->val[1]!=0) && ($req->val[2]!=0) && ($req->val[3]!=0));
		$fname = $req->val[0].$hash.".torrent";
		if(empty($req->val[0]) || !is_readable($fname))
		{
			if(strlen($req->val[4]) && is_readable($req->val[4]))
				$fname = $req->val[4];
			else
				$fname = null;
		}
		if($fname)
		{
			$torrent = new Torrent( $fname );		
			if( !$torrent->errors() )
			{
				$lst = $torrent->announce_list();
				if(!$lst)
				{
					if($torrent->announce())
						$torrent->announce_list(array_merge(array(array($torrent->announce())),$trks->list));
					else
					{
						$torrent->announce($trks->list[0][0]);
						$torrent->announce_list($trks->list);
					}
				}
				else
				{
					$addition = $trks->list;
					foreach( $lst as $group )
						foreach( $group as $tracker )
							$addition = clearTracker($addition,$tracker);
					if(!count($addition))
						return;
					$torrent->announce_list(array_merge($lst,$addition));
				}
  		                if(isset($torrent->{'libtorrent_resume'}['trackers']))
					unset($torrent->{'libtorrent_resume'}['trackers']);
				if(isset($torrent->{'rtorrent'}))
					unset($torrent->{'rtorrent'});
				$eReq = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
				if($eReq->success())
				{
					$label = rawurldecode($req->val[5]);
					rTorrent::sendTorrent($torrent, $isStart, false, $req->val[6], $label, false, true,
					        array("d.set_custom3=1") );
				}
			}
		}
	}
}
?>
