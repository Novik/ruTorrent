<?php

require_once( dirname(__FILE__)."/../../php/cache.php");

class rAutoTools
{
	public $hash = "autotools.dat";
	public $enable_label = 0;
	public $label_template = "{DIR}";
	public $enable_move = 0;
	public $path_to_finished = "";
	public $fileop_type = "Move";
	public $enable_watch = 0;
	public $path_to_watch = "";
	public $watch_start = 0;
//	public $list = array();

	static public function load()
	{
		$cache = new rCache();
		$at = new rAutoTools();
		$cache->get( $at );
		return $at;
	}
	public function store()
	{
		$cache = new rCache();
		return $cache->set( $this );
	}
	public function set()
	{
		if( !isset( $HTTP_RAW_POST_DATA ) )
			$HTTP_RAW_POST_DATA = file_get_contents( "php://input" );
		if( isset( $HTTP_RAW_POST_DATA ) )
		{
			$vars = explode( '&', $HTTP_RAW_POST_DATA );
			$this->enable_label = 0;
			$this->label_template = "{DIR}";
			$this->enable_move = 0;
			$this->fileop_type = "Move";
			$this->path_to_finished = "";
			$this->enable_watch = 0;
			$this->path_to_watch = "";
			$this->watch_start = 0;
//			$this->list = array();
//			$sample = array();
			foreach( $vars as $var )
			{
				$parts = explode( "=", $var );
				if( $parts[0] == "enable_label" )
				{
					$this->enable_label = $parts[1];
				}
				else if( $parts[0] == "label_template" )
				{
					$this->label_template = $parts[1];
				}
				else if( $parts[0] == "enable_move" )
				{
					$this->enable_move = $parts[1];
				}
				else if( $parts[0] == "fileop_type" )
				{
					$this->fileop_type = $parts[1];
				}
				else if( $parts[0] == "path_to_finished" )
				{
					$this->path_to_finished = $parts[1];
				}
				else if( $parts[0] == "enable_watch" )
				{
					$this->enable_watch = $parts[1];
				}
				else if( $parts[0] == "path_to_watch" )
				{
					$this->path_to_watch = $parts[1];
				}
				else if( $parts[0] == "watch_start" )
				{
					$this->watch_start = $parts[1];
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
		$ret  = "theWebUI.autotools = { ";
		$ret .= "EnableLabel: ".$this->enable_label;
		$ret .= ", LabelTemplate: '".addslashes( $this->label_template )."'";
		$ret .= ", EnableMove: ".$this->enable_move;
		$ret .= ", FileOpType: '".$this->fileop_type."'";
		$ret .= ", PathToFinished: '".addslashes( $this->path_to_finished )."'";
		$ret .= ", EnableWatch: ".$this->enable_watch;
		$ret .= ", PathToWatch: '".addslashes( $this->path_to_watch )."'";
		$ret .= ", WatchStart: ".$this->watch_start;
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