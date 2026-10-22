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

	/**
	 * The scheme of this request. HTTPS and REQUEST_SCHEME are set by the
	 * server and describe the connection it accepted; X-Forwarded-Proto is
	 * written by whoever spoke to it last, which includes the client. So the
	 * header is read only where the server reports no TLS of its own: a
	 * proxy that terminates TLS is believed, as it has to be, and no request
	 * can turn the links of a secured page into http.
	 *
	 * Each proxy of a chain appends to the header, and the scheme the client
	 * used is the first entry. Values are compared without regard to case,
	 * which is all CGI promises of HTTPS, and one naming no web scheme is
	 * passed over rather than carried into a URL.
	 */
	private static function requestScheme()
	{
		$secure = isset($_SERVER['HTTPS']) && is_scalar($_SERVER['HTTPS']) ? strval($_SERVER['HTTPS']) : '';
		$scheme = $secure !== '' && strcasecmp($secure, 'off') !== 0 ? 'https'
			: (isset($_SERVER['REQUEST_SCHEME']) && is_string($_SERVER['REQUEST_SCHEME'])
				? strtolower($_SERVER['REQUEST_SCHEME']) : '');
		if($scheme === 'https')
			return 'https';
		if(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && is_string($_SERVER['HTTP_X_FORWARDED_PROTO']))
		{
			$parts = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
			$forwarded = strtolower(trim($parts[0]));
			if(in_array($forwarded, array('http', 'https'), true))
				return $forwarded;
		}
		return 'http';
	}

	private static function currentOrigin()
	{
		$host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
		return $host !== '' ? self::requestScheme().'://'.$host : null;
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
