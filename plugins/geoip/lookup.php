<?php
	set_time_limit(0);
	require_once( '../../php/util.php' );
	require_once( "sqlite.php" );
	eval( FileUtil::getPluginConf( 'geoip' ) );

	// Load GeoIP2 library
	if(isset($geoip2Autoloader) && file_exists($geoip2Autoloader))
		require_once($geoip2Autoloader);

	$cityReader = null;
	$ispReader = null;
	if(isset($geoip2CityDb) && file_exists($geoip2CityDb))
	{
		try { $cityReader = new \GeoIp2\Database\Reader($geoip2CityDb); }
		catch(\Exception $e) { $cityReader = null; }
	}
	if(isset($geoip2IspDb) && file_exists($geoip2IspDb))
	{
		try { $ispReader = new \GeoIp2\Database\Reader($geoip2IspDb); }
		catch(\Exception $e) { $ispReader = null; }
	}

	function isValidCode( $country )
	{
		return( !empty($country) && (strlen($country)==2) && !is_numeric($country[1]) );
	}

	$retrieveCountry = ($retrieveCountry && $cityReader !== null);
	$retrieveCountryIPv6 = $retrieveCountry; // GeoIP2 handles both v4 and v6 natively
	$retrieveComments = ($retrieveComments && sqlite_exists());
	$ret = array();
	$dns = null;
	if(!isset($HTTP_RAW_POST_DATA))
		$HTTP_RAW_POST_DATA = file_get_contents("php://input");
	if(isset($HTTP_RAW_POST_DATA))
	{
		if($dnsResolver && $retrieveHost)
		{
			$dns = fsockopen("udp://".$dnsResolver, 53);
			$randbase = rand(0, 255) * 256;
			$idx = 0;
		}
		$vars = explode('&', $HTTP_RAW_POST_DATA);
		foreach($vars as $var)
		{
			$parts = explode("=",$var);
			if($parts[0]=="ip")
			{
				$value = trim($parts[1]);
				if(strlen($value))
				{
					$city = array();
					if($retrieveCountry)
					{
						$country = '';
						// Strip brackets from IPv6 addresses
						$lookupIp = $value;
						if(substr($lookupIp, 0, 1) == '[')
							$lookupIp = substr($lookupIp, 1, -1);

						try
						{
							$record = $cityReader->city($lookupIp);
							$country = $record->country->isoCode;
							$c = $record->city->name;
							if(!empty($c))
								$city[] = $c;
						}
						catch(\Exception $e)
						{
							$country = '';
						}

						if(!isValidCode($country))
							$country = "un";
						else
						{
							$country = strtolower($country);
							if($ispReader !== null)
							{
								try
								{
									$ispRecord = $ispReader->isp($lookupIp);
									$org = $ispRecord->organization ?? '';
									$isp = $ispRecord->isp ?? '';
									if(!empty($org))
										$city[] = $org;
									if(!empty($isp) && $isp !== $org)
										$city[] = $isp;
								}
								catch(\Exception $e) {}
							}
						}
					}
					else
						$country = "un";
					if(!empty($city))
						$country.=" (".implode(', ', $city).")";
					$host = $value;
					if($retrieveHost)
					{
						if($dns)
						{
							$pkt = pack("n", $randbase + $idx) . "\1\0\0\1\0\0\0\0\0\0";
							$ipmap[$value] = $idx++;
							if (substr($value, 0, 1) == '[')
							{
								$a = '';
								foreach(str_split(inet_pton(substr($value, 1, -1))) as $char) $a .= str_pad(dechex(ord($char)), 2, '0', STR_PAD_LEFT);
								$pkt .= "\1" . implode("\1", str_split(strrev($a))) . "\3ip6\4arpa\0\0\x0C\0\1";
							}
							else
							{
								foreach (array_reverse(explode(".", $value)) as $part)
									$pkt .= chr(strlen($part)) . $part;
								$pkt .= "\7in-addr\4arpa\0\0\x0C\0\1";
							}
							fwrite($dns, $pkt);
							fflush($dns);
							$host = $value;
						}
						else
						{
							$host = gethostbyaddr(preg_replace('/^\[?(.+?)\]?$/', '$1', $value));
							if(empty($host) || (strlen($host)<2))
								$host = $value;
						}
					}
					$comment = '';
					if($retrieveComments)
					{
						require_once( 'ip_db.php' );
						$db = new ipDB();
						$comment = $db->get($value);
					}
					$ret[] = array( "ip"=>$value, "info"=>array( "country"=>$country, "host"=>$host, "comment"=>$comment ) );
				}
			}
		}
		if($dns)
		{
			stream_set_timeout($dns, $dnsResolverTimeout);
			while($idx && ($buf=@fread($dns, 512)))
			{
				$pos = 12;
				$ip = array();
				$id = ord($buf[0]) * 256 + ord($buf[1]) - $randbase;
				while($count = ord($buf[$pos++]))
				{
					if(count($ip) < 4)
						array_unshift($ip, substr($buf, $pos, $count));
					$pos += $count;
				}
				$ip = implode(".", $ip);
				if(substr($buf, $pos, 10) != "\0\x0C\0\1\xC0\x0C\0\x0C\x00\x01")
					continue;
				$idx--;
				$pos += 16;
				$host = array();
				while($count = ord($buf[$pos++]))
				{
					if($count >= 0xc0)
					{
						$count = (($count&0x3f) << 8) | ord($buf[$pos]);
						if($count < $pos-1)
						{
							$pos = $count;
							continue;
						}
						else
						{
							$host = false;
							break;
						}
					}
					array_push($host, substr($buf, $pos, $count));
					$pos += $count;
				}
				if($host)
				{
					$host = implode(".", $host);
					$ret[$id]["info"]["host"] = $host;
				}
			}
			fclose($dns);
		}
	}
	CachedEcho::send(JSON::safeEncode($ret),"application/json");
