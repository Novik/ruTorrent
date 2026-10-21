<?php

/** Removes a directory tree a test created; symbolic links are removed, not followed. */
class TestTree
{
	public static function remove($path)
	{
		if(!is_dir($path) || is_link($path))
			return;
		foreach(scandir($path) as $entry)
			if(($entry !== '.') && ($entry !== '..'))
			{
				$child = $path.'/'.$entry;
				if(is_dir($child) && !is_link($child))
					self::remove($child);
				else
					@unlink($child);
			}
		@rmdir($path);
	}
}
