<?php

/*************************************************

Snoopy - the PHP net client
Author: Monte Ohrt <monte@ispi.net>
Copyright (c): 1999-2000 ispi, all rights reserved
Version: 1.0

 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

You may contact the author of Snoopy by e-mail at:
monte@ispi.net

Or, write to:
Monte Ohrt
CTO, ispi
237 S. 70th suite 220
Lincoln, NE 68510

The latest version of Snoopy can be obtained from:
http://snoopy.sourceforge.com

*************************************************/

require_once( 'util.php' );
require_once( 'settings.php' );

class Snoopy
{
	/**** Public variables ****/
	
	/* user definable vars */

	var $host			=	"www.php.net";		// host name we are connecting to
	var $port			=	80;					// port we are connecting to
	var $proxy_host		=	"";					// proxy host to use
	var $proxy_port		=	"";					// proxy port to use
	var $agent			=	"Snoopy v1.0";		// agent we masquerade as
	var	$referer		=	"";					// referer info to pass
	var $cookies		=	array();			// array of cookies to pass
												// $cookies["username"]="joe";
	var	$rawheaders		=	array();			// array of raw headers to send
												// $rawheaders["Content-type"]="text/html";

	var $maxredirs		=	5;					// http redirection depth maximum. 0 = disallow
	var $lastredirectaddr	=	"";				// contains address of last redirected address
	var	$offsiteok		=	true;				// allows redirection off-site
	var $maxframes		=	0;					// frame content depth maximum. 0 = disallow
	var $expandlinks	=	true;				// expand links to fully qualified URLs.
												// this only applies to fetchlinks()
												// or submitlinks()
	var $passcookies	=	true;				// pass set cookies back through redirects
												// NOTE: this currently does not respect
												// dates, domains or paths.
	
	var	$user			=	"";					// user for http authentication
	var	$pass			=	"";					// password for http authentication
	
	// http accept types
	var $accept			=	"image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*";
	
	var $results		=	"";					// where the content is put
		
	var $error			=	"";					// error messages sent here
	var	$response_code	=	"";					// response code returned from server
	var	$headers		=	array();			// headers returned from server sent here
	var	$maxlength		=	4194304;				// max return data length (body)
	var $read_timeout	=	0;					// timeout on read operations, in seconds
												// supported only since PHP 4 Beta 4
												// set to 0 to disallow timeouts
	var $timed_out		=	false;				// if a read operation timed out
	var	$status			=	0;					// http request status
	
	// send Accept-encoding: gzip?
	var $use_gzip		= true;	
	var $IP			= null;
	
	/**** Private variables ****/	
	
	var	$_maxlinelen	=	4096;				// max line length (headers)
	
	var $_httpmethod	=	"GET";				// default http request method
	var $_httpversion	=	"HTTP/1.0";			// default http request version
	var $_submit_method	=	"POST";				// default submit method
	var $_submit_type	=	"application/x-www-form-urlencoded";	// default submit type
	var $_mime_boundary	=   "";					// MIME boundary for multipart/form-data submit type
	var $_redirectaddr	=	false;				// will be set if page fetched is a redirect
	var $_redirectdepth	=	0;					// increments on an http redirect
	var $_frameurls		= 	array();			// frame src urls
	var $_framedepth	=	0;					// increments on frame depth
	
	var $_isproxy		=	false;				// set if using a proxy server
	var $_fp_timeout	=	30;					// timeout for socket connection

/*======================================================================*\
	Function:	fetch
	Purpose:	fetch the contents of a web page
				(and possibly other protocols in the
				future like ftp, nntp, gopher, etc.)
	Input:		$URI	the location of the page to fetch
	Output:		$this->results	the output text from the fetch
\*======================================================================*/

