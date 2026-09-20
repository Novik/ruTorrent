<?php

// Whether the browser will open an address that came from somewhere other
// than the page itself -- a feed item, a search result, a stored look-at
// template. js/common.js decides that for real, in isExternalURL(), and
// hands what it accepts to window.open(); this answers the same question
// where the address is stored, so that nothing is kept that could not then
// be opened and nothing is thrown away that could.
//
// Callers: plugins/rss/rss.php, for a feed item's link and permalink in the
// rss branch and the atom branch, and plugins/lookat/lookat.php, for a
// stored template. Both of those reach openExternalURL() in the browser.
//
// tests/fixtures/openable-addresses.json records, for one address per row,
// what isExternalURL() answers and what this answers. Both suites assert
// their own column against it, so the two cannot drift apart unnoticed.

class ExternalURL
{
	// Whether the browser would open this address at all. isExternalURL() in
	// js/common.js reaches its answer by parsing the address with the URL
	// parser and then looking at the scheme, so an address whose authority the
	// parser cannot make a host of never opens however its scheme reads, and a
	// test on the scheme alone would keep addresses that do nothing.
	//
	// What is accepted: an http, https, ftp, ftps or magnet address, or a
	// scheme-relative one, which takes the scheme of the page. What is not: a
	// scheme outside that list, and a reference that names no scheme of its
	// own. isExternalURL() opens that second kind, because window.open()
	// resolves it against the page it is on -- but a stored address must not be
	// able to put something in front of a user that navigates back into the
	// panel it came from, so the set here is deliberately the smaller one.
	static public function isOpenable( $url )
	{
		if(!is_string($url))
			return(false);
		// The parser removes tab, LF and CR from anywhere in the address and
		// trims leading and trailing C0 controls and spaces before it reads
		// the scheme, so "ht\ttps://host/x" names http as surely as
		// " javascript:" names javascript.
		$url = str_replace(array("\t","\n","\r"),'',trim($url,"\x00..\x20"));
		preg_match('~^(?:(https?|ftps?|magnet):)?(//)?~i',$url,$m);
		$scheme = isset($m[1]) ? strtolower($m[1]) : '';
		$hasAuthority = isset($m[2]) && ($m[2]==='//');
		if(($scheme==='') && !$hasAuthority)
			// A reference the browser would resolve against the panel's own
			// page. isExternalURL() opens one, because window.open() does; a
			// feed must not be able to put one in front of the user.
			return(false);
		if($scheme==='magnet')
			// An opaque body, with no authority to make a host of.
			return(true);
		$rest = substr($url,strlen($m[0]));
		// ftps is not one of the schemes the parser calls special, so it takes
		// an opaque body the way magnet does. http, https and ftp are, and the
		// parser refuses one of those without a host.
		if(!$hasAuthority)
			return(($scheme==='ftps') || ($rest!==''));
		return(self::hasOpenableHost($rest,$scheme!=='ftps'));
	}

	// The authority the parser would read out of the rest of the address, and
	// whether it could make a host of it. An empty host, a code point the host
	// parser forbids, a percent-escape it cannot read, brackets that do not
	// hold an ipv6 address, or a port outside 0-65535 all make the whole
	// address fail to parse, and an item carrying one is a row in the feed
	// that does nothing when it is opened.
	static protected function hasOpenableHost( $rest, $special )
	{
		$authority = preg_split('~[/?#]~',$rest,2)[0];
		$credentials = false;
		if(($at = strrpos($authority,'@'))!==false)
		{
			$credentials = true;
			$authority = substr($authority,$at+1);
		}
		if(substr($authority,0,1)==='[')
		{
			if(($close = strpos($authority,']'))===false)
				return(false);
			if(filter_var(substr($authority,1,$close-1),FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)===false)
				return(false);
			$port = substr($authority,$close+1);
		}
		else
		{
			$host = $authority;
			$port = '';
			if(($colon = strrpos($host,':'))!==false)
			{
				$port = substr($host,$colon);
				$host = substr($host,0,$colon);
			}
			if($host==='')
				// A special scheme always needs a host. A scheme that does not
				// may leave it out, but not while naming a user or a port.
				return(!$special && !$credentials && ($port===''));
			if(preg_match('~[\x00-\x20#/:<>?@\[\\\\\]^|]~',$host)===1)
				return(false);
			if(preg_match('~%(?![0-9A-Fa-f]{2})~',$host)===1)
				return(false);
		}
		if($port==='')
			return(true);
		if(preg_match('~^:([0-9]*)$~',$port,$p)!==1)
			return(false);
		return(($p[1]==='') || ((int)$p[1]<=65535));
	}
}
