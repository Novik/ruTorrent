<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');

/**
 * The directory autotools hands rtorrent is the directory the data ends up in.
 *
 * On a download finishing, rtorrent runs the pair the autotools plugin
 * installed for it. On rtorrent 0.8.8 and above, with the file operation set
 * to "Move", that pair is
 *
 *     d.set_directory_base="$execute_capture={php,check.php,...}" ; execute={php,move.php,...}
 *
 * -- check.php names a directory, rtorrent points the download at it, and only
 * then does move.php carry the files there. check.php answers from the plugin
 * settings and the path alone; it never looks at the destination. So when
 * move.php refuses, because something is already at one of the names it would
 * write, the download has already been pointed at a directory its data never
 * reached, and move.php's refusal goes nowhere: it is run through execute
 * rather than execute_capture, it prints nothing and it exits 0.
 *
 * The older branch of the same setting is not built this way: it runs move.php
 * under execute_capture into a custom field and sets the directory from that
 * field only when it is not empty, so a refusal leaves the download where its
 * data is.
 *
 * What is asserted here is the end condition rather than either script's
 * output: whatever path check.php names, the download's files are at that path
 * once move.php has run. That holds for a destination that is free and for one
 * that is taken, and it would still be the right question if the pair were
 * wired differently.
 */
class AutoMoveDestinationTest extends TestCase
{
	const HASH = '0123456789ABCDEF0123456789ABCDEF01234567';
	const USER = 'tester';

	private $base;

	public function setUp()
	{
		$this->base = sys_get_temp_dir() . '/rutorrent-automove-' . getmypid();
	}

	public function tearDown()
	{
		$this->removeTree($this->base);
	}

	private function removeTree($path)
	{
		if (is_link($path)) { @unlink($path); return; }
		if (!is_dir($path)) { @unlink($path); return; }
		foreach (glob($path . '/*') as $entry)
			$this->removeTree($entry);
		@rmdir($path);
	}

	private function repoRoot()
	{
		return realpath(__DIR__ . '/../../..');
	}

	private function bstr($s)
	{
		return strlen($s) . ':' . $s;
	}

	/** A two file torrent, built here so the fixture is its own expectation. */
	private function torrentBytes($names)
	{
		$files = '';
		foreach ($names as $n)
			$files .= 'd' . $this->bstr('length') . 'i12e'
				. $this->bstr('path') . 'l' . $this->bstr($n) . 'e' . 'e';
		$info = 'd'
			. $this->bstr('files') . 'l' . $files . 'e'
			. $this->bstr('name') . $this->bstr('mytorrent')
			. $this->bstr('piece length') . 'i32768e'
			. $this->bstr('pieces') . $this->bstr(str_repeat("\x01", 20))
			. 'e';
		return 'd' . $this->bstr('announce')
			. $this->bstr('http://tracker.invalid/announce')
			. $this->bstr('info') . $info . 'e';
	}

	/** The prelude every driver shares: one profile, one user. */
	private function prelude()
	{
		return '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
			. ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
			. 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
			. '$scgi_host = "127.0.0.1"; $scgi_port = 1; $rpcTimeOut = 1; $rpcLogCalls = false;' . "\n";
	}

	private function runPhp($file, $args = array())
	{
		$result = PhpProcess::run(array_merge(array('-d', 'error_reporting=0', $file), $args));
		return array('exit'=>$result['code'], 'printed'=>rtrim($result['out']. $result['err'], "\r\n"));
	}

