<?php
require_once( '../../php/util.php' );
require_once( '../../php/settings.php' );
eval(getPluginConf("_getdir"));

function compareEntries( $a, $b )
{
	if($a=='.')
		return( -1 );
	if($b=='.')
		return( 1 );
	if($a=='..')
		return( -1 );
	if($b=='..')
		return( 1 );
	return( function_exists("mb_strtolower") ? 
		strcmp(mb_strtolower($a), mb_strtolower($b)) :
		strcmp(strtolower($a), strtolower($b)) );
}

if(isset($_REQUEST['mode']))
{
	$output = array();
	$modes = explode(';',$_REQUEST['mode']);
	$theSettings = rTorrentSettings::get();
	if(in_array("labels",$modes))
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("d.multicall", array("",getCmd("d.get_custom1="))) );
		$labels = array();
		if($req->run() && !$req->fault)
		{
			for($i=0; $i<count($req->val); $i++)
			{
				$val = trim(rawurldecode($req->val[$i]));
				if( $val!='' )
					$labels[$val] = true;
			}
			$output["labels"] = array_keys($labels);
			natcasesort($output["labels"]);
			$output["labels"] = array_values($output["labels"]);
		}
	}
	if(in_array("dirlist",$modes))
	{
		$dh = false;
		if(isset($_REQUEST['basedir']))
		{
			$dir = rawurldecode($_REQUEST['basedir']);
			$theSettings->correctDirectory($dir);
			$dh = @opendir($dir);
			$dir = addslash($dir);

			if( $dh &&
				((strpos($dir,$topDirectory)!==0) ||
				(($theSettings->uid>=0) &&
				$checkUserPermissions &&
				!isUserHavePermission($theSettings->uid,$theSettings->gid,$dir,0x0007))))
			{
				closedir($dh);
				$dh = false;
			}
		}
		if(!$dh)
		{
			$dir = isLocalMode() ? $theSettings->directory : $topDirectory;
			if(strpos(addslash($dir),$topDirectory)!==0)
				$dir = $topDirectory;
			$dh = @opendir($dir);
		}
		if($dh)
		{
			$files = array();
			$dir = addslash($dir);
			while(false !== ($file = readdir($dh)))
		        {
				$path = fullpath($dir . $file);
				if(($file=="..") && ($dir==$topDirectory))
					continue;
				if(is_dir($path) && is_readable($path) &&
					(strpos(addslash($path),$topDirectory)===0) &&
					( $theSettings->uid<0 || !$checkUserPermissions || isUserHavePermission($theSettings->uid,$theSettings->gid,$path,0x0007) )
					)
				{
					$files[] = $file;
				}
			}
		        closedir($dh);
			usort($files,"compareEntries");
			$output["basedir"] = fullpath($dir);
			$output["dirlist"] = $files;
		}
        }
	if(in_array("channels",$modes) && $theSettings->isPluginRegistered('throttle'))
	{
		require_once( '../throttle/throttle.php' );
		$trt = rThrottle::load();
		$tnames = array();
		foreach( $trt->thr as $thr )
			$tnames[] = $thr["name"];
		$output["channels"]  = $tnames;
	}
	if(in_array("ratios",$modes) && $theSettings->isPluginRegistered('ratio'))
	{
		require_once( '../ratio/ratio.php' );
		$rat = rRatio::load();
		$rnames = array();
		foreach( $rat->rat as $r )
			$rnames[] = $r["name"];
		$output["ratios"] = $rnames;
	}
}

cachedEcho(safe_json_encode($output),"application/json");