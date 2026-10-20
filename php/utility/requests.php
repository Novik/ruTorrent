<?php

require_once('urlhost.php');

class Requests
{
	private static function normalizedPublicBase($value)
	{
		if(!is_string($value) || preg_match('/[\x00-\x20\x7f\\\\]/', $value) ||
			filter_var($value, FILTER_VALIDATE_URL) === false)
			return false;
		$parts = @parse_url($value);
		if(!is_array($parts) || empty($parts['host']) || empty($parts['scheme']) ||
			!in_array(strtolower($parts['scheme']), array('http', 'https'), true) ||
			isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']))
			return false;
		return rtrim($value, '/').'/';
	}

	/** Public RSS links need an explicit base when the proxy rewrites Host or the prefix. */
	public static function publicBaseURL($scriptSuffix)
	{
		global $publicURL;
		$environment = getenv('RU_PUBLIC_URL');
		// An empty environment value permits config.local.php overrides.
		$usesEnvironment = $environment !== false && $environment !== '';
		$configured = $usesEnvironment ? $environment : ($publicURL ?? '');
		if($configured !== '' && $configured !== null)
		{
			$base = self::normalizedPublicBase($configured);
			if($base !== false)
				return $base;
			error_log('public-url: refused setting='.($usesEnvironment ? 'RU_PUBLIC_URL' : 'publicURL')
				.'; expected an absolute HTTP(S) application base without credentials, query or fragment');
			return false;
		}
		// Forwarded-Host and CSRF grants do not identify the public application URL.
		$origin = self::currentOrigin();
		$originParts = $origin === null ? false : @parse_url($origin);
		$script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
		if(is_string($scriptSuffix) && $scriptSuffix !== '' && is_string($script) &&
			isset($script[0]) && $script[0] === '/' &&
			substr($script, -strlen($scriptSuffix)) === $scriptSuffix && is_array($originParts) &&
			!isset($originParts['path']) && !isset($originParts['query']) && !isset($originParts['fragment']))
		{
			$base = self::normalizedPublicBase($origin.substr($script, 0, -strlen($scriptSuffix)).'/');
			if($base !== false)
				return $base;
		}
		error_log('public-url: refused request origin or script path; configure RU_PUBLIC_URL for a rewriting proxy');
		return false;
	}

	private static function currentOrigin()
	{
		$scheme = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
			? strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'])
			: (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https'
				: (isset($_SERVER['REQUEST_SCHEME']) ? strtolower($_SERVER['REQUEST_SCHEME']) : 'http'));
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
		return in_array($scheme, array('http', 'https'), true) && $host !== ''
			? $scheme.'://'.$host : null;
	}

	public static function disableUnsupportedMethods()
	{
		if( isset($_SERVER['REQUEST_METHOD']) &&
			($_SERVER['REQUEST_METHOD']!='GET') &&
			($_SERVER['REQUEST_METHOD']!='POST') )
		{
			header('HTTP/1.0 405 Method Not Allowed', true, 405);
			header('Content-Type: text/plain; charset=UTF-8');
			die('Method Not Allowed');
		}
	}

	public static function makeCSRFCheck()
	{
		global $enableCSRFCheck;
		if($enableCSRFCheck &&
			isset($_SERVER['REQUEST_METHOD']) &&
			($_SERVER['REQUEST_METHOD'] != "GET"))
		{
			global $enabledOrigins;
			if(empty($enabledOrigins))
				$enabledOrigins = array();
			// A Host header is an authority, host[:port], and an IPv6 host has
			// colons of its own: read up to the first colon, "[::1]:8080" became
			// "[", matched no Origin, and every POST made through an IPv6
			// address answered 403. It is read as the Origin and Referer hosts
			// below are read, and compared as a DNS name: without regard to
			// case or trailing dots, and never as a mere substring.
			if(isset($_SERVER['HTTP_X_FORWARDED_HOST']))
				$enabledOrigins[] = UrlHost::of('http://'.$_SERVER['HTTP_X_FORWARDED_HOST']);
			if(isset($_SERVER['HTTP_HOST']))
				$enabledOrigins[] = UrlHost::of('http://'.$_SERVER['HTTP_HOST']);
			// "Origin: null" names no host, so it matches nothing here and the
			// Referer decides, as for a request without an Origin. A browser
			// sends it from sandboxed frames, data: and file: pages, and for a
			// form POST from any page with a no-referrer policy, so a foreign
			// page needs only that policy to send it. index.html declares
			// same-origin to keep the WebUI's own form POSTs from sending it.
			if(isset($_SERVER['HTTP_ORIGIN']))
			{
				if(UrlHost::isExactlyOneOf(UrlHost::of($_SERVER['HTTP_ORIGIN']),$enabledOrigins))
				{
					return;
				}
			}
			if(isset($_SERVER['HTTP_REFERER']))
			{
				if(UrlHost::isExactlyOneOf(UrlHost::of($_SERVER['HTTP_REFERER']),$enabledOrigins))
				{
					return;
				}
			}
			header('HTTP/1.0 403 Forbidden', true, 403);
			header('Content-Type: text/plain; charset=UTF-8');
			die('Forbidden');
		}
	}
}