	/**
	 * Builds a finished download, runs check.php and then move.php exactly as
	 * rtorrent runs them, and reports what each said and what is on disk.
	 *
	 * $occupy is the list of names already standing in the destination
	 * directory when the download finishes.
	 */
	private function runTheFinishedHook($occupy, $finished = 'fin', $label = '', $skip = '', $names = array('1.bin', '2.bin'))
	{
		$this->removeTree($this->base);
		$finishedPath = $this->base.'/'.$finished;
		@mkdir($this->base . '/profile/settings', 0700, true);
		@mkdir($this->base . '/session', 0700, true);
		@mkdir($this->base . '/dl/mytorrent', 0777, true);
		@mkdir($finishedPath, 0777, true);
		foreach ($names as $n)
			file_put_contents($this->base . '/dl/mytorrent/' . $n, 'download ' . $n);
		foreach ($occupy as $n)
		{
			@mkdir(dirname($finishedPath.'/'.($label === '' ? '' : $label.'/').'mytorrent/' . $n), 0777, true);
			file_put_contents($finishedPath.'/'.($label === '' ? '' : $label.'/').'mytorrent/' . $n, 'already there');
		}
		file_put_contents($this->base . '/session/' . self::HASH . '.torrent',
			$this->torrentBytes($names));

		$seed = $this->base . '/seed.php';
		file_put_contents($seed, "<?php\n" . $this->prelude()
			. 'require_once(' . var_export($this->repoRoot() . '/php/settings.php', true) . ");\n"
			. 'require_once(' . var_export($this->repoRoot() . '/plugins/autotools/autotools.php', true) . ");\n"
			. '$s = rTorrentSettings::get();' . "\n"
			. '$s->directory = ' . var_export($this->base . '/dl', true) . ";\n"
			. '$s->session = ' . var_export($this->base . '/session', true) . ";\n"
			. '$s->linkExist = true; $s->iVersion = 0x1015; $s->store();' . "\n"
			. '$at = rAutoTools::load();' . "\n"
			. '$at->enable_move = 1; $at->fileop_type = "Move";' . "\n"
			. '$at->path_to_finished = ' . var_export($finishedPath, true) . ";\n"
			. '$at->automove_filter = "/.*/"; $at->addLabel = '.($label === '' ? '0' : '1').'; $at->addName = 0;' . "\n"
			. '$at->skip_move_for_files = '.var_export($skip, true).'; $at->store();' . "\n"
			. 'echo rAutoTools::load()->path_to_finished;' . "\n");
		$seeded = $this->runPhp($seed);
		if ($seeded['printed'] !== $finishedPath)
			throw new Exception('the plugin settings did not take: ' . $seeded['printed']);

		// One driver per script, because each is a top level script that reads
		// $argv and chdir()s to where it lives.
		$driver = array();
		foreach (array('check', 'move') as $which)
		{
			$driver[$which] = $this->base . '/drive-' . $which . '.php';
			file_put_contents($driver[$which], "<?php\n" . $this->prelude()
				. 'require(' . var_export(
					$this->repoRoot() . '/plugins/autotools/' . $which . '.php', true) . ");\n");
		}

		$basePath = $this->base . '/dl/mytorrent';   // d.get_base_path
		// d.get_base_path, d.get_base_filename, d.is_multi_file, d.get_custom1, d.get_name
		$check = $this->runPhp($driver['check'],
			array($basePath, 'mytorrent', '1', rawurlencode($label), 'mytorrent', self::USER));
		// and then, with the hash in front, the same five
		$move = $this->runPhp($driver['move'],
			array(self::HASH, $basePath, 'mytorrent', '1', rawurlencode($label), 'mytorrent', self::USER));

		$named = rtrim($check['printed'], '/');
		return array(
			'check' => $check,
			'move' => $move,
			'named' => $named,
			// the download's files, at the directory check.php named
			'at_named' => array_map(function($n) use ($named) {
				return is_file($named . '/' . $n) && file_get_contents($named . '/' . $n) === 'download ' . $n;
			}, $names),
			'at_source' => array_map(function($n) {
				return is_file($this->base . '/dl/mytorrent/' . $n);
			}, $names),
			'occupants' => array_map(function($n) use ($finishedPath, $label) {
				return @file_get_contents($finishedPath.'/'.($label === '' ? '' : $label.'/').'mytorrent/' . $n);
			}, $occupy),
		);
	}

