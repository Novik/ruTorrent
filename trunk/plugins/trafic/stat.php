<?php

require_once( '../../util.php' );

function quoteEach(&$item)
{
	$item = "'$item'"; 
}

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
	protected $fname = "";

	public function rStat( $prefix )
	{
		$this->fname = $prefix;
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
		if($file=@fopen($this->fname,"w"))
		{
			fputcsv($file,$this->hourUp);
			fputcsv($file,$this->hourDown);
			fputcsv($file,$this->hourHitTimes);
			fputcsv($file,$this->monthUp);
			fputcsv($file,$this->monthDown);
			fputcsv($file,$this->monthHitTimes);
			fputcsv($file,$this->yearUp);
			fputcsv($file,$this->yearDown);
			fputcsv($file,$this->yearHitTimes);
			fclose($file);
			@chmod($this->fname,0777);
		}
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
	}
	static protected function format( $arrUp, $arrDown, $arrLabel, $mode )
	{
		return( "{ up: [".implode(",",$arrUp)."], down: [".implode(",",$arrDown).
			"], labels: [".implode(",",$arrLabel)."], mode: '".$mode."', trackers: [" );
	}
	static protected function getTrackers()
	{
		$files = array();
		$dh = @opendir("stats/trackers");
		if($dh)
		{
			while(false !== ($file = readdir($dh)))
			{
				$path = realpath("stats/trackers/" . $file);
				if(($file!="..") && ($file!=".") && is_file($path))
				{
					$files[] = basename($path, ".csv");
				}
			}
		}
		sort($files,SORT_STRING);
		array_walk($files, 'quoteEach'); 
		return( implode(",",$files)."]}" );
	}
        public function getDay()
	{
		return(self::format($this->hourUp,$this->hourDown,$this->hourHitTimes,'day').self::getTrackers());
	}
        public function getMonth()
	{
		return(self::format($this->monthUp,$this->monthDown,$this->monthHitTimes,'month').self::getTrackers());
	}
        public function getYear()
	{
		return(self::format($this->yearUp,$this->yearDown,$this->yearHitTimes,'year').self::getTrackers());
	}
}

?>