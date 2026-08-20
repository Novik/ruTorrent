<?php

require_once( '../../php/xmlrpc.php' );
require_once( '../../php/xmlrpc_proxy.php' );
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

/**
 * Resolve as much of a path as exists. A download directory usually does not
 * exist yet, so realpath() on it answers nothing and only a lexical check is
 * left -- which one symlink inside the customer's own tree defeats, and the
 * customer can create symlinks over FTP. Walk up to the deepest ancestor that
 * does exist, resolve that, and re-attach the rest.
 */
function httprpcResolvePath($path)
{
	$real = @realpath($path);
	if($real !== false)
		return $real;

	$parts = explode('/', trim($path, '/'));
	$tail = array();
	while(count($parts) > 0)
	{
		array_unshift($tail, array_pop($parts));
		$base = '/'.implode('/', $parts);
		$real = @realpath(($base === '') ? '/' : $base);
		if($real !== false)
			return rtrim($real, '/').'/'.implode('/', $tail);
	}
	return '';
}

function makeMulticall($cmds,$hash,$add,$prefix)
{
	$cmd = new rXMLRPCCommand( $prefix.".multicall", array( $hash, "" ) );
	$cmd->addParameters( array_map("getCmd", $cmds) );
	foreach( $add as $prm )
		$cmd->addParameter($prm);
	$cnt = count($cmds)+count($add);
	$req = new rXMLRPCRequest($cmd);
	// Detail polling can race with torrent erase/replacement.
	if(($prefix === 'f') || ($prefix === 'p') || ($prefix === 't'))
		$req->important = false;
	if($req->success(true))
	{
	        $result = array();
		for($i = 0; $i<count($req->val); $i+=$cnt)
			$result[] = array_slice($req->val, $i, $cnt);
		return($result);
	}
	return(false);
}

// The socket manager stages min_alloc/max_alloc until adjust_alloc recomputes
// the allocation. A rejected recompute keeps the staged values, which then also
// breaks every later recompute, so the bounds in effect are read before they are
// touched and put back if the recompute is refused.
function getSocketAlloc($categories)
{
	$req = new rXMLRPCRequest();
	foreach($categories as $category)
	{
		$req->addCommand(new rXMLRPCCommand("system.sockets.".$category.".min_alloc"));
		$req->addCommand(new rXMLRPCCommand("system.sockets.".$category.".max_alloc"));
	}
	$req->important = false;
	if(!$req->success(true) || (count($req->val)!=2*count($categories)))
		return(array());
	$ret = array();
	foreach($categories as $ndx=>$category)
		$ret[$category] = array_slice($req->val,2*$ndx,2);
	return($ret);
}

function restoreSocketAlloc($saved)
{
	$req = new rXMLRPCRequest();
	foreach($saved as $category=>$bounds)
	{
		$req->addCommand(new rXMLRPCCommand(
			"system.sockets.".$category.".min_alloc.set",array("",floatval($bounds[0]))));
		$req->addCommand(new rXMLRPCCommand(
			"system.sockets.".$category.".max_alloc.set",array("",floatval($bounds[1]))));
	}
	$req->addCommand(new rXMLRPCCommand("system.sockets.adjust_alloc"));
	// These bounds were in effect moments ago, so the recompute has to accept
	// them again. Report it rather than leave a stale allocation staged.
	if(!$req->success(true))
		FileUtil::toLog("setsettings: socket allocation left staged, restore was refused");
}

// Every settings write is recorded: what was asked for, the value in effect
// before and after, and whether rtorrent took it. A refused batch can leave a
// value clamped or untouched, so the "to" side is read back rather than assumed.
//
// Everything on the settings page has a symmetric get_* alias except these: the
// hash_* trio stopped being readable in 0.9.0, and dht is reported through
// dht.statistics instead of a getter.
function getSettingReadCommand($name)
{
	if($name=="dht")
		return(null);
	if((rTorrentSettings::get()->iVersion>=0x900) &&
		in_array($name,array("hash_interval","hash_max_tries","hash_read_ahead")))
		return(null);
	return("get_".$name);
}

function readSettingValues($names)
{
	$ret = array_fill(0,count($names),null);
	$ndxs = array();
	$req = new rXMLRPCRequest();
	foreach($names as $ndx=>$name)
		if(($cmd = getSettingReadCommand($name))!==null)
		{
			$req->addCommand(new rXMLRPCCommand($cmd));
			$ndxs[] = $ndx;
		}
	if(!count($ndxs))
		return($ret);
	$req->important = false;
	if($req->success(true) && (count($req->val)==count($ndxs)))
		foreach($ndxs as $pos=>$ndx)
			$ret[$ndx] = $req->val[$pos];
	return($ret);
}

