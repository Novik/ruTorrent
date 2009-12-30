<?php

require_once( dirname(__FILE__).'/util.php' );
require_once( $rootPath.'/php/xmlrpc.php' );
require_once( $rootPath.'/php/Torrent.php' );

class rTorrent
{
	static public function sendTorrent($fname, $isStart, $isAddPath, $directory, $label, $saveTorrent, $addition = null)
	{
		$hash = false;
		$cmd = new rXMLRPCCommand( $isStart ? 'load_start_verbose' : 'load_verbose', $fname );
		$torrent = new Torrent($fname);
		if(!$torrent->errors())
		{
			$comment = $torrent->comment();
			if($comment)
			{
				if(isInvalidUTF8($comment))
					$comment = win2utf($comment);
				if(strlen($comment)>0)
				{
					$comment = "VRS24mrker".rawurlencode($comment);
					if(strlen($comment)<=4096)
						$cmd->addParameter("d.set_custom2=".$comment);
				}
			}
			if(!$saveTorrent)
				$cmd->addParameter("d.delete_tied=".$comment);
			if($label && (strlen($label)>0))
			{
				$label = rawurlencode($label);
				if(strlen($label)<=4096)
					$cmd->addParameter("d.set_custom1=".$label);
			}
			if($directory && (strlen($directory)>0))
				$cmd->addParameter( ($isAddPath ? "d.set_directory=" : "d.set_directory_base=").$directory );
			if(is_array($addition))
				foreach($addition as $key=>$prm)
					$cmd->addParameter($prm,'string');
			$req = new rXMLRPCRequest( $cmd );
			if($req->run() && !$req->fault)
				$hash = $torrent->hash_info();
		}
		return($hash);
	}
}

?>