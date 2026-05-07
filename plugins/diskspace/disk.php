<?php

require_once( dirname(__FILE__)."/../../php/cache.php" );

class rDiskSpace
{
	public $hash = "diskspace.dat";
	public $modified = false;

	public $disks = array();

	static public function load( $paths )
	{
		$obj = new rDiskSpace();
		$cache = new rCache();

		if( !$cache->get($obj) )
		{
			$obj->initialize($paths);
			$obj->store();
		}
		else
		{
			$obj->sync($paths);
		}

		return $obj;
	}

	public function initialize( $paths )
	{
		$this->disks = array();

		foreach( $paths as $path )
		{
			$this->disks[] = array(
				'path' => $path,
				'total' => 0,
				'free' => 0,
				'updated' => 0
			);
		}
	}

	public function sync( $paths )
	{
		$current = array();

		foreach( $this->disks as $disk )
			$current[$disk['path']] = $disk;

		$newDisks = array();

		foreach( $paths as $path )
		{
			if( isset($current[$path]) )
				$newDisks[] = $current[$path];
			else
			{
				$newDisks[] = array(
					'path' => $path,
					'total' => 0,
					'free' => 0,
					'updated' => 0
				);
			}
		}

		$this->disks = $newDisks;
		$this->store();
	}

	public function store()
	{
		$cache = new rCache();
		return $cache->set($this);
	}

	public function getOldestIndex()
	{
		$oldestIndex = 0;
		$oldestTime = PHP_INT_MAX;

		foreach( $this->disks as $index => $disk )
		{
			if( $disk['updated'] < $oldestTime )
			{
				$oldestTime = $disk['updated'];
				$oldestIndex = $index;
			}
		}

		return $oldestIndex;
	}

	public function updateOldest()
	{
		if( empty($this->disks) )
			return null;

		$index = $this->getOldestIndex();

		$path = $this->disks[$index]['path'];

		if( is_dir($path) )
		{
			$this->disks[$index]['total'] = intval(@disk_total_space($path));
			$this->disks[$index]['free'] = intval(@disk_free_space($path));
		}
		else
		{
			$this->disks[$index]['total'] = 0;
			$this->disks[$index]['free'] = 0;
		}

		$this->disks[$index]['updated'] = time();

		$this->store();

		return $this->disks[$index];
	}

	public function getNewest()
	{
		if( empty($this->disks) )
			return null;

		$newest = null;
		$newestTime = 0;

		foreach( $this->disks as $disk )
		{
			if( $disk['updated'] >= $newestTime )
			{
				$newestTime = $disk['updated'];
				$newest = $disk;
			}
		}

		return $newest;
	}

	public function getAll()
	{
		return $this->disks;
	}
}