	static public function linkencode($p_url)
	{
		if(preg_match("/\%([0-9,A-F]{2})/i",$p_url)==1)
			return($p_url);
		$p_url = str_replace("\t","%09",$p_url);
		$uparts = @parse_url($p_url);
		$scheme = array_key_exists('scheme',$uparts) ? $uparts['scheme'] : "";
		$pass = array_key_exists('pass',$uparts) ? $uparts['pass']  : "";
		$user = array_key_exists('user',$uparts) ? $uparts['user']  : "";
		$port = array_key_exists('port',$uparts) ? $uparts['port']  : "";
		$host = array_key_exists('host',$uparts) ? $uparts['host']  : "";
		$path = array_key_exists('path',$uparts) ? $uparts['path']  : "";
		$query = array_key_exists('query',$uparts) ? $uparts['query']  : "";
		$fragment = array_key_exists('fragment',$uparts) ? $uparts['fragment']  : "";

		if(!empty($scheme))
			$scheme .= '://';

		if(!empty($pass) && !empty($user))
		{
			$user = rawurlencode($user).':';
			$pass = rawurlencode($pass).'@';
		}
		elseif(!empty($user))
			$user .= '@';

		if(!empty($port) && !empty($host))
			$host = ''.$host.':';
		elseif(!empty($host))
			$host=$host;

		if(!empty($path))
		{
			$arr = preg_split("/([\/;=])/", $path, -1, PREG_SPLIT_DELIM_CAPTURE);
			$path = "";
			foreach($arr as $var)
			{
				switch($var)
				{
					case "/":
					case ";":
					case "=":
						$path .= $var;
						break;
					default:
						$path .= rawurlencode($var);
				}
			}
			// legacy patch for servers that need a literal /~username
			$path = str_replace("/%7E","/~",$path);
		}

		if(!empty($query))
		{
			$arr = preg_split("/([&=;])/", $query, -1, PREG_SPLIT_DELIM_CAPTURE);
			$query = "?";
			foreach($arr as $var)
			{
				switch($var)
				{
					case "&":
					case "=":
					case ";":
						$query .= $var;
						break;
					default:
						$query .= urlencode(str_replace("+", " ", $var));
				}
			}
		}
		if(!empty($fragment))
			$fragment = '#'.urlencode($fragment);
		return implode('', array($scheme, $user, $pass, $host, $port, $path, $query, $fragment));
	}

	static public function getURLCookies(&$url)
	{	
		$cookies = array();
		$pos = strpos($url,':COOKIE:');
		if($pos!==false)
		{
			$tmp = explode(";",substr($url,$pos+8));
			foreach($tmp as $item)
			{
				list($name,$val) = explode("=",$item);
				$cookies[$name] = $val;
			}
			$url = substr($url,0,$pos);
		}
		return($cookies);
	}

	public function fetchComplex($URI,$method="GET",$content_type="",$body="")
	{
		global $rootPath;
		global $theSettings;
		if(!isset($theSettings))
			$theSettings = rTorrentSettings::load();
		if($theSettings->isPluginRegistered('cookies') && !empty($this->host))
		{
			require_once($rootPath."/plugins/cookies/cookies.php");	
			$cookies = rCookies::load();
			$this->cookies = array_merge($this->cookies,$cookies->getCookiesForHost($this->host));
		}
		$this->cookies = array_merge($this->cookies,self::getURLCookies($URI));
		if($theSettings->isPluginRegistered('loginmgr'))
		{
			require_once($rootPath."/plugins/loginmgr/accounts.php");	
			$am = accountManager::load();
			if($am===false)
			{
				$am = new accountManager();
				$am->obtain($rootPath."/plugins/loginmgr/accounts");
			}
			$acc = $am->getAccount($URI);
			if($acc)
				return($am->fetch( $acc, $this, $URI, $method, $content_type, $body ));
		}
		return($this->fetch($URI,$method,$content_type,$body));
	}

