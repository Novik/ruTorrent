<?php
/**
 *	Copyright 2008 Adrien Gibrat - Class for create and parse Torrent files
 *			 (http://www.phpclasses.org/browse/package/4896.html)
 *	Copyright 2009, 2010 Novik
 *
 * LICENSE: This source file is subject to version 3 of the GNU GPL
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/licenses/gpl.html.
 */

require_once( 'util.php' );

class Torrent
{
	protected $errors = array();
	protected $basedir = null;	
	protected $pointer = 0;
	private $data;
	protected $log_callback = null;
	protected $err_callback = null;
	protected $filename = null;

	/** Read and decode torrent file/data OR build a torrent from source folder/file(s)
	 * Supported signatures:
	 *  - Torrent( string $torrent );
	 *  - Torrent( string $torrent, string $announce );
	 *  - Torrent( string $torrent, array  $meta );
	 *  - Torrent( string $file_or_folder );
	 *  - Torrent( string $file_or_folder, string $announce_url, [int $piece_length] );
	 *  - Torrent( string $file_or_folder, array $meta, [int $piece_length] );
	 *  - Torrent( array $files_list );
	 *  - Torrent( array $files_list, string $announce_url, [int $piece_length] );
	 *  - Torrent( array $files_list, array $meta, [int $piece_length] );
	 * @param string|array torrent to read or source folder/file(s)
	 * @param string|array announce url or meta informations (optional)
	 * @param int piece length (optional)
	 */
	public function __construct( $data, $meta = array(), $piece_length = 256, $log_callback = null, $err_callback = null ) 
	{
        	try {
		if( is_string( $meta ) )
			$meta =  array( 'announce' => $meta );
		$this->log_callback = $log_callback;
		$this->err_callback = $err_callback;
		if( $this->build( $data, $piece_length * 1024 ) )
			$this->touch();
		else
		{
			$arr = $this->decode( $data );
			if(!is_array($arr))
				$this->notify_err('Bad torrent data');
			else
				$meta = array_merge( $meta, $arr );
		}
		foreach( $meta as $key => $value )
			if($key[0]!="\x00")
	                	$this->{$key} = $value;
	        } catch(Exception $e)
        	{
        		$this->errors[] = $e;
		}
	}

	protected function notify_log( $msg )
	{
		if(is_callable($this->log_callback))
		{
			call_user_func($this->log_callback,$msg);
		}
	}

	protected function notify_err( $msg )
	{
		if(is_callable($this->err_callback))
		{
			call_user_func($this->err_callback,$msg);
		}
		$this->errors[] = new Exception($msg);
	}

	/** Convert the current Torrent instance in torrent format
	 * @return string encoded torrent data
	 */
	public function __toString() 
	{
        	return $this->encode( $this );
	}

	/** Return Errors
	 * @return array|boolean error list or null if none
	 */
	public function errors() 
	{
		return(empty( $this->errors ) ? false : $this->errors);
	}

	public function getFileName() 
	{
		return($this->filename);
	}

	/**** Encode BitTorrent ****/

	/** Encode torrent data
	 * @param mixed data to encode
	 * @return string torrent encoded data
	 */
	static protected function encode( $mixed ) 
	{
		switch ( gettype( $mixed ) )
		{	
			case 'integer':
			case 'double':
				return self::encode_integer( $mixed );
			case 'object':
				$mixed = (array) $mixed;
			case 'array':
				return self::encode_array( $mixed );
			default:
				return self::encode_string( (string) $mixed );
		}
	}

	/** Encode torrent string
	 * @param string string to encode
	 * @return string encoded string
	 */
	static private function encode_string( $string ) 
	{
        	return(strlen( $string ) . ':' . $string);
	}

	/** Encode torrent integer
	 * @param integer integer to encode
	 * @return string encoded integer
	 */
	static private function encode_integer( $integer ) 
	{
        	return('i' . $integer . 'e');
	}

	/** Encode torrent dictionary or list
	 * @param array array to encode
	 * @return string encoded dictionary or list
	 */
	static private function encode_array( $array ) 
	{
        	if( self::is_list( (array) $array ) )
        	{
			$return = 'l';
			foreach( $array as $value )
				$return .= self::encode( $value );
		}
		else
		{
			ksort( $array, SORT_STRING );
			$return = 'd';
			foreach( $array as $key => $value )
			{
				$val = 	strval( $key );
				if($val[0]=="\x00")
					continue;
				$return .= self::encode( $val ) . self::encode( $value );
            		}
        	}
		return $return . 'e';
	}

