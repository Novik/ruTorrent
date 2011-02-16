<?php

require_once( "util.php" );
require_once( "settings.php" );

function pluginsSort($a, $b)
{ 
	$lvl1 = (float) $a["info"]["plugin.runlevel"];
	$lvl2 = (float) $b["info"]["plugin.runlevel"];
	if($lvl1>$lvl2)
		return(1);
	if($lvl1<$lvl2)	
		return(-1);
	return( strcmp($a["name"],$b["name"]) );
}

function getFlag($permissions,$pname,$fname)
{
	$ret = true;
	if(array_key_exists($pname,$permissions) &&
		array_key_exists($fname,$permissions[$pname]))
		$ret = $permissions[$pname][$fname];
	else
	if(array_key_exists("default",$permissions) &&
		array_key_exists($fname,$permissions["default"]))
		$ret = $permissions["default"][$fname];
	return($ret);
}

function getPluginInfo( $name, $permissions )
{
        $info = array( 
		'rtorrent.need'=>1, 
		'rtorrent.remote'=>'ok',
		'rtorrent.external.warning'=>array(),
		'rtorrent.external.error'=>array(),
		'rtorrent.script.error'=>array(),
		'rtorrent.php.error'=>array(),
		'rtorrent.version'=>0x802,
		'rtorrent.version.readable'=>'0.8.2',
		'plugin.runlevel'=>10.0, 
		'plugin.description'=>'', 
		'plugin.author'=>'unknown',
		'plugin.dependencies'=>array(),
		'php.version'=>0x50000,
		'php.version.readable'=>'5.0.0',
		'web.external.warning'=>array(),
		'web.external.error'=>array(),
		);
	$fname = "../plugins/".$name."/plugin.info";
	if(is_readable($fname))
	{
		$lines = file($fname);
		foreach($lines as $line)
		{
			$fields = explode(":",$line,2);
			if(count($fields)==2)
			{
				$value = addcslashes(trim($fields[1]),"\\\'\"\n\r\t");
				$field = trim($fields[0]); 
				switch($field)
				{
					case "plugin.author":
					case "plugin.description":
					case "rtorrent.remote":
					{
						$info[$field] = $value;
						break;
					}
                                        case "rtorrent.need":
					{
						$info[$field] = intval($value);
						break;
					}
					case "plugin.version":
					case "plugin.runlevel":
					{
						$info[$field] = floatval($value);
						break;
					}
					case "rtorrent.version":
					case "php.version":
					{
						$version = explode('.', $value);
						$info[$field] = (intval($version[0])<<16) + (intval($version[1])<<8) + intval($version[2]);
						$info[$field.'.readable'] = $value;
						break;
					}
					case "plugin.dependencies":
					case "rtorrent.external.warning":
					case "rtorrent.external.error":
					case "rtorrent.script.error":
					case "rtorrent.php.error":
					case "web.external.warning":
					case "web.external.error":
					{
						$info[$field] = explode(',', $value);
						break;
					}
// for compatibility
					case "author":
					case "description":
					{
						$info['plugin.'.$field] = $value;
						break;
					}
					case "remote":
					{
						$info['rtorrent.remote'] = $value;
						break;
					}
					case "need_rtorrent":
					{
						$info['rtorrent.need'] = intval($value);
						break;
					}
					case "version":
					case "runlevel":
					{
						$info['plugin.'.$field] = floatval($value);
						break;
					}
				}
			}
		}
		$perms = 0;
		if($permissions!==false)
		{
			if(!getFlag($permissions,$name,"enabled"))
				return(false);
			$flags = array(
				"canChangeToolbar" 	=> 0x0001,
				"canChangeMenu" 	=> 0x0002,
				"canChangeOptions"	=> 0x0004,
				"canChangeTabs"		=> 0x0008,
				"canChangeColumns"	=> 0x0010,
				"canChangeStatusBar"	=> 0x0020,
				"canChangeCategory"	=> 0x0040,
				);
			foreach($flags as $flagName=>$flagVal)
				if(!getFlag($permissions,$name,$flagName))
					$perms|=$flagVal;
		}
		$info["perms"] = $perms;
	}
	return(array_key_exists("plugin.version",$info) ? $info : false);
}

function findRemoteEXE( $exe, $err, &$remoteRequests )
{
	$st = getSettingsPath().'/'.rand();
	if(!array_key_exists($exe,$remoteRequests))
	{
		$path=realpath(dirname('.'));
		global $pathToExternals;
		$add = '';
		if(isset($pathToExternals[$exe]) && !empty($pathToExternals[$exe]))
			$add = " ".escapeshellarg($pathToExternals[$exe]);
		$req = new rXMLRPCRequest(new rXMLRPCCommand("execute", array( "sh", "-c", escapeshellarg(addslash($path)."test.sh")." ".$exe." ".escapeshellarg($st).$add)));
		$req->run();
		$remoteRequests[$exe] = array( "path"=>$st, "err"=>array() );
	}
	$remoteRequests[$exe]["err"][] = $err;
}

