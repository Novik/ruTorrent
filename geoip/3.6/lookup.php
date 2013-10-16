<?php
	set_time_limit(0);
	require_once( '../../php/util.php' );
	eval( getPluginConf( 'geoip' ) );

	function isValidCode( $country )
	{
		return( !empty($country) && (strlen($country)==2) && !ctype_digit($country[1]) );
	}

	$retrieveCountry = ($retrieveCountry && function_exists("geoip_country_code_by_name"));
	$retrieveComments = ($retrieveComments && function_exists("sqlite_open"));
	$ret = array();
	$dns = null;
	if(!isset($HTTP_RAW_POST_DATA))
		$HTTP_RAW_POST_DATA = file_get_contents("php://input");
	if(isset($HTTP_RAW_POST_DATA))
	{
		if($dnsResolver && $retrieveHost)
		{
			$dns = fsockopen("udp://".$dnsResolver, 53);
			$randbase = rand(0, 32000);
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
					        if(geoip_db_avail(GEOIP_CITY_EDITION_REV1) || geoip_db_avail(GEOIP_CITY_EDITION_REV0))
					        {
       					        	$country = @geoip_record_by_name( $value );
       					        	if(!empty($country))
							{
								$c = utf8_encode($country["city"]);
								if(!empty($c))
									$city[] = $c;
       					        		$country = $country["country_code"];
							}
						}
						if(!isValidCode($country) )
							$country = @geoip_country_code_by_name( $value );
						if(!isValidCode($country))
							$country = "un";
						else
						{
							$country = strtolower($country);
							$org = '';
							if(geoip_db_avail(GEOIP_ORG_EDITION))
							{
								$org = utf8_encode(geoip_org_by_name($value));
								if(!empty($org))
									$city[] = $org;
							}
							if(geoip_db_avail(GEOIP_ISP_EDITION))
							{
								$c = utf8_encode(geoip_isp_by_name($value));
								if(!empty($c) && ($c!=$org))
									$city[] = $c;
							}
						}
                    			}
					else
						$country = "un";
					if(!empty($city))
                                               $country.=" (".implode(', ',$city).")";
					$host = $value;
                                        if($retrieveHost)
                                        {
						if($dns) 
						{
							$pkt = pack("n", $randbase + $idx) . "\1\0\0\1\0\0\0\0\0\0";
							$ipmap[$value] = $idx++;
							foreach (array_reverse(explode(".", $value)) as $part)
								$pkt .= chr(strlen($part)) . $part;
							$pkt .= "\7in-addr\4arpa\0\0\x0C\0\1";
							fwrite($dns, $pkt);
							fflush($dns);
							$host = $value;
						} 
						else 
						{
                                                	$host = gethostbyaddr($value);
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
					$ret[$ipmap[$ip]]["info"]["host"] = $host;
				}
			}
			fclose($dns);
		}
	}
	cachedEcho(json_encode($ret),"application/json");
	