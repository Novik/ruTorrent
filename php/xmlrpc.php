<?php

require_once( 'util.php' );
require_once( 'settings.php' );

class rXMLRPCParam
{
	public $type;
	public $value;

	public function __construct( $aType, $aValue )
	{
		$this->type = $aType;
		if(($this->type=="i8") || ($this->type=="i4"))
			$this->value = number_format($aValue,0,'.','');
		else
			$this->value = htmlspecialchars($aValue,ENT_NOQUOTES,"UTF-8");
	}
}

class rXMLRPCCommand
{
	public $command;
	public $params;

	public function __construct( $cmd, $args = null )
	{
		$this->command = getCmd($cmd);
		$this->params = array();
		rTorrentSettings::get()->patchDeprecatedCommand($this,$cmd);
		if($args!==null) 
		{
		        if(is_array($args))
				foreach($args as $prm)
					$this->addParameter($prm);
			else
				$this->addParameter($args);
		}
	}

	public function addParameters( $args )
	{
		if($args!==null) 
		{
			if(is_array($args))
				foreach($args as $prm)
					$this->addParameter($prm);
			else
				$this->addParameter($args);
		}
	}

	public function addParameter( $aValue, $aType = null )
	{
		if($aType===null)
			$aType = self::getPrmType( $aValue );
		$this->params[] = new rXMLRPCParam( $aType, $aValue );
	}

	static protected function getPrmType( $prm )
	{
		if(is_int($prm) && ($prm>=XMLRPC_MIN_I4) && ($prm<=XMLRPC_MAX_I4))
			return('i4');
		if(is_float($prm))
			return('i8');
		return('string');
	}
}

class rXMLRPCRequest
{
	protected $commands = array();
	protected $content = "";
	protected $commandOffset = 0;
	public $i8s = array();
	public $strings = array();
	public $val = array();
	public $fault = false;
	public $parseByTypes = false;
	public $important = true;

	public function __construct( $cmds = null )
	{
		if($cmds)
		{
		        if(is_array($cmds))
				foreach($cmds as $cmd)
					$this->addCommand($cmd);
			else
				$this->addCommand($cmds);
		}
	}

	public static function send( $data, $trusted = true )
	{
		global $rpcLogCalls;
		if($rpcLogCalls)
			FileUtil::toLog($data);
		$result = false;
		$contentlength = strlen($data);
		if($contentlength>0)
		{
			global $rpcTimeOut;
			global $scgi_host;
			global $scgi_port;
			$socket = @fsockopen($scgi_host, $scgi_port, $errno, $errstr, $rpcTimeOut);
			if($socket) 
			{
				$reqheader = "CONTENT_LENGTH\x0".$contentlength."\x0"."CONTENT_TYPE\x0"."text/xml\x0"."SCGI\x0"."1\x0UNTRUSTED_CONNECTION\x0".($trusted ? "0" : "1")."\x0";
				$tosend = strlen($reqheader).":{$reqheader},{$data}";
				@fwrite($socket,$tosend,strlen($tosend));
				$result = '';
				while($data = fread($socket, 4096))
					$result .= $data;
				fclose($socket);
			}
		}
		if($rpcLogCalls)
			FileUtil::toLog($result);
		return($result);
	}

	public function setParseByTypes( $enable = true )
	{
		$this->parseByTypes = $enable;
	}

	public function getCommandsCount()
	{
		return(count($this->commands));
	}

	protected function makeNextCall()
	{
		$this->fault = false;
		$this->content = "";
		$cnt = count($this->commands) - $this->commandOffset;
		if($cnt>0)
		{
			$this->content = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>';
			if($cnt==1)
			{
				$cmd = $this->commands[$this->commandOffset++];
	        		$this->content .= "{$cmd->command}</methodName><params>\r\n";
	        		foreach($cmd->params as &$prm)
	        			$this->content .= "<param><value><{$prm->type}>{$prm->value}</{$prm->type}></value></param>\r\n";
		        }
			else
			{
				$maxContentSize = rTorrentSettings::get()->maxContentSize();
				$this->content .= "system.multicall</methodName><params><param><value><array><data>";
				for(; $this->commandOffset < count($this->commands); $this->commandOffset++)
				{
					$cmd = $this->commands[$this->commandOffset];
					$cmdStr = "\r\n<value><struct><member><name>methodName</name><value><string>".
						"{$cmd->command}</string></value></member><member><name>params</name><value><array><data>";
					foreach($cmd->params as &$prm)
						$cmdStr .= "\r\n<value><{$prm->type}>{$prm->value}</{$prm->type}></value>";
					$cmdStr .= "\r\n</data></array></value></member></struct></value>";
					if($this->commandOffset > count($this->commands) - $cnt and
						strlen($this->content) + strlen($cmdStr) + 35 + 22 > $maxContentSize)
						break;
					$this->content .= $cmdStr;
				}
				$this->content .= "\r\n</data></array></value></param>";
			}
			$this->content .= "</params></methodCall>";
		}
		return($cnt>0);
	}

	public function addCommand( $cmd )
	{
		$this->commands[] = $cmd;
	}

	public function run($trusted = true)
	{
	        $ret = false;
		$this->i8s = array();
		$this->strings = array();
		$this->val = array();
		rTorrentSettings::get()->patchDeprecatedRequest($this->commands);
		$this->commandOffset = 0;
		while($this->makeNextCall())
		{
			$answer = self::send($this->content,$trusted);
			if(!empty($answer))
			{
				if($this->parseByTypes)
				{
					if((preg_match_all("|<value><string>(.*)</string></value>|Us",$answer,$strings)!==false) &&
						count($strings)>1 &&
						(preg_match_all("|<value><i.>(.*)</i.></value>|Us",$answer,$this->i8s)!==false) &&
						count($this->i8s)>1)
					{
						foreach($strings[1] as $str) 
						{
							$this->strings[] = html_entity_decode(
								str_replace( array("\\","\""), array("\\\\","\\\""), $str ),
	 							ENT_COMPAT,"UTF-8");
						}
						$this->i8s = $this->i8s[1];
						$ret = true;
					}
				}
				else
				{
					if((preg_match_all("/<value>(<string>|<i.>)(.*)(<\/string>|<\/i.>)<\/value>/Us",$answer,$response)!==false) &&
						count($response)>2)
					{
						foreach($response[2] as $str) 
						{
							$this->val[] = html_entity_decode(
								str_replace( array("\\","\""), array("\\\\","\\\""), $str ),
	 							ENT_COMPAT,"UTF-8");
						}
						$ret = true;
					}
				}
				if($ret)
				{
					if(strstr($answer,"faultCode")!==false)
					{
						$this->fault = true;
						global $rpcLogFaults;
						if($rpcLogFaults && $this->important)
						{
							FileUtil::toLog($this->content);
							FileUtil::toLog($answer);
						}
						break;
					}
				} else break;
			} else break;
		}
		$this->content = "";
		$this->commands = array();
		return($ret);
	}

	public function success($trusted = true)
	{
		return($this->run($trusted) && !$this->fault);
	}
}

function getCmd($cmd)
{
	return(rTorrentSettings::get()->getCommand($cmd));
}