function testRemoteRequests($remoteRequests)
{
	$ret = "";
	foreach($remoteRequests as $exe=>$info)
	{
		$file = $info["path"].$exe.".founded";
		if(!is_file($file))
		{
			foreach($info["err"] as $err)
				$ret.=$err;
		}
		else
			@unlink($file);
	}
	return($ret);
}

$jResult = "theWebUI.deltaTime = 0;\n";
$access = getConfFile('access.ini');
if(!$access)
	$access = "../conf/access.ini";
$permissions = parse_ini_file($access);
$settingsFlags = array(
	"showDownloadsPage" 	=> 0x0001,
	"showConnectionPage" 	=> 0x0002,
	"showBittorentPage"	=> 0x0004,
	"showAdvancedPage"	=> 0x0008,
	"showPluginsTab"	=> 0x0010,
	"canChangeULRate"	=> 0x0020,
	"canChangeDLRate"	=> 0x0040,
	"canChangeTorrentProperties"	=> 0x0080,
);
$perms = 0;
foreach($settingsFlags as $flagName=>$flagVal)
	if(array_key_exists($flagName,$permissions) && $permissions[$flagName])
		$perms|=$flagVal;
$jResult .= "theWebUI.showFlags = ".$perms.";\n";
$jResult .= "theURLs.XMLRPCMountPoint = '".$XMLRPCMountPoint."';\n";
$jResult.="theWebUI.systemInfo = {};\ntheWebUI.systemInfo.php = { canHandleBigFiles : ".((PHP_INT_SIZE<=4) ? "false" : "true")." };\n";

