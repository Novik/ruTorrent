<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TestTree.php');
require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/../../php/utility/permission.php');

class PermissionTest extends TestCase
{
	protected $fixtureDir = null;
	protected $dirs = [];

	public function setUp()
	{
		$this->fixtureDir = sys_get_temp_dir() . '/rutorrent-permission-' . uniqid('', true);
		$this
			->makeDir($this->fixtureDir, 0711)
			->addDir('dir1', '/dir1')
			->addDir('dir2', '/dir2')
			->addDir('dir3', '/dir1/dir3', $this->fixtureDir . '/dir2')
			->addDir('dir4', '/dir1/dir4', '../dir2')
			->createDirs();
	}

	public function tearDown()
	{
		TestTree::remove($this->fixtureDir);
	}

	protected function addDir($name, $dir, $symlink = null)
	{
		$this->dirs[$name] = [$this->fixtureDir . $dir, $symlink];

		return $this;
	}

	protected function getDir($name)
	{
		return isset($this->dirs[$name]) ? $this->dirs[$name][0] : null;
	}

	protected function createDirs()
	{
		foreach ($this->dirs as [$dir, $symlink]) {
			if ($symlink) {
				if (is_link($dir)) {
					unlink($dir);
				}
				symlink($symlink, $dir);
			} else {
				$this->makeDir($dir, 0777);
			}
		}

		return $this;
	}

	/**
	 * mkdir() subtracts the process umask, so a fixture asked for 0777 arrives
	 * at 0600 under `umask 0177` and the cases below stop testing the mode
	 * they name. Ask for the mode with no umask in the way, then read it back.
	 */
	protected function makeDir($dir, $mode)
	{
		$mask = umask(0);
		try {
			if (!is_dir($dir) && !mkdir($dir, $mode, true)) {
				throw new RuntimeException('Could not create fixture directory ' . $dir);
			}
			if (!chmod($dir, $mode)) {
				throw new RuntimeException('Could not set the mode of fixture directory ' . $dir);
			}
		} finally {
			umask($mask);
		}
		clearstatcache(true, $dir);
		$actual = fileperms($dir) & 07777;
		if ($actual !== $mode) {
			throw new RuntimeException('Fixture directory ' . $dir . ' is mode ' . decoct($actual)
				. ', not the ' . decoct($mode) . ' it was created with');
		}

		return $this;
	}

	protected function createFile($name, $mode)
	{
		$file = $this->fixtureDir . '/' . $name;
		if (file_put_contents($file, 'owned') !== 5 || !chmod($file, $mode)) {
			throw new RuntimeException('Could not create permission file');
		}
		clearstatcache(true, $file);
		return $file;
	}

	public function testOwnerDoesNotInheritGroupOrOtherPermissions()
	{
		$file = $this->createFile('owner', 0600);
		$uid = fileowner($file);
		if ($uid === 0) {
			echo "NOT CHECKED: owner denial requires a non-root fixture owner\n";
			return;
		}
		$groups = array(filegroup($file));
		foreach (array(0004, 0040, 0044) as $mode) {
			chmod($file, $mode);
			clearstatcache(true, $file);
			$this->assertTrue(@file_get_contents($file) === false, 'The kernel denies owner read for mode ' . decoct($mode));
			$this->assertTrue(Permission::doesUserHave($uid, $groups, $file, 4) === false,
				'The owner cannot inherit another class read bit for mode ' . decoct($mode));
		}
		chmod($file, 0400);
		clearstatcache(true, $file);
		$this->assertTrue(file_get_contents($file) === 'owned', 'The kernel accepts the owner read bit');
		$this->assertTrue(Permission::doesUserHave($uid, $groups, $file, 4) === true, 'The owner read bit grants permission');
	}

	public function testMatchingGroupDoesNotInheritOtherPermissions()
	{
		$file = $this->createFile('group', 0004);
		$foreignUid = fileowner($file) === 12345 ? 12346 : 12345;
		// Settings obtains group IDs from command output, so they may be strings.
		$groups = array(-1, (string)filegroup($file), (string)filegroup($file));
		$this->assertTrue(Permission::doesUserHave($foreignUid, $groups, $file, 4) === false,
			'A matching group cannot inherit the other read bit');
		chmod($file, 0040);
		clearstatcache(true, $file);
		$this->assertTrue(Permission::doesUserHave($foreignUid, $groups, $file, 4) === true, 'The matching group read bit grants permission');
		$this->assertTrue(Permission::doesUserHave($foreignUid, array(), $file, 4) === false, 'An unrelated user cannot inherit the group read bit');
		chmod($file, 0004);
		clearstatcache(true, $file);
		$this->assertTrue(Permission::doesUserHave($foreignUid, array(), $file, 4) === true, 'An unrelated user uses the other read bit');
	}

