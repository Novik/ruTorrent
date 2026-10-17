<?php

require_once(__DIR__ . '/../../php/TestCase.php');

// Stub the dependencies the production callers (httprpc/action.php,
// plugins/erasedata/action.php) load before invoking the helper. The RPC layer
// is scripted per command so the helper's own logic is what gets exercised.
if(!class_exists('FileUtil'))
{
	class FileUtil
	{
		public static $settingsPath = null;
		public static $log = array();
		public static function getSettingsPath() { return self::$settingsPath; }
		public static function makeDirectory($dir) { return @mkdir($dir, 0777, true); }
		public static function toLog($msg) { self::$log[] = $msg; }
	}
}
if(!class_exists('rXMLRPCCommand'))
{
	class rXMLRPCCommand
	{
		public $command;
		public $params;
		public function __construct($command, $params = null)
		{
			$this->command = $command;
			$this->params = $params;
		}
	}
}
if(!class_exists('rXMLRPCRequest'))
{
	class rXMLRPCRequest
	{
		public static $responses = array();	// first command name => array(ok, val)
		public static $requested = array();	// first command name of each request, in order
		public static $erased = array();	// hashes passed to d.erase
		public static $unconfirmed = array();	// hashes whose erase request gets no confirmation

		public $val = array();
		private $commands = array();

		public function __construct($commands = null)
		{
			if(is_array($commands))
				$this->commands = $commands;
			else if(!is_null($commands))
				$this->commands = array($commands);
		}
		public function addCommand($command)
		{
			$this->commands[] = $command;
		}
		public function success($trusted = true)
		{
			if(!count($this->commands))
				return(false);
			$first = $this->commands[0]->command;
			self::$requested[] = $first;
			foreach($this->commands as $c)
				if($c->command == "d.erase")
				{
					self::$erased[] = $c->params;
					// The request went out; the reply was lost or a fault.
					if(in_array($c->params, self::$unconfirmed, true))
						return(false);
				}
			if(!array_key_exists($first, self::$responses))
				return(false);
			$this->val = self::$responses[$first]["val"];
			return(self::$responses[$first]["ok"]);
		}
	}
}
if(!function_exists('getCmd'))
{
	function getCmd($cmd) { return($cmd); }
}

require_once(__DIR__ . '/../../../plugins/erasedata/removewithdata.php');

class RemoveWithDataTest extends TestCase
{
	private $dir;

	public function setUp()
	{
		$this->dir = sys_get_temp_dir().'/erasedata-test-'.getmypid();
		@mkdir($this->dir, 0777, true);
		FileUtil::$settingsPath = $this->dir;
	}

	// setUp() runs once per class, so each test starts from a clean slate here.
	private function reset()
	{
		foreach(glob($this->dir.'/erasedata/*') as $f)
			if(is_file($f))
				@unlink($f);
		FileUtil::$log = array();
		rXMLRPCRequest::$responses = array();
		rXMLRPCRequest::$requested = array();
		rXMLRPCRequest::$erased = array();
		rXMLRPCRequest::$unconfirmed = array();
	}

	public function tearDown()
	{
		foreach(glob($this->dir.'/erasedata/*') as $f)
			@unlink($f);
		@rmdir($this->dir.'/erasedata');
		@rmdir($this->dir);
	}

	// -- helpers ------------------------------------------------------------

	private function frozen($ok, $val) { rXMLRPCRequest::$responses["d.get_base_path"] = array("ok"=>$ok, "val"=>$val); }
	private function stored($ok, $val) { rXMLRPCRequest::$responses["d.get_directory"] = array("ok"=>$ok, "val"=>$val); }
	private function eraseOk() { rXMLRPCRequest::$responses["d.set_custom5"] = array("ok"=>true, "val"=>array("","","")); }

