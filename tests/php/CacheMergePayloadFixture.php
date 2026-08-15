<?php

// A cache record that reconciles concurrent writers the way rHistoryData does:
// it remembers what this process itself added and replays exactly that on top
// of whatever another process published in the meantime. Kept in its own file
// because CacheTest runs a second, genuinely separate PHP process that has to
// unserialize the very same class.
class CacheMergePayload
{
	public $hash = 'merge-cache-test.dat';
	public $modified = false;
	public $rows = array();

	// Deliberately left out of __sleep(), exactly like rHistoryData's own
	// bookkeeping: the stored format stays what it always was.
	protected $ownAdditions = array();

	public function __sleep()
	{
		return(array('hash', 'modified', 'rows'));
	}

	public function addRow($key)
	{
		$this->rows[$key] = $key;
		$this->ownAdditions[$key] = $key;
		return((new rCache())->set($this));
	}

	public function merge($instance, $arg)
	{
		$rows = (is_object($instance) && is_array($instance->rows)) ? $instance->rows : array();
		foreach($this->ownAdditions as $key => $value)
			$rows[$key] = $value;
		$this->rows = $rows;
		return(true);
	}
}
