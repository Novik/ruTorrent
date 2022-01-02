<?php

require_once( 'lfs.php' );

class Permission
{
	private static function isUserHavePrim($uid,$gids,$file,$flags)
	{
		$ss=LFS::stat($file);
		if($ss)
		{
			$p=$ss['mode'];
			if(($p & $flags) == $flags)
			{
				return(true);
			}
			$flags<<=3;
			foreach( $gids as $ndx=>$gid)
					if(($gid==$ss['gid']) &&
					(($p & $flags) == $flags))
					return(true);
			$flags<<=3;
			if(($uid==$ss['uid']) &&
				(($p & $flags) == $flags))
				return(true);
		}
		return(false);
	}

	public static function doesUserHave($uid,$gids,$file,$flags)
	{
		if($uid<=0)
		{
				if(($flags & 0x0001) && !is_dir($file))
						return(($ss=LFS::stat($file)) && ($ss['mode'] & 0x49));
				else
				return(true);
		}
		if(is_link($file))
			$file = readlink($file);
		if(self::isUserHavePrim($uid,$gids,$file,$flags))
		{
			if(($flags & 0x0002) && !is_dir($file))
				$flags = 0x0003;
			else
				$flags = 0x0001;
			return(self::isUserHavePrim($uid,$gids,dirname($file),$flags));
		}
		return(false);
	}
}