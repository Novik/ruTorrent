<?php

/**
 * Public Suffix List lookup for ASCII cookie Domain attributes.
 *
 * The bundled file is the official ICANN + PRIVATE list. IDN names are
 * refused until both the URL host and PSL rules have the same IDNA mapping;
 * silently falling back to the PSL '*' rule would widen a cookie's scope.
 */
class PublicSuffix
{
	private static $rules;

	public static function isPublicSuffix(string $asciiDomain): bool
	{
		// Numeric/hex IPv4 spellings resolve via inet_aton even when FILTER_VALIDATE_IP rejects them.
		if($asciiDomain === '' || strlen($asciiDomain) > 253
			|| !preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/iD', $asciiDomain)
			|| preg_match('/(?:\A|\.)xn--/i', $asciiDomain)
			|| preg_match('/\A(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*\z/iD', $asciiDomain))
			throw new InvalidArgumentException('Unsafe ASCII cookie domain');

		$rules = self::rules();
		$labels = explode('.', strtolower($asciiDomain));
		$count = count($labels);
		$best = 1; // PSL's implicit '*' rule for unknown TLDs.
		for($i = 0; $i < $count; $i++)
		{
			$suffix = implode('.', array_slice($labels, $i));
			if(isset($rules['!' . $suffix]))
				return false; // An exception leaves its first label registrable.
			if(isset($rules[$suffix]))
				$best = max($best, $count - $i);
			if($i > 0 && isset($rules['*.' . $suffix]))
				$best = max($best, $count - $i + 1);
		}
		return $count === $best;
	}

	private static function rules(): array
	{
		if(self::$rules !== null)
			return self::$rules;
		if(!function_exists('file_get_contents') || !function_exists('hash'))
			throw new RuntimeException('Public Suffix List cannot be verified');
		$data = @file_get_contents(__DIR__ . '/public_suffix_list.dat');
		// Reject partial or damaged reads instead of silently widening Domain scope.
		if(!is_string($data) || hash('sha256', $data) !== '257b298daca42f6d8ec964e238c2a55518e14f09d3117917ec8acee6f188503e')
			throw new RuntimeException('Public Suffix List is unavailable or incomplete');
		$rules = array();
		foreach(explode("\n", $data) as $line)
		{
			$line = trim($line);
			if($line === '' || strncmp($line, '//', 2) === 0)
				continue;
			$rule = preg_split('/\s+/', $line, 2)[0];
			if(!preg_match('/[^\x00-\x7f]/', $rule))
				$rules[strtolower($rule)] = true;
		}
		return self::$rules = $rules;
	}
}
