<?php

require_once( "../../php/Snoopy.class.inc" );
require_once( "../../php/rtorrent.php" );

class ruTrackerChecker
{
	const STE_INPROGRESS		= 1;
	const STE_UPDATED		= 2;
	const STE_UPTODATE		= 3;
	const STE_DELETED		= 4;
	const STE_CANT_REACH_TRACKER	= 5;
	const STE_ERROR			= 6;
	const STE_NOT_NEED		= 7;

	const MAX_LOCK_TIME		= 900;	// 15 min

	static protected function setState( $hash, $state )
	{
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-state", $state."")  ),
			new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-time", time()."") )
			));
		if($state == self::STE_UPTODATE)
			$req->addCommand(new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-stime", time()."") ));
		return($req->success());
	}

	static protected function getState( $hash, &$state, &$time, &$successful_time )
	{
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-state")  ),
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-time") ),
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-stime") )
			));
		if($req->success())
		{
			$state = intval($req->val[0]);
			$time = intval($req->val[1]);
			$successful_time = intval($req->val[2]);
			return(true);
		}
		else
		{
			$state = self::STE_INPROGRESS;
			$time = time();
			$successful_time = 0;
			return(false);
		}
	}

	static public function makeClient( $url, $method="GET", $content_type="", $body="" )
	{
		$client = new Snoopy();
		$client->read_timeout = 5;
		$client->_fp_timeout = 5;
		@$client->fetchComplex($url,$method,$content_type,$body);
		return($client);
	}


	static public function run( $hash, $state = null, $time = null, $successful_time = null )
	{
		global $saveUploadedTorrents;
		if(is_null($state))
			self::getState( $hash, $state, $time, $successful_time );
		if(($state==self::STE_INPROGRESS) && ((time()-$time)>self::MAX_LOCK_TIME))
			$state = 0;
		if($state!=self::STE_INPROGRESS)
		{
			$state = self::STE_INPROGRESS;
			if(!self::setState( $hash, $state ))
				return(false);
			$fname = rTorrentSettings::get()->session.$hash.".torrent";
			if(is_readable($fname))
			{
				$torrent = new Torrent( $fname );
				if( !$torrent->errors() )
				{
                        		if( preg_match( '`^http://rutracker\.org/forum/viewtopic\.php\?t=(?P<id>\d+)$`',$torrent->comment(), $matches ) )
					{
						$client = self::makeClient($torrent->comment());
						if(($client->status==200) &&
							preg_match( "`ajax.form_token\s*=\s*'(?P<form_token>[^']+)';.*topic_id\s*:\s*(?P<topic_id>\d+),\s*t_hash\s*:\s*'(?P<t_hash>[^']+)'`s",$client->results, $matches1 ))
						{
							$client->setcookies();
							$client->fetch("http://rutracker.org/forum/ajax.php","POST","application/x-www-form-urlencoded; charset=UTF-8",
								"action=get_info_hash".
                                                                "&topic_id=".$matches1["topic_id"].
								"&t_hash=".$matches1["t_hash"].
								"&form_token=".$matches1["form_token"]);
							if($client->status==200)
							{
								$ret = json_decode($client->results,true);
								if($ret && array_key_exists("ih_hex",$ret) && (strtoupper($ret["ih_hex"])==$hash))
								{
									self::setState( $hash,  self::STE_UPTODATE );
									return(true);
								}
							}
						}
						$client->setcookies();
						$client->fetchComplex("http://dl.rutracker.org/forum/dl.php?t=".$matches["id"]);
						if($client->status==200)
						{
							$torrent = new Torrent( $client->results );
							if( !$torrent->errors() )
							{
								if( $torrent->hash_info()!=$hash )
								{
									$req =  new rXMLRPCRequest( array(
										new rXMLRPCCommand("d.get_directory_base",$hash),								
										new rXMLRPCCommand("d.get_custom1",$hash),
										new rXMLRPCCommand("d.get_throttle_name",$hash),
										new rXMLRPCCommand("d.get_connection_seed",$hash),
										new rXMLRPCCommand("d.is_open",$hash),
										new rXMLRPCCommand("d.is_active",$hash),
										new rXMLRPCCommand("d.get_state",$hash),
										new rXMLRPCCommand("d.stop",$hash),
										new rXMLRPCCommand("d.close",$hash),
										));
									if($req->success())
									{
										$addition = array( 
											getCmd("d.set_connection_seed=").$req->val[3],
											getCmd("d.set_custom")."=chk-state,".self::STE_UPDATED, 
											getCmd("d.set_custom")."=chk-time,".time(),
											getCmd("d.set_custom")."=chk-stime,".time()
											);
										$isStart = (($req->val[4]!=0) && ($req->val[5]!=0) && ($req->val[6]!=0));
										if(!empty($req->val[2]))
											$addition[] = getCmd("d.set_throttle_name=").$req->val[2];
										if(preg_match('/rat_(\d+)/',$req->val[3],$ratio))
											$addition[] = getCmd("view.set_visible=")."rat_".$ratio;
										$label = rawurldecode($req->val[1]);
										if(rTorrent::sendTorrent($torrent, $isStart, false, $req->val[0], 
											$label, $saveUploadedTorrents, false, true, $addition))
										{
											$req = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
											if($req->success())
												$state = null;
										}
									}
								}
								else
									$state = self::STE_UPTODATE;
							}
							else
								$state = self::STE_DELETED;
						}
						else
							$state = (($client->status<0) ? self::STE_CANT_REACH_TRACKER : self::STE_DELETED );
					}
					else
						$state = self::STE_NOT_NEED;
				}
			}
			if($state==self::STE_INPROGRESS) 
				$state==self::STE_ERROR;
			if(!is_null($state))
				self::setState( $hash, $state );
		}
		return($state != self::STE_CANT_REACH_TRACKER);
	}

}