	/** Helper to test if an array is a list
	 * @param array array to test
	 * @return boolean is the array a list
	 */
	static protected function is_list ( $array ) 
	{
        	foreach( array_keys( $array ) as $key )
			if( !is_int( $key ) )
				return false;
		return true;
	}

	/**** Decode BitTorrent ****/

	public function decode( $string ) 
	{
		if(is_file( $string ))
		{
			$this->data = file_get_contents( $string );
			$this->filename = $string;
		}
		else
			$this->data = $string;
		$this->pointer = 0;
		return($this->decode_data());
	}

	protected function decode_data() 
	{
        	if($this->pointer>=strlen($this->data))
        		throw new Exception('Bad torrent data1 '.$this->pointer);
	        switch( $this->data[$this->pointer] )
        	{
        		case 'i':
				return($this->decode_integer());
			case 'l':
				return($this->decode_list());
			case 'd':
				return($this->decode_dictionary());
		        default:
				return $this->decode_string();
        	}
	}

	private function decode_dictionary() 
	{
		$dictionary = array();
		$this->pointer++;
        	while( !$this->isOfType('e') )
		{
			$key = $this->decode_string();
			$dictionary[$key] = $this->decode_data();
	        }
        	$this->pointer++;
	        return($dictionary);
	}

	private function decode_list() 
	{
		$list = array();
		$this->pointer++;
		while( !$this->isOfType('e') )
			$list[] = $this->decode_data();
	        $this->pointer++;
		return($list);
	}

	private function decode_string() 
	{
		$delim_pos = strpos($this->data, ':', $this->pointer);
	        if($delim_pos===false)
		       	throw new Exception('Bad torrent data4');
		$elem_len = intval(substr($this->data, $this->pointer, $delim_pos - $this->pointer));
		$this->pointer = $delim_pos + 1;
	        if($this->pointer>=strlen($this->data))
		       	throw new Exception('Bad torrent data2 '.$delim_pos);
		$elem_name = substr($this->data, $this->pointer, $elem_len);
		$this->pointer += $elem_len;
		return($elem_name);
	}

	private function decode_integer() 
	{
		$this->pointer++;
	        $delim_pos = strpos($this->data, 'e', $this->pointer);
        	if($delim_pos===false)
	       		throw new Exception('Bad torrent data4');
		$integer = substr($this->data, $this->pointer, $delim_pos - $this->pointer);
		if(($integer === '-0') || ((substr($integer, 0, 1) == '0') && (strlen($integer) > 1)))
			$this->notify_err('Bad integer');
//		$integer = abs(floatval($integer));
		$integer = floatval($integer);
		$this->pointer = $delim_pos + 1;
		return($integer);
	}

	private function isOfType($type)
	{
        	if($this->pointer>=strlen($this->data))
        		throw new Exception('Bad torrent data3 '.$this->pointer);
		return($this->data[$this->pointer] == $type);
	}

	/**** Make BitTorrent ****/

	/** Getter and setter of torrent annouce url
	 * @param null|string annouce url (optional, if omitted it's a getter)
	 * @return string|null annouce url or null if not set
	 */
    	public function announce( $announce = null ) 
	{
        	return(is_null( $announce ) ?
			isset( $this->announce ) ? $this->announce : null :
			$this->touch( $this->announce = (string) $announce ));
    	}

	public function clear_announce()
    	{
    		unset($this->announce);
    		$this->touch();
    	}

	/** Getter and setter of torrent annouce list
     	 * @param null|array annouce list (optional, if omitted it's a getter)
     	 * @return array|null annouce list or null if not set
	 */

    	public function announce_list( $announce_list = null ) 
    	{
        	return(is_null( $announce_list ) ?
			isset( $this->{'announce-list'} ) ? $this->{'announce-list'} : null :
			$this->touch( $this->{'announce-list'} = (array) $announce_list ));
    	}

	public function clear_announce_list()
	{
		unset($this->{'announce-list'});
		$this->touch();
	}

	/** Getter and setter of torrent comment
	 * @param null|string comment (optional, if omitted it's a getter)
	 * @return string|null comment or null if not set
	 */
    	public function comment( $comment = null ) 
    	{
        	return(is_null( $comment ) ?
            		isset( $this->comment ) ? $this->comment : null :
            		$this->touch( $this->comment = (string) $comment ));
	}

