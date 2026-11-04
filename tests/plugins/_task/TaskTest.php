<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/_task/task.php');

// A payload class for the params test: unserialize() must not construct it, so
// its __wakeup() must never run.
class TaskPayload
{
    public static $woken = false;
    public function __wakeup()
    {
        self::$woken = true;
    }
}

class TaskTest extends TestCase
{
    // No system hands out a pid this large: a kill for it reaches the shell
    // and signals nothing.
    public const UNUSED_PID = '999999999';

    protected $tasks = null;
    protected $marker = null;
    protected $outside = null;

    public function setUp()
    {
        $this->tasks = FileUtil::getSettingsPath() . '/tasks';
        $this->marker = FileUtil::getSettingsPath() . '/task-test-marker';
        $this->outside = FileUtil::getSettingsPath() . '/task-test-outside';
        @mkdir($this->tasks, 0777, true);
        @unlink($this->marker);
        TaskPayload::$woken = false;
    }

    public function tearDown()
    {
        @unlink($this->marker);
        $this->removeDir($this->outside);
    }

    public function testKillRunsNothingFromANonNumericPidFile()
    {
        list($id, $dir) = $this->makeTask(self::UNUSED_PID . '$(touch ' . $this->marker . ')');
        rTask::kill($id);
        sleep(1);
        $this->assertEquals(
            false,
            file_exists($this->marker),
            'The contents of a pid file cannot reach the shell',
        );
        $this->assertEquals(false, is_dir($dir), 'The task is still cleaned up');
    }