	/**
	 * The control. With nothing in the way the pair works, and the assertion
	 * below is therefore about the collision and not about the fixture.
	 */
	public function testAFinishedDownloadIsMovedToTheDirectoryItIsPointedAt()
	{
		$r = $this->runTheFinishedHook(array());

		$this->assertTrue($r['named'] !== '',
			'check.php names a directory for the finished download; it printed: '
			. var_export($r['check']['printed'], true));
		$this->assertEquals(array(true, true), $r['at_named'],
			'and both files of the download are in it afterwards: ' . $r['named']);
		$this->assertEquals(array(false, false), $r['at_source'],
			'and none is left where the download ran');
	}

	/**
	 * The whole point: a destination name that move.php will refuse must not
	 * be the one rtorrent is given, because rtorrent is given it first. The
	 * download must end up at the directory it was pointed at, whichever
	 * directory the pair settles on.
	 */
	public function testTheDownloadIsAtTheDirectoryItWasPointedAtWhenTheMoveIsRefused()
	{
		$r = $this->runTheFinishedHook(array('2.bin'));

		$this->assertTrue($r['named'] !== '',
			'check.php names a directory even when the destination is taken; it printed: '
			. var_export($r['check']['printed'], true));
		$this->assertEquals(array('already there'), $r['occupants'],
			'the file that was already at the destination survives');
		$this->assertEquals(array(true, true), $r['at_named'],
			'and the download is at the directory rtorrent was pointed at (' . $r['named']
			. '), rather than pointed at a directory its data never reached');
	}

	/**
	 * The same run seen from the source. A refused move leaves the download
	 * whole where it is, so it is still seedable; a move that half happened
	 * is not.
	 */
	public function testARefusedMoveLeavesTheDownloadWholeInOnePlace()
	{
		$r = $this->runTheFinishedHook(array('2.bin'));

		$whole = ($r['at_source'] === array(true, true)) || ($r['at_named'] === array(true, true));
		$this->assertTrue($whole,
			'after a refused automove the download is whole in one directory; at the '
			. 'source: ' . json_encode($r['at_source']) . ', at ' . $r['named'] . ': '
			. json_encode($r['at_named']));
	}
	public function testLegacyLabelsAndFinishedPathsKeepTheirOriginalBytes()
	{
		foreach(array(array("fin\xFF", "label\xFE"), array('映画', 'серия'), array('literal%FF', 'literal%FE')) as $case)
		{
			$r = $this->runTheFinishedHook(array(), $case[0], $case[1]);
			$this->assertEquals($this->base.'/'.$case[0].'/'.$case[1].'/mytorrent', $r['named'], 'precheck names the exact selected bytes');
			$this->assertEquals(array(true, true), $r['at_named'], 'move puts the files at the same byte path');
			$this->assertEquals(array(false, false), $r['at_source'], 'the move consumes only its source files');
		}
	}

	public function testSkipFilterKeepsUnicodeSemanticsAndMatchesLegacyBytes()
	{
		// skip_move() runs from move.php's own source; only its settings object is a stub.
		$source = file_get_contents($this->repoRoot().'/plugins/autotools/move.php');
		$start = strpos($source, 'function skip_move(');
		$function = substr($source, $start, strpos($source, "\n}\n", $start) - $start + 2);
		$code = 'require '.var_export($this->repoRoot().'/php/utility/utf.php',true).';'
			.'function Debug($message) {} $at=new stdClass(); $at->skip_move_for_files="/^.$/"; $results=array();'
			.$function
			.'foreach('.var_export(array('雪', "\xFF", '雪雪', 'ab', 'a'),true).' as $name) $results[]=skip_move(array($name));'
			.'echo json_encode($results);';
		$result=PhpProcess::run(array('-d','display_errors=stderr','-r',$code));
		$this->assertEquals(0,$result['code'],'the shared filter owner completes');
		$this->assertEquals('',$result['err'],'both filter modes emit no diagnostics');
		$this->assertEquals(array(true,true,false,false,true),json_decode($result['out'],true),
			'valid Unicode keeps character semantics and raw bytes use byte semantics');
	}

}
