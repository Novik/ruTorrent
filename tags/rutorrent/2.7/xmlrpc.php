<?php

require_once( 'util.php' );

class rXMLRPCParam
{
	public $type;
	public $value;

	public function rXMLRPCParam( $aType, $aValue )
	{
		$this->type = $aType;
		if(($this->type=="i8") || ($this->type=="i4"))
			$this->value = number_format($aValue,0,'.','');
		else
			$this->value = $aValue;
	}
}

class rXMLRPCCommand 
{
	public $command;
	public $params;

	public function rXMLRPCCommand( $cmd, $args = null )
	{
		$this->command = $cmd;
		$this->params = array();
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
		if(is_int($prm))
			return('i4');
		if(is_double($prm))
			return('i8');
		return('string');
	}
}

class rXMLRPCRequest
{
	protected $commands = array();
	protected $content = "";
	public $i8s = array();
	public $strings = array();
	public $fault = false;

	public function rXMLRPCRequest( $cmds = null )
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

	public function getCommandsCount()
	{
		return(count($this->commands));
	}

	protected function makeCall()
	{
		$this->fault = false;
		$this->content = "";
		$cnt = count($this->commands);
		if($cnt>0)
		{
			$this->content = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>';
			if($cnt==1)
			{
				$cmd = $this->commands[0];
	        		$this->content .= "{$cmd->command}</methodName><params>\r\n";
	        		foreach($cmd->params as &$prm)
	        			$this->content .= "<param><value><{$prm->type}>{$prm->value}</{$prm->type}></value></param>\r\n";
		        }
			else
			{
				$this->content .= "system.multicall</methodName><params><param><value><array><data>";
				foreach($this->commands as &$cmd)
				{
					$this->content .= "\r\n<value><struct><member><name>methodName</name><value><string>".
						"{$cmd->command}</string></value></member><member><name>params</name><value><array><data>";
					foreach($cmd->params as &$prm)
						$this->content .= "\r\n<value><{$prm->type}>{$prm->value}</{$prm->type}></value>";
					$this->content .= "\r\n</data></array></value></member></struct></value>";
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

	public function run()
	{
		$this->i8s = array();
		$this->strings = array();
		if($this->makeCall())
		{
//toLog($this->content);
			$answer = send2RPC($this->content);
//toLog($answer);
			if(strlen($answer)>0)
			{
				preg_match_all("|<value><string>(.*)</string></value>|Us",$answer,$this->strings);
				$this->strings = str_replace("\\","\\\\",$this->strings[1]);
				$this->strings = str_replace("\"","\\\"",$this->strings);
				foreach($this->strings as &$string) 
					$string = html_entity_decode($string,ENT_COMPAT,"UTF-8");
				preg_match_all("|<value><i.>(.*)</i.></value>|Us",$answer,$this->i8s);
				$this->i8s = $this->i8s[1];
				if(strstr($answer,"faultCode")!==false)
					$this->fault = true;	
				$this->content = "";
				$this->commands = array();
				return(true);
			}
		}
		return(false);
	}
}

?>