	private function listFor($hash)
	{
		$f = $this->dir.'/erasedata/'.$hash.'.list2';
		return(is_file($f) ? file($f, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : false);
	}

	// -- frozen paths available (an opened download) ------------------------

	public function testFrozenPathsUsedForMultiFile()
	{
		$this->reset();
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin", "/d/name/sub/b.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		$this->assertEquals(array("/d/name/a.bin","/d/name/sub/b.bin","/d/name","1","1"), $this->listFor("A"), 'multi-file list from frozen paths');
		$this->assertEquals(array("d.get_base_path","d.set_custom5"), rXMLRPCRequest::$requested, 'no fallback request when frozen paths exist');
	}

	public function testFrozenPathsUsedForSingleFile()
	{
		$this->reset();
		$this->frozen(true, array("/d/movie.mkv", 0, "/d/movie.mkv"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		$this->assertEquals(array("/d/movie.mkv","/d/movie.mkv","0","1"), $this->listFor("A"), 'single-file list from frozen paths');
	}

	// -- frozen paths empty (a download never opened this session) ----------

	public function testFallsBackToStoredPathsForMultiFile()
	{
		$this->reset();
		$this->frozen(true, array("", 1, "", ""));
		$this->stored(true, array("/d/name", 1, "a.bin", "sub/b.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		$this->assertEquals(array("/d/name/a.bin","/d/name/sub/b.bin","/d/name","1","1"), $this->listFor("A"), 'multi-file list rebuilt from d.directory + f.path');
		$this->assertEquals(array("d.get_base_path","d.get_directory","d.set_custom5"), rXMLRPCRequest::$requested, 'fallback request issued');
	}

	public function testFallsBackToStoredPathsForSingleFile()
	{
		$this->reset();
		$this->frozen(true, array("", 0, ""));
		$this->stored(true, array("/d", 0, "movie.mkv"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		// d.base_path reports the file itself for a single-file torrent, while
		// d.directory reports the directory holding it.
		$this->assertEquals(array("/d/movie.mkv","/d/movie.mkv","0","1"), $this->listFor("A"), 'single-file base path is the file, not its directory');
	}

	public function testFallbackNormalisesTrailingSlash()
	{
		$this->reset();
		$this->frozen(true, array("", 1, ""));
		$this->stored(true, array("/d/name/", 1, "a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		$this->assertEquals(array("/d/name/a.bin","/d/name","1","1"), $this->listFor("A"), 'no doubled separator from a trailing slash');
	}

	public function testFallbackUsedWhenFrozenRequestFails()
	{
		$this->reset();
		$this->frozen(false, array());
		$this->stored(true, array("/d/name", 1, "a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		$this->assertEquals(array("/d/name/a.bin","/d/name","1","1"), $this->listFor("A"), 'a failed frozen request also falls back');
	}

	// -- the delete mode is recorded verbatim -------------------------------

	public function testForceDeleteFlagRecorded()
	{
		$this->reset();
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "2");
		$lines = $this->listFor("A");
		$this->assertEquals("2", end($lines), 'delete-path mode recorded as the last line');
	}

	// -- refuse to erase what cannot be cleaned up ---------------------------

	public function testTorrentNotErasedWhenNoPathsResolve()
	{
		$this->reset();
		$this->frozen(true, array("", 1, "", ""));
		$this->stored(true, array("", 1, "", ""));
		$this->eraseOk();
		$result = erasedataRemoveWithData(array("A"), "1");
		$this->assertTrue($this->listFor("A") === false, 'no list written when no path resolves');
		$this->assertEquals(array(), rXMLRPCRequest::$erased, 'torrent must not be erased when its files are unknown');
		$this->assertTrue($result === false, 'caller is told the removal did not happen');
		$this->assertEquals(1, count(FileUtil::$log), 'the refusal is logged');
	}

	public function testResolvableHashesStillErasedInAMixedBatch()
	{
		$this->reset();
		// The scripted RPC layer answers per command, so both hashes see the
		// same empty frozen reply; only the stored reply resolves.
		$this->frozen(true, array("", 1, ""));
		$this->stored(true, array("/d/name", 1, "a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A","B"), "1");
		$this->assertEquals(array("A","B"), rXMLRPCRequest::$erased, 'every resolvable hash is erased');
	}

	// -- a path the list format cannot carry --------------------------------

	// The list is newline-delimited. libtorrent accepts a path element that is
	// not empty, not "." or "..", and carries no '/' and no NUL, so a line
	// break in one reaches here from a torrent anyone can publish -- and the
	// collector reads the extra line as another file to unlink.

	private function rawListFor($hash)
	{
		$f = $this->dir.'/erasedata/'.$hash.'.list2';
		return(is_file($f) ? file_get_contents($f) : false);
	}

	public function testPathWithALineBreakIsNotWritten()
	{
		$this->reset();
		// One element ending in a line break, the elements after it spelling
		// out an absolute path: what rtorrent reports back for a crafted
		// torrent, and two entries once the collector reads it.
		$this->frozen(true, array("/d/name", 1,
			"/d/name/inject\n/etc/cron.d/victim", "/d/name/b.bin"));
		$this->eraseOk();
		$result = erasedataRemoveWithData(array("A"), "1");

		$this->assertTrue($this->rawListFor("A") === false,
			'no list is written for a path carrying a line break');
		$this->assertEquals(array(), rXMLRPCRequest::$erased,
			'and the torrent is kept, so its data can still be identified');
		$this->assertTrue($result === false, 'the caller is told the removal did not happen');
		$this->assertEquals(1, count(FileUtil::$log), 'the refusal is logged');
	}

	public function testCarriageReturnIsRefusedToo()
	{
		$this->reset();
		$this->frozen(true, array("/d/name", 1, "/d/name/inject\r/etc/cron.d/victim"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		$this->assertTrue($this->rawListFor("A") === false,
			'a bare carriage return is refused as well');
	}

	public function testALineBreakInTheBasePathIsRefused()
	{
		$this->reset();
		// The base path, the multi flag and the deletion mode are the last
		// three lines. A line break in the base moves all three.
		$this->frozen(true, array("/d/na\nme", 1, "/d/na\nme/a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");
		$this->assertTrue($this->rawListFor("A") === false,
			'a line break in the base path is refused');
	}

	public function testOtherHashesInTheBatchAreUnaffected()
	{
		$this->reset();
		// Refusing one download must not cost the rest of the batch: the
		// scripted layer answers the same reply for both hashes, so this
		// checks the refusal is per item rather than for the call.
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A","B"), "1");
		$this->assertEquals(array("A","B"), rXMLRPCRequest::$erased,
			'a batch of resolvable downloads is erased in full');
	}

	// -- the list is staged before the erase and published after it ---------

	// erasedataRemoveWithData() writes the list under a temporary name, erases
	// the torrent, and renames the list into place once rtorrent has confirmed
	// the erase. After the erase the list is the only thing that still names
	// the download's files, so a list that cannot be written keeps the torrent
	// -- the same end state the two refusals above exist to avoid, arrived at
	// by not checking a return value. A list that cannot be renamed after the
	// erase is a failure that is logged, with the data left in place.

	private function publishBlocked()
	{
		// A plain file where the list directory belongs: every attempt to
		// create a file under it fails with ENOTDIR, for any user, whatever
		// the list is named.
		@rmdir($this->dir.'/erasedata');
		file_put_contents($this->dir.'/erasedata', "not a directory\n");
	}

	private function publishUnblocked()
	{
		@unlink($this->dir.'/erasedata');
		@mkdir($this->dir.'/erasedata', 0777, true);
	}

	public function testTheTorrentIsKeptWhenTheListCannotBeWritten()
	{
		$this->reset();
		$this->publishBlocked();
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		$result = erasedataRemoveWithData(array("A"), "1");
		$this->publishUnblocked();

		$this->assertEquals(array(), rXMLRPCRequest::$erased,
			'a torrent whose file list could not be recorded is not erased');
		$this->assertTrue($result === false, 'the caller is told the removal did not happen');
		$this->assertEquals(1, count(FileUtil::$log), 'the failure is logged');
	}

	public function testAListThatCannotBePublishedAfterTheEraseIsLogged()
	{
		$this->reset();
		// The scripted RPC layer answers per command, so both hashes resolve.
		// Only the first has a directory sitting on its list name: the staged
		// list is written beside it, the erase goes through, and the rename
		// into place is what fails. The other hash is unaffected.
		@mkdir($this->dir.'/erasedata', 0777, true);
		@mkdir($this->dir.'/erasedata/A.list2', 0777, true);
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		$result = erasedataRemoveWithData(array("A","B"), "1");
		@rmdir($this->dir.'/erasedata/A.list2');

		$this->assertEquals(array("A","B"), rXMLRPCRequest::$erased, 'both downloads are erased');
		$this->assertEquals(array("/d/name/a.bin","/d/name","1","1"), $this->listFor("B"),
			'the list of the one that could be published is there');
		$this->assertTrue($this->listFor("A") === false, 'the other has none');
		$this->assertTrue($result === false, 'the caller is told something did not happen');
		$this->assertEquals(1, count(FileUtil::$log), 'and the failure is logged');
		$this->assertTrue(strpos(FileUtil::$log[0], 'A was erased but its files could not be recorded') !== false
			&& strpos(FileUtil::$log[0], '/d/name') !== false,
			'naming the download and where its data is: ' . FileUtil::$log[0]);
	}

	public function testAFailedPublicationLeavesNothingBehind()
	{
		$this->reset();
		@mkdir($this->dir.'/erasedata', 0777, true);
		@mkdir($this->dir.'/erasedata/A.list2', 0777, true);
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");

		$strays = array_filter(glob($this->dir.'/erasedata/*'), 'is_file');
		@rmdir($this->dir.'/erasedata/A.list2');
		$this->assertEquals(array(), array_values($strays),
			'a publication that failed leaves no half-written list and no temporary file');
	}

	// A list is published by renaming a fully written temporary file over the
	// name the collector reads, because the collector is another process and
	// may read that name at any moment. The last three lines are the base
	// path, the multi-file flag and the deletion mode, so a list read while it
	// is being written is not a short list: it is a different one.
	//
	// rename() gives the name a different inode. A write in place does not.
	public function testTheListIsPublishedByRename()
	{
		$this->reset();
		@mkdir($this->dir.'/erasedata', 0777, true);
		$name = $this->dir.'/erasedata/A.list2';
		file_put_contents($name, "previous\n");
		clearstatcache(true, $name);
		$before = stat($name);

		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");

		clearstatcache(true, $name);
		$after = stat($name);
		$this->assertTrue($after !== false, 'the list is there');
		$this->assertTrue($before['ino'] !== $after['ino'],
			'and it is a new inode, so the name was never the file being written');
		$this->assertEquals(array("/d/name/a.bin","/d/name","1","1"), $this->listFor("A"),
			'carrying the list, not the file that was there before');
	}

	public function testNoTemporaryFileSurvivesASuccess()
	{
		$this->reset();
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		erasedataRemoveWithData(array("A"), "1");

		$this->assertEquals(array($this->dir.'/erasedata/A.list2'),
			array_values(array_filter(glob($this->dir.'/erasedata/*'), 'is_file')),
			'the list is the only file publication leaves in the directory');
	}

	// -- a list is published only for an erase rtorrent confirmed -----------

	// The collector deletes what a list names once rtorrent no longer lists the
	// hash, so a list must only exist for an erase rtorrent confirmed. A request
	// whose reply never came, or came as a fault, leaves the download where it
	// may well still be: loaded, with its files in use.

	public function testNoListIsPublishedForAnEraseThatWasNotConfirmed()
	{
		$this->reset();
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		rXMLRPCRequest::$unconfirmed = array("A");
		$result = erasedataRemoveWithData(array("A"), "1");

		$this->assertEquals(array("A"), rXMLRPCRequest::$erased, 'the erase was asked for');
		$this->assertTrue($this->rawListFor("A") === false,
			'but no list is published for an erase that was not confirmed');
		$this->assertEquals(array(), array_values(array_filter(glob($this->dir.'/erasedata/*'), 'is_file')),
			'and the staged list is gone too');
		$this->assertTrue($result === false, 'the caller is told the removal did not happen');
		$this->assertEquals(1, count(FileUtil::$log), 'and it is logged');
		$this->assertTrue(strpos(FileUtil::$log[0], 'did not confirm the erase of A') !== false
			&& strpos(FileUtil::$log[0], '/d/name') !== false,
			'naming the download and where its data is: ' . FileUtil::$log[0]);
	}

	public function testOnlyTheConfirmedEraseIsRecorded()
	{
		$this->reset();
		// Each download is erased in a request of its own, so the one whose
		// reply was lost does not decide for the one that was confirmed.
		$this->frozen(true, array("/d/name", 1, "/d/name/a.bin"));
		$this->eraseOk();
		rXMLRPCRequest::$unconfirmed = array("A");
		$result = erasedataRemoveWithData(array("A","B"), "1");

		$this->assertEquals(array("d.get_base_path","d.set_custom5","d.get_base_path","d.set_custom5"),
			rXMLRPCRequest::$requested, 'one erase request per download');
		$this->assertTrue($this->rawListFor("A") === false, 'no list for the unconfirmed erase');
		$this->assertEquals(array("/d/name/a.bin","/d/name","1","1"), $this->listFor("B"),
			'and the list of the confirmed one');
		$this->assertTrue($result === false, 'the caller is told something did not happen');
	}
}
