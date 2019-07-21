<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__).'/../../php/settings.php' );

class rStat
{
	public $hourUp = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
	public $hourDown = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
	public $hourHitTimes = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
	public $monthUp = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
	public $monthDown = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
	public $monthHitTimes = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
	public $yearUp = array(0,0,0,0,0,0,0,0,0,0,0,0);
	public $yearDown = array(0,0,0,0,0,0,0,0,0,0,0,0);
	public $yearHitTimes = array(0,0,0,0,0,0,0,0,0,0,0,0);
	public $fname = "";

	public function __construct( $prefix )
	{
		$this->fname = getSettingsPath().'/trafic/'.$prefix;
		if($file=@fopen($this->fname,"r"))
		{
			$this->hourUp = fgetcsv($file);
			$this->hourDown = fgetcsv($file);
			$this->hourHitTimes = fgetcsv($file);
			$this->monthUp = fgetcsv($file);
			$this->monthDown = fgetcsv($file);
			$this->monthHitTimes = fgetcsv($file);
			$this->yearUp = fgetcsv($file);
			$this->yearDown = fgetcsv($file);
			$this->yearHitTimes = fgetcsv($file);
			fclose($file);
		}
	}
	public function flush()
	{
		global $profileMask;
		$randName = getTempFilename('trafic');
		if($file=@fopen($randName,"w"))
		{
			if( (fputcsv($file,$this->hourUp)!==false) &&
				(fputcsv($file,$this->hourDown)!==false) &&
				(fputcsv($file,$this->hourHitTimes)!==false) &&
				(fputcsv($file,$this->monthUp)!==false) &&
				(fputcsv($file,$this->monthDown)!==false) &&
				(fputcsv($file,$this->monthHitTimes)!==false) &&
				(fputcsv($file,$this->yearUp)!==false) &&
				(fputcsv($file,$this->yearDown)!==false) &&
				(fputcsv($file,$this->yearHitTimes)!==false))
			{
				if( fclose($file)!==false )
				{
					rename( $randName, $this->fname );
					@chmod($this->fname,$profileMask & 0666);
					return(true);
				}
			}
			fclose($file);
			unlink($randName);
		}
		return(false);
	}
	public function correct($deltaUp,$deltaDown)
	{
		$tm = getdate();
		$ndx = $tm["hours"];
		$was = getdate($this->hourHitTimes[$ndx]);
		if(($tm[0] - $this->hourHitTimes[$ndx]>3600+3600) ||	// +3600 - for daylight
			($ndx!=$was["hours"]))
		{
			$this->hourHitTimes[$ndx] = $tm[0];
			$this->hourUp[$ndx] = 0;
			$this->hourDown[$ndx] = 0;
		}
		$this->hourUp[$ndx]+=$deltaUp;
		$this->hourDown[$ndx]+=$deltaDown;
                $ndx = $tm["mday"]-1;
		$was = getdate($this->monthHitTimes[$ndx]);
		if(($tm[0] - $this->monthHitTimes[$ndx]>3600*24+3600) ||
			($ndx!=$was["mday"]-1))
		{
			$this->monthHitTimes[$ndx] = $tm[0];
			$this->monthUp[$ndx] = 0;
			$this->monthDown[$ndx] = 0;
		}
		$this->monthUp[$ndx]+=$deltaUp;
		$this->monthDown[$ndx]+=$deltaDown;
                $ndx = $tm["mon"]-1;
		$was = getdate($this->yearHitTimes[$ndx]);
                if(($tm[0] - $this->yearHitTimes[$ndx]>3600*24*31+3600) ||
			($ndx!=$was["mon"]-1))
		{
			$this->yearHitTimes[$ndx] = $tm[0];
			$this->yearUp[$ndx] = 0;
			$this->yearDown[$ndx] = 0;
		}
		$this->yearUp[$ndx]+=$deltaUp;
		$this->yearDown[$ndx]+=$deltaDown;
		rTorrentSettings::get()->pushEvent( "TraficUpdated", array( "stat"=>&$this ) );
	}
	static protected function format( $arrUp, $arrDown, $arrLabel, $mode )
	{
		return( '{ "up": ['.implode(",",$arrUp).'], "down": ['.implode(",",$arrDown).
			'], "labels": ['.implode(",",$arrLabel).'], "mode": "'.$mode.'", "trackers": [' );
	}
	static protected function getTrackers()
	{
		$files = array();
		$dir = getSettingsPath().'/trafic/trackers';
		$dh = @opendir($dir);
		if($dh)
		{
			while(false !== ($file = readdir($dh)))
			{
				$path = $dir.'/'.$file;
				if(($file!="..") && ($file!=".") && is_file($path))
				{
					$files[] = basename($path, ".csv");
				}
			}
			closedir($dh);
		}
		sort($files,SORT_STRING);
		return( $files );
	}
        public function getDay()
	{
		return(array
		(
			"up" 		=> $this->hourUp,
			"down" 		=> $this->hourDown,
			"labels" 	=> $this->hourHitTimes,
			"mode" 		=> 'day',
			"trackers" 	=> self::getTrackers()
		));

	}
        public function getMonth()
	{
		return(array
		(
			"up" 		=> $this->monthUp,
			"down" 		=> $this->monthDown,
			"labels"	=> $this->monthHitTimes,
			"mode"	 	=> 'month',
			"trackers"	=> self::getTrackers()
		));
	}
        public function getYear()
	{
		return(array
		(
			"up" 		=> $this->yearUp,
			"down"		=> $this->yearDown,
			"labels"	=> $this->yearHitTimes,
			"mode"		=> 'year',
			"trackers"	=> self::getTrackers()
		));
	}
	public function getRatios( $time )
	{
		$ret = array(0,0,0);
		for($i = 0; $i<count($this->hourHitTimes); $i++)
			if( $time-$this->hourHitTimes[$i] < 60*60*24 )
				$ret[0]+=$this->hourUp[$i];
		for($i = 0; $i<count($this->monthHitTimes); $i++)
		{
			if( $time-$this->monthHitTimes[$i] < 60*60*24*7 )
				$ret[1]+=$this->monthUp[$i];
			if( $time-$this->monthHitTimes[$i] < 60*60*24*30 )
				$ret[2]+=$this->monthUp[$i];
		}
		return($ret);
	}
}
