<?php

/** Reads the same SCGI netstring request for both scripted daemon fixtures. */
class SCGIRequest
{
	public static function read($client)
	{
		stream_set_timeout($client, 10);
		$length = '';
		while(($byte = self::readBytes($client, 1)) !== ':')
		{
			if($byte < '0' || $byte > '9' || strlen($length) >= 12)
				throw new RuntimeException('invalid SCGI header length');
			$length .= $byte;
		}
		if($length === '') throw new RuntimeException('missing SCGI header length');
		$header = self::readBytes($client, (int)$length);
		if(self::readBytes($client, 1) !== ',') throw new RuntimeException('missing SCGI netstring comma');
		$parts = explode("\0", $header);
		if(array_pop($parts) !== '' || count($parts) % 2 !== 0)
			throw new RuntimeException('invalid SCGI header fields');
		$fields = array();
		for($i = 0; $i < count($parts); $i += 2) $fields[$parts[$i]] = $parts[$i+1];
		if(!isset($fields['CONTENT_LENGTH']) || !preg_match('/^[0-9]+$/D', $fields['CONTENT_LENGTH']))
			throw new RuntimeException('missing SCGI content length');
		return array('header' => $header, 'payload' => self::readBytes($client, (int)$fields['CONTENT_LENGTH']));
	}

	private static function readBytes($client, $length)
	{
		$bytes = '';
		while(strlen($bytes) < $length)
		{
			$part = fread($client, $length-strlen($bytes));
			if($part === false || $part === '') throw new RuntimeException('incomplete SCGI request');
			$bytes .= $part;
		}
		return $bytes;
	}
}
