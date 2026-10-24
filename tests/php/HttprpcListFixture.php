<?php

/**
 * XML-RPC replies the way rTorrent writes them: a name that is not UTF-8
 * stays raw bytes inside <string>, with only &, < and > escaped.
 */
class HttprpcListFixture
{
	public static function value($value)
	{
		if(!is_array($value))
			return '<value><string>'.str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), (string)$value).'</string></value>';
		$struct = count($value) && array_keys($value) !== range(0, count($value)-1);
		$xml = $struct ? '<value><struct>' : '<value><array><data>';
		foreach($value as $key=>$item)
			$xml .= $struct ? '<member><name>'.$key.'</name>'.self::value($item).'</member>' : self::value($item);
		return $xml.($struct ? '</struct></value>' : '</data></array></value>');
	}

	public static function body($results)
	{
		return '<?xml version="1.0"?><methodResponse><params><param>'.self::value($results).'</param></params></methodResponse>';
	}

	public static function raw($xml)
	{
		return FakeRtorrentDaemon::raw("Status: 200 OK\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($xml)."\r\n\r\n".$xml);
	}
}