    public function testKillRunsNothingForPidOne()
    {
        // A stand-in pgrep that records the pids kill asks about. It lists
        // no children, so nothing is signalled through it.
        $pgrep = tempnam(sys_get_temp_dir(), 'task-test-pgrep-');
        $record = $pgrep . '.argv';
        file_put_contents($pgrep, "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> " . escapeshellarg($record) . "\n");
        chmod($pgrep, 0755);
        $previous = isset($GLOBALS['pathToExternals']['pgrep']) ? $GLOBALS['pathToExternals']['pgrep'] : null;
        $GLOBALS['pathToExternals']['pgrep'] = $pgrep;
        try {
            list($id, $dir) = $this->makeTask('1');
            rTask::kill($id);
            $this->assertEquals(false, is_dir($dir), 'The task of pid 1 is still cleaned up');
            list($id) = $this->makeTask(self::UNUSED_PID);
            rTask::kill($id);
            list($id, $dir) = $this->makeTask(null);
            $pids = $this->startTree('sleep 39 & echo $!; echo $$; wait', $dir);
            rTask::kill($id);
            $this->assertEquals(
                '-P ' . end($pids) . "\n",
                file_exists($record) ? file_get_contents($record) : '',
                'Of pid 1, a pid no process has and a task shell, only the task shell reaches pgrep',
            );
        } finally {
            if ($previous === null) {
                unset($GLOBALS['pathToExternals']['pgrep']);
            } else {
                $GLOBALS['pathToExternals']['pgrep'] = $previous;
            }
            foreach ([$pgrep, $record] as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testKillStopsEveryChildOfTheTask()
    {
        // A task shell with three children, as a pipeline would have.
        list($id, $dir) = $this->makeTask(null);
        $pids = $this->startTree('sleep 31 & echo $!; sleep 32 & echo $!; sleep 33 & echo $!; echo $$; wait', $dir);
        $this->assertEquals(4, count($pids), 'A task shell and three children were started');
        $this->assertKillStops($id, $pids);
    }

    public function testKillStopsTheProgramsAPluginScriptStarts()
    {
        // A task shell running a plugin script, which runs the program
        // that does the work, as create's inner.sh runs createtorrent.php.
        list($id, $dir) = $this->makeTask(null);
        $pids = $this->startTree('echo $$; sh -c ' . escapeshellarg('echo $$; sleep 34 & echo $!; wait') . '; sleep 35', $dir);
        $this->assertEquals(3, count($pids), 'A task shell, a script and its program were started');
        $this->assertKillStops($id, $pids);
    }

    // The pid file outlives the task shell: a task that ended without
    // writing its status leaves it behind, and the number may since have
    // gone to a process of no task. Its command line may even name the
    // task's start.sh, which is still there, as that of the shell running
    // killtree.sh does.
    public function testKillLeavesAProcessThatIsNotTheTaskAlone()
    {
        list($id, $dir) = $this->makeTask(null);
        file_put_contents($dir . '/start.sh', "#!/bin/sh\n");
        $pids = $this->startTree('echo $$; sh -c ' . escapeshellarg('echo $$; sleep 36 & echo $!; wait') . '; sleep 37', null, $dir . '/start.sh');
        $this->assertEquals(3, count($pids), 'A shell, its child and a grandchild were started');
        file_put_contents($dir . '/pid', $pids[0]);
        $this->killThroughProc($id);
        foreach ($pids as $pid) {
            $this->assertEquals(true, $this->leftAlone($pid), 'Process ' . $pid . ' is left alone');
        }
        $this->assertEquals(false, is_dir($dir), 'The task is still cleaned up');
    }

    public function testKillLeavesTheShellOfAnotherTaskAlone()
    {
        list($other, $otherDir) = $this->makeTask(null);
        $pids = $this->startTree('sleep 38 & echo $!; echo $$; wait', $otherDir);
        $this->assertEquals(2, count($pids), 'Another task shell and its child were started');
        // The stale task's own start.sh is still there, too.
        list($id, $dir) = $this->makeTask(end($pids));
        file_put_contents($dir . '/start.sh', "#!/bin/sh\n");
        $this->killThroughProc($id);
        foreach ($pids as $pid) {
            $this->assertEquals(true, $this->leftAlone($pid), 'Process ' . $pid . ' of the other task is left alone');
        }
        $this->assertKillStops($other, $pids);
    }

    // start() and kill() may reach the task's directory by different paths:
    // when only one of them is given RU_PROFILE_PATH, say, and the default
    // share is a symbolic link to it.
    public function testKillStopsATaskStartedThroughAnotherPathToItsDirectory()
    {
        list($id, $dir) = $this->makeTask(null);
        $link = tempnam(sys_get_temp_dir(), 'task-test-link-');
        unlink($link);
        symlink($dir, $link);
        try {
            $pids = $this->startTree('echo $$; sh -c ' . escapeshellarg('echo $$; sleep 40 & echo $!; wait') . '; sleep 41', $link);
            $this->assertEquals(3, count($pids), 'A task shell, a script and its program were started');
            $this->assertKillStops($id, $pids);
        } finally {
            unlink($link);
        }
    }

    public function testCheckDoesNotConstructClassesNamedByTheParamsFile()
    {
        list($id, $dir) = $this->makeTask('999999', serialize(new TaskPayload()));
        $ret = rTask::check($id);
        $this->assertEquals(
            false,
            TaskPayload::$woken,
            'No class from the params file is woken',
        );
        $this->assertEquals(
            true,
            $ret['params'] instanceof __PHP_Incomplete_Class,
            'The object arrives inert',
        );
        $this->removeDir($dir);
    }

    public function testCheckOfAPathOutsideTheTasksDirectoryFindsNoTask()
    {
        mkdir($this->outside, 0777, true);
        file_put_contents($this->outside . '/pid', '424242');
        file_put_contents($this->outside . '/params', serialize(['leaked' => 'data']));
        $ret = rTask::check('../' . basename($this->outside));
        $this->assertEquals(0, $ret['pid'], 'No pid is read from outside the tasks directory');
        $this->assertEquals([], $ret['params'], 'No params are read from there either');
        $this->assertEquals(true, is_file($this->outside . '/pid'), 'And nothing there was touched');
    }

    protected function removeDir($dir)
    {
        foreach (['pid', 'status', 'flags', 'params'] as $file) {
            @unlink($dir . '/' . $file);
        }
        @rmdir($dir);
    }

    // A task the plugin itself could have produced: a real id, and the flags
    // value that makes rTask::run() use the local shell rather than rtorrent.
    // With no $pid, the pid file is left to the task's start.sh.
    protected function makeTask($pid, $params = null)
    {
        $id = uniqid(time(), true);
        $dir = rTask::formatPath($id);
        mkdir($dir, 0777, true);
        if (!is_null($pid)) {
            file_put_contents($dir . '/pid', $pid);
        }
        file_put_contents($dir . '/flags', rTask::FLG_RUN_AS_WEB);
        if (!is_null($params)) {
            file_put_contents($dir . '/params', $params);
        }
        return [$id, $dir];
    }

    // The state letter in /proc/<pid>/stat, or null once the process is gone.
    protected function processState($pid)
    {
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        return ($stat === false) ? null : substr($stat, strrpos($stat, ')') + 2, 1);
    }

    // A process counts as finished once it is gone or a zombie.
    protected function running($pid)
    {
        $state = $this->processState($pid);
        return $state !== null && $state !== 'Z';
    }

    // A process no signal reached: it is neither gone, a zombie nor stopped
    // by a signal (state T).
    protected function leftAlone($pid)
    {
        return !in_array($this->processState($pid), [null, 'Z', 'T', 't'], true);
    }

    // Starts a shell whose processes write their pids to a file, and returns
    // those pids. Given a task's directory, the shell is that task's: the
    // script is its start.sh, which writes the pid file first and is started
    // by rTask::run() as rTask::start() starts one. Otherwise the shell
    // belongs to no task, and is given $argument after its script. The
    // processes end on their own; the tests signal none of them.
    protected function startTree($script, $dir = null, $argument = '')
    {
        $pids = tempnam(sys_get_temp_dir(), 'task-test-pids-');
        $script = '{ ' . $script . "\n} > " . Utility::escapeShellArg($pids);
        if (is_null($dir)) {
            exec('sh -c ' . Utility::escapeShellArg($script) . ' sh ' . Utility::escapeShellArg($argument) . ' >/dev/null 2>&1 &');
        } else {
            file_put_contents($dir . '/start.sh', "#!/bin/sh\necho \$\$ > \"\$(dirname \"\$0\")\"/pid\n" . $script . "\n");
            chmod($dir . '/start.sh', 0755);
            rTask::run($dir . '/start.sh', rTask::FLG_RUN_AS_WEB);
        }
        sleep(1);
        $lines = array_map('intval', file($pids, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        unlink($pids);
        return $lines;
    }

    // Kills task $id through a stand-in pgrep -P that reads /proc (the test
    // images have no procps).
    protected function killThroughProc($id)
    {
        $pgrep = tempnam(sys_get_temp_dir(), 'task-test-pgrep-');
        file_put_contents($pgrep, "#!/bin/sh\n"
            . "awk -v p=\"\$2\" '{ sub(/.*\\) /, \"\"); if (\$2 == p) { n = split(FILENAME, f, \"/\"); print f[3] } }' /proc/[0-9]*/stat 2>/dev/null\n");
        chmod($pgrep, 0755);
        $previous = isset($GLOBALS['pathToExternals']['pgrep']) ? $GLOBALS['pathToExternals']['pgrep'] : null;
        $GLOBALS['pathToExternals']['pgrep'] = $pgrep;
        try {
            rTask::kill($id);
            sleep(1);
        } finally {
            if ($previous === null) {
                unset($GLOBALS['pathToExternals']['pgrep']);
            } else {
                $GLOBALS['pathToExternals']['pgrep'] = $previous;
            }
            unlink($pgrep);
        }
    }

    protected function assertKillStops($id, $pids)
    {
        $this->killThroughProc($id);
        foreach ($pids as $pid) {
            $this->assertEquals(false, $this->running($pid), 'Process ' . $pid . ' is stopped');
        }
    }
}
