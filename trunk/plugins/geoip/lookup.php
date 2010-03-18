<?php
	set_time_limit(0);
	require_once( '../../php/util.php' );
	eval( getPluginConf( 'geoip' ) );

	$retrieveCountry = ($retrieveCountry && function_exists("geoip_country_code_by_name"));

	$ret = array();
	if(!isset($HTTP_RAW_POST_DATA))
		$HTTP_RAW_POST_DATA = file_get_contents("php://input");
	if(isset($HTTP_RAW_POST_DATA))
	{
		$vars = split('&', $HTTP_RAW_POST_DATA);
		foreach($vars as $var)
		{
			$parts = split("=",$var);
			if($parts[0]=="ip")
			{
				$value = trim($parts[1]);
				if(strlen($value))
				{
					$info = '{ip: "'.$value.'", info: {country: "';
					$country = "unknown";
					if($retrieveCountry)
					{
						$country = @geoip_country_code_by_name( $value );
						if(!empty($country))
							$country = strtolower($country);
						else
							$country = "unknown";
                    			}
					$info.=$country;
					$info.='", host: "';
					$host = $value;
					if($retrieveHost)
					{
						$host = gethostbyaddr($value);
						if(empty($host) || (strlen($host)<2))
							$host = $value;
					}
					$info.=$host;
					$info.='" }}';
					$ret[] = $info;
				}
			}
		}
	}
	$content = '['.implode(',',$ret).']';
	if(!ini_get("zlib.output_compression"))
		header( "Content-Length: ".strlen( $content ) );
	header("Content-Type: application/json; charset=UTF-8");
	echo $content;

?>
