<?php
require_once( 'util.php' );

class rCache
{
	protected $dir;
	// How a cache file looked when get() loaded it or set() stored a merge-capable
	// object: the stamp stampOf() read and a digest of the bytes, which
	// decides when the stamp repeats. Kept per loaded object, not per cache
	// key: two objects of one key in one process can hold different versions,
	// and a load through one must not stand for the other. spl_object_id()
	// is reused once an object is freed, so each entry also holds a
	// WeakReference that tells whether it still describes the same object.
	protected static $loadStamps = [];

	public function __construct( $name = '' )
	{
		$this->dir = FileUtil::getSettingsPath().$name;
		if(!is_dir($this->dir))
			FileUtil::makeDirectory($this->dir);
	}
	public static function flock( $fp )
	{
		$i = 0;
		while(!flock($fp, LOCK_EX | LOCK_NB))
		{
			usleep(round(rand(0, 100)*1000));
			if(++$i>20)
				return(false);
		}
		return(true);
	}
	// What get() or set() recorded for this very object; null when neither
	// loaded nor stored it, or when its class has no merge().
	protected static function loadedStamp( $rss )
	{
		$loaded = self::$loadStamps[spl_object_id($rss)] ?? null;
		return((!is_null($loaded) && ($loaded['object']->get() === $rss)) ? $loaded : null);
	}
	// A key names one file inside the cache directory: the shipped ones are a
	// '<name>.dat', an md5 or a short fixed word. It is concatenated into a
	// path, and it is not always internal -- plugins/rss/action.php takes one
	// from a request parameter -- so a key holding a separator would choose
	// which file is read and unserialized, or which file a store replaces.
	protected static function isValidKey( $key )
	{
		return(is_string($key) && (strlen($key)>0) &&
			(strpbrk($key,"/\\\0")===false) &&
			($key!=='.') && ($key!=='..'));
	}
	// unserialize() constructs whatever class the stored bytes name, and runs
	// that class's magic methods while doing it. So the classes a cache file
	// may name are the ones the caller stores: its own, plus any it declares
	// through a static cacheClasses(). A file naming another class is not
	// loaded at all -- see holdsRefusedClass() -- rather than half loaded.
	protected static function allowedClasses( $target )
	{
		if(!is_object($target))
			return(false);
		$class = get_class($target);
		$allowed = array($class);
		if(is_callable(array($class,'cacheClasses')))
			$allowed = array_merge($allowed,call_user_func(array($class,'cacheClasses')));
		return($allowed);
	}
	// A class the caller declares may be nested in its value, not stand in
	// its place: an object's file must hold that object's class at its root,
	// and an array's file an array. The serialized header is checked before
	// unserialize(), so a wrong root is not constructed on the way to being
	// refused. set() writes every cache file with serialize(), which writes
	// an object as O: unless its class implements Serializable without
	// __serialize() (that writes C:), and no class stored in a cache does.
	protected static function hasExpectedRoot( $bytes, $target )
	{
		if(is_array($target))
			return(strncmp($bytes,'a:',2)===0);
		if(!is_object($target))
			return(false);
		$class = get_class($target);
		$prefix = 'O:'.strlen($class).':"'.$class.'":';
		return(strncmp($bytes,$prefix,strlen($prefix))===0);
	}
	// What a refused file holds at its root, for the log line: one of a few
	// fixed words, or the class its header names -- only when that name is
	// the length the header claims, at most ROOT_NAME_MAX bytes and spelled
	// in ASCII class-name characters, so no other byte of the file is logged.
	const ROOT_NAME_MAX = 128;
	protected static function describeRoot( $bytes )
	{
		if($bytes === '')
			return('an empty file');
		if(strncmp($bytes,'a:',2)===0)
			return('an array');
		if(preg_match('/^[OC]:([0-9]{1,3}):"([A-Za-z_][A-Za-z0-9_\\\\]*)":/',
				substr($bytes,0,self::ROOT_NAME_MAX+9),$match) &&
			(strlen($match[2]) === intval($match[1])) &&
			(strlen($match[2]) <= self::ROOT_NAME_MAX))
			return('class '.$match[2]);
		if(preg_match('/^[OC]:/',$bytes))
			return('an object');
		if(preg_match('/^[bids]:/',$bytes))
			return('a scalar');
		if(strncmp($bytes,'N;',2)===0)
			return('null');
		return('no serialized value');
	}
	// What the walk below may spend. Its shape is chosen by whoever wrote the
	// cache file, so it cannot be allowed to cost whatever that shape asks
	// for. An object met twice is recognised and not walked again, but an
	// array is a value and has no identity to recognise: an array containing
	// itself -- fifteen bytes of "a:1:{i:0;R:1;}" -- is walked until the
	// process dies, and a file may also nest deeper than the stack goes or
	// hold one sub-value at so many places through R: back-references that a
	// few hundred bytes describe more nodes than can ever be visited.
	//
	// So the walk is allowed one node per byte of the file the value came
	// from, and never fewer than WALK_MIN_NODES. A file pays its own length
	// for what it asks to have walked: the shortest a node can be written is
	// two bytes, so no honest file ever reaches its own allowance, while a
	// file that names one sub-value at a million places buys only the nodes
	// its R: back-references are written in. What a walk costs is therefore
	// bounded by what the file costs to read, rather than by the graph the
	// file describes.
	//
	// A file that exceeds either budget is refused, which is the same answer
	// as a refused class: a miss. Note that a miss is not always rebuilt --
	// rCookies::load() and rRetrackers::load() return their defaults and
	// leave the file alone -- so a refused file stays on disk and is walked
	// again on the next read. That is what keeps the allowance small.
	const WALK_MAX_DEPTH = 64;
	const WALK_MIN_NODES = 100000;

