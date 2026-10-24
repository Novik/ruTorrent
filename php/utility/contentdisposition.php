<?php

require_once(__DIR__.'/utf.php');

/**
 * RFC 6266 download names: a quoted ASCII fallback and an RFC 8187 UTF-8 name.
 * Quotes and backslashes must be quoted-pairs in the fallback. Unknown bytes
 * get a visible Unicode label before they reach either header parameter.
 */
class ContentDisposition
{
	public static function attachment( $name, $userAgent = null )
	{
		$name = UTF::display((string)$name);
		if(is_string($userAgent) && (strpos($userAgent,'MSIE')!==false))
			$fallback = rawurlencode($name);
		else
			$fallback = str_replace(array('\\', '"'), array('\\\\', '\\"'),
				preg_replace('/[^\x20-\x7E]/', '_', $name));
		$value = 'attachment; filename="'.$fallback.'"';
		if(preg_match('//u', $name) === 1)
			$value .= "; filename*=UTF-8''".rawurlencode($name);
		return($value);
	}
}