	public function clear_comment()
	{
    		unset($this->comment);
    		$this->touch();
	}

	/** Getter and setter of torrent name
	 * @param null|string name (optional, if omitted it's a getter)
	 * @return string|null name or null if not set
	 */
	public function name( $name = null ) 
    	{
        	return(is_null( $name ) ?
			isset( $this->info['name'] ) ? $this->info['name'] : null :
			$this->touch( $this->info['name'] = (string) $name ));
    }

    /** Getter and setter of the source
     * @param null|string source (optional, if omitted it's a getter)
     * @return string|null source or null if not set
     */
    public function source( $source = null )
    {
        if (is_null($source)) {
            return isset($this->info["source"]) ? $this->info["source"] : null;
        } else {
            $this->touch($this->info['source'] = (string)$source);
        }
    }

	/** Getter and setter of private flag
	 * @param null|boolean is private or not (optional, if omitted it's a getter)
	 * @return boolean private flag
	 */
	public function is_private( $private = null ) 
	{
        	return(is_null( $private ) ?
			!empty( $this->info['private'] ) :
			$this->touch( $this->info['private'] = $private ? 1 : 0 ));
	}

	/** Getter and setter of webseed(s) url list ( GetRight implementation )
	 * @param null|string|array webseed or webseeds mirror list (optional, if omitted it's a getter)
	 * @return string|array|null webseed(s) or null if not set
	 */
	public function url_list( $urls = null ) 
	{
        	return(is_null( $urls ) ?
			isset( $this->{'url-list'} ) ? $this->{'url-list'} : null :
			$this->touch( $this->{'url-list'} = $urls ));
	}

	/** Getter and setter of httpseed(s) url list ( Bittornado implementation )
	 * @param null|string|array httpseed or httpseeds mirror list (optional, if omitted it's a getter)
	 * @return array|null httpseed(s) or null if not set
	 */
	public function httpseeds( $urls = null ) 
	{
        	return(is_null( $urls ) ?
			isset( $this->httpseeds ) ? $this->httpseeds : null :
			$this->touch( $this->httpseeds = (array)  $urls ));
	}

	/** Save torrent file to disk
	 * @param null|string name of the file (optional)
	 * @return boolean file has been saved or not
	 */
	public function save( $filename = null ) 
	{
	        $this->filename = is_null( $filename ) ? $this->info['name'] . '.torrent' : $filename;
        	return file_put_contents( $this->filename, $this->__toString() );
	}

