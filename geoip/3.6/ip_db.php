<?php

require_once( dirname(__FILE__)."/../../php/util.php" );

class ipDB
{
        public $handle = null;
        public $error = null;
	
	public function __construct()
	{
		$pathToDatabase = getSettingsPath().'/peers.dat';
		@makeDirectory( dirname($pathToDatabase) );
		$needCreate = (!is_readable($pathToDatabase));
		if( $this->handle = sqlite_open($pathToDatabase, 0666, $this->error) )
		{
			if($needCreate)
			{
				sqlite_exec($this->handle, 
					'create table comments( '.
						'id integer primary key,'.
						'ip text unique,'.
						'comment text)', 
						$this->error);
				@chmod($pathToDatabase, 0666);
			}
		}
	}

	public function __destruct()
	{
		if($this->handle)
			sqlite_close($this->handle);
	}

	static public function trunc( $str, $maxlength = 128 )
	{
		$str = trim($str);
		if(function_exists('mb_substr'))
			$ret = mb_substr($str,0,$maxlength,'UTF-8');
		else
			$ret = substr($str,0,$maxlength);
		return($ret);
	}

	public function get( $ip )
	{
		$ret = '';
		if($query = sqlite_unbuffered_query($this->handle, "select comment from comments where ip='".sqlite_escape_string($ip)."'", SQLITE_ASSOC, $this->error ))
			$ret = strval(sqlite_fetch_single($query));
		return($ret);
	}

	public function add( $ip, $comment )
	{
		$comment = self::trunc($comment);
		$ip = "'".sqlite_escape_string($ip)."'";
		if( $comment=='' )
			sqlite_exec($this->handle, "delete from comments where ip=".$ip,
				$this->error);
		else
		{
			$comment = "'".sqlite_escape_string($comment)."'";
			if( ($query = sqlite_unbuffered_query($this->handle, "select count(comment) from comments where ip=".$ip, SQLITE_ASSOC, $this->error )) &&
				sqlite_fetch_single($query) )
			{
				sqlite_exec($this->handle, "update comments set comment=".$comment." where ip=".$ip,
					$this->error);
			}
			else
				sqlite_exec($this->handle, "insert into comments (ip,comment) values (".$ip.",".$comment.")",
					$this->error);
		}
	}
}