	public function testPermissionClassSelectionAppliesToEveryRequestedBit()
	{
		$file = $this->createFile('bits', 0600);
		$uid = fileowner($file);
		$foreignUid = $uid === 12345 ? 12346 : 12345;
		try {
			chmod($this->fixtureDir, 0777);
			clearstatcache(true, $this->fixtureDir);
			foreach (array(1, 2, 3, 5, 7) as $flags) {
				chmod($file, $flags);
				clearstatcache(true, $file);
				if ($uid !== 0) {
					$this->assertTrue(Permission::doesUserHave($uid, array(filegroup($file)), $file, $flags) === false,
						'Owner selection does not inherit other bits ' . $flags);
				}
				$this->assertTrue(Permission::doesUserHave($foreignUid, array(filegroup($file)), $file, $flags) === false,
					'Group selection does not inherit other bits ' . $flags);
				$this->assertTrue(Permission::doesUserHave($foreignUid, array(), $file, $flags) === true,
					'Other bits grant the complete requested mask ' . $flags);
				chmod($file, $flags << 6);
				clearstatcache(true, $file);
				$this->assertTrue(Permission::doesUserHave($uid, array(), $file, $flags) === true,
					'Owner bits grant the complete requested mask ' . $flags);
				chmod($file, $flags << 3);
				clearstatcache(true, $file);
				$this->assertTrue(Permission::doesUserHave($foreignUid, array(filegroup($file)), $file, $flags) === true,
					'Group bits grant the complete requested mask ' . $flags);
			}
			chmod($file, 0444);
			clearstatcache(true, $file);
			foreach (array(array($uid, array()), array($foreignUid, array(filegroup($file))), array($foreignUid, array())) as [$user, $groups]) {
				if ($user !== 0) {
					$this->assertTrue(Permission::doesUserHave($user, $groups, $file, 5) === false,
						'A partial selected mask does not grant every requested bit');
				}
			}
		} finally {
			chmod($this->fixtureDir, 0711);
		}
	}

	public function testSelectedClassAlsoControlsParentDirectoryPermission()
	{
		$file = $this->createFile('parent-check', 0444);
		$foreignUid = fileowner($file) === 12345 ? 12346 : 12345;
		$groups = array(filegroup($file));
		try {
			chmod($this->fixtureDir, 0701);
			clearstatcache(true, $this->fixtureDir);
			$this->assertTrue(Permission::doesUserHave($foreignUid, $groups, $file, 4) === false,
				'A matching parent group cannot inherit other search permission');
			chmod($this->fixtureDir, 0710);
			clearstatcache(true, $this->fixtureDir);
			$this->assertTrue(Permission::doesUserHave($foreignUid, $groups, $file, 4) === true,
				'The selected parent group search bit permits a readable child');
		} finally {
			chmod($this->fixtureDir, 0711);
		}
	}

	public function testBareRelativeSymlinkResolvesBesideTheLink()
	{
		$file = $this->createFile('relative-leaf', 0444);
		$link = $this->fixtureDir . '/relative-link';
		if (!symlink('relative-leaf', $link)) {
			throw new RuntimeException('Could not create relative symlink');
		}
		$this->assertTrue(file_get_contents($link) === 'owned', 'The real bare relative symlink is readable');
		$this->assertTrue(Permission::doesUserHave(12345, array(), $link, 4) === true,
			'A bare relative target is resolved in its containing directory');
	}

	public function testMissingPathsAndBrokenSymlinksAreRefused()
	{
		$file = $this->fixtureDir . '/missing';
		$this->assertTrue(Permission::doesUserHave(12345, array(), $file, 4) === false, 'An absent file has no permission');
		$link = $this->fixtureDir . '/broken-link';
		if (!symlink('missing', $link)) {
			throw new RuntimeException('Could not create broken symlink');
		}
		$this->assertTrue(Permission::doesUserHave(12345, array(), $link, 4) === false, 'A broken relative symlink has no permission');
	}