	// Whether unserialize() met a class it was not allowed to construct, at
	// any depth. Such a class comes back as __PHP_Incomplete_Class, which
	// would fail later and further away if it were handed to the caller.
	// The refusal names the class: without it the reader has to guess which
	// of the stored object's properties the file is being refused for.
	// True as well when the value costs more than the budgets above, because
	// what could not be walked has not been shown to be free of one. $bytes
	// is the length of the file the value was unserialized from, and $reason
	// comes back saying which of the three refused it.
	protected static function holdsRefusedClass( $value, $bytes = 0, &$reason = null )
	{
		$budget = max(self::WALK_MIN_NODES,$bytes);
		$reason = null;
		return(self::walkForRefusedClass($value,new SplObjectStorage(),0,$budget,$reason));
	}
	private static function walkForRefusedClass( $value, $seen, $depth, &$budget, &$reason )
	{
		if(--$budget < 0)
		{
			$reason = 'describes more than a cache file of its size may describe';
			return(true);
		}
		if($depth > self::WALK_MAX_DEPTH)
		{
			$reason = 'nests deeper than a cache file may nest';
			return(true);
		}
		if(is_object($value))
		{
			if($value instanceof __PHP_Incomplete_Class)
			{
				// The original name is kept in a property of the stand-in;
				// reading it through the array cast avoids the notice that
				// touching an incomplete class's property raises.
				$name = ((array)$value)['__PHP_Incomplete_Class_Name'] ?? '';
				$reason = is_string($name) && ($name !== '')
					? ('names the class '.$name.', which it may not hold')
					: 'names a class it may not hold';
				return(true);
			}
			// Not contains()/attach(): PHP 8.5 deprecates them, and
			// offsetExists()/offsetSet() do the same since PHP 5.3.
			if($seen->offsetExists($value))
				return(false);
			$seen->offsetSet($value);
			$value = (array)$value;
		}
		if(is_array($value))
			foreach($value as $item)
				if(self::walkForRefusedClass($item,$seen,$depth+1,$budget,$reason))
					return(true);
		return(false);
	}
	// A cache file's identity, used to tell whether it is still the one an
	// object was loaded from. filemtime resolves only to the second, so two
	// writes inside one second look identical -- and that is the common case
	// here: replacing a torrent fires three writers within the same second.
	// set() always publishes through rename(), which gives the file a fresh
	// inode, so the inode is what separates them -- until it repeats, which
	// hasChangedSinceLoad() is for.
	protected static function stampOf( $name )
	{
		clearstatcache(true, $name);
		$st = @stat($name);
		return($st===false ? null : $st['ino'].':'.$st['mtime'].':'.$st['size']);
	}
	// True when the file on disk is no longer the one the object was loaded
	// from, false when it still is, null when that cannot be told.
	//
	// A repeated stamp does not settle it. rename() frees the inode the
	// object was loaded from, and the next writer's temporary file is handed
	// that number again (12 of 12 times on an idle ext4); with a same-second
	// mtime and a change of the same length, two stores by other writers
	// leave the loaded stamp on a file holding neither its bytes, and a writer
	// trusting the stamp published its stale copy over both. So a repeated
	// stamp is followed by comparing a digest of the bytes, under the key
	// lock the caller holds, which is what makes the comparison and the
	// store one decision. When the bytes cannot be read there is nothing to
	// compare, and the caller must not publish over a version it cannot
	// see: that is the null.
	protected static function hasChangedSinceLoad( $name, $loaded )
	{
		if(is_null($loaded) || is_null($loaded['stamp']))
		{
			// The object holds nothing it can compare with: it was never
			// loaded, or the file was absent or unreadable when get() ran.
			// Any file present now may hold what the object has not seen,
			// and merging what is on disk is always safe for the
			// merge-capable classes that are the only callers of this check.
			return(is_file($name));
		}
		if($loaded['stamp'] !== self::stampOf($name))
			return(true);
		$digest = @hash_file('sha256',$name);
		if($digest === false)
			return(null);
		return($digest !== $loaded['digest']);
	}
	public function set( $rss, $arg = null )
	{
		global $profileMask;
		$name = $this->getName($rss);
		if(is_null($name))
			return(false);
		$lockName = $name.'.lock';
		// One writer per cache key. The changed-since-load check, the merge
		// and the publishing rename must form a single critical section: two
		// writers that both check before either publishes both conclude
		// nothing changed, both skip merging, and the later rename erases the
		// earlier writer's data. The lock is a sidecar file because the cache
		// file itself is replaced by rename() on every store.
		$lock = fopen( $lockName, "c" );
		if($lock===false)
			return(false);
		@chmod($lockName,$profileMask & 0666);
		if(!self::flock( $lock ))
		{
			fclose($lock);
			return(false);
		}

		$loaded = is_object($rss) ? self::loadedStamp($rss) : null;
		$changed = (is_object($rss) && method_exists($rss,"merge"))
			? self::hasChangedSinceLoad($name, $loaded) : false;
		if(is_null($changed))
		{
			flock( $lock, LOCK_UN );
			fclose( $lock );
			FileUtil::toLog('rCache: '.basename($name).' cannot be read to compare with the loaded version; not stored.');
			return(false);
		}
		if($changed)
		{
			$className = get_class($rss);
			$newInstance = new $className();
			if($this->get($newInstance) &&
				!$rss->merge($newInstance, $arg))
			{
				flock( $lock, LOCK_UN );
				fclose( $lock );
				return(false);
			}
		}
		// Use a per-process temporary file and publish it atomically under the key lock.
		$tmpName = $name.'.'.getmypid().'.'.uniqid('', true).'.tmp';
		$fp = fopen( $tmpName, "wb" );
		if($fp!==false)
		{
			$str = serialize( $rss );
			if((fwrite( $fp, $str ) == strlen($str)) && fflush( $fp ))
			{
				if(fclose( $fp ) !== false)
				{
					@chmod($tmpName,$profileMask & 0666);
					if(@rename( $tmpName, $name ))
					{
						@chmod($name,$profileMask & 0666);
						// The next store must not merge this object's own published version.
						if(is_object($rss) && method_exists($rss,"merge"))
							self::$loadStamps[spl_object_id($rss)] = array(
								'object' => WeakReference::create($rss),
								'stamp' => self::stampOf($name), 'digest' => hash('sha256',$str));
						flock( $lock, LOCK_UN );
						fclose( $lock );
						return(true);
					}
				}
				else
					@unlink( $tmpName );
			}
			else
				fclose( $fp );
		}
		@unlink( $tmpName );
		flock( $lock, LOCK_UN );
		fclose( $lock );
	        return(false);
	}
	public function get( &$rss )
	{
		$fname = $this->getName($rss);
		if(is_null($fname))
			return(false);
		// Stamp before reading. If a concurrent rename lands between the stat
		// and the read, this process holds new content under an old stamp and
		// its set() merely performs one redundant merge. Stamping after the
		// read inverts that: a rename between read and stat pairs old content
		// with the new identity, and that concurrent write is never merged.
		$stamp = self::stampOf($fname);
		$ret = @file_get_contents($fname);
		if($ret!==false)
		{
			if(!self::hasExpectedRoot($ret,$rss))
			{
				FileUtil::toLog('rCache: '.basename($fname).' does not hold '.
					(is_object($rss) ? get_class($rss) : 'an array').' at its root (found '.
					self::describeRoot($ret).'); not loaded.');
				return(false);
			}
			$tmp = @unserialize($ret,array('allowed_classes'=>self::allowedClasses($rss)));
			if(self::holdsRefusedClass($tmp,strlen($ret),$reason))
			{
				FileUtil::toLog('rCache: '.basename($fname).' '.$reason.'; not loaded.');
				return(false);
			}
			if(is_array($tmp))
			{
				$rss = $tmp;
				$ret = true;
			}
			else
			{
				if(($tmp!==false) &&
					(!isset($rss->version) ||
					(isset($rss->version) && !isset($tmp->version)) ||
					(isset($tmp->version) && ($tmp->version==$rss->version))))
				{
					$rss = $tmp;
					// Only a merge-capable class ever compares what it
					// loaded, so only it is remembered and pays for
					// hashing the file.
					if(method_exists($rss,"merge"))
						self::$loadStamps[spl_object_id($rss)] = array(
							'object' => WeakReference::create($rss),
							'stamp' => $stamp, 'digest' => hash('sha256',$ret));
					$ret = true;
				}
				else
					$ret = false;
			}
		}
		return($ret);
	}
	public function remove( $rss )
	{
		global $profileMask;
		$name = $this->getName($rss);
		if(is_null($name))
			return(false);
		$lockName = $name.'.lock';
		// Writers may have opened this sidecar before remove() acquired it.
		// Keep its inode so those writers and later ones still share one lock.
		$lock = fopen( $lockName, "c" );
		if($lock!==false)
		{
			@chmod($lockName,$profileMask & 0666);
			if(self::flock( $lock ))
			{
				$ret = @unlink($name);
				flock( $lock, LOCK_UN );
				fclose( $lock );
				return($ret);
			}
			fclose($lock);
		}
		// Deletion needs the writer lock too; a failed lock is not permission
		// to unlink an entry that another process may be publishing.
		return(false);
	}
	// Null when the key names anything other than a file in this directory.
	// Every caller treats that as a miss rather than reaching for the path.
	protected function getName($rss)
	{
		$key = is_object($rss) ? $rss->hash : (isset($rss['__hash__']) ? $rss['__hash__'] : null);
		if(!self::isValidKey($key))
			return(null);
		return($this->dir."/".$key);
	}
	public function getModified( $obj = null )
	{
		if(is_null($obj))
			return(@filemtime($this->dir));
		$name = is_object($obj) ? $this->getName($obj) :
			(self::isValidKey($obj) ? $this->dir."/".$obj : null);
		return(is_null($name) ? false : @filemtime($name));

	}
}