	public function fetch($URI,$method="GET",$content_type="",$body="")
	{
		$URI_PARTS = parse_url($URI);
		if (!empty($URI_PARTS["user"]))
			$this->user = $URI_PARTS["user"];
		if (!empty($URI_PARTS["pass"]))
			$this->pass = $URI_PARTS["pass"];
		$this->_httpmethod = $method;
				
		switch($URI_PARTS["scheme"])
		{
			case "http":
				$this->host = $URI_PARTS["host"];
				if(!empty($URI_PARTS["port"]))
					$this->port = $URI_PARTS["port"];
				if($this->_connect($fp))
				{
					if($this->_isproxy)
					{
						// using proxy, send entire URI
						$this->_httprequest($URI,$fp,$URI,$this->_httpmethod,$content_type,$body);
					}
					else
					{
						$path = $URI_PARTS["path"].(isset($URI_PARTS["query"]) ? "?".$URI_PARTS["query"] : "");
						// no proxy, send only the path
						$this->_httprequest($path, $fp, $URI, $this->_httpmethod,$content_type,$body);
					}
					
					$this->_disconnect($fp);

					if($this->_redirectaddr)
					{
						/* url was redirected, check if we've hit the max depth */
						if($this->maxredirs > $this->_redirectdepth)
						{
							// only follow redirect if it's on this site, or offsiteok is true
							if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
							{
								/* follow the redirect */
								$this->_redirectdepth++;
								$this->lastredirectaddr=$this->_redirectaddr;
								$this->fetch($this->_redirectaddr);
							}
						}
					}

					if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
					{
						$frameurls = $this->_frameurls;
						$this->_frameurls = array();
						
						while(list(,$frameurl) = each($frameurls))
						{
							if($this->_framedepth < $this->maxframes)
							{
								$this->fetch($frameurl);
								$this->_framedepth++;
							}
							else
								break;
						}
					}					
				}
				else
				{
					return false;
				}
				return true;					
				break;
			case "https":
				$this->host = $URI_PARTS["host"];
				if(!empty($URI_PARTS["port"]))
					$this->port = $URI_PARTS["port"];
				if($this->_isproxy)
				{
					// using proxy, send entire URI
					$this->_httpsrequest($URI,$URI,$this->_httpmethod,$content_type,$body);
				}
				else
				{
					$path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
					// no proxy, send only the path
					$this->_httpsrequest($path, $URI, $this->_httpmethod,$content_type,$body);
				}

				if($this->_redirectaddr)
				{
					/* url was redirected, check if we've hit the max depth */
					if($this->maxredirs > $this->_redirectdepth)
					{
						// only follow redirect if it's on this site, or offsiteok is true
						if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
						{
							/* follow the redirect */
							$this->_redirectdepth++;
							$this->lastredirectaddr=$this->_redirectaddr;
							$this->fetch($this->_redirectaddr);
						}
					}
				}

				if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
				{
					$frameurls = $this->_frameurls;
					$this->_frameurls = array();

					while(list(,$frameurl) = each($frameurls))
					{
						if($this->_framedepth < $this->maxframes)
						{
							$this->fetch($frameurl);
							$this->_framedepth++;
						}
						else
							break;
					}
				}					
				return true;					
				break;
			default:
				// not a valid protocol
				$this->error	=	'Invalid protocol "'.$URI_PARTS["scheme"].'"\n';
				return false;
				break;
		}		
		return true;
	}

	function get_filename()
	{
		foreach( $this->headers as $i=>$header)
			if(preg_match_all("/^Content-Disposition:.*(\s|;)filename=\"(.*)\.torrent\"/i",$header,$matches) && (count($matches)>2))
				return( str_replace( '/', '_', $matches[2][0].".torrent" ) );
		return(false);	
	}

/*======================================================================*\
	Private functions
\*======================================================================*/
	
/*======================================================================*\
	Function:	_striplinks
	Purpose:	strip the hyperlinks from an html document
	Input:		$document	document to strip.
	Output:		$match		an array of the links
\*======================================================================*/