function logSettingsWrite($names,$values,$before,$after,$accepted,$faultString)
{
	foreach($names as $ndx=>$name)
		FileUtil::toLog("setsettings: ".$name.
			" requested=".$values[$ndx].
			" from=".(($before[$ndx]===null) ? "unavailable" : $before[$ndx]).
			" to=".(($after[$ndx]===null) ? "unavailable" : $after[$ndx]).
			" ".($accepted ? "accepted" : "rejected"));
	FileUtil::toLog("setsettings: batch ".($accepted ? "accepted" : "rejected").
		((!$accepted && ($faultString!=='')) ? " (".$faultString.")" : ""));
}

function makeSimpleCall($cmds,$hash)
{
	$req = new rXMLRPCRequest();
	foreach($hash as $h)
		foreach($cmds as $cmd)
			$req->addCommand( new rXMLRPCCommand( $cmd, $h ) );
       	return($req->success(true) ? $req->val : false);
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
			"d.get_chunks_hashed=", "d.get_base_path=", "d.get_creation_date=", "d.get_tracker_size=", "d.is_active=",
			"d.get_message=", "d.get_custom2=", "d.get_free_diskspace=", "d.is_private=", "d.is_multi_file=",
			"d.is_partially_done="
			);
		$cmd = new rXMLRPCCommand( "d.multicall", "main" );
		$cmd->addParameters( array_map("getCmd", $cmds) );
		foreach( $add as $prm )
			$cmd->addParameter($prm);
		$cnt = count($cmds)+count($add);
		$req = new rXMLRPCRequest($cmd);
		if($req->success(true))
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
		if($result === false)
			$result = array();
		break;
	}
	case "prs":	/**/
	{
		$result = makeMulticall(array(
			"p.get_id=", "p.get_address=", "p.get_client_version=", "p.is_incoming=", "p.is_encrypted=",
			"p.is_snubbed=", "p.get_completed_percent=", "p.get_down_total=", "p.get_up_total=", "p.get_down_rate=",
			"p.get_up_rate=", "p.get_id_html=", "p.get_peer_rate=", "p.get_peer_total=", "p.get_port="
			),$hash[0],$add,'p');
		if($result === false)
			$result = array();
		break;
	}
	case "trk":	/**/
	{
		$result = makeMulticall(array(
		        "t.get_url=", "t.get_type=", "t.is_enabled=", "t.get_group=", "t.get_scrape_complete=",
			"t.get_scrape_incomplete=", "t.get_scrape_downloaded=",
			"t.get_normal_interval=", "t.get_scrape_time_last="
			),$hash[0],$add,'t');
		if($result === false)
			$result = array();
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
		if($req->success(true))
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
		if($req->success(true))
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
		if($req->success(true)) {
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
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "trkstate":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($vs as $ndx=>$value)
			$req->addCommand( new rXMLRPCCommand( "t.set_enabled", array($hash[0], intval($value), intval($ss[0])) ) );
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "setprio":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($vs as $v)
			$req->addCommand( new rXMLRPCCommand( "f.set_priority", array($hash[0], intval($v), intval($ss[0])) ) );
		$req->addCommand( new rXMLRPCCommand("d.update_priorities", $hash[0]) );
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "recheck":	/**/
	{
        	$result = makeSimpleCall(array("d.check_hash"), $hash);
		break;
	}
	case "getsavepath":	/**/
	{
		if(isset($hash[0]))
		{
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand( "d.open", $hash[0] ),
				new rXMLRPCCommand( "d.get_base_path", $hash[0] ),
				new rXMLRPCCommand( "d.close", $hash[0] )
			) );
			if($req->success(true))
				$result = $req->val;
		}
		break;
	}
	case "start":	/**/
	{
		$result = makeSimpleCall(array("d.open","d.start","d.resume"), $hash);
		break;
	}
	case "stop":	/**/
	{
		$result = makeSimpleCall(array("d.stop","d.close"), $hash);
		break;
	}
	case "updatetracker":	/**/
	{
		$result = makeSimpleCall(array("d.tracker_announce"), $hash);
		break;
	}
	case "pause":	/**/
	{
		$result = makeSimpleCall(array("d.stop"), $hash);
		break;
	}
	case "unpause":	/**/
	{
		// d.start (not just d.resume) so d.state is restored: d.resume alone
		// leaves the torrent flagged paused in the UI forever and marked
		// user-stopped for the next rtorrent restart (the direct XML-RPC
		// mount's unpause stub sends d.start as well)
		$result = makeSimpleCall(array("d.start"), $hash);
		break;
	}
	case "removewithdata":	/**/
	{
		$forceDelete = isset($vs[0]) ? $vs[0] : "1";
		// Delegate to the shared erasedata helper so the httprpc and direct
		// (plugins/erasedata/action.php) paths record the identical delete list.
		// removewithdata only originates from the erasedata plugin, so its helper
		// is present; guard anyway and fall back to a plain erase if it is not.
		$helper = dirname(__FILE__)."/../erasedata/removewithdata.php";
		if(file_exists($helper))
		{
			require_once($helper);
			$result = erasedataRemoveWithData($hash, $forceDelete);
		}
		else
		{
			$req = new rXMLRPCRequest();
			foreach($hash as $h)
				$req->addCommand( new rXMLRPCCommand( getCmd("d.erase"), $h ) );
			if($req->success())
				$result = $req->val;
		}
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
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "setlabel":	/**/
	{
		$req = new rXMLRPCRequest();
		foreach($hash as $ndx=>$h)
			$req->addCommand( new rXMLRPCCommand( "d.set_custom1", array($h, $vs[0]) ) );
		if($req->success(true))
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
	       		if($req->success(true))
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
		$socketCategories = array();
		$logNames = array();
		$logValues = array();
		foreach($vs as $ndx=>$v)
		{
			if($ss[$ndx][0]=='n')
				$v = floatval($v);
			if( ($ss[$ndx]=="sdirectory") && !rTorrentSettings::get()->correctDirectory($v) )
				continue;
			$socketAlloc = rTorrentSettings::get()->getSocketAllocCategory($ss[$ndx]);
			if($ss[$ndx]=="ndht")
				$cmd = new rXMLRPCCommand('dht',(($v==0) ? "disable" : "auto"));
			else
			if($socketAlloc!==null)
			{
				$req->addCommand(new rXMLRPCCommand(
					"system.sockets.".$socketAlloc.".min_alloc.set",array("",$v)));
				$cmd = new rXMLRPCCommand(
					"system.sockets.".$socketAlloc.".max_alloc.set",array("",$v));
				$socketCategories[$socketAlloc] = true;
			}
			else
				$cmd = new rXMLRPCCommand('set_'.substr($ss[$ndx],1),$v);
			$req->addCommand($cmd);
			$logNames[] = substr($ss[$ndx],1);
			$logValues[] = $v;
		}
		$socketCategories = array_keys($socketCategories);
		// The staged min_alloc/max_alloc values only take effect once the
		// socket manager recomputes its allocation. Send it once per batch,
		// having first read the bounds it would replace.
		$savedAlloc = array();
		if(count($socketCategories))
		{
			$savedAlloc = getSocketAlloc($socketCategories);
			$req->addCommand(new rXMLRPCCommand("system.sockets.adjust_alloc"));
		}
		if($req->getCommandsCount())
		{
			$before = readSettingValues($logNames);
			$accepted = $req->success(true);
			if($accepted)
		        	$result = $req->val;
			else
			if(count($savedAlloc))
				restoreSocketAlloc($savedAlloc);
			logSettingsWrite($logNames,$logValues,$before,
				readSettingValues($logNames),$accepted,$req->faultString);
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
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "setul":	/**/
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("set_upload_rate", $ss[0]) );
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "setdl":	/**/
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("set_download_rate", $ss[0]) );
		if($req->success(true))
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
		if($req->success(true))
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
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "kick":
	{
		$req = new rXMLRPCRequest();
                foreach($vs as $v)
			$req->addCommand( new rXMLRPCCommand("p.disconnect", $hash[0].":p".$v) );
		if($req->success(true))
	        	$result = $req->val;
		break;
	}
	case "add_peer":
	{
		$req = new rXMLRPCRequest(
			new rXMLRPCCommand( "add_peer", array($hash[0], $vs[0]) ) );
		if($req->success(true))
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
		if($req->success(true))
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
			eval(FileUtil::getPluginConf('httprpc'));
			$proxyMode = isset($XMLRPCProxy) ? $XMLRPCProxy : 'sanitize';
			$proxyLog = isset($XMLRPCProxyLog) ? $XMLRPCProxyLog : true;
			$proxySafeParams = isset($XMLRPCProxySafeParams) ? $XMLRPCProxySafeParams : array();
			$proxyLocalPaths = isset($XMLRPCProxyAllowLocalPaths) ? $XMLRPCProxyAllowLocalPaths : false;
			// d.directory.set names the directory rtorrent writes a download
			// into, and the caller supplies the torrent, so it names the file
			// too. Confine it to the same boundary the panel already holds
			// itself to: correctDirectory() is applied to the directory in
			// sendTorrent(), in addtorrent.php, and to sdirectory in the
			// setsettings branch above. Raw XMLRPC reached rtorrent without it.
			//
			// $topDirectory is a global by now -- php/util.php requires
			// conf/config.php, and php/xmlrpc.php requires util.php. Where it is
			// "/" this permits everything, which is what the panel permits with
			// that setting too; this makes the two doors agree rather than
			// making one stricter.
			$proxyOptions = array('directory' => array(
				'root' => (isset($topDirectory) && ($topDirectory !== ''))
					? $topDirectory : '/',
				'resolve' => 'httprpcResolvePath',
			));
			$result = XMLRPCProxy::process($HTTP_RAW_POST_DATA, $proxyMode, $proxyLog, $proxySafeParams, $proxyLocalPaths, $proxyOptions);
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
	$message = "Could not reach rTorrent over XMLRPC. Is rTorrent running?";
	if(isset($req) && $req->fault)
		$message = ($req->faultString==='') ? "Warning: the XMLRPC call failed." : $req->faultString;
	CachedEcho::send($message,"text/html");
}
else
	CachedEcho::send(JSON::safeEncode($result),"application/json");
