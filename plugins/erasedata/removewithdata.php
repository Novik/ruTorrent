<?php

// Shared "remove with data" logic used by both the httprpc RPC handler and the
// direct (non-httprpc) endpoint plugins/erasedata/action.php. Records the list
// of files to delete -- read over RPC, which works on every rtorrent version --
// into the erasedata list directory for the garbage collector, then erases the
// torrents. The caller must have already loaded php/xmlrpc.php.
if(!function_exists('erasedataRemoveWithData'))
{
	function erasedataRemoveWithData($hashes, $forceDelete)
	{
		$listPath = FileUtil::getSettingsPath()."/erasedata";
		@FileUtil::makeDirectory($listPath);
		// rXMLRPCRequest flattens every returned value into ->val, so query one
		// torrent per request to keep the layout unambiguous:
		// val[0] = base path, val[1] = is_multi, val[2..] = each file path.
		foreach($hashes as $h)
		{
			$info = new rXMLRPCRequest( array(
				new rXMLRPCCommand( getCmd("d.get_base_path"), $h ),
				new rXMLRPCCommand( getCmd("d.is_multi_file"), $h ),
				new rXMLRPCCommand( getCmd("f.multicall"), array($h, "", getCmd("f.get_frozen_path")."=") )
			) );
			if($info->success() && count($info->val) >= 3)
			{
				$lines = array();
				foreach(array_slice($info->val, 2) as $path)
					if(strlen($path))
						$lines[] = $path;
				if(count($lines))
				{
					$lines[] = $info->val[0];
					$lines[] = $info->val[1] ? "1" : "0";
					$lines[] = $forceDelete;
					@file_put_contents($listPath."/".$h.".list", implode("\n", $lines)."\n");
				}
			}
		}
		$req = new rXMLRPCRequest();
		foreach($hashes as $h)
		{
			$req->addCommand( new rXMLRPCCommand( getCmd("d.set_custom5"), array($h, "") ) );
			$req->addCommand( new rXMLRPCCommand( getCmd("d.delete_tied"), $h ) );
			$req->addCommand( new rXMLRPCCommand( getCmd("d.erase"), $h ) );
		}
		return $req->success() ? $req->val : false;
	}
}
