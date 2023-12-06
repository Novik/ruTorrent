<?php

require_once('utility.php');

class LFS
{
	static public function test($fname,$flag)
	{
		$out = array();
		@exec( 'test -'.$flag.' '.escapeshellarg( $fname ), $out, $ret );
		return($ret == 0);
	}

	static protected function statPrim($fname)
	{
		$out = array();
		$st = explode(':',@exec( Utility::getExternal('stat').' -Lc%d:%i:%f:%h:%u:%g:%s:%X:%Y:%Z:%B:%b '.escapeshellarg( $fname ), $out, $ret ));
		return(($ret == 0) ? array( 
		        "dev"	=>	intval($st[0]),
			"ino"	=>	intval($st[1]),
			"mode"	=>	intval($st[2],16),
			"nlink"	=>	intval($st[3]),
			"uid"	=>	intval($st[4]),
			"gid"	=>	intval($st[5]),
			"size"	=>	floatval($st[6]),
			"atime"	=>	intval($st[7]),
			"mtime"	=>	intval($st[8]),
			"ctime"	=>	intval($st[9]),
			"blksize" =>	intval($st[10]),
			"blocks" =>	intval($st[11])) : false);
	}

	static public function is_file($fname)
	{
		return(@is_file($fname) || ((PHP_INT_SIZE<=4) && !@is_dir($fname) && @file_exists($fname) && self::test($fname,'f')));
	}

	static public function is_readable($fname)
	{
		return(@is_readable($fname) || ((PHP_INT_SIZE<=4) && @file_exists($fname) && self::test($fname,'r')));
	}

	static public function stat($fname)
	{
	        $ss = @stat($fname);
	        if(PHP_INT_SIZE<=4)
	        {
		        if($ss==false)
		        	return(@file_exists($fname) ? self::statPrim($fname) : false);
		        if(($ss["blocks"]==-1) || ($ss["blocks"]>4194303))
		        	$ss = self::statPrim($fname);
		}
		return($ss);
	}

	static public function filesize($fname)
	{
		$ss = self::stat($fname);
		return(($ss==false) ? false : floatval($ss["size"]));
	}

	static public function filemtime($fname)
	{
		$ss = self::stat($fname);
		return(($ss==false) ? false : floatval($ss["mtime"]));
	}
}
