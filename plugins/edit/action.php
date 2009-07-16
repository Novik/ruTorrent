<?php

require_once( '../../xmlrpc.php' );
require_once( '../../Torrent.php' );
require_once( '../../settings.php' );

ob_start();
ignore_user_abort(true);
set_time_limit(0);
$errors = array();
if(!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
if(isset($HTTP_RAW_POST_DATA))
{
	$vars = split('&', $HTTP_RAW_POST_DATA);
	$hash = null;
	$announce_list = array(); 
	$trackers = array();
	$comment = '';
	$trackersCount = 0;
	foreach($vars as $var)
	{
		$parts = split("=",$var);
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
			new rXMLRPCCommand("d.get_directory",$hash),
			new rXMLRPCCommand("d.get_connection_seed",$hash)
			) );
		$throttle = "";
		$theSettings = rTorrentSettings::load();
		if($theSettings->isPluginRegistered("throttle"))
			$req->addCommand(new rXMLRPCCommand("d.get_throttle_name",$hash));
		if($req->run() && !$req->fault)
		{
			$isStart = (($req->i8s[0]!=0) && ($req->i8s[1]!=0) && ($req->i8s[2]!=0));
			$fname = $req->strings[0].$hash.".torrent";
			if(empty($req->strings[0]) || !is_readable($fname))
			{
				if(strlen($req->strings[1]) && is_readable($req->strings[1]))
					$fname = $req->strings[1];
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
					if(isset($torrent->{'libtorrent_resume'}['trackers']))
						unset($torrent->{'libtorrent_resume'}['trackers']);
					if(count($req->strings)>5)
						$throttle = "<param><value><string>d.set_throttle_name=".$req->strings[6]."</string></value></param>";
				        $fname = "../../".$uploads."/".$hash.'.torrent';
					if($torrent->save($fname))
					{
						$eReq = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
						if($eReq->run() && !$eReq->fault)
						{
							chmod($fname,0666);
							$fname = realpath($fname);
							$label = rawurldecode($req->strings[2]);

							if(sendFile2rTorrent($fname, false, $isStart, true, $req->strings[4], $label, 
								"<param><value><string>d.set_directory_base=\"".$req->strings[3]."\"</string></value></param>".
								"<param><value><string>d.set_custom3=1</string></value></param>".
								"<param><value><string>d.set_connection_seed=".$req->strings[5]."</string></value></param>".
								$throttle
								 )===false)
								$errors[] = array('desc'=>"WUILang.badLinkTorTorrent", 'prm'=>'');
						}
						else
							$errors[] = array('desc'=>"WUILang.errorAddTorrent", 'prm'=>$fname);
					}
					else
						$errors[] = array('desc'=>"WUILang.errorWriteTorrent", 'prm'=>$fname);
				}
				else
					$errors[] = array('desc'=>"WUILang.errorReadTorrent", 'prm'=>$fname);
			}
			else
				$errors[] = array('desc'=>"WUILang.cantFindTorrent", 'prm'=>'');
                }
                else
			$errors[] = array('desc'=>"WUILang.badLinkTorTorrent", 'prm'=>'');

	}
}
$ret = "{ errors: [";
foreach($errors as $err)
	$ret.="{ prm: \"".addslashes($err['prm'])."\", desc: ".$err['desc']." },";
$len = strlen($ret);
if($ret[$len-1]==',')
	$ret = substr($ret,0,$len-1);
$ret.="]}";
$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$ret.']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
ob_flush();
flush();
?>
