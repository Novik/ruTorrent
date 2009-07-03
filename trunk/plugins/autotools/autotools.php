<?php
$rootPath = "./";
if( !is_file( 'util.php' ) ) $rootPath = "../../";
require_once( $rootPath."util.php" );

class rAutoTools
{
	public $hash = "autotools.dat";
	public $enable_label = 0;
	public $enable_move = 0;
	public $path_to_finished = "";
//	public $list = array();

	static public function load()
	{
		global $settings;
		global $rootPath;
		$cache = new rCache( $rootPath.$settings );
		$at = new rAutoTools();
		$cache->get( $at );
		return $at;
	}
	public function store()
	{
		global $settings;
		global $rootPath;
		$cache = new rCache( $rootPath.$settings );
		return $cache->set( $this );
	}
	public function set()
	{
		if( !isset( $HTTP_RAW_POST_DATA ) )
			$HTTP_RAW_POST_DATA = file_get_contents( "php://input" );
		if( isset( $HTTP_RAW_POST_DATA ) )
		{
			$vars = split( '&', $HTTP_RAW_POST_DATA );
			$this->enable_label = 0;
			$this->enable_move = 0;
			$this->path_to_finished = "";
//			$this->list = array();
//			$sample = array();
			foreach( $vars as $var )
			{
				$parts = split( "=", $var );
				if( $parts[0] == "enable_label" )
				{
					$this->enable_label = $parts[1];
				}
				else if( $parts[0] == "enable_move" )
				{
					$this->enable_move = $parts[1];
				}
				else if( $parts[0] == "path_to_finished" )
				{
					$this->path_to_finished = $parts[1];
				}
//				else if( $parts[0] == "sample" )
//				{
//					$value = trim( rawurldecode( $parts[1] ) );
//					if( strlen( $value ) > 0 )
//					{
//						$sample[] = $value;
//					}
//					else if( count( $sample) > 0 )
//					{
//						$this->list[] = $sample;
//						$sample = array();
//					}
//				}
			}
//			if( count( $sample ) > 0 )
//				$this->list[] = $sample;
		}
		$this->store();
	}
	public function get()
	{
		$ret  = "utWebUI.autotools = { ";
		$ret .= "EnableLabel: ".$this->enable_label;
		$ret .= ", EnableMove: ".$this->enable_move;
		$ret .= ", PathToFinished: '".addslashes( $this->path_to_finished )."'";
//		$ret .= ", sample: [";
//		for( $i = 0; $i < count( $this->list ); $i++ )
//		{
//			$grp = array_map( 'quoteAndDeslashEachItem', $this->list[$i] );
//			$cnt = count( $grp );
//			if( $cnt > 0 )
//			{
//				$ret .= "[";
//				$ret .= implode( ",", $grp );
//				$ret .= "],";
//			}
//		}
//		$len = strlen( $ret );
//		if( $ret[$len - 1] == ',' )
//			$ret = substr( $ret, 0, $len - 1 );
//		$ret .= "]";
		return $ret." };\n";
	}
}

?>
