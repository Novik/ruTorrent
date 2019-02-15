<?php
require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );

class rTask
{
	const MAX_CONSOLE_SIZE = 80;
	const MAX_ARG_LENGTH = 2048;

	const FLG_WAIT		= 0x0001;
	const FLG_STRIP_LOGS	= 0x0002;
	const FLG_ONE_LOG	= 0x0004;
	const FLG_ECHO_CMD	= 0x0008;
	const FLG_DEFAULT	= 0x000A;
	const FLG_NO_ERR	= 0x0010;
	const FLG_RUN_AS_WEB	= 0x0020;
	const FLG_RUN_AS_CMD	= 0x0040;
	const FLG_STRIP_ERRS	= 0x0080;
	const FLG_NO_LOG	= 0x0100;
	const FLG_REMOVE_ASCII	= 0x0200;	

	public $params = array();
	public $id = 0;

	public function __construct( $params, $taskNo = null )
	{
		$this->params = $params;
		$this->id = $taskNo;
		if(empty($this->id))
			$this->id = uniqid( time(), true );
	}

	static public function formatPath( $taskNo )
	{
		return( getSettingsPath().'/tasks/'.$taskNo );
	}

	public function makeDirectory()
	{
		$dir = self::formatPath($this->id);
		makeDirectory($dir);		
		return($dir);
	}

	public function start( $commands, $flags = self::FLG_DEFAULT )
	{
		if(!rTorrentSettings::get()->linkExist)
			$flags|=self::FLG_RUN_AS_WEB;
	        if(count($commands))
	        {
			$dir = $this->makeDirectory();
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
					if($cmd=='!{')
						fputs($sh,'if [ $last -ne 0 ] ; then '."\n");
					else
					if($cmd[0]=='>')
						fputs($sh,'echo '.escapeshellarg(substr($cmd,1)).' >> "${dir}"/log'."\n");
					else
					{
						if($flags & self::FLG_ECHO_CMD)
							fputs($sh,'echo '.escapeshellarg($cmd).' >> "${dir}"/log'."\n");
                                	        if($flags & self::FLG_NO_ERR)
							fputs($sh,$cmd.' >> "${dir}"/log'."\n");
						else
        	                                if($flags & self::FLG_NO_LOG)
							fputs($sh,$cmd.' >> "${dir}"/errors 2>> "${dir}"/errors'."\n");
						else
							fputs($sh,$cmd.' 2>> "${dir}"/'.$err.' >> "${dir}"/log'."\n");
						fputs($sh,'if [ $? -ne 0 ] ; then '."\n\t".'last=1'."\n".'fi'."\n");
					}
				}
				fputs($sh,'echo $last > "${dir}"/status'."\n");
				fputs($sh,getPHP().' '.escapeshellarg(dirname(__FILE__).'/notify.php').' '.
					'$last "${dir}" '.
					escapeshellarg(getUser()).' '.
					'> /dev/null 2>> /dev/null &'."\n");
				fclose($sh);
				@chmod($dir."/start.sh",0755);
				file_put_contents( $dir."/params", serialize($this->params) );
				rTorrentSettings::get()->pushEvent( 'TaskStart', $this->params );
				if(!self::run($dir."/start.sh",$flags))
				{
					if(!($flags & self::FLG_WAIT))
						sleep(1);
					return(self::check($this->id,$flags));
				}
			}
			self::clean($dir);
		}
		return(array
		( 
			"no"=>$this->id, 
			"pid"=>0, 
			"status"=>255, 
			"log"=>array(), 
			"params"=>array(), 
			"errors"=>array(count($commands) ? "Can't start operation" : "Incorrect target directory") 
		));
	}

	static public function clean( $dir )
	{
		@deleteDirectory( $dir );
	}

	static protected function removeASCII( $subject )
	{
		$subject = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', "",$subject);
		$subject = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', "",$subject);
		$subject = preg_replace('/[\x03|\x1a]/', "", $subject);  
		return($subject);
	}

	static public function notify( $dir, $subject )
	{
		if(is_file($dir.'/params') && is_readable($dir.'/params'))
		{
			$params = unserialize(file_get_contents($dir.'/params'));
			if( is_array($params) )
			{
				rTorrentSettings::get()->pushEvent( $subject, $params );
			}
		}
	}

	static protected function tail($filename, $lines = 128, $buffer = 16384)
	{
		$sz = filesize($filename);
		if( $sz < 0xFFFF )
			return( file($filename) );
		else
		{
			 $f = fopen($filename, "rb");
			fseek($f, -1, SEEK_END);
			if(fread($f, 1) != "\n") $lines -= 1;

			$output = '';
			$chunk = '';

			while(ftell($f) > 0 && ($lines >= 0))
    			{
				$seek = min(ftell($f), $buffer);
				fseek($f, -$seek, SEEK_CUR);
			        $output = ($chunk = fread($f, $seek)).$output;
			        fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
			        $lines -= substr_count($chunk, "\n");
    			}
			fclose($f);

			return( explode("\n", $output) );
		}	
	}

	static protected function processLog( $dir, $logName, &$ret, $stripConsole, $removeASCII )
	{
		if(is_file($dir.'/'.$logName) && is_readable($dir.'/'.$logName))
		{
			$lines = self::tail($dir.'/'.$logName);
			foreach( $lines as $line )
			{
//				if($stripConsole)
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
				if($removeASCII)
					$line = self::removeASCII( $line );
				$ret[$logName][] = rtrim($line);
			}
			if($stripConsole && (count($ret[$logName])>self::MAX_CONSOLE_SIZE))
				array_splice($ret[$logName],0,count($ret[$logName])-self::MAX_CONSOLE_SIZE);
		}		
	}

	static public function check( $taskNo, $flags = null )
	{
		$dir = self::formatPath($taskNo);
		$ret = array
		( 
			"no"=>$taskNo, 
			"pid"=>0, 
			"status"=>-1, 
			"log"=>array(), 
			"errors"=>array(), 
			"params"=>array(), 
			"start"=>@filemtime($dir.'/pid'), 
			"finish"=>0 
		);
		if(is_file($dir.'/pid') && is_readable($dir.'/pid'))
		{
			if(is_null($flags))
				$flags = intval(file_get_contents($dir.'/flags'));
			$ret["pid"] = intval(trim(file_get_contents($dir.'/pid')));
			if(is_file($dir.'/status') && is_readable($dir.'/status'))
			{
				$status = trim(file_get_contents($dir.'/status'));
				if(strlen($status))
				{
					$ret["status"] = intval($status);
					$ret["finish"] = filemtime($dir.'/status');
				}					
			}
			if(is_file($dir.'/params') && is_readable($dir.'/params'))
				$ret["params"] = unserialize(file_get_contents($dir.'/params'));
			self::processLog($dir, 'log', $ret, ($flags & self::FLG_STRIP_LOGS), ($flags & self::FLG_REMOVE_ASCII));
			self::processLog($dir, 'errors', $ret, ($flags & self::FLG_STRIP_ERRS), ($flags & self::FLG_REMOVE_ASCII));
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
		$ret = array
		( 
			"no"=>$taskNo, 
			"pid"=>0, 
			"status"=>-1, 
			"log"=>array(), 
			"params"=>array(), 
			"errors"=>array() 
		);
		if(is_file($dir.'/pid') && is_readable($dir.'/pid'))
		{
			if(is_file($dir.'/status') && is_readable($dir.'/status'))
			{
				$status = trim(file_get_contents($dir.'/status'));
				if(strlen($status))
					$ret["status"] = intval($status);
			}
			if($ret["status"]<0)
			{
				if(is_null($flags))
					$flags = intval(file_get_contents($dir.'/flags'));
				$pid = trim(file_get_contents($dir.'/pid'));
				self::run("kill -9 `".getExternal("pgrep")." -P ".$pid."` ; kill -9 ".$pid, ($flags & self::FLG_RUN_AS_WEB) | self::FLG_WAIT | self::FLG_RUN_AS_CMD );
				self::notify($dir,"TaskKill");
			}
			self::clean($dir);
		}
		return(true);
	}
}

