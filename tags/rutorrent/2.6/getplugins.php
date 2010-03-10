<?php

require_once( "settings.php" );

function so($a, $b)
{ 
	$lvl1 = (float) $a["level"];
	$lvl2 = (float) $b["level"];
	if($lvl1>$lvl2)
		return(1);
	if($lvl1<$lvl2)	
		return(-1);
	return( 0 );
}

$jResult = "utWebUI.deltaTime = new Date().getTime() - ".time()."*1000;\n";

if($handle = opendir('./plugins')) 
{
	ignore_user_abort(true);
	set_time_limit(0);
	@chmod('/tmp',0777);
	$theSettings = new rTorrentSettings();
	$theSettings->obtain();
	if(!$theSettings->linkExist)
		$jResult.="log(WUILang.badLinkTorTorrent);";
	else
	{
	        if(DO_DIAGNOSTIC)
        	{
			@chmod($uploads,0777);
			@chmod($settings,0777);
			@chmod('./test.sh',0755);
        		if(!isUserHavePermission($theSettings->myuid,$theSettings->mygid,$uploads,0x0007))
				$jResult.="log(WUILang.badUploadsPath+' (".realpath($uploads).")');";
	        	if(!isUserHavePermission($theSettings->myuid,$theSettings->mygid,$settings,0x0007))
        		        $jResult.="log(WUILang.badSettingsPath+' (".realpath($settings).")');";
			if(!empty($theSettings->session))
			{
				if(($theSettings->uid<0) || ($theSettings->gid<0))
					$jResult.="log(WUILang.badSessionPath+' (".$theSettings->session.")');";
				else
				{
					if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$uploads,0x0007))
						$jResult.="log(WUILang.badUploadsPath2+' (".realpath($uploads).")');";
					if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$settings,0x0007))
						$jResult.="log(WUILang.badSettingsPath2+' (".realpath($settings).")');";
					if(!isUserHavePermission($theSettings->uid,$theSettings->gid,'./test.sh',0x0005))
						$jResult.="log(WUILang.badTestPath+' (".realpath('./test.sh').")');";
				}
			}
			if($theSettings->badXMLRPCVersion)
				$jResult.="log(WUILang.badXMLRPCVersion);";
		}
		$init = array();
		while(false !== ($file = readdir($handle)))
		{
			if($file != "." && $file != ".." && is_dir('./plugins/'.$file))
			{
				$js = "./plugins/".$file."/init.js";
                	        if(!is_readable($js))
					$js = NULL;
	                        $php = "./plugins/".$file."/init.php";
				if(!is_readable($php))
					$php = NULL;
				$runlevel = 10.0;
				$level = "./plugins/".$file."/runlevel.info";
				if(is_readable($level))
					$runlevel = floatval(file_get_contents($level));
				$init[] = array( "js" => $js, "php" => $php, "level" => $runlevel, "name" => $file );
			}
		} 
		usort($init,"so");
		$remoteRequests = array();
		foreach($init as $plugin)
		{
			if($plugin["js"])
			{
				$jResult.=file_get_contents($plugin["js"]);
				$jResult.="\r\n";
			}
			if($plugin["php"])
				require_once( $plugin["php"] );
			else
				$theSettings->registerPlugin($plugin["name"]);
		}
		$jResult.=testRemoteRequests($remoteRequests);
		$theSettings->store();
	}
	closedir($handle);
}
echo $jResult;

?>