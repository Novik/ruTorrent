<?php

// strftime() and gmstrftime() are deprecated as of PHP 8.1. This renders the
// same conversions, flags and widths as they produce in the C locale, which
// is the LC_TIME every ruTorrent entry point runs under, using date(). The
// one difference is %s: strftime() reads the local time back through the
// system time zone instead of PHP's, and here it is the timestamp itself.
class TimeFormat
{
	public static function strftime($format, $timestamp = null)
	{
		return self::format($format, $timestamp, false);
	}

	public static function gmstrftime($format, $timestamp = null)
	{
		return self::format($format, $timestamp, true);
	}

	private static function format($format, $timestamp, $utc)
	{
		$at = new DateTime('@'.(is_null($timestamp) ? time() : (int)$timestamp));
		$at->setTimezone(new DateTimeZone($utc ? 'UTC' : date_default_timezone_get()));
		return self::render((string)$format, $at, $utc);
	}

	private static function render($format, $at, $utc)
	{
		return preg_replace_callback('`%([_0^#-]*)([0-9]*)([EO]?)(.?)`s',
			function($m) use ($at, $utc)
			{
				return self::conversion($m, $at, $utc);
			}, $format);
	}

	private static function conversion($m, $at, $utc)
	{
		list($spec, $flags, $width, $modifier, $conv) = $m;
		$pad = '';
		$upper = false;
		$swap = false;
		foreach(str_split($flags) as $flag)
		{
			if($flag === '^')
				$upper = true;
			elseif($flag === '#')
				$swap = true;
			elseif($flag !== '')
				$pad = $flag;
		}
		$width = ($width === '') ? 0 : (int)$width;
		if((($modifier === 'E') && (strpos('aAbBdDeFgGhHIjklmMSUVwW', $conv) !== false)) ||
			(($modifier === 'O') && (strpos('aAcDFxXY', $conv) !== false)))
			return self::literal($spec, $conv, $upper, $swap, $pad, $width);

		$composite = array('c' => '%a %b %e %H:%M:%S %Y', 'D' => '%m/%d/%y', 'x' => '%m/%d/%y',
			'F' => '%Y-%m-%d', 'r' => '%I:%M:%S %p', 'R' => '%H:%M', 'T' => '%H:%M:%S', 'X' => '%H:%M:%S');
		if(isset($composite[$conv]))
		{
			$text = self::render($composite[$conv], $at, $utc);
			return self::fill($upper ? strtoupper($text) : $text, $width, ($pad === '0') ? '0' : ' ');
		}

		$number = null;
		$digits = 2;
		$spaces = false;
		$text = null;
		switch($conv)
		{
			case 'a': $text = $at->format('D'); $upper = $upper || $swap; break;
			case 'A': $text = $at->format('l'); $upper = $upper || $swap; break;
			case 'b':
			case 'h': $text = $at->format('M'); $upper = $upper || $swap; break;
			case 'B': $text = $at->format('F'); $upper = $upper || $swap; break;
			case 'C': $number = intdiv((int)$at->format('Y'), 100); break;
			case 'd': $number = (int)$at->format('j'); break;
			case 'e': $number = (int)$at->format('j'); $spaces = true; break;
			case 'g': $number = ((int)$at->format('o')) % 100; break;
			case 'G': $number = (int)$at->format('o'); $digits = 1; break;
			case 'H': $number = (int)$at->format('G'); break;
			case 'I': $number = (int)$at->format('g'); break;
			case 'j': $number = (int)$at->format('z') + 1; $digits = 3; break;
			case 'k': $number = (int)$at->format('G'); $spaces = true; break;
			case 'l': $number = (int)$at->format('g'); $spaces = true; break;
			case 'm': $number = (int)$at->format('n'); break;
			case 'M': $number = (int)$at->format('i'); break;
			case 'n': $text = "\n"; break;
			case 'p': $text = $at->format('A'); if($swap) $text = strtolower($text); break;
			case 'P': $text = $at->format('a'); $upper = false; break;
			case 's': $number = $at->getTimestamp(); $digits = 1; $spaces = true; break;
			case 'S': $number = (int)$at->format('s'); break;
			case 't': $text = "\t"; break;
			case 'u': $number = (int)$at->format('N'); $digits = 1; break;
			case 'U': $number = intdiv((int)$at->format('z') + 7 - (int)$at->format('w'), 7); break;
			case 'V': $number = (int)$at->format('W'); break;
			case 'w': $number = (int)$at->format('w'); $digits = 1; break;
			case 'W': $number = intdiv((int)$at->format('z') + 7 - ((int)$at->format('w') + 6) % 7, 7); break;
			case 'y': $number = ((int)$at->format('Y')) % 100; break;
			case 'Y': $number = (int)$at->format('Y'); $digits = 1; break;
			case 'z':
				$offset = (int)$at->format('Z');
				$sign = self::fill(($offset < 0) ? '-' : '+', $width, $pad === '0' ? '0' : ' ');
				$offset = abs($offset);
				return $sign.self::number(intdiv($offset, 3600) * 100 + intdiv($offset % 3600, 60), 4, false, $pad, $width);
			case 'Z': $text = $utc ? 'GMT' : $at->format('T'); if($swap) $text = strtolower($text); break;
			case '%': $text = '%'; break;
			default: return self::literal($spec, $conv, $upper, $swap, $pad, $width);
		}
		if(!is_null($number))
			return self::number($number, $digits, $spaces, $pad, $width);
		if($upper)
			$text = strtoupper($text);
		return self::fill($text, $width, ($pad === '0') ? '0' : ' ');
	}

	// A conversion strftime() does not know is copied as written, in the
	// case its flags ask for.
	private static function literal($spec, $conv, $upper, $swap, $pad, $width)
	{
		if($upper || ($swap && (strpos('bBh', $conv) !== false)))
			$spec = strtoupper($spec);
		return self::fill($spec, $width, ($pad === '0') ? '0' : ' ');
	}

	private static function number($number, $digits, $spaces, $pad, $width)
	{
		$text = (string)$number;
		if($width > 0)
			return self::fill($text, ($pad === '-') ? $width : max($width, $digits),
				(($pad === '0') || (($pad === '') && !$spaces)) ? '0' : ' ');
		if($pad === '-')
			return $text;
		return self::fill($text, $digits, (($pad === '_') || (($pad === '') && $spaces)) ? ' ' : '0');
	}

	private static function fill($text, $width, $with)
	{
		return str_pad($text, $width, $with, STR_PAD_LEFT);
	}
}
