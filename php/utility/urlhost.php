<?php

/**
 * One answer to "is this URL's host one of ours?". In this tree it decides
 * which origin a POST came from (Requests::makeCSRFCheck()); it is kept a
 * leaf so that any other place that decides an identity from a URL can
 * take it as well.
 *
 * The host is taken out of the URL by parse_url(), never searched for in
 * it. A substring test accepts "https://evil.test/x/tracker.example/dl.php",
 * whose host is the attacker's, and "https://tracker.example@evil.test/",
 * where the tracker's name is the userinfo. Cutting an authority at its
 * first colon is no better: "[::1]:8080" is the host "[::1]", not "[".
 *
 * Two spellings of the same host are folded before they are compared,
 * because whoever writes the URL picks them: case (DNS names have none) and
 * trailing dots (the DNS root; "tracker.example." is "tracker.example").
 *
 * A leaf on purpose: it requires nothing and touches no state, so a plugin
 * can take it without taking another plugin.
 */
class UrlHost
{
	/**
	 * A host name folded to the one spelling the tests below compare: lower
	 * case, without trailing dots. Anything that is not a string folds to ''.
	 */
	static public function normalize($host)
	{
		if(!is_string($host))
			return '';
		return strtolower(rtrim($host, '.'));
	}

	/**
	 * The host of a URL, normalized, or null when the URL has none that
	 * parse_url() can find -- which is the answer for a URL that only
	 * mentions a host somewhere in its path or query. A scheme-relative
	 * reference (//host/path) has a host; a caller that needs a scheme
	 * checks it itself. An IPv6 literal keeps its brackets, and an IDN is
	 * compared as spelled, without Unicode/punycode conversion.
	 */
	static public function of($url)
	{
		$host = @parse_url((string) $url, PHP_URL_HOST);
		if(!is_string($host) || $host === '')
			return null;
		$host = self::normalize($host);
		return $host === '' ? null : $host;
	}

	/**
	 * True when $host is exactly one of $hosts, subdomains excluded: for a
	 * list that names every host it admits. Both sides are normalized; an
	 * empty host matches nothing, so a URL without one admits nothing.
	 */
	static public function isExactlyOneOf($host, $hosts)
	{
		$host = self::normalize($host);
		return $host !== '' && in_array($host, array_map(array(__CLASS__, 'normalize'), (array) $hosts), true);
	}

	/** True for a normalized host or one of its subdomains, at a label boundary. */
	static public function isOneOf($host, $hosts)
	{
		$host = self::normalize($host);
		if($host === '')
			return false;
		foreach((array) $hosts as $candidate)
		{
			$candidate = self::normalize($candidate);
			if($candidate !== '' && ($host === $candidate
				|| substr($host, -strlen($candidate) - 1) === '.' . $candidate))
				return true;
		}
		return false;
	}

}
