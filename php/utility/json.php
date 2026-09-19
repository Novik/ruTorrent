<?php

require_once('utf.php');

class JSON
{
	public static function safeEncode($value)
	{
		return json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
	}

	// A JavaScript literal for $value, to be interpolated into an emitted
	// script body. The literal carries its own quotes, so a caller must not
	// add any. The HEX_* flags keep <, >, &, ' and " out of the result, so no
	// input can close the surrounding string literal or the <script> element
	// that holds it.
	public static function jsValue($value)
	{
		$json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE |
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		return($json===false ? 'null' : $json);
	}
}
