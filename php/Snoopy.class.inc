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

	var $host		=	"www.php.net";		// host name we are connecting to
	var $port		=	80;			// port we are connecting to
        var $proxy_proto	=       "http";			// http or https
	var $proxy_host		=	"";			// proxy host to use
	var $proxy_port		=	"";			// proxy port to use
	var $agent		=	"Snoopy v1.0";		// agent we masquerade as
	var $referer		=	"";			// referer info to pass
	var $cookies		=	array();		// array of cookies to pass
								// $cookies["username"]="joe";
	var $rawheaders		=	array();		// array of raw headers to send
								// $rawheaders["Content-type"]="text/html";

	var $maxredirs		=	5;			// http redirection depth maximum. 0 = disallow
	var $lastredirectaddr	=	"";			// contains address of last redirected address
	var $passcookies	=	true;			// pass set cookies back through redirects
	
	var $user		=	"";			// user for http authentication
	var $pass		=	"";			// password for http authentication
	
	// http accept types
	var $accept			=	"image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*";
	
	var $results		=	"";			// where the content is put
		
	var $error		=	"";			// error messages sent here
	var $response_code	=	"";			// response code returned from server
	var $headers		=	array();		// headers returned from server sent here
	var $maxlength		=	4194304;		// max return data length (body)
	var $read_timeout	=	0;			// timeout on read operations, in seconds

	var $timed_out		=	false;			// if a read operation timed out
	var $status		=	0;			// http request status
	
	// send Accept-encoding: gzip?
	var $use_gzip		= 	true;	
	var $IP			= 	null;
	
	/**** Private variables ****/	
	
	var $_maxlinelen	=	4096;			// max line length (headers)
	
	var $_httpversion	=	"HTTP/1.0";		// default http request version
	var $_mime_boundary	=   	"";			// MIME boundary for multipart/form-data submit type
	var $_redirectaddr	=	false;			// will be set if page fetched is a redirect
	var $_redirectdepth	=	0;			// increments on an http redirect
	
	var $_isproxy		=	false;			// set if using a proxy server
	var $_fp_timeout	=	30;			// timeout for socket connection

	public function __construct()
	{
		global $httpIP;
		global $httpProxy;
		if(@constant('HTTP_USER_AGENT')!==null)
			$this->agent = @constant('HTTP_USER_AGENT');
		if(@constant('HTTP_TIME_OUT')!==null)
		{
			$this->_fp_timeout = @constant('HTTP_TIME_OUT');
			$this->read_timeout = @constant('HTTP_TIME_OUT');
		}
		if(is_array($httpProxy) && $httpProxy['use'])
		{
			$this->proxy_proto = $httpProxy['proto'];
			$this->proxy_host = $httpProxy['host'];
			$this->proxy_port = $httpProxy['port'];
		}
		$this->use_gzip = @constant('HTTP_USE_GZIP');
		if(isset($httpIP))
			$this->IP = $httpIP;
	}

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
		else
		    $path="/";

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
		$parts = parse_url($URI);
		$this->host = $parts["host"];
		if(rTorrentSettings::get()->isPluginRegistered('cookies') && !empty($this->host))
		{
			require_once($rootPath."/plugins/cookies/cookies.php");	
			$cookies = rCookies::load();
			$this->cookies = array_merge($this->cookies,$cookies->getCookiesForHost($this->host));
		}
		$this->cookies = array_merge($this->cookies,self::getURLCookies($URI));
		if(rTorrentSettings::get()->isPluginRegistered('loginmgr'))
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
		rTorrentSettings::get()->pushEvent( "HostConnect", array
		( 
			"client" => &$this, 
			"uri" => &$URI, 
			"method" => &$method, 
			"content_type" => &$content_type, 
			"body" => &$body 
		) );
		$parts = parse_url($URI);
		$this->port = empty($parts["port"]) ? (($parts["scheme"]=="https") ? 443 : 80) : $parts["port"];
		if(!empty($parts["user"]))
			$this->user = $parts["user"];
		if(!empty($parts["pass"]))
			$this->pass = $parts["pass"];
		$this->host = $parts["host"];
		$this->_isproxy = (!empty($this->proxy_host) && !empty($this->proxy_port));
		$ret = true;
		switch($parts["scheme"])
		{
			case "dummy":
			{
				break;
			}
			case "http":
			{
				if($this->proxy_proto!='https')
				{
					if(($fp = $this->connect())!==false)
					{
						$path = (isset($parts["path"]) ? $parts["path"] : "/").(isset($parts["query"]) ? "?".$parts["query"] : "");
						$ret = ( $this->_isproxy ? 
							$this->_httprequest($URI,$fp,$URI,$method,$content_type,$body) :
							$this->_httprequest($path,$fp,$URI,$method,$content_type,$body) );
						fclose($fp);
					}
					else
						$ret = false;
					break;
				}
			}
			case "https":
			{
				$ret = $this->_httpsrequest($URI,$content_type,$body);
				break;
			}
			default:
			{
				// not a valid protocol
				$this->error = 'Invalid protocol "'.$parts["scheme"].'"\n';
				$ret = false;
				break;
			}
		}		
		if($ret)
		{
			rTorrentSettings::get()->pushEvent( "URLFetched", array
			( 
				"client" => $this, 
				"uri" => $URI, 
				"method" => $method, 
				"content_type" => $content_type, 
				"body" => $body 
			) );
			if($this->_redirectaddr)
			{
				/* url was redirected, check if we've hit the max depth */
				if($this->maxredirs > $this->_redirectdepth)
				{
					$this->_redirectdepth++;
					$this->lastredirectaddr=$this->_redirectaddr;
					$ret = $this->fetch($this->_redirectaddr);
				}
			}
		}
		return($ret);
	}

	function get_header( $name )
	{
		foreach( $this->headers as $i=>$header )
			if(preg_match_all("/^$name:\s*(.*)/i",$header,$matches))
				return( $matches[1][0] );
		return(false);
	}	

	function get_filename()
	{
		foreach( $this->headers as $i=>$header )
			if(preg_match_all("/^Content-Disposition:.*(\s|;)filename=\"(.*)\.torrent\"/i",$header,$matches) && (count($matches)>2))
				return( str_replace( '/', '_', rawurldecode($matches[2][0]).".torrent" ) );
		return(false);	
	}

	function get_modified()
	{
		$value = $this->get_header( 'Last-Modified' );
		return( $value ? strtotime($value) : false );
	}

	function _httprequest($url,$fp,$URI,$http_method,$content_type="",$body="")
	{
		if($this->passcookies && $this->_redirectaddr)
			$this->setcookies();
		if(empty($url))
			$url = "/";
		$headers = $http_method." ".$url." ".$this->_httpversion."\r\n";		
		if(!empty($this->agent))
			$headers .= "User-Agent: ".$this->agent."\r\n";
		if(!empty($this->host) && !isset($this->rawheaders['Host']))
			$headers .= "Host: ".$this->host.($this->port == 80 ? "" : ":".$this->port)."\r\n";
		if(!empty($this->accept))
			$headers .= "Accept: ".$this->accept."\r\n";
		if($this->use_gzip && function_exists("gzinflate")) 
			$headers .= "Accept-encoding: gzip\r\n";
		if(!empty($this->referer))
			$headers .= "Referer: ".$this->referer."\r\n";
		if(count($this->cookies)) 
		{
			$cookie_headers = 'Cookie: ';
			foreach( $this->cookies as $cookieKey => $cookieVal )
				$cookie_headers .= $cookieKey."=".$cookieVal."; ";
			$headers .= substr($cookie_headers,0,-2) . "\r\n";
		}
		foreach( $this->rawheaders as $headerKey => $headerVal )
			$headers .= $headerKey.": ".$headerVal."\r\n";
		if(!empty($content_type)) 
		{
			$headers .= "Content-type: $content_type";
			if ($content_type == "multipart/form-data")
				$headers .= "; boundary=".$this->_mime_boundary;
			$headers .= "\r\n";
		}
		if(!empty($body))	
			$headers .= "Content-length: ".strlen($body)."\r\n";
		if(!empty($this->user) || !empty($this->pass))	
			$headers .= "Authorization: Basic ".base64_encode($this->user.":".$this->pass)."\r\n";

		$headers .= "\r\n";
		
		// set the read timeout if needed
		if ($this->read_timeout > 0)
			socket_set_timeout($fp, $this->read_timeout);
		$this->timed_out = false;
		fwrite($fp,$headers.$body,strlen($headers.$body));
//toLog($headers.$body);
		$this->_redirectaddr = false;
		$this->headers = array();
		$is_gzipped = false;
						
		while($currentHeader = fgets($fp,$this->_maxlinelen))
		{
//toLog($currentHeader);
			if ($this->read_timeout > 0 && $this->_check_timeout($fp))
			{
				$this->status=-100;
				return false;
			}
				
			if(preg_match("/^\r?\n$/", $currentHeader))
			      break;
						
			// if a header begins with Location: or URI:, set the redirect
			if(preg_match("/^(Location:|URI:|Refresh:)/i",$currentHeader))
			{
				// get URL portion of the redirect
				if(!preg_match("/^(Location:|URI:)\s+(.*)/i",chop($currentHeader),$matches))
					preg_match("/(^Refresh:\s+\d+\s*;\s*url\s*=\s*)(.*)/i",chop($currentHeader),$matches);
				// look for :// in the Location header to see if hostname is included
				if(!preg_match("|\:\/\/|",$matches[2]))
				{
					$host = isset($this->rawheaders['Host']) ? $this->rawheaders['Host'] : $this->host;
					$this->_redirectaddr = "http://".$host.":".$this->port;
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
			
			if (preg_match("/Content-Encoding: gzip/", $currentHeader) )
				$is_gzipped = true;
			
			$this->headers[] = $currentHeader;
		}

		$results = "";
		while( $data = fread($fp, 4096) ) 
		{
			$results .= $data;
			if( strlen($results) > $this->maxlength )
			        break;
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
				$randName = getTempFilename('answer');
				file_put_contents($randName.".gz",$results);
				exec(escapeshellarg(getExternal('gzip'))." -d ".$randName.".gz",$results,$return);
				if(is_file($randName))
				{
					$results = file_get_contents($randName);
					unlink($randName);
				}
				else
				{
					unlink($randName.".gz");
				}
			}
		}
		
		if ($this->read_timeout > 0 && $this->_check_timeout($fp))
		{
			$this->status=-100;
			return false;
		}
	
		$this->results = $results;
//toLog($this->results);		
		return true;
	}

	function _httpsrequest($url,$content_type="",$body="")
	{
		if($this->passcookies && $this->_redirectaddr)
			$this->setcookies();

		$headers = array();		
		if(!empty($this->agent))
			$headers[] = "User-Agent: ".$this->agent;
		if(!empty($this->host) && !isset($this->rawheaders['Host']))
			$headers[] = "Host: ".$this->host.($this->port == 443 ? "" : ":".$this->port);
		if(!empty($this->accept))
			$headers[] = "Accept: ".$this->accept;
		if(!empty($this->referer))
			$headers[] = "Referer: ".$this->referer;
		if( count($this->cookies) ) 
		{
			$cookie_str = 'Cookie: ';
			foreach( $this->cookies as $cookieKey => $cookieVal )
				$cookie_str .= $cookieKey."=".$cookieVal."; ";
			$headers[] = substr($cookie_str,0,-2);
		}
		foreach( $this->rawheaders as $headerKey => $headerVal )
			$headers[] = $headerKey.": ".$headerVal;
		if(!empty($content_type)) 
		{
			if ($content_type == "multipart/form-data")
				$headers[] = "Content-type: $content_type; boundary=".$this->_mime_boundary;
			else
				$headers[] = "Content-type: $content_type";
		}
		if(!empty($body))	
			$headers[] = "Content-length: ".strlen($body);
		if(!empty($this->user) || !empty($this->pass))	
			$headers[] = "Authorization: Basic ".base64_encode($this->user.":".$this->pass);

		$cmdline_params = '';
		foreach( $headers as $header )
			$cmdline_params .= " -H ".escapeshellarg($header);
			  	                         
		if(!empty($body))
			$cmdline_params .= " -d ".escapeshellarg($body);
		
		if($this->read_timeout > 0)
			$cmdline_params .= " -m ".$this->read_timeout;

		if(!is_null($this->IP))
			$cmdline_params .= " --interface ".$this->IP;

		if($this->_isproxy)
			$cmdline_params .= " --proxy-insecure --proxy ".(empty($this->proxy_proto) ? '' : $this->proxy_proto.'://').$this->proxy_host.":".$this->proxy_port;
		
		$headerfile = getTempFilename('https-header');
		$contfile = getTempFilename('https-body');
		
		# accept self-signed certs
		$cmdline_params .= " -k -s "; 
		exec(escapeshellarg(getExternal('curl'))." -g -D ".escapeshellarg($headerfile)." -o ".escapeshellarg($contfile)." ".$cmdline_params." ".escapeshellarg($url),$results,$return);
		$this->_redirectaddr = false;
		$this->headers = array();
		$is_gzipped = false;
		if($return)
			$this->error = "Error: cURL could not retrieve the document, error $return.";
		else
		{
			$results = @file_get_contents($contfile);
			$result_headers = file($headerfile);
			foreach($result_headers as $header)
			{
				// if a header begins with Location: or URI:, set the redirect
				if(preg_match("/^(Location:|URI:|Refresh:)/i",$header))
				{
					// get URL portion of the redirect
					if(!preg_match("/^(Location: |URI:)(.*)/i",chop($header),$matches))
						preg_match("/(^Refresh:\s+\d+\s*;\s*url\s*=\s*)(.*)/i",chop($header),$matches);

					// look for :// in the Location header to see if hostname is included
					if(!preg_match("|\:\/\/|",$matches[2]))
					{
						// no host in the path, so prepend
						$host = isset($this->rawheaders['Host']) ? $this->rawheaders['Host'] : $this->host;
						$this->_redirectaddr = "https://".$host.":".$this->port;
						// eliminate double slash
						if(!preg_match("|^/|",$matches[2]))
							$this->_redirectaddr .= "/".$matches[2];
						else
							$this->_redirectaddr .= $matches[2];
					}
					else
						$this->_redirectaddr = $matches[2];
				}
				if(preg_match("|^HTTP/|",$header))
				{
					$this->response_code = $header;
					if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|",$this->response_code, $match))
						$this->status= $match[1];
				}
				if (preg_match("/Content-Encoding: gzip/", $header) )
					$is_gzipped = true;
				$this->headers[] = $header;
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
					$randName = getTempFilename('answer');
					file_put_contents($randName.".gz",$results);
					exec(escapeshellarg(getExternal('gzip'))." -d ".$randName.".gz",$results,$return);
					if(is_file($randName))
					{
						$results = file_get_contents($randName);
						unlink($randName);
					}
					else
					{
						unlink($randName.".gz");
					}
				}
			}

			$this->results = $results;
		}
		@unlink($headerfile);
		@unlink($contfile);
		return(!$return);
	}

	function setcookies()
	{
		foreach($this->headers as $header)
		{
			if(preg_match("/^set-cookie:[\s]+([^=]+)=([^;]+)/i", $header,$match))
			{
				if($match[2]=="deleted")
					unset($this->cookies[$match[1]]);
				else
					$this->cookies[$match[1]] = $match[2];
			}
		}
	}

	function _check_timeout($fp)
	{
		if ($this->read_timeout > 0) 
		{
			$fp_status = socket_get_status($fp);
			if ($fp_status["timed_out"]) 
			{
				$this->timed_out = true;
				return true;
			}
		}
		return false;
	}

	function connect()
	{
		$fp = false;
		if($this->_isproxy)
		{
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
			$fp = stream_socket_client("tcp://".$host.":".$port, $errno, $errstr, $this->_fp_timeout, STREAM_CLIENT_CONNECT, $context); 
		}
		else
			$fp = fsockopen($host,$port,$errno,$errstr,$this->_fp_timeout);
		if(!$fp)
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
		}
		return($fp);
	}
}
