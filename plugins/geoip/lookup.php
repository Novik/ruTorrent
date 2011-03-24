<?php
	set_time_limit(0);
	require_once( '../../php/util.php' );
	eval( getPluginConf( 'geoip' ) );

	function isValidCode( $country )
	{
		return( !empty($country) && (strlen($country)==2) && !ctype_digit($country[1]) );
	}

	$retrieveCountry = ($retrieveCountry && function_exists("geoip_country_code_by_name"));

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
					$city = '';

					if($retrieveCountry)
					{
						$country = '';
					        if(geoip_db_avail(GEOIP_CITY_EDITION_REV1) || geoip_db_avail(GEOIP_CITY_EDITION_REV0))
					        {
       					        	$country = @geoip_record_by_name( $value );
       					        	if(!empty($country))
							{
								$city = utf8_encode($country["city"]);
       					        		$country = $country["country_code"];
							}
						}
						if(!isValidCode($country) )
							$country = @geoip_country_code_by_name( $value );
						if(!isValidCode($country))
							$country = "un";
						else
							$country = strtolower($country);
                    			}
					else
						$country = "un";
					if(!empty($city))
                                               $country.=" (".$city.")";
					$host = $value;
                                        if($retrieveHost)
                                        {
                                                $host = gethostbyaddr($value);
                                                if(empty($host) || (strlen($host)<2))
                                                        $host = $value;
                                        }
					$ret[] = array( "ip"=>$value, "info"=>array( "country"=>$country, "host"=>$host ) );
				}
			}
		}
	}
	cachedEcho(json_encode($ret),"application/json");
?>