class rTaskManager
{
	const MAX_TASK_COUNT = 100;

	static public function obtain()
	{
		$tasks = array();
		$dir = getSettingsPath().'/tasks/';
		if( $handle = @opendir($dir) )
		{
			while(false !== ($file = readdir($handle)))
			{
				if($file != "." && $file != ".." && is_dir($dir.$file))
				{
					$tasks[$file] = rTask::check( $file );
					$tasks[$file]["name"] = $tasks[$file]["params"]["name"];
					$tasks[$file]["requester"] = $tasks[$file]["params"]["requester"];
					unset($tasks[$file]["params"]["name"]);
					unset($tasks[$file]["params"]["requester"]);
				}
			} 
			closedir($handle);		
	        }
	        uasort($tasks,array('self', 'sortByStarted'));
	        return($tasks);
	}
	
	static public function sortByStarted($a,$b)
	{
		return( $a['start'] > $b['start'] ? -1 : ($a['start'] < $b['start'] ? 1 : 0) );
	}

	static public function isPIDExists( $pid )
	{
		return( function_exists( 'posix_getpgid' ) ? (posix_getpgid($pid)!==false) : file_exists( '/proc/'.$pid ) );
	}

	static public function cleanup()
	{
		$counter = 0;
		$tasks = self::obtain();
		foreach( $tasks as $id=>$task )
		{
			$finished_with_error = ($task["status"]>0);
			$in_progress = !$finished_with_error && $task["pid"] && self::isPIDExists($task["pid"]);
			if( !$finished_with_error && !$in_progress && ($counter>=self::MAX_TASK_COUNT) )
				rTask::clean(rTask::formatPath($id));
			else
				$counter++;
		}
	}

	static public function remove( $list )
	{
		$tasks = array();
		$dir = getSettingsPath().'/tasks/';
		if( $handle = @opendir($dir) )
		{
			while(false !== ($file = readdir($handle)))
			{
				if($file != "." && $file != ".." && is_dir($dir.$file) && in_array($file,$list))
					$tasks[] = $file;
			} 
			closedir($handle);
			foreach( $tasks as $id )
				rTask::kill( $id );
			$tasks = self::obtain();
	        }
	        return($tasks);
	}
}