	public function testReadlinkFailuresCannotGrantTheLinkParentPermissions()
	{
		$file = $this->createFile('readlink-leaf', 0600);
		$uid = fileowner($file);
		if ($uid === 0) {
			echo "NOT CHECKED: readlink refusal requires a non-root fixture owner\n";
			return;
		}
		// The namespace changes only function lookup, making the unlink between
		// is_link() and readlink() deterministic without rewriting the decisions.
		$code = <<<'PHP'
require $argv[1];
$source = file_get_contents($argv[2]);
set_include_path(dirname($argv[2]) . PATH_SEPARATOR . get_include_path());
eval('namespace PermissionReadlinkFault; use \\LFS; use \\Throwable; use \\Exception;
function readlink($file) {
    switch ($GLOBALS["readlinkFault"]) {
        case "false": return false;
        case "null": return null;
        case "empty": return "";
        case "exception": throw new \\RuntimeException("readlink failed");
        case "error": throw new \\Error("readlink unavailable");
        case "race": \\unlink($file); return \\readlink($file);
    }
}
' . substr($source, 5));
$results = array();
foreach (array('false', 'null', 'empty', 'exception', 'error', 'race') as $fault) {
    $GLOBALS['readlinkFault'] = $fault;
    $link = $argv[3] . '/fault-link';
    if (is_link($link)) unlink($link);
    if (!symlink('readlink-leaf', $link)) throw new RuntimeException('Could not create fault link');
    clearstatcache();
    try {
        $results[$fault] = PermissionReadlinkFault\Permission::doesUserHave((int)$argv[4], array(), $link, 7);
    } catch (Throwable $error) {
        $results[$fault] = get_class($error);
    }
}
$results['race-removed-leaf'] = !file_exists($link);
echo serialize($results);
PHP;
		$run = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d', 'display_errors=stderr', '-r', $code,
			realpath(__DIR__ . '/../../php/utility/lfs.php'), realpath(__DIR__ . '/../../php/utility/permission.php'),
			$this->fixtureDir, (string)$uid)));
		$this->assertTrue($run['code'] === 0 && !$run['timedOut'], 'Readlink failure fixtures complete: ' . $run['err']);
		$this->assertTrue($run['err'] === '', 'Readlink failures emit no diagnostics');
		$results = @unserialize($run['out'], array('allowed_classes' => false));
		foreach (array('false', 'null', 'empty', 'exception', 'error', 'race') as $fault) {
			$this->assertTrue(is_array($results) && ($results[$fault] ?? null) === false,
				'Readlink ' . $fault . ' refuses the link instead of checking its parent');
		}
		$this->assertTrue(is_array($results) && ($results['race-removed-leaf'] ?? false) === true,
			'The race fixture actually removes the symlink before native readlink');
	}

	public function testDisabledReadlinkRefusesWithoutAnError()
	{
		$file = $this->createFile('disabled-readlink-leaf', 0600);
		$uid = fileowner($file);
		if ($uid === 0) {
			echo "NOT CHECKED: disabled readlink refusal requires a non-root fixture owner\n";
			return;
		}
		$link = $this->fixtureDir . '/disabled-readlink';
		if (!symlink('disabled-readlink-leaf', $link)) {
			throw new RuntimeException('Could not create disabled-readlink fixture');
		}
		$code = 'require $argv[1]; echo serialize(Permission::doesUserHave((int)$argv[2], array(), $argv[3], 7));';
		$run = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d', 'disable_functions=readlink',
			'-d', 'display_errors=stderr', '-r', $code, realpath(__DIR__ . '/../../php/utility/permission.php'), (string)$uid, $link)));
		$this->assertTrue($run['code'] === 0 && !$run['timedOut'], 'Disabled readlink does not abort the permission check: ' . $run['err']);
		$this->assertTrue($run['err'] === '', 'Disabled readlink emits no diagnostics');
		$this->assertTrue($run['out'] === serialize(false), 'Disabled readlink refuses the symlink');
	}

	public function testRelativeSymlinkTargetNamedZeroIsValid()
	{
		$this->createFile('0', 0444);
		$link = $this->fixtureDir . '/zero-link';
		if (!symlink('0', $link)) {
			throw new RuntimeException('Could not create zero-name symlink');
		}
		$this->assertTrue(file_get_contents($link) === 'owned', 'The real target named zero is readable');
		$this->assertTrue(Permission::doesUserHave(12345, array(), $link, 4) === true, 'A nonempty target named zero is accepted');
	}

	public function testAbsoluteSymlinkChecksItsTargetParent()
	{
		$file = $this->getDir('dir2') . '/absolute-leaf';
		if (file_put_contents($file, 'owned') !== 5 || !chmod($file, 0444)
			|| !symlink($file, $this->fixtureDir . '/absolute-link')) {
			throw new RuntimeException('Could not create absolute symlink');
		}
		try {
			chmod($this->getDir('dir2'), 0700);
			clearstatcache();
			$this->assertTrue(Permission::doesUserHave(12345, array(), $this->fixtureDir . '/absolute-link', 4) === false,
				'The target parent must permit search, even when the link parent does');
			chmod($this->getDir('dir2'), 0711);
			clearstatcache();
			$this->assertTrue(Permission::doesUserHave(12345, array(), $this->fixtureDir . '/absolute-link', 4) === true,
				'The absolute link is allowed once its target parent permits search');
		} finally {
			chmod($this->getDir('dir2'), 0711);
		}
	}

	public function testSuppliedIdentityDoesNotRequirePosixHelpers()
	{
		$file = $this->createFile('without-posix', 0444);
		$code = 'require ' . var_export(realpath(__DIR__ . '/../../php/utility/permission.php'), true) . ';'
			. 'echo serialize(array(Permission::doesUserHave(12345, array(), $argv[1], 4),'
			. 'Permission::doesUserHave(12345, array(), $argv[1]."-missing", 4)));';
		$run = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d',
			'disable_functions=posix_getuid,posix_geteuid,posix_getgid,posix_getegid,posix_getgroups',
			'-r', $code, $file)));
		$this->assertTrue($run['code'] === 0 && !$run['timedOut'], 'The helper uses the supplied identity with POSIX functions disabled: ' . $run['err']);
		$this->assertTrue($run['err'] === '', 'Disabling POSIX functions emits no diagnostics');
		$this->assertTrue($run['out'] === serialize(array(true, false)), 'The POSIX-free child grants the real readable file and refuses the missing file');
	}

	/**
	 * Settings splits `id -G`, so an entry can be a string that spells no
	 * number at all. Such an entry names no group, and the answer must be the
	 * one a caller with no groups gets. Group zero is the only value a
	 * non-numeric entry can be confused with, so the fixture needs that group.
	 */
	public function testAnEntryThatNamesNoGroupLeavesTheOtherClassSelected()
	{
		$file = $this->createFile('no-group-entry', 0004);
		@chgrp($file, 0);
		clearstatcache(true, $file);
		if (filegroup($file) !== 0) {
			echo "NOT CHECKED: a group-zero fixture requires a process in group 0\n";
			return;
		}
		$foreignUid = fileowner($file) === 12345 ? 12346 : 12345;
		$noGroups = Permission::doesUserHave($foreignUid, array(), $file, 4);
		$this->assertTrue($noGroups === true,
			'A caller with no groups reads a group-zero file through the other bit');
		$entries = array('empty string' => '', 'null' => null, 'non-numeric' => 'abc',
			'blank' => ' ', 'false' => false, 'non-integer' => '0.5', 'unrelated number' => '4242');
		foreach ($entries as $name => $entry) {
			$this->assertTrue(Permission::doesUserHave($foreignUid, array($entry), $file, 4) === $noGroups,
				'An entry that names no group (' . $name . ') leaves the other class selected');
		}
		$this->assertTrue(Permission::doesUserHave($foreignUid, array('0'), $file, 4) === false,
			'An entry that does name group zero still selects the group class');
	}

	/**
	 * The kernel decides each row: a child drops to an unprivileged account
	 * either outside group 0 or inside it, and reads the fixture for real. A
	 * gid list is a claim about the caller, so each shape is compared with the
	 * identity it claims to be -- shapes naming no group against the child
	 * outside group 0, shapes naming group zero against the child inside it.
	 */
	public function testGroupClassSelectionAgreesWithTheKernel()
	{
		if (posix_getuid() !== 0) {
			echo "NOT CHECKED: a group-zero fixture owned by another user, and a probe from outside group 0, both need root\n";
			return;
		}
		$file = $this->createFile('kernel-oracle', 0004);
		$this->assertTrue(fileowner($file) === 0 && filegroup($file) === 0,
			'The kernel oracle fixture is owned by user and group zero');
		$shapes = array('no groups' => array(), 'empty string' => array(''), 'null' => array(null),
			'non-numeric' => array('abc'), 'blank' => array(' '), 'false' => array(false),
			'non-integer' => array('0.5'), 'unrelated number' => array('4242'),
			'group zero string' => array('0'), 'group zero int' => array(0),
			'duplicated group zero' => array('0', '0'), 'minus one then duplicates' => array(-1, '0', '0'));
		$claimsGroupZero = array('group zero string', 'group zero int', 'duplicated group zero',
			'minus one then duplicates');
		foreach (array(0004, 0040, 0044, 0000) as $mode) {
			$this->assertTrue(chmod($file, $mode), 'The oracle fixture takes mode ' . decoct($mode));
			$probes = array();
			foreach (array('outside', 'inside') as $where) {
				$probes[$where] = $this->probeAsUnprivileged($file, $where, $shapes);
				if ($probes[$where] === null) {
					return;
				}
			}
			$this->assertTrue(!in_array(0, $probes['outside']['groups'], true),
				'The outside probe really left group 0: ' . implode(',', $probes['outside']['groups']));
			$this->assertTrue(in_array(0, $probes['inside']['groups'], true),
				'The inside probe really kept group 0: ' . implode(',', $probes['inside']['groups']));
			foreach ($shapes as $name => $ignored) {
				$where = in_array($name, $claimsGroupZero, true) ? 'inside' : 'outside';
				$this->assertTrue($probes[$where]['answers'][$name] === $probes[$where]['kernel'],
					'Mode ' . decoct($mode) . ', ' . $name . ': the helper answers '
					. var_export($probes[$where]['answers'][$name], true) . ' and the kernel '
					. var_export($probes[$where]['kernel'], true));
			}
		}
	}

	/**
	 * Runs doesUserHave() and a real fopen() in a child that has dropped to an
	 * unprivileged account, so both sides of a row see the same identity.
	 * `$where` is 'outside' to replace the supplementary groups with the
	 * account's own, or 'inside' to keep group 0.
	 */
	protected function probeAsUnprivileged($file, $where, $shapes)
	{
		$code = <<<'PHP'
require $argv[1];
$account = null;
foreach (array('nobody', 'daemon', 'bin', 'www-data') as $name) {
    $candidate = posix_getpwnam($name);
    if ($candidate && ($candidate['uid'] > 0) && ($candidate['gid'] > 0)) {
        $account = $candidate;
        break;
    }
}
if (!$account) {
    fwrite(STDERR, "no unprivileged account to drop to\n");
    exit(3);
}
if ($argv[3] === 'outside') {
    if (!posix_initgroups($account['name'], $account['gid']) || !posix_setgid($account['gid'])) {
        fwrite(STDERR, "could not leave group 0\n");
        exit(4);
    }
} else if (!posix_setgid(0)) {
    fwrite(STDERR, "could not keep group 0\n");
    exit(5);
}
if (!posix_setuid($account['uid'])) {
    fwrite(STDERR, "could not drop the user\n");
    exit(6);
}
$uid = posix_getuid();
$handle = @fopen($argv[2], 'r');
$kernel = ($handle !== false);
if ($handle) fclose($handle);
$answers = array();
foreach (unserialize($argv[4], array('allowed_classes' => false)) as $name => $gids) {
    $answers[$name] = Permission::doesUserHave($uid, $gids, $argv[2], 4);
}
echo serialize(array('uid' => $uid, 'groups' => posix_getgroups(), 'kernel' => $kernel, 'answers' => $answers));
PHP;
		$run = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d', 'display_errors=stderr',
			'-r', $code, realpath(__DIR__ . '/../../php/utility/permission.php'), $file, $where,
			serialize($shapes))));
		$this->assertTrue($run['code'] === 0 && !$run['timedOut'],
			'The ' . $where . ' probe completes: ' . $run['err']);
		$this->assertTrue($run['err'] === '', 'The ' . $where . ' probe emits no diagnostics: ' . $run['err']);
		$probe = @unserialize($run['out'], array('allowed_classes' => false));
		$this->assertTrue(is_array($probe) && isset($probe['answers']) && ($probe['uid'] > 0),
			'The ' . $where . ' probe reports an unprivileged identity and a full row');
		return (is_array($probe) && isset($probe['answers']) && ($probe['uid'] > 0)) ? $probe : null;
	}

	public function testPermission()
	{
		// doesUserHave() answers "would this uid/gids have rwx on the file" from
		// the file's stat. Probe with the running process's own uid/gids -- it
		// owns the fixtures -- rather than a hardcoded 1000, so the test does not
		// depend on which uid runs it.
		$uid = fileowner($this->fixtureDir);
		$gids = array(filegroup($this->fixtureDir));
		// A symlink target is resolved against the link's own directory, so a
		// fixture built from a relative base points nowhere and every answer
		// below would be about a broken link rather than about permissions.
		$this->assertTrue(is_dir($this->getDir('dir3')), 'Symlinked fixture resolves');
		$this->assertTrue(is_dir($this->getDir('dir4')), 'Relative symlinked fixture resolves');
		$this->assertTrue(Permission::doesUserHave($uid, $gids, $this->getDir('dir1'), 0x0007), 'User has permission for directory');
		$this->assertTrue(Permission::doesUserHave($uid, $gids, $this->getDir('dir3'), 0x0007), 'User has permission for symlinked directory');
		$this->assertTrue(Permission::doesUserHave($uid, $gids, $this->getDir('dir4'), 0x0007), 'User has permission for relative symlinked directory');
	}
}
