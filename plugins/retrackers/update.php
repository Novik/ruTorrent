<?php
require_once( 'retrackers.php' );
require_once( '../../xmlrpc.php' );

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
if(count($trks->list) && (count($argv)>1))
{
	$hash = $argv[1];
	$req = new rXMLRPCRequest( array(		
		new rXMLRPCCommand("get_session"),
		new rXMLRPCCommand("d.is_open",$hash),
		new rXMLRPCCommand("d.is_active",$hash),
		new rXMLRPCCommand("d.get_state",$hash),
		new rXMLRPCCommand("d.get_tied_to_file",$hash),
		new rXMLRPCCommand("d.get_custom1",$hash),
		new rXMLRPCCommand("d.get_directory_base",$hash),
		new rXMLRPCCommand("d.get_directory",$hash),
		new rXMLRPCCommand("d.is_private",$hash)
		) );

	if($req->run() && !$req->fault)
	{
		if($req->i8s[3] && $trks->dontAddPrivate)
			return;
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
				$lst = $torrent->announce_list();
				if(!$lst)
				{
					if($torrent->announce())
					{
						$torrent->announce_list(array_merge(array(array($torrent->announce())),$trks->list));
					}
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
			        $fname = "../../".$uploads."/".$hash.'.torrent';
				if($torrent->save($fname))
				{
					$eReq = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
					if($eReq->run() && !$req->fault)
					{
						@chmod($fname,0666);
						$fname = realpath($fname);
						$label = rawurldecode($req->strings[2]);
						if(sendFile2rTorrent($fname, false, $isStart, true, $req->strings[4], $label, 
							"<param><value><string>d.set_directory_base=\"".$req->strings[3]."\"</string></value></param>".
							"<param><value><string>d.set_custom3=1</string></value></param>" )===false)
							$errors[] = array('desc'=>"WUILang.badLinkTorTorrent", 'prm'=>'');
					}
				}
			}
		}
	}
}
?>
