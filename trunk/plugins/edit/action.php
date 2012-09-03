<?php

require_once( '../../php/settings.php' );
require_once( '../../php/rtorrent.php' );

ignore_user_abort(true);
set_time_limit(0);
$errors = array();
$hash = null;
if(!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
if(isset($HTTP_RAW_POST_DATA))
{
	$vars = explode('&', $HTTP_RAW_POST_DATA);
	$announce_list = array(); 
	$trackers = array();
	$comment = '';
	$trackersCount = 0;
	foreach($vars as $var)
	{
		$parts = explode("=",$var);
		if($parts[0]=="hash")
			$hash = $parts[1];
		else
		if($parts[0]=="comment")
			$comment = trim(rawurldecode($parts[1]));
		else
		if($parts[0]=="tracker")
		{
			$value = trim(rawurldecode($parts[1]));
			if(strlen($value))
			{
				$trackers[] = $value;
				$trackersCount = $trackersCount+1;
			}
			else
			{
				if(count($trackers)>0)
				{
					$announce_list[] = $trackers;
					$trackers = array();
				}
			}
		}
	}
	if(count($trackers)>0)
		$announce_list[] = $trackers;
	if($hash)	
	{
		$req = new rXMLRPCRequest( array(		
			new rXMLRPCCommand("get_session"),
			new rXMLRPCCommand("d.is_open",$hash),
			new rXMLRPCCommand("d.is_active",$hash),
			new rXMLRPCCommand("d.get_state",$hash),
			new rXMLRPCCommand("d.get_tied_to_file",$hash),
			new rXMLRPCCommand("d.get_custom1",$hash),
			new rXMLRPCCommand("d.get_directory_base",$hash),
			new rXMLRPCCommand("d.get_connection_seed",$hash),
			new rXMLRPCCommand("d.get_complete",$hash),
			) );
		$throttle = null;
		if(rTorrentSettings::get()->isPluginRegistered("throttle"))
			$req->addCommand(new rXMLRPCCommand("d.get_throttle_name",$hash));
		if($req->run() && !$req->fault)
		{
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
					$torrent->clear_announce();
					$torrent->clear_announce_list();
					$torrent->clear_comment();
					if(count($announce_list)>0)
					{
						$torrent->announce($announce_list[0][0]);
						if($trackersCount>1)
							$torrent->announce_list($announce_list);
					}
					$comment = trim($comment);
					if(strlen($comment))
						$torrent->comment($comment);
					if(isset($torrent->{'rtorrent'}))
						unset($torrent->{'rtorrent'});
					if(count($req->val)>9)
						$throttle = getCmd("d.set_throttle_name=").$req->val[9];
					$eReq = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
					if($eReq->run() && !$eReq->fault)
					{
						$label = rawurldecode($req->val[5]);
						if(!rTorrent::sendTorrent($torrent, $isStart, false, $req->val[6], $label, false, ($req->val[8]==1), false,
						        array(	getCmd("d.set_custom3")."=1",
								getCmd("d.set_connection_seed=").$req->val[7],
								$throttle)))
							$errors[] = array('desc'=>"theUILang.errorAddTorrent", 'prm'=>$fname);
					}
					else
						$errors[] = array('desc'=>"theUILang.badLinkTorTorrent", 'prm'=>'');
				}
				else
					$errors[] = array('desc'=>"theUILang.errorReadTorrent", 'prm'=>$fname);
			}
			else
				$errors[] = array('desc'=>"theUILang.cantFindTorrent", 'prm'=>'');
                }
                else
			$errors[] = array('desc'=>"theUILang.badLinkTorTorrent", 'prm'=>'');

	}
}

cachedEcho(json_encode(array( "errors"=>$errors, "hash"=>$hash )),"application/json");
