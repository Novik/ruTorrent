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

function preg_match_map($pattern, $text, $mapfunc)
{
	$offset = 0;
	while(preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, $offset)===1)
	{
		$mapfunc($match);
		$offset = $match[0][1] + strlen($match[0][0]);
	}
	return $offset > 0;
}

class rXMLRPCRequest
{
	protected $commands = array();
	protected $content = "";
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
		if(LOG_RPC_CALLS)
			toLog($data);
		global $scgi_host;
		global $scgi_port;
		$result = false;
		$contentlength = strlen($data);
		if($contentlength>0)
		{
			$socket = @fsockopen($scgi_host, $scgi_port, $errno, $errstr, RPC_TIME_OUT);
			if($socket) 
			{
				$reqheader =  "CONTENT_LENGTH\x0".$contentlength."\x0"."SCGI\x0"."1\x0".($trusted ? "" : "UNTRUSTED_CONNECTION\x0"."1\x0");
				$tosend = strlen($reqheader).":{$reqheader},{$data}";
				@fwrite($socket,$tosend,strlen($tosend));
				$result = '';
				while($data = fread($socket, 4096))
					$result .= $data;
				fclose($socket);
			}
		}
		if(LOG_RPC_CALLS)
			toLog($result);
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
		$commands = array_slice($this->commands, $this->commandOffset);
		$this->fault = false;
		$this->content = "";
		$cnt = count($commands);
		if($cnt>0)
		{
			$this->content = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>';
			if($cnt==1)
			{
				$cmd = $commands[0];
	        		$this->content .= "{$cmd->command}</methodName><params>\r\n";
	        		foreach($cmd->params as &$prm)
	        			$this->content .= "<param><value><{$prm->type}>{$prm->value}</{$prm->type}></value></param>\r\n";
		        }
			else
			{
				$maxContentSize = rTorrentSettings::get()->maxContentSize();
				$this->content .= "system.multicall</methodName><params><param><value><array><data>";
				foreach($commands as $i => $cmd)
				{
					$cmdContent = "\r\n<value><struct><member><name>methodName</name><value><string>".
						"{$cmd->command}</string></value></member><member><name>params</name><value><array><data>";
					foreach($cmd->params as &$prm)
						$cmdContent .= "\r\n<value><{$prm->type}>{$prm->value}</{$prm->type}></value>";
					$cmdContent .= "\r\n</data></array></value></member></struct></value>";
					if($i > 0 and strlen($this->content) + strlen($cmdContent) + 35 + 22 > $maxContentSize)
					{
						$cnt = $i;
						break;
					}
					$this->content .= $cmdContent;
				}
				$this->content .= "\r\n</data></array></value></param>";
			}
			$this->content .= "</params></methodCall>";
		}
		$this->commandOffset += $cnt;
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
		$this->commandOffset = 0;
		rTorrentSettings::get()->patchDeprecatedRequest($this->commands);
		while($this->makeNextCall())
		{
			$answer = self::send($this->content,$trusted);
			if(!empty($answer))
			{
				if($this->parseByTypes)
				{
					$ret = preg_match_map("|<value><string>(.*)</string></value>|Us", $answer, function ($match)
					{
						$this->strings[] = html_entity_decode(
							str_replace( array("\\","\""), array("\\\\","\\\""), $match[1][0] ),
							ENT_COMPAT,"UTF-8");
					});
					$ret = $ret && preg_match_map("|<value><i.>(.*)</i.></value>|Us", $answer, function ($match)
					{
						$this->i8s[] = $match[1][0];
					});
				}
				else
				{
					$ret = preg_match_map("/<value>(<string>|<i.>)(.*)(<\/string>|<\/i.>)<\/value>/Us", $answer, function ($match)
					{
						$this->val[] = html_entity_decode(
							str_replace( array("\\","\""), array("\\\\","\\\""), $match[2][0] ),
							ENT_COMPAT,"UTF-8");
					});
				}
				if($ret)
				{
					if(strstr($answer,"faultCode")!==false)
					{
						$this->fault = true;	
						if(LOG_RPC_FAULTS && $this->important)
						{
							toLog($this->content);
							toLog($answer);
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
