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
        $level = false;
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
					case "plugin.runlevel":
					case "runlevel":
					{
						$level = floatval($value);
						break;
					}
					case "plugin.version":
					case "version":
					{
						if($level==false)
							$level = 10.0;
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
	return($level);
}

if( !function_exists( 'preg_match_all' ) )
	exit;

$_SERVER['REMOTE_USER'] = $argv[1];

require_once( "util.php" );
require_once( "settings.php" );

$theSettings = new rTorrentSettings();
$theSettings->obtain();
if( $theSettings->linkExist && ($handle = opendir('../plugins')))
{
	$permissions = parse_ini_file("../conf/plugins.ini",true);
	$init = array();
	while(false !== ($file = readdir($handle)))
	{
		if($file != "." && $file != ".." && is_dir('../plugins/'.$file))
		{
			$level = getPluginInfo( $file, $permissions );
			if($level!==false)
			{
				$php = "../plugins/".$file."/init.php";
				if(!is_readable($php))
					$php = NULL;
				$init[] = array( "php" => $php, "name" => $file, "level" => $level );
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
		if($plugin["php"])
			require_once( $plugin["php"] );
	}
	$theSettings->store();
}

?>