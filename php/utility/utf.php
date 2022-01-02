<?php

class UTF
{	
	public static function isInvalidUTF8($str)
	{
		$len = strlen($str);
		for($i = 0; $i < $len; $i++)
		{
			$c = ord($str[$i]);
			if($c > 128) 
			{
				if(($c > 247)) return(true);
				elseif($c > 239) $bytes = 4;
				elseif($c > 223) $bytes = 3;
				elseif($c > 191) $bytes = 2;
				else return(true);
				if(($i + $bytes) > $len) return(true);
				while ($bytes > 1) 
				{
					$i++;
					$b = ord($str[$i]);
					if($b < 128 || $b > 191) return(true);
					$bytes--;
				}
			}
		}
		return(false);
	}

	public static function win2utf($str)
	{
		$outstr='';
		$recode=array
		(
			0x0402,0x0403,0x201A,0x0453,0x201E,0x2026,0x2020,0x2021,
			0x20AC,0x2030,0x0409,0x2039,0x040A,0x040C,0x040B,0x040F,
			0x0452,0x2018,0x2019,0x201C,0x201D,0x2022,0x2013,0x2014,
			0x0000,0x2122,0x0459,0x203A,0x045A,0x045C,0x045B,0x045F,
			0x00A0,0x040E,0x045E,0x0408,0x00A4,0x0490,0x00A6,0x00A7,
			0x0401,0x00A9,0x0404,0x00AB,0x00AC,0x00AD,0x00AE,0x0407,
			0x00B0,0x00B1,0x0406,0x0456,0x0491,0x00B5,0x00B6,0x00B7,
			0x0451,0x2116,0x0454,0x00BB,0x0458,0x0405,0x0455,0x0457,
			0x0410,0x0411,0x0412,0x0413,0x0414,0x0415,0x0416,0x0417,
			0x0418,0x0419,0x041A,0x041B,0x041C,0x041D,0x041E,0x041F,
			0x0420,0x0421,0x0422,0x0423,0x0424,0x0425,0x0426,0x0427,
			0x0428,0x0429,0x042A,0x042B,0x042C,0x042D,0x042E,0x042F,
			0x0430,0x0431,0x0432,0x0433,0x0434,0x0435,0x0436,0x0437,
			0x0438,0x0439,0x043A,0x043B,0x043C,0x043D,0x043E,0x043F,
			0x0440,0x0441,0x0442,0x0443,0x0444,0x0445,0x0446,0x0447,
			0x0448,0x0449,0x044A,0x044B,0x044C,0x044D,0x044E,0x044F
		);
		$and=0x3F;
		for($i=0;$i<strlen($str);$i++) 
		{
			$octet=array();
			if(ord($str[$i])<0x80) 
				$strhex=ord($str[$i]);
			else
				$strhex=$recode[ord($str[$i])-128];
			if($strhex<0x0080)
				$octet[0]=0x0;
			elseif($strhex<0x0800)
			{
				$octet[0]=0xC0;
				$octet[1]=0x80;
			} 
			elseif($strhex<0x10000) 
			{
				$octet[0]=0xE0;
				$octet[1]=0x80;
				$octet[2]=0x80;
			} 
			elseif($strhex<0x200000) 
			{
				$octet[0]=0xF0;
				$octet[1]=0x80;
				$octet[2]=0x80;
				$octet[3]=0x80;
			} 
			elseif ($strhex<0x4000000) 
			{
				$octet[0]=0xF8;
				$octet[1]=0x80;
				$octet[2]=0x80;
				$octet[3]=0x80;
				$octet[4]=0x80;
			} 
			else 
			{
				$octet[0]=0xFC;
				$octet[1]=0x80;
				$octet[2]=0x80;
				$octet[3]=0x80;
				$octet[4]=0x80;
				$octet[5]=0x80;
				}
				for($j=(count($octet)-1);$j>=1;$j--) 
			{
				$octet[$j]=$octet[$j] + ($strhex & $and);
				$strhex=$strhex>>6;
			}
			$octet[0]=$octet[0] + $strhex;
			for($j=0;$j<count($octet);$j++) 
				$outstr.=chr($octet[$j]);
		}
		return($outstr);
	}

	private static function mix2utf($str, $inv = '_') 
	{
		$len = strlen($str);
		for($i = 0; $i < $len; $i++)
		{
			$c = ord($str[$i]);
			if($c > 128) 
			{
				$bytes = 0;
				if(($c > 247)) $str[$i] = $inv;
				elseif($c > 239) $bytes = 4;
				elseif($c > 223) $bytes = 3;
				elseif($c > 191) $bytes = 2;
				else $str[$i] = $inv;
				if($bytes)
				{
					if(($i + $bytes) > $len) $str[$i] = $inv;
					else
					{
						$start = $i;
						$cnt = $bytes;
						while($bytes > 1) 
						{
							$i++;
							$b = ord($str[$i]);
							if($b < 128 || $b > 191) 
							{
								$str[$start] = $inv;
								$i = $start;
								break;
							}
							$bytes--;
						}
					}
				}
			}
		}
		return($str);
	}

	public static function utf8ize($mixed) 
	{
		if(is_array($mixed) || is_object($mixed)) 
		{
				foreach($mixed as $key => $value) 
				{
						$mixed[$key] = self::utf8ize($value);
				}
			} 
			else 
				if(is_string($mixed)) 
					$mixed = self::mix2utf($mixed);
		return($mixed);
	}
}