	/** Send torrent file to client
	 * @param null|string name of the file (optional)
	 * @return void script exit
	 */
	public function send( $filename = null ) 
	{
	        if(is_null( $filename ))
			$filename = $this->info['name'].'.torrent';
		if(isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'],'MSIE'))
			$filename = rawurlencode($filename);
        	header( 'Content-Disposition: attachment; filename="'.$filename.'"' );
        	cachedEcho( $this->__toString(), 'application/x-bittorrent', true );
    	}

	/** Build torrent info
     	 * @param string|array source folder/file(s) path
     	 * @param integer piece length
	 * @return array|boolean torrent info or false if data isn't folder/file(s)
	 */
	protected function build( $data, $piece_length ) 
	{
        	if( is_null( $data ) )
            		return(false);
		if( is_array( $data ) && self::is_list( $data ) )
		{
			$this->info = $this->files( $data, $piece_length );
	        	return(true);
		}
		if( is_dir( $data ) )
		{
			$this->info = $this->folder( $data, $piece_length );
			return(true);
		}
        	if( is_file( $data ) && (pathinfo( $data, PATHINFO_EXTENSION ) != 'torrent') )
		{
			$this->info = $this->file( $data, $piece_length );
			return(true);
		}
		return false;
	}

	/** Build torrent info from single file
	 * @param string file path
	 * @param integer piece length
	 * @return array torrent info
	 */
	private function file( $file, $piece_length ) 
	{
	        $this->notify_log( 'Hash '.$file );
        	if(!($handle = @fopen( $file, 'r' )))
        	{
			$this->notify_err('Failed to open file: "' . $file . '"');
			return(false);
        	}
		$pieces = '';
		while( $piece = fread( $handle, $piece_length ) )
			$pieces .= self::pack( $piece );
		fclose( $handle );
		return(array(
            		'length'        => filesize( $file ),
            		'name'          => basename( $file ),
            		'piece length'  => $piece_length,
            		'pieces'        => $pieces
        		));
    	}
    	
    	private static function sortNames($a,$b)
    	{
		$ret = substr_count($b,DIRECTORY_SEPARATOR)-substr_count($a,DIRECTORY_SEPARATOR);
		return( ($ret==0) ? strcoll($a, $b) : $ret );
    	}

	/** Build torrent info from files
	 * @param array file list
	 * @param integer piece length
	 * @return array torrent info
	 */
	private function files( $files, $piece_length ) 
	{
		if(!$this->basedir)
        		$files  = array_map( 'realpath', $files );

		usort( $files, array($this,'sortNames') );

		if($this->basedir)
			$path   = explode( DIRECTORY_SEPARATOR, $this->basedir );
		else	
		        $path   = explode( DIRECTORY_SEPARATOR, dirname( realpath( current( $files ) ) ) );

	        $length = $piece_length;
        	$piece  = $pieces = '';
	        $info_files = array();
		foreach( $files as $i => $file )
		{
			$this->notify_log( 'Hash '.$file );
			if( $path != array_intersect_assoc( $file_path = explode( DIRECTORY_SEPARATOR, $file ), $path ) ) 
			{
				$this->notify_err('Files must be in the same folder: "' . $file . '" discarded');
		                continue;
        		}
			if( !($handle = @fopen( $file, 'r' )) )
			{
				$this->notify_err('Failed to open file: "' . $file . '" discarded');
	                	continue;
        	    	}
			while(!feof( $handle ))
			{
				if( ( $length = strlen( $piece .= fread( $handle, $length ) ) ) == $piece_length )
                    			$pieces .= self::pack( $piece );
				else
					$length = $piece_length - $length;
			}
			fclose( $handle );
			$info_files[$i] = array(
				'length'    => filesize( $file ),
				'path'      => array_diff_assoc( $file_path, $path )
				);
		}

	        switch( count( $info_files ) )
	        {
			case 0:
				return false;
			default:
				return(array(
					'files'         => $info_files,
					'name'          => end( $path ),
					'piece length'  => $piece_length,
					'pieces'        => $pieces . ( $piece ? self::pack( $piece ) : '' )
					));
        	}
    	}

	/** Build torrent info from folder content
	 * @param string folder path
	 * @param integer piece length
	 * @return array torrent info
	 */
	private function folder( $dir, $piece_length )
	{
		$this->basedir = $dir;
		$len = strlen($this->basedir);
		if(($len>1) && ($this->basedir[$len-1]=='/'))
			$this->basedir = substr($this->basedir,0,-1);
		return($this->files( self::scandir( $this->basedir ), $piece_length ));
    	}

	/** Set torrent creator and creation date
	 * @return void
	 */
	protected function touch()
	{
        	$this->{'created by'}       = 'ruTorrent (PHP Class - Adrien Gibrat)';
	        $this->{'creation date'}    = time();
    	}

	/** Helper scan directories files and sub directories recursivly
	 * @param string directory path
	 * @return array directory content list
	 */
	public function scandir( $dir )
	{
	        $this->notify_log('Scan directory '.$dir);
		$paths = array();
	        $files = @scandir( $dir );
        	if($files!==false)
	        	foreach( $files as $item  )
	        	        if( $item != '.' && $item != '..')
				{
					$path = $dir . DIRECTORY_SEPARATOR . $item;
					if(is_link($path) && (strpos(realpath( $path ),$this->basedir.'/')===0))
						continue;
					if(is_dir( $path ))
						$paths = array_merge( self::scandir( $path ), $paths );
					else
						if(is_file($path))
							$paths[] = $path;
				}
        	return $paths;
	}

	/** Helper to pack data hash
	 * @param string data
	 * @return string packed data hash
	 */
	static protected function pack( & $data )
	{
		return(sha1( $data, true ) . ( $data = '' ));
    	}

	/**** Analyze BitTorrent ****/

	/** Get piece length
	 * @return integer piece length or null if not set
	 */
	public function piece_length()
	{
        	return(isset( $this->info['piece length'] ) ? $this->info['piece length'] : null );
	}

	/** Compute hash info
	 * @return string hash info or null if info not set
	 */
	public function hash_info()
	{
        	return(isset( $this->info ) ? strtoupper(sha1( self::encode( $this->info ) )) : null);
	}
}
