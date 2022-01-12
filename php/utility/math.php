<?php

class Math
{
	const PHP_INT_MIN = ~PHP_INT_MAX;
	const XMLRPC_MAX_I4 = 2147483647;
	const XMLRPC_MIN_I4 = ~XMLRPC_MAX_I4;
	const XMLRPC_MIN_I8 = -9.999999999999999E+15;
	const XMLRPC_MAX_I8 = 9.999999999999999E+15;
	
	public static function iclamp( $val, $min = 0, $max = self::XMLRPC_MAX_I8 )
	{
		$val = floatval($val);	
		if( $val < $min )
			$val = $min;
		if( $val > $max )
			$val = $max;
		return( ((PHP_INT_SIZE>4) || ( ($val>=self::PHP_INT_MIN) && ($val<=PHP_INT_MAX) )) ? intval($val) : $val );
	}
}