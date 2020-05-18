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
		return( ($this->client->status == 503 || $this->client->status == 429) &&
			(stripos( $this->client->get_header("Server"), "cloudflare" ) === 0) &&
			$this->client->results &&
			(stripos( $this->client->results, "jschl_vc" ) !== false) &&
			(stripos( $this->client->results, "jschl_answer" ) !== false) );
	}

	public static function is_module_present()
	{
		exec( escapeshellarg(getExternal('python'))." -c \"import cloudscraper\" > /dev/null 2>&1", $output, $error_code);
		return($error_code === 0);
	}

	public function process()
	{
		global $recaptcha_solving_enabled;
		global $cloudscraper_recaptcha;

		$ret = false;
		if( $this->is_cloudflare_challenge() )
		{
			$url = '"'.addslashes($this->url).'"';
			$agent = $this->client->agent ? '"'.addslashes($this->client->agent).'"' : 'None';
			$proxies = '';
			$recaptcha = '';
			if($this->client->_isproxy)
			{
				// Warning: Python will not work with 'https' proxies by normal way.
				$proxy = (empty($this->client->proxy_proto) ? '' : $this->client->proxy_proto.'://').$this->client->proxy_host.":".$this->client->proxy_port;
				$proxies = ", proxies={\"http\": \"$proxy\", \"https\": \"$proxy\"}";
			}
			if($recaptcha_solving_enabled)
			{
				$recaptcha = ",recaptcha={\"provider\": \"$cloudscraper_recaptcha[provider]\",\"api_key\": \"$cloudscraper_recaptcha[api_key]\",\"username\": \"$cloudscraper_recaptcha[username]\",\"password\": \"$cloudscraper_recaptcha[password]\"},delay=15";
			}
			$code = escapeshellarg(getExternal('python'))." -c ".
				escapeshellarg("import cloudscraper\nimport json\ntokens, user_agent = cloudscraper.get_tokens({$url}{$proxies}{$recaptcha})\nprint(json.dumps([tokens,user_agent]))");
			$data = `{$code}`;
			if($data &&
				($data = json_decode($data,true)) &&
				is_array($data) &&
				count($data) > 1 &&
				!empty($data[0]))
			{
				$this->client->setcookies();
				$this->client->cookies = array_merge($this->client->cookies,$data[0]);
				$this->client->agent = $data[1];
				$ret = true;
			}
		}
		return($ret);
	}
}