	function _striplinks($document)
	{	
		preg_match_all("'<\s*a\s+.*href\s*=\s*			# find <a href=
						([\"\'])?					# find single or double quote
						(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
													# quote, otherwise match up to next space
						'isx",$document,$links);
						

		// catenate the non-empty matches from the conditional subpattern

		while(list($key,$val) = each($links[2]))
		{
			if(!empty($val))
				$match[] = $val;
		}				
		
		while(list($key,$val) = each($links[3]))
		{
			if(!empty($val))
				$match[] = $val;
		}		
		
		// return the links
		return $match;
	}

/*======================================================================*\
	Function:	_stripform
	Purpose:	strip the form elements from an html document
	Input:		$document	document to strip.
	Output:		$match		an array of the links
\*======================================================================*/

	function _stripform($document)
	{	
		preg_match_all("'<\/?(FORM|INPUT|SELECT|TEXTAREA|(OPTION))[^<>]*>(?(2)(.*(?=<\/?(option|select)[^<>]*>[\r\n]*)|(?=[\r\n]*))|(?=[\r\n]*))'Usi",$document,$elements);
		
		// catenate the matches
		$match = implode("\r\n",$elements[0]);
				
		// return the links
		return $match;
	}

	
	
/*======================================================================*\
	Function:	_striptext
	Purpose:	strip the text from an html document
	Input:		$document	document to strip.
	Output:		$text		the resulting text
\*======================================================================*/

	function _striptext($document)
	{
		
		// I didn't use preg eval (//e) since that is only available in PHP 4.0.
		// so, list your entities one by one here. I included some of the
		// more common ones.
								
		$search = array("'<script[^>]*?>.*?</script>'si",	// strip out javascript
						"'<[\/\!]*?[^<>]*?>'si",			// strip out html tags
						"'([\r\n])[\s]+'",					// strip out white space
						"'&(quote|#34);'i",					// replace html entities
						"'&(amp|#38);'i",
						"'&(lt|#60);'i",
						"'&(gt|#62);'i",
						"'&(nbsp|#160);'i",
						"'&(iexcl|#161);'i",
						"'&(cent|#162);'i",
						"'&(pound|#163);'i",
						"'&(copy|#169);'i"
						);				
		$replace = array(	"",
							"",
							"\\1",
							"\"",
							"&",
							"<",
							">",
							" ",
							chr(161),
							chr(162),
							chr(163),
							chr(169));
					
		$text = preg_replace($search,$replace,$document);
								
		return $text;
	}

/*======================================================================*\
	Function:	_expandlinks
	Purpose:	expand each link into a fully qualified URL
	Input:		$links			the links to qualify
				$URI			the full URI to get the base from
	Output:		$expandedLinks	the expanded links
\*======================================================================*/

	function _expandlinks($links,$URI)
	{
		
		preg_match("/^[^\?]+/",$URI,$match);

		$match = preg_replace("|/[^\/\.]+\.[^\/\.]+$|","",$match[0]);
				
		$search = array( 	"|^http://".preg_quote($this->host)."|i",
							"|^(?!http://)(\/)?(?!mailto:)|i",
							"|/\./|",
							"|/[^\/]+/\.\./|"
						);
						
		$replace = array(	"",
							$match."/",
							"/",
							"/"
						);			
				
		$expandedLinks = preg_replace($search,$replace,$links);

		return $expandedLinks;
	}

/*======================================================================*\
	Function:	_httprequest
	Purpose:	go get the http data from the server
	Input:		$url		the url to fetch
				$fp			the current open file pointer
				$URI		the full URI
				$body		body contents to send if any (POST)
	Output:		
\*======================================================================*/
	
	function _httprequest($url,$fp,$URI,$http_method,$content_type="",$body="")
	{
		if($this->passcookies && $this->_redirectaddr)
			$this->setcookies();
			
		$URI_PARTS = parse_url($URI);
		if(empty($url))
			$url = "/";
		$headers = $http_method." ".$url." ".$this->_httpversion."\r\n";		
		if(!empty($this->agent))
			$headers .= "User-Agent: ".$this->agent."\r\n";
		if(!empty($this->host) && !isset($this->rawheaders['Host']))
			$headers .= "Host: ".$this->host."\r\n";
		if(!empty($this->accept))
			$headers .= "Accept: ".$this->accept."\r\n";
		
		if($this->use_gzip && function_exists("gzinflate")) 
			$headers .= "Accept-encoding: gzip\r\n";
		
		if(!empty($this->referer))
			$headers .= "Referer: ".$this->referer."\r\n";

		if(!empty($this->cookies))
                {			
			if(!is_array($this->cookies))
				$this->cookies = (array)$this->cookies;
	
			reset($this->cookies);
			if ( count($this->cookies) > 0 ) {
				$cookie_headers = 'Cookie: ';
				foreach ( $this->cookies as $cookieKey => $cookieVal ) {
				$cookie_headers .= $cookieKey."=".$cookieVal."; ";
				}
				$headers .= substr($cookie_headers,0,-2) . "\r\n";
			} 
		}
		if(!empty($this->rawheaders))
		{
			if(!is_array($this->rawheaders))
				$this->rawheaders = (array)$this->rawheaders;
			while(list($headerKey,$headerVal) = each($this->rawheaders))
				$headers .= $headerKey.": ".$headerVal."\r\n";
		}
		if(!empty($content_type)) {
			$headers .= "Content-type: $content_type";
			if ($content_type == "multipart/form-data")
				$headers .= "; boundary=".$this->_mime_boundary;
			$headers .= "\r\n";
		}
		if(!empty($body))	
			$headers .= "Content-length: ".strlen($body)."\r\n";
		if(!empty($this->user) || !empty($this->pass))	
			$headers .= "Authorization: BASIC ".base64_encode($this->user.":".$this->pass)."\r\n";

		$headers .= "\r\n";
		
		// set the read timeout if needed
		if ($this->read_timeout > 0)
			socket_set_timeout($fp, $this->read_timeout);
		$this->timed_out = false;
		fwrite($fp,$headers.$body,strlen($headers.$body));
		
		$this->_redirectaddr = false;
		unset($this->headers);
		
		// content was returned gzip encoded?
		$is_gzipped = false;
						
		while($currentHeader = fgets($fp,$this->_maxlinelen))
		{
			if ($this->read_timeout > 0 && $this->_check_timeout($fp))
			{
				$this->status=-100;
				return false;
			}
				
			if(preg_match("/^\r?\n$/", $currentHeader) )
			      break;
						
			// if a header begins with Location: or URI:, set the redirect
			if(preg_match("/^(Location:|URI:)/i",$currentHeader))
			{
				// get URL portion of the redirect
				preg_match("/^(Location:|URI:)\s+(.*)/",chop($currentHeader),$matches);
				// look for :// in the Location header to see if hostname is included
				if(!preg_match("|\:\/\/|",$matches[2]))
				{
					// no host in the path, so prepend
					$this->_redirectaddr = $URI_PARTS["scheme"]."://".$this->host.":".$this->port;
					// eliminate double slash
					if(!preg_match("|^/|",$matches[2]))
							$this->_redirectaddr .= "/".$matches[2];
					else
							$this->_redirectaddr .= $matches[2];
				}
				else
					$this->_redirectaddr = $matches[2];
			}
		
			if(preg_match("|^HTTP/|",$currentHeader))
			{
		                if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|",$currentHeader, $status))
				{
					$this->status= $status[1];
                		}				
				$this->response_code = $currentHeader;
			}
			
			if (preg_match("/Content-Encoding: gzip/", $currentHeader) ) {
				$is_gzipped = true;
			}
			
			$this->headers[] = $currentHeader;
		}

		$results = "";
		while ( $data = fread($fp, 4096) ) {
		    $results .= $data;
		    if (
		        strlen($results) > $this->maxlength ) {
		        break;
		    }
		}

		// gunzip
		if ( $is_gzipped ) 
		{
			if(function_exists("gzinflate"))
			{
				$results = substr($results, 10);
				$results = gzinflate($results);
			}
			else
			{
				$randName = uniqid("/tmp/rutorrent-gz-");
				file_put_contents($randName.".gz",$results);
				exec(escapeshellarg(getExternal('gzip'))." -d ".$randName.".gz",$results,$return);
				if(is_file($randName))
				{
					$results = file_get_contents($randName);
					unlink($randName);
				}
				else				
					unlink($randName.".gz");
			}
		}
		
		if ($this->read_timeout > 0 && $this->_check_timeout($fp))
		{
			$this->status=-100;
			return false;
		}
		
		// check if there is a a redirect meta tag
		
		if(preg_match("'<meta[\s]*http-equiv[^>]*?content[\s]*=[\s]*[\"\']?\d+;[\s]+URL[\s]*=[\s]*([^\"\']*?)[\"\']?>'i",$results,$match))
		{
			$this->_redirectaddr = $this->_expandlinks($match[1],$URI);	
		}

		// have we hit our frame depth and is there frame src to fetch?
		if(($this->_framedepth < $this->maxframes) && preg_match_all("'<frame\s+.*src[\s]*=[\'\"]?([^\'\"\>]+)'i",$results,$match))
		{
			$this->results[] = $results;
			for($x=0; $x<count($match[1]); $x++)
				$this->_frameurls[] = $this->_expandlinks($match[1][$x],$URI_PARTS["scheme"]."://".$this->host);
		}
		// have we already fetched framed content?
		elseif(is_array($this->results))
			$this->results[] = $results;
		// no framed content
		else
			$this->results = $results;
		
		return true;
	}

/*======================================================================*\
	Function:	_httpsrequest
	Purpose:	go get the https data from the server using curl
	Input:		$url		the url to fetch
				$URI		the full URI
				$body		body contents to send if any (POST)
	Output:		
\*======================================================================*/
	
	function _httpsrequest($url,$URI,$http_method,$content_type="",$body="")
	{
		if($this->passcookies && $this->_redirectaddr)
			$this->setcookies();

		$headers = array();		
					
		$URI_PARTS = parse_url($URI);
		if(empty($url))
			$url = "/";
		// GET ... header not needed for curl
		//$headers[] = $http_method." ".$url." ".$this->_httpversion;		
		if(!empty($this->agent))
			+$headers[] = "User-Agent: ".$this->agent;
		if(!empty($this->host))
			$headers[] = "Host: ".$this->host;
		if(!empty($this->accept))
			$headers[] = "Accept: ".$this->accept;
		if(!empty($this->referer))
			$headers[] = "Referer: ".$this->referer;

		if(!empty($this->cookies))
		{			
			if(!is_array($this->cookies))
				$this->cookies = (array)$this->cookies;
	
			reset($this->cookies);
			if ( count($this->cookies) > 0 ) {
				$cookie_str = 'Cookie: ';
				foreach ( $this->cookies as $cookieKey => $cookieVal ) {
				$cookie_str .= $cookieKey."=".$cookieVal."; ";
				}
				$headers[] = substr($cookie_str,0,-2);
			}
		}
		if(!empty($this->rawheaders))
		{
			if(!is_array($this->rawheaders))
				$this->rawheaders = (array)$this->rawheaders;
			while(list($headerKey,$headerVal) = each($this->rawheaders))
				$headers[] = $headerKey.": ".$headerVal;
		}
		if(!empty($content_type)) {
			if ($content_type == "multipart/form-data")
				$headers[] = "Content-type: $content_type; boundary=".$this->_mime_boundary;
			else
				$headers[] = "Content-type: $content_type";
		}
		if(!empty($body))	
			$headers[] = "Content-length: ".strlen($body);
		if(!empty($this->user) || !empty($this->pass))	
			$headers[] = "Authorization: BASIC ".base64_encode($this->user.":".$this->pass);

		$cmdline_params = '';			
		for($curr_header = 0; $curr_header < count($headers); $curr_header++) {
			$cmdline_params .= " -H ".escapeshellarg($headers[$curr_header]);
		}
			  	                         
		if(!empty($body))
			$cmdline_params .= " -d ".escapeshellarg($body);
		
		if($this->read_timeout > 0)
			$cmdline_params .= " -m ".$this->read_timeout;

		if(!is_null($this->IP))
			$cmdline_params .= " --interface ".$this->IP;
		
		$headerfile = uniqid(time());
		$contfile = uniqid(time());
		
		# accept self-signed certs
		$cmdline_params .= " -k -s"; 
		exec(escapeshellarg(getExternal('curl'))." -D \"/tmp/$headerfile\" -o \"/tmp/$contfile\"".$cmdline_params." ".escapeshellarg($URI),$results,$return);
		$this->_redirectaddr = false;
		unset($this->headers);
		
		if($return)
			$this->error = "Error: cURL could not retrieve the document, error $return.";
		else
		{
			$results = file_get_contents("/tmp/$contfile");
			$result_headers = file("/tmp/$headerfile");
				
			for($currentHeader = 0; $currentHeader < count($result_headers); $currentHeader++)
			{
			
				// if a header begins with Location: or URI:, set the redirect
				if(preg_match("/^(Location: |URI: )/i",$result_headers[$currentHeader]))
				{
					// get URL portion of the redirect
					preg_match("/^(Location: |URI:)(.*)/",chop($result_headers[$currentHeader]),$matches);
					// look for :// in the Location header to see if hostname is included
					if(!preg_match("|\:\/\/|",$matches[2]))
					{
						// no host in the path, so prepend
						$this->_redirectaddr = $URI_PARTS["scheme"]."://".$this->host.":".$this->port;
						// eliminate double slash
						if(!preg_match("|^/|",$matches[2]))
								$this->_redirectaddr .= "/".$matches[2];
						else
								$this->_redirectaddr .= $matches[2];
					}
					else
						$this->_redirectaddr = $matches[2];
				}
		
				if(preg_match("|^HTTP/|",$result_headers[$currentHeader]))
				{
					$this->response_code = $result_headers[$currentHeader];
					if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|",$this->response_code, $match))
					{
						$this->status= $match[1];
                	    		}
				}
				$this->headers[] = $result_headers[$currentHeader];
			}

			// check if there is a a redirect meta tag
		
			if(preg_match("'<meta[\s]*http-equiv[^>]*?content[\s]*=[\s]*[\"\']?\d+;[\s]+URL[\s]*=[\s]*([^\"\']*?)[\"\']?>'i",$results,$match))
			{
				$this->_redirectaddr = $this->_expandlinks($match[1],$URI);	
			}

			// have we hit our frame depth and is there frame src to fetch?
			if(($this->_framedepth < $this->maxframes) && preg_match_all("'<frame\s+.*src[\s]*=[\'\"]?([^\'\"\>]+)'i",$results,$match))
			{
				$this->results[] = $results;
				for($x=0; $x<count($match[1]); $x++)
					$this->_frameurls[] = $this->_expandlinks($match[1][$x],$URI_PARTS["scheme"]."://".$this->host);
			}
			// have we already fetched framed content?
			elseif(is_array($this->results))
				$this->results[] = $results;
			// no framed content
			else
				$this->results = $results;
		}
		unlink("/tmp/$headerfile");
		unlink("/tmp/$contfile");
		return(!$return);
	}

/*======================================================================*\
	Function:	setcookies()
	Purpose:	set cookies for a redirection
\*======================================================================*/
	
	function setcookies()
	{
		for($x=0; $x<count($this->headers); $x++)
		{
		if(preg_match("/^set-cookie:[\s]+([^=]+)=([^;]+)/i", $this->headers[$x],$match))
			$this->cookies[$match[1]] = $match[2];
		}
	}

	
/*======================================================================*\
	Function:	_check_timeout
	Purpose:	checks whether timeout has occurred
	Input:		$fp	file pointer
\*======================================================================*/

	function _check_timeout($fp)
	{
		if ($this->read_timeout > 0) {
			$fp_status = socket_get_status($fp);
			if ($fp_status["timed_out"]) {
				$this->timed_out = true;
				return true;
			}
		}
		return false;
	}

/*======================================================================*\
	Function:	_connect
	Purpose:	make a socket connection
	Input:		$fp	file pointer
\*======================================================================*/
	
	function _connect(&$fp)
	{
		if(!empty($this->proxy_host) && !empty($this->proxy_port))
			{
				$this->_isproxy = true;
				$host = $this->proxy_host;
				$port = $this->proxy_port;
			}
		else
		{
			$host = $this->host;
			$port = $this->port;
		}
		$this->status = 0;
		if(function_exists("stream_socket_client") && !is_null($this->IP))
		{
			$opts = array('socket' => array('bindto' => $this->IP.':0'));
			$context = stream_context_create($opts);
			$fp = stream_socket_client("tcp://".$host.":".$this->port, $errno, $errstr, $this->_fp_timeout, STREAM_CLIENT_CONNECT, $context); 
		}
		else
		{
			$fp = fsockopen(
					$host,
					$port,
					$errno,
					$errstr,
					$this->_fp_timeout
					);
		}
		if($fp)
		{
			// socket connection succeeded
			return true;
		}
		else
		{
			// socket connection failed
			$this->status = $errno;
			switch($errno)
			{
				case -3:
					$this->error="socket creation failed (-3)";
				case -4:
					$this->error="dns lookup failure (-4)";
				case -5:
					$this->error="connection refused or timed out (-5)";
				default:
					$this->error="connection failed (".$errno.")";
			}
			return false;
		}
	}
/*======================================================================*\
	Function:	_disconnect
	Purpose:	disconnect a socket connection
	Input:		$fp	file pointer
\*======================================================================*/
	
	function _disconnect($fp)
	{
		return(fclose($fp));
	}

	
/*======================================================================*\
	Function:	_prepare_post_body
	Purpose:	Prepare post body according to encoding type
	Input:		$formvars  - form variables
				$formfiles - form upload files
	Output:		post body
\*======================================================================*/
	
	function _prepare_post_body($formvars, $formfiles)
	{
		settype($formvars, "array");
		settype($formfiles, "array");

		if (count($formvars) == 0 && count($formfiles) == 0)
			return;
		
		switch ($this->_submit_type) {
			case "application/x-www-form-urlencoded":
				reset($formvars);
				while(list($key,$val) = each($formvars)) {
					if (is_array($val) || is_object($val)) {
						while (list($cur_key, $cur_val) = each($val)) {
							$postdata .= urlencode($key)."[]=".urlencode($cur_val)."&";
						}
					} else
						$postdata .= urlencode($key)."=".urlencode($val)."&";
				}
				break;

			case "multipart/form-data":
				$this->_mime_boundary = "Snoopy".md5(uniqid(microtime()));
				
				reset($formvars);
				while(list($key,$val) = each($formvars)) {
					if (is_array($val) || is_object($val)) {
						while (list($cur_key, $cur_val) = each($val)) {
							$postdata .= "--".$this->_mime_boundary."\r\n";
							$postdata .= "Content-Disposition: form-data; name=\"$key\[\]\"\r\n\r\n";
							$postdata .= "$cur_val\r\n";
						}
					} else {
						$postdata .= "--".$this->_mime_boundary."\r\n";
						$postdata .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
						$postdata .= "$val\r\n";
					}
				}
				
				reset($formfiles);
				while (list($field_name, $file_names) = each($formfiles)) {
					settype($file_names, "array");
					while (list(, $file_name) = each($file_names)) {
						if (!is_readable($file_name)) continue;

						$fp = fopen($file_name, "r");
						$file_content = fread($fp, filesize($file_name));
						fclose($fp);
						$base_name = basename($file_name);

						$postdata .= "--".$this->_mime_boundary."\r\n";
						$postdata .= "Content-Disposition: form-data; name=\"$field_name\"; filename=\"$base_name\"\r\n\r\n";
						$postdata .= "$file_content\r\n";
					}
				}
				$postdata .= "--".$this->_mime_boundary."--\r\n";
				break;
		}

		return $postdata;
	}
}

?>