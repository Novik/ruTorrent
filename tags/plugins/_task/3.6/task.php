<?php
require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );

class rTask
{
	const MAX_CONSOLE_SIZE = 25;
	const MAX_ARG_LENGTH = 2048;

	const FLG_WAIT		= 0x0001;
	const FLG_STRIP_LOGS	= 0x0002;
	const FLG_ONE_LOG	= 0x0004;
	const FLG_ECHO_CMD	= 0x0008;
	const FLG_DEFAULT	= 0x000A;
	const FLG_NO_ERR	= 0x0010;
	const FLG_RUN_AS_WEB	= 0x0020;
	const FLG_RUN_AS_CMD	= 0x0040;

	static public function formatPath( $taskNo )
	{
		return(getTempDirectory().'rutorrent-tsk-'.getUser().$taskNo );
	}

	static public function start( $commands, $flags = self::FLG_DEFAULT )
	{
	        $taskNo = time();
	        $dir = self::formatPath($taskNo);
	        if(count($commands))
	        {
			makeDirectory($dir);
			if(($sh = fopen($dir."/start.sh","w"))!==false)
		        {
				fputs($sh,'#!/bin/sh'."\n");
				fputs($sh,'dir="$(dirname $0)"'."\n");
				fputs($sh,'echo $$ > "${dir}"/pid'."\n");
				fputs($sh,'chmod a+rw "${dir}"/pid'."\n");
				file_put_contents($dir."/flags",$flags);
				@chmod($dir."/flags",0666);
				fputs($sh,'touch "${dir}"/status'."\n");
				fputs($sh,'chmod a+rw "${dir}"/status'."\n");
				fputs($sh,'touch "${dir}"/errors'."\n");
				fputs($sh,'chmod a+rw "${dir}"/errors'."\n");
				fputs($sh,'touch "${dir}"/log'."\n");
				fputs($sh,'chmod a+rw "${dir}"/log'."\n");
				fputs($sh,'last=0'."\n");
				$err = ($flags & self::FLG_ONE_LOG) ? "log" : "errors";
				foreach( $commands as $ndx=>$cmd )
				{
					if($cmd=='{')
						fputs($sh,'if [ $last -eq 0 ] ; then '."\n");
					else
					if($cmd=='}')				
						fputs($sh,'fi'."\n");
					else
					if($cmd[0]=='>')				
						fputs($sh,'echo "'.substr($cmd,1).'" >> "${dir}"/log'."\n");
					else
					{
						if($flags & self::FLG_ECHO_CMD)
							fputs($sh,'echo "'.$cmd.'" >> "${dir}"/log'."\n");
                                	        if($flags & self::FLG_NO_ERR)
							fputs($sh,$cmd.' >> "${dir}"/log'."\n");
						else
							fputs($sh,$cmd.' 2>> "${dir}"/'.$err.' >> "${dir}"/log'."\n");
						fputs($sh,'if [ $? -ne 0 ] ; then '."\n\t".'last=1'."\n".'fi'."\n");
					}
				}
				fputs($sh,'echo $last > "${dir}"/status'."\n");
				fclose($sh);
				@chmod($dir."/start.sh",0755);
				if(!self::run($dir."/start.sh",$flags))
				{
					if(!($flags & self::FLG_WAIT))
						sleep(1);
					return(self::check($taskNo,$flags));
				}
			}
			self::clean($dir);
		}
		return(array( "no"=>$taskNo, "pid"=>0, "status"=>255, "log"=>array(), "errors"=>array("Can't start operation") ));
	}

	static protected function clean( $dir )
	{
		@unlink($dir.'/pid');
		@unlink($dir.'/flags');
		@unlink($dir.'/status');
		@unlink($dir.'/errors');
		@unlink($dir.'/log');
		@unlink($dir.'/start.sh');
		@rmdir($dir);
	}

	static protected function processLog( $dir, $logName, &$ret, $stripConsole )
	{
		if(is_file($dir.'/'.$logName) && is_readable($dir.'/'.$logName))
		{
			$lines = file($dir.'/'.$logName);
			foreach( $lines as $line )
			{
				if($stripConsole)
				{
					$pos = strrpos($line,"\r");
					if($pos!==false)
					{
						$line = rtrim(substr($line,$pos+1));
						if(strlen($line)==0)
							continue;
					}
					if(strrpos($line,chr(8))!==false)
					{
						$len = strlen($line);
						$res = array();
						for($i=0; $i<$len; $i++)
						{
							if($line[$i]==chr(8))
								array_pop($res);
							else
								$res[] = $line[$i];
						}
						$line = implode('',$res);
					}
				}
				$ret[$logName][] = rtrim($line);
			}
			if($stripConsole && (count($ret[$logName])>self::MAX_CONSOLE_SIZE))
				array_splice($ret[$logName],0,count($ret[$logName])-self::MAX_CONSOLE_SIZE);
		}		
	}

	static public function check( $taskNo, $flags = null )
	{
		$dir = self::formatPath($taskNo);
		$ret = array( "no"=>$taskNo, "pid"=>0, "status"=>-1, "log"=>array(), "errors"=>array() );
		if(is_file($dir.'/pid') && is_readable($dir.'/pid'))
		{
			if(is_null($flags))
				$flags = intval(file_get_contents($dir.'/flags'));
			$ret["pid"] = intval(trim(file_get_contents($dir.'/pid')));
			if(is_file($dir.'/status') && is_readable($dir.'/status'))
			{
				$status = trim(file_get_contents($dir.'/status'));
				if(strlen($status))
					$ret["status"] = intval($status);
			}
			self::processLog($dir, 'log', $ret, ($flags & self::FLG_STRIP_LOGS));
			self::processLog($dir, 'errors', $ret, false);
			if($ret["status"]>=0)
				self::clean($dir);
		}
		return($ret);
	}

	static public function run( $cmd, $flags = 0 )
	{
		$ret = -1;
		$params = " >/dev/null 2>&1";
		if(!($flags & self::FLG_WAIT))
			$params.=" &";
		if($flags & self::FLG_RUN_AS_WEB)
		{
			if(self::FLG_RUN_AS_CMD)
				$cmd = '-c "'.$cmd.'"';
			exec('sh '.$cmd.$params, $output, $ret);
		}
		else
		{
			$req = new rXMLRPCRequest( 
				((rTorrentSettings::get()->iVersion>=0x900) && !($flags & self::FLG_WAIT)) ?
					new rXMLRPCCommand( "execute.nothrow.bg", array("","sh",$cmd) ) :
					new rXMLRPCCommand( "execute_nothrow", array("sh","-c",$cmd.$params) )
				);
			if($req->success() && count($req->val))
				$ret = intval($req->val[0]);
		}
		return($ret);
	}

	static public function kill( $taskNo, $flags = null )
	{
		$dir = self::formatPath($taskNo);
		$ret = array( "no"=>$taskNo, "pid"=>0, "status"=>-1, "log"=>array(), "errors"=>array() );
		if(is_file($dir.'/pid') && is_readable($dir.'/pid'))
		{
			if(is_null($flags))
				$flags = intval(file_get_contents($dir.'/flags'));
			$pid = trim(file_get_contents($dir.'/pid'));
			self::run("kill -9 ".$pid." ; kill -9 `".getExternal("pgrep")." -P ".$pid."`", ($flags & self::FLG_RUN_AS_WEB) | self::FLG_WAIT | self::FLG_RUN_AS_CMD );
			self::clean($dir);
		}
		return(true);
	}
}
