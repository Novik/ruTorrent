<?php
require_once( 'util.php' );

class rLock
{
	protected $file;
	protected $lockTime;
	protected $locked = false;

	const MAX_LOCK_TIME = 1800;

	protected function __construct( $name, $maxLockTime )
	{
		$this->lockTime = $maxLockTime;
		$this->file = getSettingsPath().'/'.$name.".lock";
	}

	function __destruct()
	{
		$this->release();
	}

	public function lock()
	{
		clearstatcache();
		$this->locked = (!file_exists($this->file) || (time()-filemtime($this->file)>$this->lockTime)) && touch($this->file);
		return($this->locked);
	}

	public function release()
	{
		if($this->locked)
		{
			$this->locked = false;
			unlink($this->file);
		}
	}

	static public function obtain( $name = '', $maxLockTime = self::MAX_LOCK_TIME )
	{
		$lck = new rLock($name,$maxLockTime);
		if(!$lck->lock())
			$lck = false;
		return($lck);
	}
}
