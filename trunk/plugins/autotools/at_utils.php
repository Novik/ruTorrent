<?php

function AddTailSlash( $str )
{
	$len = strlen( $str );
	if( $len > 0 && $str[$len-1] == '/' )
		return $str;
	return $str.'/';
}

function RemoveTailSlash( $str )
{
	$len = strlen( $str );
	if( $len == 0 || $str[$len-1] != '/' )
		return $str;
	return substr( $str, 0, -1 );
}

function RemoveHeadSlash( $str )
{
	$len = strlen( $str );
	if( $len == 0 || $str[0] != '/' )
		return $str;
	return substr( $str, 1 );
}

function RemoveLastToken( $str, $sep )
{
	$pos = strrpos( $str, $sep );
	if( $pos === false )
		return $str;
	return substr( $str, 0, $pos );
}

function GetRelativePath( $base_dir, $real_dir )
{
	$base_dir = AddTailSlash( $base_dir );
	$len = strlen( $base_dir );
	$str = substr( $real_dir, 0, $len );
	if( $str != $base_dir )
		return '';			// $real_dir is NOT SUBDIR of $base_dir
	$str = substr( $real_dir, $len );
	if( $str != '' )
		return $str;			// $read_dir is SUBDIR of $base_dir
	return './';				// $real_dir is EQUAL to $base_dir
}

function RemoveDirectory( $path, $with_files = false )
{
	$path = RemoveTailSlash( $path );
	if( !file_exists( $path ) || !is_dir( $path ) )
		return false;
	$handle = opendir( $path );
	$empty = true;
	while( false !== ( $item = readdir( $handle ) ) )
	{
		if( $item == '.' || $item == '..' )
			continue;
		$path_to_item = $path.'/'.$item;
		if( is_dir( $path_to_item ) )
		{
			if( !RemoveDirectory( $path_to_item, $with_files ) )
				$empty = false;
		}
		else
		{
			if( !$with_files || !unlink( $path_to_item ) )
				$empty = false;
		}
	}
	closedir( $handle );
	return ( $empty && rmdir( $path ) );
}


?>
