<?php

require_once( '../../php/xmlrpc.php' );
require_once( 'rpccache.php' );

$mode = "raw";
$add = array();
$ss = array();
$vs = array();
$hash = array();
if (!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
if(isset($HTTP_RAW_POST_DATA))
{
	$vars = explode('&', $HTTP_RAW_POST_DATA);
	foreach($vars as $var)
	{
		$parts = explode("=",$var);
		switch($parts[0])
		{
			case "cmd":
			{
				$c = getCmd(rawurldecode($parts[1]));
				if(strpos($c,"execute")===false)
					$add[] = $c;
				break;  	
			}
			case "s":
			{
				$ss[] = rawurldecode($parts[1]);
				break;
			}
			case "v":
			{
				$vs[] = rawurldecode($parts[1]);
				break;
			}
			case "hash":
			{
				$hash[] = $parts[1];
				break;
			}
			case "mode":
			{
				$mode  = $parts[1];
				break;
			}
			case "cid":
			{
				$cid  = $parts[1];
				break;
			}
		}
	}
}

function makeMulticall($cmds,$hash,$add,$prefix)
{
	$cmd = new rXMLRPCCommand( $prefix.".multicall", array( $hash, "" ) );
	$cmd->addParameters( array_map("getCmd", $cmds) );
	foreach( $add as $prm )
		$cmd->addParameter($prm);
	$cnt = count($cmds)+count($add);
	$req = new rXMLRPCRequest($cmd);
	if($req->success(false))
	{
	        $result = array();
		for($i = 0; $i<count($req->val); $i+=$cnt)
			$result[] = array_slice($req->val, $i, $cnt);
		return($result);
	}
	return(false);
}

function makeSimpleCall($cmds,$hash)
{
	$req = new rXMLRPCRequest();
	foreach($hash as $h)
		foreach($cmds as $cmd)	
			$req->addCommand( new rXMLRPCCommand( $cmd, $h ) );
       	return($req->success(false) ? $req->val : false);
}

$result = null;

switch($mode)
{
	case "list":	/**/
	{
		$cmds = array(
			"d.get_hash=", "d.is_open=", "d.is_hash_checking=", "d.is_hash_checked=", "d.get_state=",
			"d.get_name=", "d.get_size_bytes=", "d.get_completed_chunks=", "d.get_size_chunks=", "d.get_bytes_done=",
			"d.get_up_total=", "d.get_ratio=", "d.get_up_rate=", "d.get_down_rate=", "d.get_chunk_size=",
			"d.get_custom1=", "d.get_peers_accounted=", "d.get_peers_not_connected=", "d.get_peers_connected=", "d.get_peers_complete=",
			"d.get_left_bytes=", "d.get_priority=", "d.get_state_changed=", "d.get_skip_total=", "d.get_hashing=",
			"d.get_chunks_hashed=", "d.get_base_path=", "d.get_creation_date=", "d.get_tracker_focus=", "d.is_active=",
			"d.get_message=", "d.get_custom2=", "d.get_free_diskspace=", "d.is_private=", "d.is_multi_file="
			);
		$cmd = new rXMLRPCCommand( "d.multicall", "main" );
		$cmd->addParameters( array_map("getCmd", $cmds) );
		foreach( $add as $prm )
			$cmd->addParameter($prm);
		$cnt = count($cmds)+count($add);
		$req = new rXMLRPCRequest($cmd);
		if($req->success(false))
		{
			$theCache = new rpcCache();
			$dTorrents = array();
			$torrents = array();
			foreach($req->val as $index=>$value) 
			{
				if($index % $cnt == 0) 
				{
					$current_index = $value;
					$torrents[$current_index] = array();
				} 
				else
					$torrents[$current_index][] = $value;
			}

			$theCache->calcDifference( $cid, $torrents, $dTorrents );
			$result = array( "t"=>$torrents, "cid"=>$cid );
			if(count($dTorrents))
				$result["d"] = $dTorrents;
		}
		break;
	}
	case "fls":	/**/
	{
		$result = makeMulticall(array(
			"f.get_path=", "f.get_completed_chunks=", "f.get_size_chunks=", "f.get_size_bytes=", "f.get_priority="
			),$hash[0],$add,'f');
		break;
	}
	case "prs":	/**/
	{
		$result = makeMulticall(array(
			"p.get_id=", "p.get_address=", "p.get_client_version=", "p.is_incoming=", "p.is_encrypted=",
			"p.is_snubbed=", "p.get_completed_percent=", "p.get_down_total=", "p.get_up_total=", "p.get_down_rate=",
			"p.get_up_rate=", "p.get_id_html=", "p.get_peer_rate=", "p.get_peer_total=", "p.get_port="
			),$hash[0],$add,'p');
		break;
	}
	case "trk":	/**/
	{
		$result = makeMulticall(array(
		        "t.get_url=", "t.get_type=", "t.is_enabled=", "t.get_group=", "t.get_scrape_complete=", 
			"t.get_scrape_incomplete=", "t.get_scrape_downloaded=",
			"t.get_normal_interval=", "t.get_scrape_time_last="
			),$hash[0],$add,'t');
		break;
	}
	case "stg":	/**/
	{
		$cmds = array(
			"get_check_hash", "get_bind", "get_dht_port", "get_directory", "get_download_rate", 
			"get_hash_interval", "get_hash_max_tries", "get_hash_read_ahead", "get_http_cacert", "get_http_capath",
			"get_http_proxy", "get_ip", "get_max_downloads_div", "get_max_downloads_global", "get_max_file_size",
			"get_max_memory_usage", "get_max_open_files", "get_max_open_http", "get_max_peers", "get_max_peers_seed",
			"get_max_uploads", "get_max_uploads_global", "get_min_peers_seed", "get_min_peers", "get_peer_exchange",
			"get_port_open", "get_upload_rate", "get_port_random", "get_port_range", "get_preload_min_size",
			"get_preload_required_rate", "get_preload_type", "get_proxy_address", "get_receive_buffer_size", "get_safe_sync",
			"get_scgi_dont_route", "get_send_buffer_size", "get_session", "get_session_lock", "get_session_on_completion",
			"get_split_file_size", "get_split_suffix", "get_timeout_safe_sync", "get_timeout_sync", "get_tracker_numwant",
			"get_use_udp_trackers", "get_max_uploads_div", "get_max_open_sockets"
			);
		if(rTorrentSettings::get()->iVersion>=0x900)
			$cmds[5] = $cmds[6] = $cmds[7] = "cat";
		$req = new rXMLRPCRequest( new rXMLRPCCommand( "dht_statistics" ) );
		foreach( $cmds as $cmd )
			$req->addCommand( new rXMLRPCCommand( $cmd ) );	
		foreach( $add as $prm )
			$req->addCommand( new rXMLRPCCommand( $prm ) );	
		if($req->success(false))
		{
	        	$result = array();
			$dht_active = $req->val[0];
                        $dht = $req->val[1];
			$i = 3;
                        if($dht_active!='0')
			{
				$i+=(count($req->val)-51);
				$dht = $req->val[5];
			}
			$result = array_slice($req->val, $i, count($cmds));
			array_unshift($result, (($dht=="auto") || ($dht=="on")) ? 1 : 0);
		}
		break;
	}
	case "ttl":	/**/
	{
		$cmds = array(
		        "get_up_total", "get_down_total", "get_upload_rate", "get_download_rate"
		        );
		$req = new rXMLRPCRequest();
		foreach( $cmds as $cmd )
			$req->addCommand( new rXMLRPCCommand( $cmd ) );	
		foreach( $add as $prm )
			$req->addCommand( new rXMLRPCCommand( $prm ) );	
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "opn":	/**/
	{
		$cmds = array(
			"network.http.current_open", "network.open_sockets"
		);
		if (rTorrentSettings::get()->apiVersion >= 11)
			$cmds[] = "network.open_files";
		$req = new rXMLRPCRequest();
		foreach( $cmds as $cmd )
			$req->addCommand( new rXMLRPCCommand( $cmd ) );
		if($req->success(false)) {
			$result = $req->val;
			if (count($cmds) < 3)
				$result[] = -1;
		}
		break;
	}
	case "prp":	/**/
	{
		$cmds = array(
			"d.get_peer_exchange", "d.get_peers_max", "d.get_peers_min", "d.get_tracker_numwant", "d.get_uploads_max",
			"d.is_private", "d.get_connection_seed"
		        );
		$req = new rXMLRPCRequest();
		foreach( $cmds as $cmd )
			$req->addCommand( new rXMLRPCCommand( $cmd, $hash[0] ) );	
		foreach( $add as $prm )
			$req->addCommand( new rXMLRPCCommand( $prm, $hash[0] ) );	
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "trkstate":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($vs as $ndx=>$value)
			$req->addCommand( new rXMLRPCCommand( "t.set_enabled", array($hash[0], intval($value), intval($ss[0])) ) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "setprio":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($vs as $v)
			$req->addCommand( new rXMLRPCCommand( "f.set_priority", array($hash[0], intval($v), intval($ss[0])) ) );
		$req->addCommand( new rXMLRPCCommand("d.update_priorities", $hash[0]) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "recheck":	/**/
	{
        	$result = makeSimpleCall(array("d.check_hash"), $hash);
		break;
	}
	case "start":	/**/
	{
		$result = makeSimpleCall(array("d.open","d.start"), $hash);
		break;
	}
	case "stop":	/**/
	{
		$result = makeSimpleCall(array("d.stop","d.close"), $hash);
		break;
	}
	case "pause":	/**/
	{
		$result = makeSimpleCall(array("d.stop"), $hash);
		break;
	}
	case "unpause":	/**/
	{
		$result = makeSimpleCall(array("d.start"), $hash);
		break;
	}
	case "remove":	/**/
	{
		$result = makeSimpleCall(array("d.erase"), $hash);
		break;
	}
	case "dsetprio":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($hash as $ndx=>$h)
			$req->addCommand( new rXMLRPCCommand( "d.set_priority", array($h, intval($vs[0])) ) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "setlabel":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($hash as $ndx=>$h)
			$req->addCommand( new rXMLRPCCommand( "d.set_custom1", array($h, $vs[0]) ) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "trkall":	/**/
	{
		$cmds = array(
		        "t.get_url=", "t.get_type=", "t.is_enabled=", "t.get_group=", "t.get_scrape_complete=", 
			"t.get_scrape_incomplete=", "t.get_scrape_downloaded="
		        );
		$result = array();
		if(empty($hash))
		{
			$prm = getCmd("cat").'="$'.getCmd("t.multicall=").getCmd("d.get_hash=").",";
			foreach( $cmds as $tcmd )
				$prm.=getCmd($tcmd).','.getCmd("cat=#").',';
			foreach( $add as $tcmd )
				$prm.=getCmd($tcmd).','.getCmd("cat=#").',';
			$prm = substr($prm, 0, -1).'"';
			$cnt = count($cmds)+count($add);
			$req = new rXMLRPCRequest();
			$req->addCommand( new rXMLRPCCommand( "d.multicall", array
			( 
				"main",
				getCmd("d.get_hash="),
				$prm
			) ) );						
	       		if($req->success(false))
			{
				for( $i = 0; $i< count($req->val); $i+=2 )
				{
					$tracker = explode( '#', $req->val[$i+1] );
					if(!empty($tracker))
						unset( $tracker[ count($tracker)-1 ] );
					$result[ $req->val[$i] ] = array_chunk( $tracker, $cnt );
				}
			}									
		}
		else
		{
			foreach($hash as $ndx=>$h)
			{
				$ret = makeMulticall($cmds,$h,$add,'t');
				if($ret===false)
					$result[$h] = array();				
				else
					$result[$h] = $ret;
			}
		}
		break;
	}
	case "setsettings":
	{
		$req = new rXMLRPCRequest();
		foreach($vs as $ndx=>$v)
		{
			if($ss[$ndx][0]=='n')
				$v = floatval($v);
			if( ($ss[$ndx]=="sdirectory") && !rTorrentSettings::get()->correctDirectory($v) )
				continue;
			if($ss[$ndx]=="ndht")
				$cmd = new rXMLRPCCommand('dht',(($v==0) ? "disable" : "auto"));
			else
				$cmd = new rXMLRPCCommand('set_'.substr($ss[$ndx],1),$v);
			$req->addCommand($cmd);
		}
		if($req->getCommandsCount())
		{
			if($req->success(false))
		        	$result = $req->val;
        	}
        	else
	        	$result = array();
		break;
	}
	case "setprops":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($ss as $ndx=>$s)
		{
			if($s=="superseed")
			{
        			$conn = ($vs[$ndx]!=0) ? "initial_seed" : "seed";
				$cmd = new rXMLRPCCommand("branch", array(
					$hash[0],
					getCmd("d.is_active="),
					getCmd("cat").'=$'.getCmd("d.stop=").',$'.getCmd("d.close=").',$'.getCmd("d.set_connection_seed=").$conn.',$'.getCmd("d.open=").',$'.getCmd("d.start="),
					getCmd("d.set_connection_seed=").$conn
					));
			}
			else
			{
				if($s=="ulslots")
					$cmd = new rXMLRPCCommand("d.set_uploads_max");
				else
				if($s=="pex")
					$cmd = new rXMLRPCCommand("d.set_peer_exchange");
				else
					$cmd = new rXMLRPCCommand("d.set_".$s);
				$cmd->addParameters( array($hash[0], $vs[$ndx]) );
			}
			$req->addCommand($cmd);
		}
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "setul":	/**/
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("set_upload_rate", $ss[0]) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "setdl":	/**/
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("set_download_rate", $ss[0]) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "unsnub":
	case "snub":
	{
		$on = (($mode=="snub") ? 1 : 0);
		$req = new rXMLRPCRequest();
                foreach($vs as $v)
			$req->addCommand( new rXMLRPCCommand("p.snubbed.set", array($hash[0].":p".$v,$on)) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "ban":
	{
		$req = new rXMLRPCRequest();
                foreach($vs as $v)
		{
			$req->addCommand( new rXMLRPCCommand("p.banned.set", array($hash[0].":p".$v,1)) );
			$req->addCommand( new rXMLRPCCommand("p.disconnect", $hash[0].":p".$v) );
		}
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "kick":
	{
		$req = new rXMLRPCRequest();
                foreach($vs as $v)
			$req->addCommand( new rXMLRPCCommand("p.disconnect", $hash[0].":p".$v) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "add_peer":
	{
		$req = new rXMLRPCRequest(
			new rXMLRPCCommand( "add_peer", array($hash[0], $vs[0]) ) );
		if($req->success(false))
	        	$result = $req->val;
		break;
	}
	case "getchunks":
	{
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( "d.get_bitfield", $hash[0] ),
			new rXMLRPCCommand( "d.get_chunk_size", $hash[0] ),
			new rXMLRPCCommand( "d.get_size_chunks", $hash[0] ) ));
		if(rTorrentSettings::get()->apiVersion>=4)
			$req->addCommand(new rXMLRPCCommand( "d.chunks_seen", $hash[0] ));
		if($req->success(false))
		{
	        	$result = array( "chunks"=>$req->val[0], "size"=>$req->val[1], "tsize"=>$req->val[2] );
			if(rTorrentSettings::get()->apiVersion>=4)
				$result["seen"] = $req->val[3];
	        }
		break;
	}
	default:
	{
		if(isset($HTTP_RAW_POST_DATA))
		{
			$result = rXMLRPCRequest::send($HTTP_RAW_POST_DATA,false);
			if(!empty($result))
			{
				$pos = strpos($result, "\r\n\r\n");
				if($pos !== false)
					$result = substr($result,$pos+4);
				CachedEcho::send($result, "text/xml");
			}
		}
		break;
	}
}

if(is_null($result))
{
	header("HTTP/1.0 500 Server Error");
	CachedEcho::send( (isset($req) && $req->fault) ? "Warning: XMLRPC call is failed." : "Link to XMLRPC failed. Maybe, rTorrent is down?","text/html");
}
else
	CachedEcho::send(JSON::safeEncode($result),"application/json");
