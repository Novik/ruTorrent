<?php

class Decode
{
	public static function base32decode($input)
	{
		$keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567=";
			$buffer = 0;
			$bitsLeft = 0;    
			$output = '';
			$i = 0;
			$input = strtoupper($input);
			$len = strlen($input);
			while($i < $len)
			{
			$val = strpos( $keyStr, $input[$i++]);
			if($val >= 0 && $val < 32) 
			{
				$buffer <<= 5;
				$buffer |= $val;
				$bitsLeft += 5;
				if($bitsLeft >= 8) 
				{
					$output .= chr(($buffer >> ($bitsLeft - 8)) & 0xFF);
					$bitsLeft -= 8;
				}
			} 
			}
			if($bitsLeft > 0) 
			{
			$buffer <<= 5;    
			$output .= chr(($buffer >> ($bitsLeft - 3)) & 0xFF);
			}         
		return( strtoupper(bin2hex($output)) );
	}
}