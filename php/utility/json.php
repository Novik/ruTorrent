<?php

require_once('utf.php');

class JSON
{	
	public static function safeEncode($value)
	{
		return json_encode($value, JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
	}
}