if($handle = opendir('../plugins')) 
{
	ignore_user_abort(true);
	set_time_limit(0);
	@chmod('/tmp',0777);
	if(!function_exists('preg_match_all'))
	{
		$jResult.="log(theUILang.PCRENotFound);";
		$jResult.="theWebUI.systemInfo.rTorrent = { started: false, iVersion : 0, version : '?', libVersion : '?' };\n";
	}
	else
	{
		$remoteRequests = array();
		$theSettings = rTorrentSettings::get(true);
		if(!$theSettings->linkExist)
		{
			$jResult.="log(theUILang.badLinkTorTorrent);";
			$jResult.="theWebUI.systemInfo.rTorrent = { started: false, iVersion : 0, version : '?', libVersion : '?' };\n";
		}
		else
		{
		        if($theSettings->idNotFound)
				$jResult.="log(theUILang.idNotFound);";
			$jResult.="theWebUI.systemInfo.rTorrent = { started: true, iVersion : ".$theSettings->iVersion.", version : '".$theSettings->version."', libVersion : '".$theSettings->libVersion."' };\n";
			if($theSettings->mostOfMethodsRenamed)
				$jResult.="theWebUI.systemInfo.rTorrent.newMethodsSet = true;\n";
	        	if($do_diagnostic)
	        	{
	        	        if(PHP_USE_GZIP && (findEXE('gzip')===false))
	        	        {
	        	        	@define('PHP_USE_GZIP', false);
	        	        	$jResult.="log(theUILang.gzipNotFound);";
	        	        }
				if(PHP_INT_SIZE<=4)
				{
					if(findEXE('stat')===false)
						$jResult.="log(theUILang.statNotFoundW);";
                                        findRemoteEXE('stat',"log(theUILang.statNotFound);",$remoteRequests);
				}
	        	        $up = getUploadsPath();
	        	        $st = getSettingsPath();
				@chmod($up,$profileMask);
				@chmod($st,$profileMask);
				@chmod('./test.sh',$profileMask & 0755);
	        		if(!@file_exists($up.'/.') || !is_readable($up) || !is_writable($up))
					$jResult.="log(theUILang.badUploadsPath+' (".$up.")');";
	        		if(!@file_exists($st.'/.') || !is_readable($st) || !is_writable($st))
        			        $jResult.="log(theUILang.badSettingsPath+' (".$st.")');";
				if(isLocalMode() && !$theSettings->idNotFound)
				{
					if($theSettings->uid<0)
						$jResult.="log(theUILang.cantObtainUser);";
					else
					{
						if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$up,0x0007))
							$jResult.="log(theUILang.badUploadsPath2+' (".$up.")');";
						if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$st,0x0007))
							$jResult.="log(theUILang.badSettingsPath2+' (".$st.")');";
						if(!isUserHavePermission($theSettings->uid,$theSettings->gid,'./test.sh',0x0005))
							$jResult.="log(theUILang.badTestPath+' (".realpath('./test.sh').")');";
					}
				}
				if($theSettings->badXMLRPCVersion)
					$jResult.="log(theUILang.badXMLRPCVersion);";
			}
		}
		$plg = getConfFile('plugins.ini');
		if(!$plg)
			$plg = "../conf/plugins.ini";
		$permissions = parse_ini_file($plg,true);
		$init = array();
		$names = array();
		$phpVersion = phpversion();
		if( ($pos=strpos($phpVersion, '-'))!==false )
			$phpVersion = substr($phpVersion,0,$pos);
		$phpIVersion = explode('.',$phpVersion);
		$phpIVersion = (intval($phpIVersion[0])<<16) + (intval($phpIVersion[1])<<8) + intval($phpIVersion[2]);
		while(false !== ($file = readdir($handle)))
		{
			if($file != "." && $file != ".." && is_dir('../plugins/'.$file))
			{
				$info = getPluginInfo( $file, $permissions );
				if($info!==false)
				{
				        if(!$theSettings->linkExist && $info["rtorrent.need"])
						continue;
					if($info['php.version']>$phpIVersion)
					{
						$jResult.="log('".$file.": '+theUILang.badPHPVersion+' '+'".$info['php.version.readable']."'+'.');";
						continue;
					}
					$extError = false;
					if(count($info['web.external.error']) || 
						count($info['web.external.warning']) ||
						count($info['rtorrent.external.error']) || 
						count($info['rtorrent.external.warning']))
						eval( getPluginConf( $file ) );
					foreach( $info['web.external.error'] as $external )
					{
						if(findEXE($external)==false)
						{
							$jResult.="log('".$file.": '+theUILang.webExternalNotFoundError+' ('+'".$external."'+').');";
							$extError = true;
						}
					}
					if($extError)
						continue;
					if($theSettings->linkExist)
					{
						if($info['rtorrent.version']>$theSettings->iVersion)
						{
							$jResult.="log('".$file.": '+theUILang.badrTorrentVersion+' '+'".$info['rtorrent.version.readable']."'+'.');";
							continue;
						}
                				foreach( $info['rtorrent.external.error'] as $external )
							findRemoteEXE($external,"log('".$file.": '+theUILang.rTorrentExternalNotFoundError+' ('+'".$external."'+').'); thePlugins.get('".$file."').disable();",$remoteRequests);
						foreach( $info['rtorrent.script.error'] as $external )
						{
						       	$fname = $rootPath.'/plugins/'.$file.'/'.$external;
							@chmod($fname,$profileMask & 0755);
							if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$fname,0x0005))
							{
								$jResult.="log('".$file.": '+theUILang.rTorrentBadScriptPath+' ('+'".$fname."'+').');";
								$extError = true;
							}
						}
						if($extError)
							continue;
						foreach( $info['rtorrent.php.error'] as $external )
						{
					       		$fname = $rootPath.'/plugins/'.$file.'/'.$external;
							@chmod($fname,$profileMask & 0644);
							if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$fname,0x0004))
							{
								$jResult.="log('".$file.": '+theUILang.rTorrentBadPHPScriptPath+' ('+'".$fname."'+').');";
								$extError = true;
							}
						}
						if($extError)
							continue;
				        	if(!isLocalMode())
					        {
					        	if($info["rtorrent.remote"]=="error")
							{
								$jResult.="log('".$file.": '+theUILang.errMustBeInSomeHost);";
								continue;
							}
				        		if($do_diagnostic && ($info["rtorrent.remote"]=="warning"))
								$jResult.="log('".$file.": '+theUILang.warnMustBeInSomeHost);";
					        }
					}
					if($do_diagnostic)
					{
						if($theSettings->linkExist)
							foreach( $info['rtorrent.external.warning'] as $external )
								findRemoteEXE($external,"log('".$file.": '+theUILang.rTorrentExternalNotFoundWarning+' ('+'".$external."'+').');",$remoteRequests);
						foreach( $info['web.external.warning'] as $external )
							if(findEXE($external)==false)
								$jResult.="log('".$file.": '+theUILang.webExternalNotFoundWarning+' ('+'".$external."'+').');";
					}
					$js = "../plugins/".$file."/init.js";
	                	        if(!is_readable($js))
						$js = NULL;
        		                $php = "../plugins/".$file."/init.php";
					if(!is_readable($php))
						$php = NULL;
					$init[] = array( "js" => $js, "php" => $php, "info" => $info, "name" => $file );
					$names[] = $file;
				}
			}
		} 
		usort($init,"pluginsSort");
		foreach($init as $plugin)
		{
		        $jEnd = '';
		        $pInfo = $plugin["info"];

			$deps = array_diff( $pInfo["plugin.dependencies"], $names );
			if(count($deps))
			{
				$jResult.="log('".$plugin["name"].": '+theUILang.dependenceError+' ".implode(",",$deps)."');";
				continue;
			}

			$jResult.="(function () { var plugin = new rPlugin( '".$plugin["name"]."',".$pInfo["plugin.version"].
				",'".$pInfo["plugin.author"]."','".$pInfo["plugin.description"]."',".$pInfo["perms"]." );\n";
			if($plugin["php"])
				require_once( $plugin["php"] );
			else
				$theSettings->registerPlugin($plugin["name"]);
			if($plugin["js"])
			{
				$jResult.=file_get_contents($plugin["js"]);
				$jResult.="\n";
			}
			$jResult.=$jEnd;
			$jResult.="\n})();";
		}
		$jResult.=testRemoteRequests($remoteRequests);
		$theSettings->store();
	}
	closedir($handle);
}

cachedEcho($jResult,"application/javascript",true);
?>