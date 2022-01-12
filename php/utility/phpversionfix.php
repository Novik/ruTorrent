<?php

class phpVersionFix
{	
	private static function stripSlashesFromArray(&$arr)
	{
        if(is_array($arr))
        {
			foreach($arr as $k=>$v)
			{
				if(is_array($v))
				{
					self::stripSlashesFromArray($v);
					$arr[$k] = $v;
				}
				else
				{
					$arr[$k] = stripslashes($v);
				}
			}
		}
	}

	public static function fix_magic_quotes_gpc()
	{
		if(function_exists('ini_set'))
		{
			ini_set('magic_quotes_runtime', 0);
			ini_set('magic_quotes_sybase', 0);
		}
		if(get_magic_quotes_gpc())
		{
			self::stripSlashesFromArray($_POST);
			self::stripSlashesFromArray($_GET);
			self::stripSlashesFromArray($_COOKIE);
			self::stripSlashesFromArray($_REQUEST);
		}
	}
}

phpVersionFix::fix_magic_quotes_gpc();