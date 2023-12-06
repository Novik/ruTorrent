<?php

require_once('utf.php');

class JSON
{	
	public static function safeEncode($value)
	{
		$encoded = json_encode($value);
		return(!function_exists('json_last_error') || json_last_error()==JSON_ERROR_NONE ? $encoded : json_encode(UTF::utf8ize($value)));
	}
}