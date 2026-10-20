<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/requests.php');

class RequestsPublicURLTest extends TestCase
{
	private $server;
	private $environment;
	private $setting;
	private $log;
	private $logPath;

	public function setUp()
	{
		global $publicURL;
		$this->server = $_SERVER;
		$this->environment = getenv('RU_PUBLIC_URL');
		$this->setting = $publicURL ?? null;
		$this->log = ini_get('error_log');
		$this->logPath = sys_get_temp_dir() . '/public-url-' . getmypid() . '.log';
		ini_set('error_log', $this->logPath);
	}

	public function tearDown()
	{
		global $publicURL;
		$_SERVER = $this->server;
		$publicURL = $this->setting;
		putenv($this->environment === false ? 'RU_PUBLIC_URL' : 'RU_PUBLIC_URL=' . $this->environment);
		@unlink($this->logPath);
		ini_set('error_log', $this->log);
	}

	// TestCase calls setUp once per class; every scenario needs its own request and policy.
	private function resetRequest()
	{
		global $publicURL;
		putenv('RU_PUBLIC_URL');
		$publicURL = '';
		$_SERVER = array('HTTP_HOST' => 'public.test:8443', 'HTTPS' => 'off',
			'SCRIPT_NAME' => '/app/plugins/feeds/action.php');
	}

	public function testNonemptyEnvironmentOverridesConfigAndRequestHosts()
	{
		$this->resetRequest();
		global $publicURL;
		$publicURL = 'https://config.test/app/';
		putenv('RU_PUBLIC_URL=https://public.test:9443/proxy');
		$_SERVER['HTTP_X_FORWARDED_HOST'] = 'trap.test';
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === 'https://public.test:9443/proxy/',
			'The environment selects the public origin, port and path prefix');
	}

	public function testEmptyEnvironmentKeepsTheConfiguredPublicBase()
	{
		$this->resetRequest();
		global $publicURL;
		$publicURL = 'https://config.test/proxy/';
		putenv('RU_PUBLIC_URL=');
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === 'https://config.test/proxy/',
			'An empty environment value permits the local configuration');
	}

	public function testDirectFallbackUsesOnlyTheHostSchemeAndActualScript()
	{
		$this->resetRequest();
		$_SERVER['HTTP_X_FORWARDED_HOST'] = 'trap.test';
		$_SERVER['PHP_SELF'] = '/other/plugins/feeds/action.php/path-info';
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === 'http://public.test:8443/app/',
			'HTTPS=off is HTTP and SCRIPT_NAME takes precedence over PHP_SELF');
		$_SERVER['HTTP_HOST'] = '[::1]:8443';
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === 'https://[::1]:8443/app/',
			'A single forwarded scheme and IPv6 authority are preserved');
		unset($_SERVER['SCRIPT_NAME']);
		$_SERVER['PHP_SELF'] = '/fallback/plugins/feeds/action.php';
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === 'https://[::1]:8443/fallback/',
			'PHP_SELF is a compatibility fallback when SCRIPT_NAME is absent');
		$this->resetRequest();
		$_SERVER['HTTPS'] = 'on';
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === 'https://public.test:8443/app/',
			'A directly secured request uses HTTPS');
		$this->resetRequest();
		unset($_SERVER['HTTPS']);
		$_SERVER['REQUEST_SCHEME'] = 'HTTPS';
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === 'https://public.test:8443/app/',
			'The server request scheme is normalized when HTTPS is absent');
	}

	public function testInvalidExplicitBaseIsVisibleAndNeverUsesTheInternalHost()
	{
		foreach (array('https://user:secret@public.test/app/', 'https://public.test/app/?a=b',
			'https://public.test/app/#anchor', 'ftp://public.test/', '//public.test/app/',
			"https://public.test/\r\nInjected: value", 'https://public.test\\trap/', array('https://public.test/')) as $bad) {
			$this->resetRequest();
			global $publicURL;
			$publicURL = $bad;
			$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === false,
				'Invalid explicit configuration refuses instead of silently using request metadata');
		}
		$log = file_get_contents($this->logPath);
		$this->assertTrue(strpos($log, 'public-url: refused setting=publicURL') !== false
			&& strpos($log, 'secret') === false && strpos($log, 'Injected') === false,
			'The classified configuration refusal identifies the setting without echoing its contents');
		$this->resetRequest();
		putenv('RU_PUBLIC_URL=https://user:secret@public.test/');
		$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === false,
			'An invalid environment override also refuses instead of falling back');
	}

	public function testInvalidRequestMetadataIsRefusedWithoutAnExplicitBase()
	{
		foreach (array(array('HTTP_HOST' => 'user@trap.test'), array('HTTP_HOST' => 'public.test/path'),
			array('HTTP_HOST' => "public.test\r\nHeader: x"), array('HTTP_X_FORWARDED_PROTO' => 'https,http'),
			array('SCRIPT_NAME' => '/app/plugins/feeds/action.php/extra')) as $bad) {
			$this->resetRequest();
			$_SERVER = array_merge($_SERVER, $bad);
			$this->assertTrue(Requests::publicBaseURL('/plugins/feeds/action.php') === false,
				'Malformed request metadata cannot manufacture a public application base');
		}
		$this->resetRequest();
		$this->assertTrue(Requests::publicBaseURL('') === false, 'An empty endpoint suffix is refused');
	}
}
