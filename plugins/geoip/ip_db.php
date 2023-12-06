<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( "sqlite.php" );

class ipDB
{
        public $handle = null;
        public $error = null;

	public function __construct()
	{
		$pathToDatabase = FileUtil::getSettingsPath().'/'.sqlite_db_name();
		@FileUtil::makeDirectory( dirname($pathToDatabase) );
		$needCreate = (!is_readable($pathToDatabase));
		if( $this->handle = sqlite_open1($pathToDatabase, 0666, $this->error) )
		{
			if($needCreate)
			{
				sqlite_exec1($this->handle, 
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
			sqlite_close1($this->handle);
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
		return(sqlite_query1($this->handle, "select comment from comments where ip='".sqlite_escape_string1($ip)."'", $this->error));
	}

	public function add( $ip, $comment )
	{
		$comment = self::trunc($comment);
		$ip = "'".sqlite_escape_string1($ip)."'";
		if( $comment=='' )
			sqlite_exec1($this->handle, "delete from comments where ip=".$ip,
				$this->error);
		else
		{
			$comment = "'".sqlite_escape_string1($comment)."'";
			if(sqlite_query1($this->handle, "select count(comment) from comments where ip=".$ip, $this->error ))
			{
				sqlite_exec1($this->handle, "update comments set comment=".$comment." where ip=".$ip,
					$this->error);
			}
			else
				sqlite_exec1($this->handle, "insert into comments (ip,comment) values (".$ip.",".$comment.")",
					$this->error);
		}
	}
}
