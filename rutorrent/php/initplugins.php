<?php
/*
 * initplugins.php, contributed by dmrom
 *
 * Script for loading all ruTorrent plugins on rTorrent's start.
 * Add the following lines into your "rtorrent.rc" file.
 *

# Load all ruTorrent plugins
execute = {sh,-c,/usr/local/bin/php /usr/local/www/rt/php/initplugins.php user_name &}

 *
 * All plugins would be run according their runlevel
 */

if( !chdir( dirname( __FILE__ ) ) )
        exit;

function pluginsSort($a, $b)
{ 
	$lvl1 = (float) $a["level"];
	$lvl2 = (float) $b["level"];
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
	return($ret);
}

function getPluginInfo( $name, $permissions )
{
        $info = array( 
		'rtorrent.php.error'=>array(),
		'rtorrent.version'=>0x802,
		'plugin.runlevel'=>10.0, 
		'plugin.dependencies'=>array(),
		'php.version'=>0x50000,
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
					{
						$info[$field] = explode(',', $value);
						break;
					}
// for compatibility
					case "version":
					case "runlevel":
					{
						$info['plugin.'.$field] = floatval($value);
						break;
					}
				}
			}
		}
		if($permissions!==false)
		{
			if(!getFlag($permissions,$name,"enabled"))
				return(false);
		}
	}
	return(array_key_exists("plugin.version",$info) ? $info : false);
}

if( !function_exists( 'preg_match_all' ) )
	exit;

if( count( $argv ) > 1 )
	$_SERVER['REMOTE_USER'] = $argv[1];

require_once( "util.php" );
require_once( "settings.php" );

$theSettings = new rTorrentSettings();
$theSettings->obtain();
if( $theSettings->linkExist && ($handle = opendir('../plugins')))
{
	$permissions = parse_ini_file("../conf/plugins.ini",true);
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
			if(($info!==false) &&
				($info['php.version']<=$phpIVersion) &&
				($info['rtorrent.version']<=$theSettings->iVersion))
			{
				$php = "../plugins/".$file."/init.php";
				if(!is_readable($php))
					$php = NULL;
				$init[] = array( "php" => $php, "name" => $file, "level" => $info["plugin.runlevel"], "deps"=>$info["plugin.dependencies"] );
				$names[] = $file;
			} 
		}
	}
	closedir($handle);
	usort($init,"pluginsSort");
	$do_diagnostic = false;
	$jResult = '';
	$jEnd = '';
	foreach($init as $plugin)
	{
		if($plugin["php"] && !count(array_diff( $plugin["deps"], $names )))
			require_once( $plugin["php"] );
	}
	$theSettings->store();
}

?>