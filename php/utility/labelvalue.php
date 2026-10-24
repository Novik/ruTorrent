<?php

class LabelValue
{
	/**
	 * d.custom1 holds a percent-encoded label, trimmed here as the WebUI trims
	 * it. A UTF-8 label stays a string; any other becomes a display name plus
	 * its byte key. Null when the label cannot be decoded.
	 */
	public static function fromEncoded($encoded)
	{
		try {
			// PHP 7.4 answers NULL for a disabled function, so check each step.
			$value = @rawurldecode($encoded);
			$value = is_string($value) ? @trim($value) : null;
			$key = is_string($value) ? @rawurlencode($value) : null;
		} catch(Throwable $error) {
			return null;
		}
		if(!is_string($key))
			return null;
		return UTF::isValid($value) ? $value : array('name' => UTF::display($value), 'labelKey' => $key);
	}
}
