<?php
require_once( dirname(__FILE__)."/../../php/util.php" );
eval( getPluginConf( '_cloudflare' ) );

class rCloudflare
{
	protected $client = null;
	protected $url = null;

	public function __construct( $client, $url )
	{
		$this->client = $client;
		$this->url = $url;
	}

	protected function is_cloudflare_challenge()
	{
		return( $this->client->status == 503 && 
			(stripos( $this->client->get_header("Server"), "cloudflare" ) === 0) &&
			$this->client->results && 
			(stripos( $this->client->results, "jschl_vc" ) !== false) &&
			(stripos( $this->client->results, "jschl_answer" ) !== false) );
	}

	public static function is_module_present()
	{
		exec( escapeshellarg(getExternal('python'))." -c \"import cfscrape\" > /dev/null 2>&1", $output, $error_code);
		return($error_code === 0);
	}

	public function process()
	{
		$ret = false;
		if( $this->is_cloudflare_challenge() )
		{
			$url = '"'.addslashes($this->url).'"';
			$agent = $client->agent ? '"'.addslashes($client->agent).'"' : 'None';
			$code = escapeshellarg(getExternal('python'))." -c ".
				escapeshellarg("import cfscrape\nimport json\ntokens, user_agent = cfscrape.get_tokens($url,user_agent=$agent)\nprint(json.dumps(tokens))");
			$cookies = `{$code}`;
			if($cookies && 
				($cookies = json_decode($cookies,true)) &&
				is_array($cookies) &&
				!empty($cookies))
			{
				$this->client->setcookies();
				$this->client->cookies = array_merge($this->client->cookies,$cookies);
				$ret = true;
			}
		}
		return($ret);
	}
}
