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
            $this->assertEquals(
                '-P ' . self::UNUSED_PID . "\n",
                file_exists($record) ? file_get_contents($record) : '',
                'Only the pid other than 1 reaches the shell',
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

    public function testKillStopsTheProcessThePidFileNames()
    {
        $pid = intval(exec('sh -c \'sleep 30 >/dev/null 2>&1 & echo $!\''));
        $this->assertEquals(true, $pid > 0, 'A process to kill was started');
        list($id, $dir) = $this->makeTask($pid);
        rTask::kill($id);
        sleep(1);
        $this->assertEquals(false, posix_kill($pid, 0), 'The named process is gone');
    }

    public function testKillStopsEveryChildOfTheTask()
    {
        // A task shell with three children, as a pipeline would have.
        $pids = $this->startTree('sleep 31 & echo $!; sleep 32 & echo $!; sleep 33 & echo $!; echo $$; wait');
        $this->assertEquals(4, count($pids), 'A task shell and three children were started');
        $this->assertKillStops(end($pids), $pids);
    }

    public function testKillStopsTheProgramsAPluginScriptStarts()
    {
        // A task shell running a plugin script, which runs the program
        // that does the work, as create's inner.sh runs createtorrent.php.
        $pids = $this->startTree('echo $$; sh -c ' . escapeshellarg('echo $$; sleep 34 & echo $!; wait') . '; sleep 35');
        $this->assertEquals(3, count($pids), 'A task shell, a script and its program were started');
        $this->assertKillStops($pids[0], $pids);
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
    protected function makeTask($pid, $params = null)
    {
        $id = uniqid(time(), true);
        $dir = rTask::formatPath($id);
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/pid', $pid);
        file_put_contents($dir . '/flags', rTask::FLG_RUN_AS_WEB);
        if (!is_null($params)) {
            file_put_contents($dir . '/params', $params);
        }
        return [$id, $dir];
    }

    // A process counts as stopped once it is gone or a zombie.
    protected function running($pid)
    {
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        if ($stat === false) {
            return false;
        }
        $state = substr($stat, strrpos($stat, ')') + 2, 1);
        return $state !== 'Z';
    }

    // Starts a task shell whose processes write their pids to a file, and
    // returns those pids, the task shell last.
    protected function startTree($script)
    {
        $pids = tempnam(sys_get_temp_dir(), 'task-test-pids-');
        exec('sh -c ' . escapeshellarg('sh -c ' . escapeshellarg($script) . ' > ' . $pids . ' 2>&1 &') . '; sleep 1');
        $lines = array_map('intval', file($pids, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        unlink($pids);
        return $lines;
    }

    // Kills the task of $parent through a stand-in pgrep -P that reads
    // /proc (the test images have no procps), then checks every pid.
    protected function assertKillStops($parent, $pids)
    {
        $pgrep = tempnam(sys_get_temp_dir(), 'task-test-pgrep-');
        file_put_contents($pgrep, "#!/bin/sh\n"
            . "awk -v p=\"\$2\" '{ sub(/.*\\) /, \"\"); if (\$2 == p) { n = split(FILENAME, f, \"/\"); print f[3] } }' /proc/[0-9]*/stat 2>/dev/null\n");
        chmod($pgrep, 0755);
        $previous = isset($GLOBALS['pathToExternals']['pgrep']) ? $GLOBALS['pathToExternals']['pgrep'] : null;
        $GLOBALS['pathToExternals']['pgrep'] = $pgrep;
        try {
            list($id) = $this->makeTask($parent);
            rTask::kill($id);
            sleep(1);
            foreach ($pids as $pid) {
                $this->assertEquals(false, $this->running($pid), 'Process ' . $pid . ' is stopped');
            }
        } finally {
            if ($previous === null) {
                unset($GLOBALS['pathToExternals']['pgrep']);
            } else {
                $GLOBALS['pathToExternals']['pgrep'] = $previous;
            }
            unlink($pgrep);
            foreach ($pids as $pid) {
                @posix_kill($pid, 9);
            }
        }
    }
}
