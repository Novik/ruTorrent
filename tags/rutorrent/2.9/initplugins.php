<?php
/*
 * initplugins.php, contributed by dmrom
 *
 * Script for loading all ruTorrent plugins on rTorrent's start.
 * Add the following lines into your "rtorrent.rc" file.
 *

# Load all ruTorrent plugins
execute = {sh,-c,/usr/local/bin/php /usr/local/www/rt/initplugins.php &}

 *
 * All plugins would be run according their runlevel.info
 */

if( !chdir( dirname( __FILE__ ) ) )
        exit;
require_once( "util.php" );
require_once( "settings.php" );

function InitPlugins_Sort( $a, $b )
{
	$lvl1 = (float) $a["level"];
	$lvl2 = (float) $b["level"];
	if( $lvl1 > $lvl2 )
		return 1;
	if( $lvl1 < $lvl2 )
		return -1;
	return 0;
}

if( !function_exists( 'preg_match_all' ) )
{
	//toLog( "PCRENotFound" );
	exit;
}

$theSettings = new rTorrentSettings();
$theSettings->obtain();
if( !$theSettings->linkExist )
{
	//tolog( "badLinkTorTorrent" );
	exit;
}

if( $handle = opendir( './plugins' ) )
{
	$init = array();
	while( false !== ( $file = readdir( $handle ) ) )
	{
		if( $file != "." && $file != ".." && is_dir( "./plugins/".$file ) )
		{
			$php = "./plugins/".$file."/init.php";
			if( !is_file( $php ) || !is_readable( $php ) )
				$php = NULL;
			$runlevel = 10.0;
			$level = "./plugins/".$file."/runlevel.info";
			if( is_file( $level ) && is_readable( $level ) )
				$runlevel = floatval( file_get_contents( $level ) );
			$init[] = array( "php" => $php, "level" => $runlevel, "name" => $file );
		}
	}
	closedir( $handle );

	usort( $init, "InitPlugins_Sort" );
	foreach( $init as $plugin )
	{
		if( $plugin["php"] )
			require_once( $plugin["php"] );
	}
	$theSettings->store();
}

?>