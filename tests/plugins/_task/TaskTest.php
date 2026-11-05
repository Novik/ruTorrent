<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/GlobalSettingsFixture.php');
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

// Stands for the path of pgrep and counts how often it is read: kill() reads
// it only to build the command line that runs killtree.sh.
class TaskPgrepLookups
{
    public $count = 0;
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function __toString()
    {
        $this->count++;
        return $this->path;
    }
}

// Settings that record the events pushed instead of running the hooks of
// plugins.
class TaskEventRecorder extends rTorrentSettings
{
    public $events = [];

    public function pushEvent($ename, $prm)
    {
        $this->events[] = $ename;
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

    public function testKillRunsNothingForPidOneOrBelow()
    {
        // A stand-in pgrep that records the pids kill asks about. It lists
        // no children, so nothing is signalled through it.
        $pgrep = tempnam(sys_get_temp_dir(), 'task-test-pgrep-');
        $record = $pgrep . '.argv';
        file_put_contents($pgrep, "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> " . Utility::escapeShellArg($record) . "\n");
        chmod($pgrep, 0755);
        // killtree.sh does not ask pgrep about a pid that does not run the
        // task, so whether such a pid got as far as the script shows in the
        // lookups of pgrep's path instead.
        $lookups = new TaskPgrepLookups($pgrep);
        $previous = $this->usePgrep($lookups);
        try {
            foreach (['1', '0', '-1'] as $pid) {
                list($id, $dir) = $this->makeTask($pid);
                rTask::kill($id);
                $this->assertEquals(false, is_dir($dir), 'The task of pid ' . $pid . ' is still cleaned up');
            }
            $this->assertEquals(0, $lookups->count, 'None of pids 1, 0 and -1 reaches killtree.sh');
            list($id) = $this->makeTask(self::UNUSED_PID);
            rTask::kill($id);
            list($id, $dir) = $this->makeTask(null);
            $pids = $this->startTree('sleep 39 & echo $!; echo $$; wait', $dir);
            rTask::kill($id);
            $this->assertEquals(
                '-P ' . end($pids) . "\n",
                file_exists($record) ? file_get_contents($record) : '',
                'Of a pid no process has and a task shell, only the task shell reaches pgrep',
            );
        } finally {
            $this->usePgrep($previous);
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

    // rTask::run() is given the path of the task's start.sh, which the shell
    // has to take as it is: a space, a quote, a backslash, a dollar sign or a
    // backquote in it is not shell syntax.
    public function testATaskWhosePathHoldsShellSyntaxStartsAndStops()
    {
        list($id, $dir) = $this->makeTask(null);
        $link = $this->linkWithShellSyntax($dir);
        try {
            $pids = $this->startTree('sleep 42 & echo $!; echo $$; wait', $link);
            $this->assertEquals(2, count($pids), 'A task shell and its child were started through ' . $link);
            $this->assertKillStops($id, $pids);
        } finally {
            unlink($link);
        }
    }

    // The start.sh that start() writes finds its directory from $0, so it
    // has to take a path with shell syntax as it is too. By default the
    // tasks directory is below the install directory, whose path it shares.
    public function testATaskStartedInADirectoryWhosePathHoldsShellSyntaxRuns()
    {
        $link = $this->linkWithShellSyntax(FileUtil::getProfilePath());
        $previousPath = self::swapProfilePath($link);
        $previous = self::swapSettings($this->newSettings());
        $dir = null;
        try {
            $task = new rTask(['arg' => 'x', 'requester' => 'test', 'name' => 'test']);
            $ret = $task->start(['sleep 43'], rTask::FLG_DEFAULT | rTask::FLG_RUN_AS_WEB);
            $dir = rTask::formatPath($ret['no']);
            sleep(1);
            $pid = intval(@file_get_contents($dir . '/pid'));
            $this->assertEquals(true, $this->running($pid), 'The task under ' . $link . ' runs and wrote its pid');
            $this->assertKillStops($ret['no'], [$pid]);
        } finally {
            self::swapSettings($previous);
            self::swapProfilePath($previousPath);
            unlink($link);
            // A finished kill has removed it already.
            if (!is_null($dir) && is_dir(rTask::formatPath(basename($dir)))) {
                rTask::clean(rTask::formatPath(basename($dir)));
            }
        }
    }

    // Through rtorrent, a task that is waited for, as mediainfo and dump
    // start theirs, runs as part of the command line given to sh -c, so its
    // path is quoted there as well. The daemon here is a stand-in: the
    // arguments it is asked to execute are run as rtorrent runs them.
    public function testThroughRtorrentAWaitedTaskWhosePathHoldsShellSyntaxStarts()
    {
        list($id, $dir) = $this->makeTask(null);
        file_put_contents($dir . '/start.sh', "#!/bin/sh\necho \$\$ > \"\$(dirname \"\$0\")\"/pid\n");
        chmod($dir . '/start.sh', 0755);
        $link = $this->linkWithShellSyntax($dir);
        $globals = GlobalSettingsFixture::scgi();
        $previous = self::swapSettings($this->newSettings());
        $daemon = new FakeRtorrentDaemon([['0']], sys_get_temp_dir() . '/task-test-daemon-' . getmypid() . '.log');
        try {
            $this->useDaemon($daemon);
            $this->assertEquals(0, rTask::run($link . '/start.sh', rTask::FLG_WAIT), 'rtorrent reports the task run');
            $bodies = $daemon->bodies();
            $this->assertEquals(['execute_nothrow'], $daemon->calls(), 'rtorrent is asked to execute it');
            preg_match_all('`<string>(.*?)</string>`s', isset($bodies[0]) ? $bodies[0] : '', $strings);
            $arguments = array_map('htmlspecialchars_decode', $strings[1]);
            if (count($arguments)) {
                proc_close(proc_open($arguments, [], $pipes));
            }
            $this->assertEquals(true, is_file($dir . '/pid'), 'The task ran: ' . json_encode($arguments));
        } finally {
            $daemon->stop();
            self::swapSettings($previous);
            $globals->restore();
            unlink($link);
            rTask::clean($dir);
        }
    }

    // killtree.sh stops each process before it lists that process's
    // children, so that none can start another that escapes the list, and
    // kills none of them before it has stopped them all. Should the script
    // die on the way, those it stopped stay stopped, and the task, whose
    // kill did not finish, is kept, so that a later kill still finds them.
    // Here the script dies at its third listing, that of the program's
    // children.
    public function testAKillThatDiesOnTheWayKeepsTheTaskForALaterKill()
    {
        list($id, $dir) = $this->makeTask(null);
        $pids = $this->startTree('echo $$; sh -c ' . escapeshellarg('echo $$; sleep 43 & echo $!; wait') . '; sleep 44', $dir);
        $this->assertEquals(3, count($pids), 'A task shell, a script and its program were started');
        $calls = tempnam(sys_get_temp_dir(), 'task-test-calls-');
        // The third time it is run, this pgrep stops every shell running
        // killtree.sh from its parent up, and then kills them all at once.
        $dies = <<<'SH'
            echo >> CALLS
            if [ "$(wc -l < CALLS)" -eq 3 ] ; then
                p=$PPID
                while [ "$(tr '\000' '\n' < /proc/$p/cmdline | sed -n 2p)" -ef KILLTREE ] ; do
                    kill -STOP $p
                    shells="$shells $p"
                    p=$(awk '{ sub(/.*\) /, ""); print $2 }' /proc/$p/stat)
                done
                kill -9 $shells
                exit 0
            fi
            SH;
        $pgrep = $this->newPgrep(strtr($dies, [
            'CALLS' => Utility::escapeShellArg($calls),
            'KILLTREE' => Utility::escapeShellArg(__DIR__ . '/../../../plugins/_task/killtree.sh'),
        ]) . "\n" . $this->procPgrep());
        $previous = $this->usePgrep($pgrep);
        try {
            rTask::kill($id);
            sleep(1);
        } finally {
            $this->usePgrep($previous);
            unlink($pgrep);
            unlink($calls);
        }
        foreach ($pids as $pid) {
            $this->assertEquals(true, in_array($this->processState($pid), ['T', 't'], true), 'Process ' . $pid . ' is left stopped');
        }
        $this->assertEquals(true, is_dir($dir), 'A kill that did not finish keeps the task');
        $this->assertKillStops($id, $pids);
        $this->assertEquals(false, is_dir($dir), 'A later kill removes the task');
        foreach ($pids as $pid) {
            if (in_array($this->processState($pid), ['T', 't'], true)) {
                // Let it run to its end, as the others do.
                exec('kill -CONT ' . $pid);
            }
        }
    }

    // Once killtree.sh has stopped the whole tree, it kills each process
    // after its descendants, the task shell last: should the script die
    // between two of those kills, a later kill still finds the task shell
    // and, below it, what is left. A shell that runs the script with a
    // function in place of the builtin kill records the order.
    public function testKilltreeKillsEachProcessAfterItsDescendants()
    {
        list($id, $dir) = $this->makeTask(null);
        $pids = $this->startTree('echo $$; sh -c ' . escapeshellarg('echo $$; sleep 48 & echo $!; wait') . '; sleep 49', $dir);
        $this->assertEquals(3, count($pids), 'A task shell, a script and its program were started');
        $pgrep = $this->newPgrep($this->procPgrep());
        $record = tempnam(sys_get_temp_dir(), 'task-test-kills-');
        try {
            exec('sh -c ' . Utility::escapeShellArg('kill() { echo "$*" >> ' . Utility::escapeShellArg($record) . '; command kill "$@"; }; . "$0"')
                . ' ' . Utility::escapeShellArg(__DIR__ . '/../../../plugins/_task/killtree.sh')
                . ' ' . Utility::escapeShellArg($pgrep) . ' ' . $pids[0] . ' ' . Utility::escapeShellArg($dir . '/start.sh'), $output, $status);
            sleep(1);
            $kills = file($record, FILE_IGNORE_NEW_LINES);
        } finally {
            unlink($pgrep);
            unlink($record);
            rTask::clean($dir);
        }
        list($shell, $script, $program) = $pids;
        $this->assertEquals(0, $status, 'killtree.sh finishes');
        $this->assertEquals(
            ['-STOP ' . $shell, '-STOP ' . $script, '-STOP ' . $program, '-9 ' . $program . ' ' . $script . ' ' . $shell],
            $kills,
            'Each process is stopped before its children, then all are killed at once, the program first and the task shell last: ' . json_encode($kills),
        );
        foreach ($pids as $pid) {
            $this->assertEquals(false, $this->running($pid), 'Process ' . $pid . ' is stopped');
        }
    }

    // When pgrep is missing, or fails for the task shell or for a process
    // below it, killtree.sh kills nothing: every process runs on, and the
    // task is kept and its kill logged. A kill that can list them stops it,
    // and only that kill pushes TaskKill.
    public function testKillKeepsATaskWhoseChildrenItCannotList()
    {
        $failing = tempnam(sys_get_temp_dir(), 'task-test-pgrep-');
        chmod($failing, 0755);
        $globals = new GlobalSettingsFixture(['log_file']);
        $GLOBALS['log_file'] = tempnam(sys_get_temp_dir(), 'task-test-log-');
        $settings = $this->newSettings();
        $previousSettings = self::swapSettings($settings);
        try {
            foreach (['is missing', 'fails', 'fails below the task shell'] as $case) {
                list($id, $dir) = $this->makeTask(null, serialize([]));
                $pids = $this->startTree('echo $$; sh -c ' . escapeshellarg('echo $$; sleep 45 & echo $!; wait') . '; sleep 46', $dir);
                $this->assertEquals(3, count($pids), 'A task shell, a script and its program were started');
                if ($case == 'is missing') {
                    $pgrep = sys_get_temp_dir() . '/task-test-no-pgrep';
                } elseif ($case == 'fails') {
                    file_put_contents($failing, "#!/bin/sh\nexit 2\n");
                    $pgrep = $failing;
                } else {
                    // It fails for the script, which it lists before a
                    // process that has ended: a walk that went on past the
                    // script would meet no other failure.
                    file_put_contents($failing, "#!/bin/sh\n"
                        . '[ "$2" = ' . $pids[1] . " ] && exit 2\n"
                        . '[ "$2" = ' . $pids[0] . " ] && printf '%s\\n' " . $pids[1] . ' ' . self::UNUSED_PID . " && exit 0\n"
                        . $this->procPgrep());
                    $pgrep = $failing;
                }
                $settings->events = [];
                $previous = $this->usePgrep($pgrep);
                try {
                    rTask::kill($id);
                    sleep(1);
                } finally {
                    $this->usePgrep($previous);
                }
                foreach ($pids as $pid) {
                    $this->assertEquals(true, $this->leftAlone($pid), 'Process ' . $pid . ' runs on when pgrep ' . $case);
                }
                $this->assertEquals(true, is_dir($dir), 'The task is kept when pgrep ' . $case);
                $this->assertEquals(
                    true,
                    strpos(file_get_contents($GLOBALS['log_file']), '] _task: kill of task ' . $id . " did not finish (status 1), the task is kept\n") !== false,
                    'Its kill is logged when pgrep ' . $case,
                );
                $this->assertEquals([], $settings->events, 'No TaskKill is pushed when pgrep ' . $case);
                $this->assertKillStops($id, $pids);
                $this->assertEquals(false, is_dir($dir), 'and removed once it is stopped');
                $this->assertEquals(['TaskKill'], $settings->events, 'and TaskKill is pushed then');
            }
        } finally {
            self::swapSettings($previousSettings);
            unlink($GLOBALS['log_file']);
            $globals->restore();
            unlink($failing);
        }
    }

    // Through rtorrent, kill() reads the wait status of sh that rtorrent
    // answers with. A request rtorrent does not answer, and sh that did not
    // exit with 0, keep the task, and the log gives the status as a shell
    // would.
    // The task's id here is not of the shape uniqid() gives, as a plugin
    // may choose: the log names the task as its directory does, without
    // the line break.
    public function testThroughRtorrentKillKeepsATaskItCouldNotStop()
    {
        list($id, $dir) = $this->makeTask(self::UNUSED_PID, serialize([]), "task\n" . uniqid());
        $globals = GlobalSettingsFixture::scgi();
        $settings = $this->newSettings();
        $previous = self::swapSettings($settings);
        $GLOBALS['log_file'] = tempnam(sys_get_temp_dir(), 'task-test-log-');
        // No answer; sh exited with 1; sh was killed by SIGKILL; sh was
        // killed by SIGSEGV (11) and dumped core, which sets 0x80.
        $daemon = new FakeRtorrentDaemon([null, [256], [9], [0x80 | 11]], sys_get_temp_dir() . '/task-test-daemon-' . getmypid() . '.log');
        try {
            $this->useDaemon($daemon);
            foreach ([-1, 1, 137, 139] as $status) {
                rTask::kill($id, 0);
                $this->assertEquals(true, is_dir($dir), 'The task is kept after status ' . $status);
                $this->assertEquals(
                    true,
                    strpos(file_get_contents($GLOBALS['log_file']), '] _task: kill of task ' . rTask::formatId($id) . ' did not finish (status ' . $status . "), the task is kept\n") !== false,
                    'Its kill is logged with status ' . $status,
                );
            }
            $this->assertEquals([], $settings->events, 'No TaskKill is pushed');
        } finally {
            $daemon->stop();
            self::swapSettings($previous);
            unlink($GLOBALS['log_file']);
            $globals->restore();
            if (is_dir($dir)) {
                rTask::clean($dir);
            }
        }
    }

    // A listed process may end on its own before killtree.sh gets to it,
    // and pgrep may list one that has: a signal to a process that is gone
    // is no failure, and the task is removed.
    public function testKillRemovesATaskWhoseListedProcessEndedBeforeItsTurn()
    {
        list($id, $dir) = $this->makeTask(null);
        $pids = $this->startTree('sleep 47 & echo $!; echo $$; wait', $dir);
        $this->assertEquals(2, count($pids), 'A task shell and its child were started');
        $pgrep = $this->newPgrep('[ "$2" = ' . end($pids) . ' ] && echo ' . self::UNUSED_PID . "\n" . $this->procPgrep());
        $previous = $this->usePgrep($pgrep);
        try {
            rTask::kill($id);
            sleep(1);
        } finally {
            $this->usePgrep($previous);
            unlink($pgrep);
        }
        foreach ($pids as $pid) {
            $this->assertEquals(false, $this->running($pid), 'Process ' . $pid . ' is stopped');
        }
        $this->assertEquals(false, is_dir($dir), 'The task is removed');
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

    // A task the plugin itself could have produced: by default a real id,
    // and the flags value that makes rTask::run() use the local shell rather
    // than rtorrent. With no $pid, the pid file is left to the task's
    // start.sh.
    protected function makeTask($pid, $params = null, $id = null)
    {
        $id = is_null($id) ? uniqid(time(), true) : $id;
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

    // A symbolic link to $dir whose name holds what a shell reads as syntax.
    protected function linkWithShellSyntax($dir)
    {
        $link = sys_get_temp_dir() . '/task-test \'q\' "q" \\ $HOME `true` ' . uniqid();
        symlink($dir, $link);
        return $link;
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

    // Makes $pgrep the pgrep kill() runs, and returns the one it replaces,
    // null for none, which this takes back as well.
    protected function usePgrep($pgrep)
    {
        $previous = isset($GLOBALS['pathToExternals']['pgrep']) ? $GLOBALS['pathToExternals']['pgrep'] : null;
        if ($pgrep === null) {
            unset($GLOBALS['pathToExternals']['pgrep']);
        } else {
            $GLOBALS['pathToExternals']['pgrep'] = $pgrep;
        }
        return $previous;
    }

    // The body of a pgrep -P that reads /proc, since the test images have no
    // procps. Like pgrep, it fails with 1 when there are no children, and it
    // skips a process that ends while it reads: awk would stop at its file.
    protected function procPgrep()
    {
        return "awk -v p=\"\$2\" 'BEGIN { for (i = 1; i < ARGC; i++) if ((getline s < ARGV[i]) > 0) { close(ARGV[i]); sub(/.*\\) /, \"\", s); split(s, f, \" \"); if (f[2] == p) { split(ARGV[i], d, \"/\"); print d[3]; found = 1 } } exit !found }' /proc/[0-9]*/stat\n";
    }

    // Writes a pgrep that runs $body with sh, and returns its path.
    protected function newPgrep($body)
    {
        $pgrep = tempnam(sys_get_temp_dir(), 'task-test-pgrep-');
        file_put_contents($pgrep, "#!/bin/sh\n" . $body);
        chmod($pgrep, 0755);
        return $pgrep;
    }

    // Kills task $id through the pgrep of procPgrep().
    protected function killThroughProc($id)
    {
        $pgrep = $this->newPgrep($this->procPgrep());
        $previous = $this->usePgrep($pgrep);
        try {
            rTask::kill($id);
            sleep(1);
        } finally {
            $this->usePgrep($previous);
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

    // Settings of rtorrent 0.9.4 that record the events pushed.
    protected function newSettings()
    {
        $settings = (new ReflectionClass('TaskEventRecorder'))->newInstanceWithoutConstructor();
        $settings->iVersion = 0x904;
        $settings->apiVersion = 0;
        $settings->aliases = [];
        return $settings;
    }

    // Points the requests to rtorrent at $daemon.
    protected function useDaemon($daemon)
    {
        $GLOBALS['scgi_host'] = '127.0.0.1';
        $GLOBALS['scgi_port'] = $daemon->port();
        $GLOBALS['rpcTimeOut'] = 5;
        $GLOBALS['rpcTransferTimeOut'] = 5;
        $GLOBALS['rpcLogCalls'] = false;
        $GLOBALS['rpcLogFaults'] = false;
    }

    private static function swapProfilePath($path)
    {
        return Closure::bind(function ($path) {
            $previous = FileUtil::$profilePathInstance;
            FileUtil::$profilePathInstance = $path;
            return $previous;
        }, null, 'FileUtil')->__invoke($path);
    }

    private static function swapSettings($settings)
    {
        return Closure::bind(function ($settings) {
            $previous = rTorrentSettings::$theSettings;
            rTorrentSettings::$theSettings = $settings;
            return $previous;
        }, null, 'rTorrentSettings')->__invoke($settings);
    }
}
