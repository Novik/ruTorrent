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
	if(!isset($HTTP_RAW_POST_DATA))
		$HTTP_RAW_POST_DATA = file_get_contents("php://input");
	if(isset($HTTP_RAW_POST_DATA))
	{
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
                                                $host = gethostbyaddr($value);
                                                if(empty($host) || (strlen($host)<2))
                                                        $host = $value;
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
	}
	cachedEcho(json_encode($ret),"application/json");
