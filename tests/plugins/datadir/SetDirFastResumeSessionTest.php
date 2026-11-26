<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../php/ExpectedDiagnostics.php');
require_once(__DIR__ . '/../../php/FakeRtorrentDaemon.php');
require_once(__DIR__ . '/../../php/PhpProcess.php');
require_once(__DIR__ . '/../../php/RtorrentProbeFixture.php');
require_once(__DIR__ . '/../../php/TestTree.php');

class SetDirCopyObserver
{
    private $index = 0;
    private $replies;
    private $uploads;
    private $marker;
    private $exclude;

    public function __construct($replies, $uploads, $marker, $exclude)
    {
        $this->replies = $replies;
        $this->uploads = $uploads;
        $this->marker = $marker;
        $this->exclude = $exclude;
    }

    public function __invoke($body, $methods)
    {
        if (in_array('d.erase', $methods, true)) {
            $files = array_values(array_diff(glob($this->uploads . '/*.torrent'), [$this->exclude]));
            file_put_contents($this->marker, count($files) === 1 ? file_get_contents($files[0]) : '');
        }
        return isset($this->replies[$this->index]) ? $this->replies[$this->index++] : [];
    }
}

/**
 * A fast resume re-adds the download from its copy in the session directory,
 * whose path rtSetDataDir() asks rtorrent for. rtorrent reports it as its
 * own bytes, so a session directory holding a '"' or a '\' still has to be
 * where the copy is read from, and the same goes for the file the download
 * is tied to, read when the session holds no copy. When neither can be read
 * the move falls back to a plain d.set_directory, which is how a wrong path
 * shows.
 */
class SetDirFastResumeSessionTest extends TestCase
{
    public const HASH = '9485646D4F5F05AC4428057712E46FC8189DD1DD';
    public const USER = 'tester';

    private $base;
    private $daemon;

    public function setUp()
    {
        $this->base = sys_get_temp_dir() . '/rutorrent-datadir-fastresume-' . getmypid();
    }

    public function tearDown()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
        TestTree::remove($this->base);
    }

    public function testAFastResumeReadsTheCopyInASessionDirectoryWithAQuoteAndABackslash()
    {
        $this->assertFastResumed('se"ss\\ion');
    }

    public function testAFastResumeReadsTheCopyInASessionDirectoryWithABackslashBeforeAQuote()
    {
        $this->assertFastResumed('a\\"b');
    }

    public function testAFastResumeReadsTheCopyInAPlainSessionDirectory()
    {
        $this->assertFastResumed('session');
    }

    public function testAFastResumeReadsATiedFileWithAQuoteAndABackslashWhenTheSessionHoldsNoCopy()
    {
        $this->assertFastResumed('session', 'ti"ed\\x/t.torrent');
    }

    public function testCopyIsSavedBeforeErase()
    {
        $this->assertFastResumed('session', null, 'observe-copy');
    }

    public function testConfirmedReloadRemovesCopy()
    {
        $this->assertFastResumed('session', null, 'confirmed');
    }

    public function testDelayedReloadRemovesCopy()
    {
        $this->assertFastResumed('session', null, 'delayed');
    }

    public function testUnavailableClockKeepsAndNamesCopy()
    {
        $this->assertFastResumed('session', null, 'no-clock');
    }

    public function testNullClockKeepsAndNamesCopy()
    {
        $this->assertFastResumed('session', null, 'null-clock');
    }

    public function testUnconfirmedReloadKeepsAndNamesCopy()
    {
        $this->assertFastResumed('session', null, 'unconfirmed');
    }

    public function testCopyNeverReplacesUploadedTorrent()
    {
        $this->assertFastResumed('session', null, 'overwrite');
    }

    public function testPartialCopyKeepsSlowPath()
    {
        $this->assertRefused('partial');
    }

    public function testUnwritableCopyKeepsSlowPath()
    {
        $this->assertRefused('copy');
    }

    public function testUnavailableFopenKeepsSlowPath()
    {
        $this->assertRefused('no-fopen');
    }

    public function testUnavailableFwriteKeepsSlowPath()
    {
        $this->assertRefused('no-fwrite');
    }

    public function testPartialCopyWithUnavailableUnlinkKeepsSlowPath()
    {
        $this->assertRefused('partial-no-unlink');
    }

    public function testConfirmedReloadWithFailedUnlinkNamesRecovery()
    {
        $this->assertFastResumed('session', null, 'confirmed-no-unlink');
    }

    public function testCopyNameCollisionKeepsUserFile()
    {
        $this->assertRefused('collision');
    }

    public function testUnavailableRandomBytesKeepsSlowPath()
    {
        $this->assertRefused('no-random');
    }

    public function testFailedCloseKeepsSlowPath()
    {
        $this->assertRefused('close-failed');
    }

    public function testWriteExceptionClosesTheStream()
    {
        $this->assertRefused('write-close');
    }

    public function testFailedSendReturnsFailureEvenWhenHashAppears()
    {
        $this->assertFastResumed('session', null, 'send-false');
    }

    public function testPollingHonoursPauseAndDeadline()
    {
        $this->assertFastResumed('session', null, 'timed');
    }

    public function testDeadlineExpiresDuringPollWithoutPause()
    {
        $this->assertFastResumed('session', null, 'poll-deadline');
    }

    public function testReloadListedOnlyAtTheFinalCheckRemovesCopy()
    {
        $this->assertFastResumed('session', null, 'late');
    }

    public function testReloadNotListedAtTheFinalCheckKeepsAndExplainsCopy()
    {
        $this->assertFastResumed('session', null, 'unlisted');
    }

    public function testFailedFinalCheckKeepsCopy()
    {
        $this->assertFastResumed('session', null, 'final-fault');
    }

    public function testEraseFaultWithTheDownloadStillLoadedRemovesCopy()
    {
        $this->assertFastResumed('session', null, 'erase-loaded', true);
    }

    public function testDifferentTorrentIsNotErased()
    {
        $this->assertRefused('hash');
    }

    public function testInvalidAdditionIsNotErased()
    {
        $this->assertRefused('addition');
    }

    public function testRefusedDirectoryIsNotErased()
    {
        $this->assertRefused('directory');
    }

    public function testRefusedDirectoryRestoresStateWhenLoggingThrows()
    {
        $this->assertRefused('directory-log');
    }

    public function testCopyRefusalRestoresStateWhenLoggingThrows()
    {
        $this->assertRefused('copy-log');
    }

    public function testRetainedRecoverySurvivesLoggingException()
    {
        $this->assertFastResumed('session', null, 'retained-log', true);
    }

    public function testLostEraseReplyKeepsAndNamesCopy()
    {
        $this->assertFastResumed('session', null, 'erase-lost', true);
    }

    public function testEraseRefusalRestoresRunningState()
    {
        $this->assertFastResumed('session', null, 'erase', true);
    }

    public function testEraseExceptionKeepsRecovery()
    {
        $this->assertFastResumed('session', null, 'erase-exception', true);
    }

    public function testLowercaseHashMatchesTheTorrent()
    {
        $this->assertFastResumed('session', null, 'lower-hash');
    }

    private function assertRefused($reason)
    {
        $this->assertFastResumed('session', null, $reason, true);
    }

    /**
     * The download's copy is in $sessionName, or, given $tiedName, only in
     * that file below the test's base.
     */
    private function assertFastResumed($sessionName, $tiedName = null, $refusal = '', $active = false)
    {
        TestTree::remove($this->base);
        $session = $this->base . '/' . $sessionName . '/';
        $dest = $this->base . '/dest';
        $copyFailures = ['partial', 'no-fopen', 'no-fwrite', 'partial-no-unlink', 'collision', 'no-random', 'close-failed', 'write-close'];
        $bytes = $this->torrentBytes($refusal === 'hash' ? 'other' : 'subx', ['f.bin']);
        $hash = self::HASH;
        if ($refusal === 'lower-hash') {
            $hash = strtolower($hash);
        }
        $copy = ($tiedName === null) ? $session . $hash . '.torrent' : $this->base . '/' . $tiedName;
        $tied = ($tiedName === null) ? '' : $copy;
        $this->makeDir($session);
        $this->makeDir($dest);
        if ($refusal !== 'copy' && $refusal !== 'copy-log') {
            $this->makeDir($this->base . '/profile/users/' . self::USER . '/torrents');
        }
        $this->makeDir(dirname($copy));
        file_put_contents($copy, $bytes);
        $this->assertTrue(
            RtorrentProbeFixture::warmSettingsCache($this->base . '/profile/users/' . self::USER, '', $this->base . '/dl', $session),
            'the settings cache is warmed',
        );
        $uploads = $this->base . '/profile/users/' . self::USER . '/torrents';
        $userFile = $uploads . '/' . $hash . '.torrent';
        if ($refusal === 'overwrite') {
            file_put_contents($userFile, 'user-owned');
        }
        $replies = [[$active ? '1' : '0', $active ? '1' : '0', $active ? '1' : '0']];
        if ($active) {
            $replies[] = ['0', '0'];
        }
        $replies[] = [$session, $tied, '', $refusal === 'addition' ? '$bad' : '', ''];
        $replies[] = ['0'];
        // Polls in the whole confirmation window: 50 at 100 ms, then 55 at 1 s.
        $window = ['unconfirmed', 'timed', 'late', 'unlisted', 'final-fault'];
        if (in_array($refusal, ['erase', 'erase-lost', 'erase-exception', 'erase-loaded'], true)) {
            // erase-exception throws before the erase is sent.
            if ($refusal !== 'erase-exception') {
                $replies[] = $refusal === 'erase-lost' ? null : FakeRtorrentDaemon::fault('erase refused');
            }
            // The final check, then the slow path's d.set_directory and d.open, d.start.
            $replies[] = $refusal === 'erase-loaded'
                ? ['0000000000000000000000000000000000000000', self::HASH]
                : ($refusal === 'erase-lost' ? FakeRtorrentDaemon::fault('unreachable') : []);
            $replies[] = $refusal === 'erase-lost' ? FakeRtorrentDaemon::fault('download missing') : ['0'];
            $replies[] = ['0', '0'];
        } else {
            $replies[] = ['0'];
            $replies[] = $refusal === 'send-false' ? FakeRtorrentDaemon::fault('load refused') : ['0', '0'];
            if (in_array($refusal, $window, true)) {
                $replies = array_merge($replies, array_fill(0, 105, []));
            } elseif (in_array($refusal, ['delayed', 'poll-deadline'], true)) {
                $replies[] = [];
            }
            // The final check; 'unconfirmed' finds the daemon gone.
            if ($refusal === 'late') {
                $replies[] = [self::HASH];
            } elseif ($refusal === 'unlisted') {
                $replies[] = ['0000000000000000000000000000000000000000'];
            } elseif ($refusal === 'final-fault') {
                $replies[] = FakeRtorrentDaemon::fault('busy');
            } elseif (in_array($refusal, ['timed', 'poll-deadline', 'no-clock', 'null-clock', 'retained-log'], true)) {
                $replies[] = [];
            } elseif ($refusal !== 'unconfirmed') {
                $replies[] = [self::HASH];
            }
        }
        if (in_array($refusal, ['observe-copy', 'confirmed', 'overwrite'], true)) {
            $replies = new SetDirCopyObserver($replies, $uploads, $this->base . '/at-erase', $refusal === 'overwrite' ? $userFile : '');
        }
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log', false, [__FILE__]);
        $run = $this->runPhp($this->driver(
            'echo rtSetDataDir(' . var_export($hash, true) . ', ' . var_export($dest, true) . ', true, false, true, false) ? "ok" : "failed";',
            $this->daemon->port(),
            $refusal,
        ), substr($refusal, -4) === '-log');
        $bodies = implode("\n", $this->daemon->bodies());
        $this->daemon->stop();
        $this->daemon = null;
        $this->assertEquals(in_array($refusal, ['unconfirmed', 'unlisted', 'final-fault', 'erase-lost', 'retained-log', 'no-clock', 'null-clock', 'send-false', 'timed', 'poll-deadline'], true) ? 'failed' : 'ok', $run['printed'], 'rtSetDataDir() returns the expected result: ' . $run['printed'] . $run['errors']);
        if ($refusal === 'retained-log') {
            $this->assertEquals(1, count(glob($uploads . '/datadir-recovery-*.torrent')), 'recovery remains after a logging exception');
            return;
        }
        if ($refusal === 'confirmed-no-unlink') {
            $recoveries = glob($uploads . '/datadir-recovery-*.torrent');
            $this->assertEquals(1, count($recoveries), 'confirmed reload retains the copy when unlink returns false');
            $this->assertTrue(strpos((string) @file_get_contents($this->base . '/application.log'), 'datadir: recovery copy for ' . $hash . ' could not be removed; delete ' . $recoveries[0]) !== false, 'the retained path is logged as one to delete');
            return;
        }
        if ($refusal === 'erase-exception') {
            $recoveries = glob($uploads . '/datadir-recovery-*.torrent');
            $this->assertEquals(1, count($recoveries), 'recovery remains after an erase exception');
            $this->assertTrue(strpos((string) @file_get_contents($this->base . '/application.log'), $recoveries[0]) !== false, 'recovery path is logged');
            return;
        }
        if ($refusal === 'directory-log' || $refusal === 'copy-log') {
            $this->assertTrue(strpos($bodies, '<methodName>d.erase</methodName>') === false, 'download was not erased');
            $this->assertTrue(strpos($bodies, '<methodName>d.directory.set</methodName>') !== false && strpos($bodies, '<string>d.start</string>') !== false, 'slow path restores the running download');
            return;
        }
        if (in_array($refusal, ['observe-copy', 'confirmed', 'overwrite'], true)) {
            $this->assertEquals($bytes, @file_get_contents($this->base . '/at-erase'), 'the full copy exists before erase');
        }
        if (in_array($refusal, ['confirmed', 'delayed', 'late', 'erase-loaded'], true)) {
            $this->assertEquals([], glob($uploads . '/*.torrent'), 'confirmed reload removes the copy');
            if ($refusal === 'erase-loaded') {
                // The refused erase is logged as a fault; the copy is not mentioned.
                $this->assertTrue(strpos((string) @file_get_contents($this->base . '/application.log'), 'recovery') === false, 'a removed copy is not logged');
            } else {
                $this->assertTrue(!is_file($this->base . '/application.log'), 'confirmed reload is not logged');
            }
            if ($refusal === 'delayed') {
                $this->assertTrue(substr_count($bodies, '<methodName>download_list</methodName>') >= 2, 'reload is polled again');
            }
            if ($refusal === 'late') {
                $this->assertEquals(106, substr_count($bodies, '<methodName>download_list</methodName>'), 'the final check follows the whole window');
            }
            if ($refusal === 'erase-loaded') {
                $this->assertTrue(strpos((string) @file_get_contents($this->base . '/application.log'), 'erase refused') !== false, 'the erase fault is what was logged');
                $this->assertTrue(strpos($bodies, '<methodName>d.erase</methodName>') !== false, 'erase was attempted and refused');
                $this->assertTrue(strpos($bodies, '<methodName>d.directory.set</methodName>') !== false && strpos($bodies, '<string>d.start</string>') !== false, 'slow path restores the running download');
                $this->assertTrue(strpos($bodies, '<string>load.raw_start</string>') === false, 'a loaded download is not added again');
                return;
            }
        }
        if (in_array($refusal, ['unconfirmed', 'unlisted', 'final-fault', 'erase', 'erase-lost', 'no-clock', 'null-clock', 'timed', 'poll-deadline'], true)) {
            $recoveries = glob($uploads . '/datadir-recovery-*.torrent');
            $this->assertEquals(1, count($recoveries), 'one recovery is retained');
            $recovery = count($recoveries) === 1 ? $recoveries[0] : '';
            $this->assertEquals($bytes, $recovery === '' ? false : @file_get_contents($recovery), 'unconfirmed reload keeps the complete copy');
            $log = @file_get_contents($this->base . '/application.log');
            $this->assertTrue($log !== false && strpos($log, $hash) !== false && $recovery !== '' && strpos($log, $recovery) !== false, 'the log names the hash and copy');
            $this->assertTrue(
                $log !== false && $recovery !== ''
                    && strpos($log, 'datadir: reload not confirmed for ' . $hash . '; recovery copy kept at ' . $recovery . ' - delete it if the download is listed') !== false,
                'the log says what to do with the copy: ' . $log,
            );
        }
        if ($refusal === 'erase-lost') {
            $this->assertTrue(strpos($bodies, '<methodName>d.erase</methodName>') !== false, 'erase was attempted before the reply was lost');
            return;
        }
        if ($refusal === 'poll-deadline') {
            $this->assertEquals(2, substr_count($bodies, '<methodName>download_list</methodName>'), 'one poll before the deadline, then the final check');
            $this->assertTrue(!is_file($this->base . '/pauses'), 'no pause after the deadline');
            return;
        }
        if ($refusal === 'timed') {
            $this->assertEquals(106, substr_count($bodies, '<methodName>download_list</methodName>'), 'polling stops at the deadline, then the final check');
            $this->assertEquals(str_repeat("100000\n", 50) . str_repeat("1000000\n", 55), file_get_contents($this->base . '/pauses'), 'polls are 100 ms apart for 5 s, then 1 s apart up to 60 s');
            return;
        }
        if ($refusal === 'send-false') {
            $this->assertEquals([], glob($uploads . '/datadir-recovery-*.torrent'), 'confirmed hash removes the copy after a refused load');
            $this->assertTrue(strpos($bodies, '<methodName>download_list</methodName>') !== false, 'the hash was checked after the load fault');
            return;
        }
        if ($refusal === 'no-clock' || $refusal === 'null-clock') {
            $this->assertEquals(1, substr_count($bodies, '<methodName>download_list</methodName>'), 'unavailable clock prevents polling; only the final check is made');
            return;
        }
        if ($refusal === 'overwrite') {
            $this->assertEquals('user-owned', file_get_contents($userFile), 'the uploaded torrent remains unchanged');
        }
        if ($refusal === 'collision') {
            $collisions = glob($uploads . '/datadir-recovery-*.torrent');
            $this->assertEquals(1, count($collisions), 'the competing upload remains');
            $this->assertEquals('competing upload', count($collisions) === 1 ? file_get_contents($collisions[0]) : '', 'the competing upload is untouched');
        }
        if ($refusal === 'partial') {
            $this->assertEquals([], glob($uploads . '/datadir-recovery-*.torrent'), 'a partial copy is removed');
        }
        if ($refusal === 'partial-no-unlink') {
            $partialCopies = glob($uploads . '/datadir-recovery-*.torrent');
            $this->assertEquals(1, count($partialCopies), 'the partial copy remains when unlink is unavailable');
            $this->assertEquals(1, count($partialCopies) === 1 ? filesize($partialCopies[0]) : 0, 'the retained copy is incomplete');
            $this->assertTrue(strpos((string) @file_get_contents($this->base . '/application.log'), $partialCopies[0]) !== false, 'the retained path is logged');
        }
        if ($refusal === 'write-close') {
            $this->assertEquals('closed', @file_get_contents($this->base . '/closed'), 'a write exception closes the opened stream');
        }
        if ((in_array($refusal, ['hash', 'addition', 'directory', 'erase', 'copy'], true) || in_array($refusal, $copyFailures, true))) {
            if ($refusal === 'erase') {
                $this->assertTrue(strpos($bodies, '<methodName>d.erase</methodName>') !== false, 'erase was attempted and refused');
            } else {
                $this->assertTrue(strpos($bodies, '<methodName>d.erase</methodName>') === false, $refusal . ' must not erase the download');
                $log = @file_get_contents($this->base . '/application.log');
                $this->assertTrue(
                    $log !== false && strpos($log, 'fast resume refused (' . (in_array($refusal, $copyFailures, true) ? 'copy' : $refusal) . ') ' . $hash) !== false,
                    $refusal . ' is recorded in the application log',
                );
            }
            if ($active) {
                $this->assertTrue(strpos($bodies, '<methodName>d.directory.set</methodName>') !== false, $refusal . ' sets the requested directory');
                $this->assertTrue(strpos($bodies, '<string>d.start</string>') !== false, $refusal . ' restores the running state');
            }
            return;
        }
        $this->assertTrue(
            (strpos($bodies, '<methodName>d.erase</methodName>') !== false)
                && (strpos($bodies, '<string>load.raw_start</string>') !== false),
            'and re-adds the download from ' . $copy . '; requests sent: ' . $bodies,
        );
    }

    private function driver($code, $port, $refusal = '')
    {
        $file = $this->base . '/drive.php';
        $partial = in_array($refusal, ['partial', 'no-fopen', 'no-fwrite', 'partial-no-unlink', 'collision', 'no-random', 'no-clock', 'null-clock', 'close-failed', 'write-close', 'timed', 'poll-deadline', 'erase-exception', 'retained-log', 'confirmed-no-unlink', 'unconfirmed', 'late', 'unlisted', 'final-fault'], true);
        if ($partial) {
            // Namespace only this plugin's functions so the copy write can be
            // shortened without also breaking the SCGI transport's fwrite.
            $source = file_get_contents($this->repoRoot() . '/plugins/datadir/util_setdir.php');
            $source = preg_replace('/^<\?php/', '<?php namespace CopyFault;', $source, 1);
            $source = str_replace(['catch (Throwable', 'new RuntimeException'], ['catch (\\Throwable', 'new \\RuntimeException'], $source);
            if ($refusal === 'confirmed-no-unlink') {
                $source .= "\n" . 'function unlink($path) { return false; }';
            } elseif ($refusal === 'erase-exception') {
                $source .= "\n" . 'function rtExec($cmds, $hash, $dbg) { if ($cmds === "d.erase") throw new \Error("erase"); return \rtExec($cmds, $hash, $dbg); }';
            } elseif ($refusal === 'poll-deadline') {
                $source .= "\n" . '$GLOBALS["clock_calls"] = 0; function microtime($float) { return ++$GLOBALS["clock_calls"] < 3 ? 100.0 : 161.0; }'
                    . ' function usleep($delay) { file_put_contents(' . var_export($this->base . '/pauses', true) . ', $delay); }';
            } elseif (in_array($refusal, ['timed', 'unconfirmed', 'late', 'unlisted', 'final-fault'], true)) {
                // A clock that only moves when the code pauses, by the pause.
                $source .= "\n" . '$GLOBALS["copy_clock"] = 0;'
                    . ' function microtime($asFloat) { return 100.0 + $GLOBALS["copy_clock"] / 1000000; }'
                    . ' function usleep($delay) { file_put_contents(' . var_export($this->base . '/pauses', true) . ', $delay . "\\n", FILE_APPEND); $GLOBALS["copy_clock"] += $delay; }';
            } elseif ($refusal === 'close-failed') {
                $source .= "\n" . 'function fclose($stream) { \\fclose($stream); return false; }';
            } elseif ($refusal === 'write-close') {
                $source .= "\n" . 'function fwrite($stream, $bytes) { throw new \\Error("unavailable"); }'
                    . ' function fclose($stream) { file_put_contents(' . var_export($this->base . '/closed', true) . ', "closed"); return \\fclose($stream); }';
            } elseif ($refusal === 'no-clock' || $refusal === 'retained-log') {
                $source .= "\n" . 'function microtime($asFloat) { throw new \\Error("unavailable"); }';
            } elseif ($refusal === 'null-clock') {
                $source .= "\n" . 'function microtime($asFloat) { return null; }';
            } elseif ($refusal === 'collision') {
                $source .= "\n" . 'function fopen($path, $mode) { file_put_contents($path, "competing upload"); return \\fopen($path, $mode); }';
            } elseif ($refusal === 'no-random') {
                $source .= "\n" . 'function random_bytes($count) { return null; }';
            } elseif ($refusal === 'no-fopen') {
                $source .= "\n" . 'function fopen($path, $mode) { throw new \\Error("unavailable"); }';
            } elseif ($refusal === 'no-fwrite') {
                $source .= "\n" . 'function fwrite($stream, $bytes) { throw new \\Error("unavailable"); }';
            } else {
                $source .= "\n" . 'function fwrite($stream, $bytes) { return \\fwrite($stream, substr($bytes, 0, 1)); }';
                if ($refusal === 'partial-no-unlink') {
                    $source .= "\n" . 'function unlink($path) { throw new \\Error("unavailable"); }';
                }
            }
            file_put_contents($this->base . '/partial.php', $source);
            $code = str_replace('rtSetDataDir(', '\\CopyFault\\rtSetDataDir(', $code);
        }
        file_put_contents($file, "<?php\n"
            . '$_SERVER[\'REMOTE_USER\'] = ' . var_export(self::USER, true) . ';'
            . ' $_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->base . '/profile', true) . ";\n"
            . 'require_once(' . var_export($this->repoRoot() . '/conf/config.php', true) . ");\n"
            . '$log_file = ' . var_export($this->base . '/application.log', true) . ";\n"
            . '$scgi_host = "127.0.0.1"; $scgi_port = ' . (int) $port . '; $rpcTimeOut = 5; $rpcLogCalls = false;' . "\n"
            . ($refusal === 'overwrite' ? '$overwriteUploadedTorrents = true;' : '')
            . (in_array($refusal, ['directory', 'directory-log'], true) ? '$topDirectory = ' . var_export($this->base . '/elsewhere/', true) . ';' : '')
            // util_setdir.php's own requires are relative to the plugin's directory.
            . 'chdir(' . var_export($this->repoRoot() . '/plugins/datadir', true) . ");\n"
            . ($partial
                ? 'require_once("../../php/xmlrpc.php"); require_once("../../php/Torrent.php"); require_once("../../php/rtorrent.php"); require_once("./util_rt.php");'
                    . ' foreach (["Torrent", "FileUtil", "rTorrent", "rTorrentSettings", "rXMLRPCRequest"] as $class) class_alias($class, ' . var_export('CopyFault\\', true) . ' . $class);'
                    . ' require_once(' . var_export($this->base . '/partial.php', true) . ");\n"
                : 'require_once(' . var_export($this->repoRoot() . '/plugins/datadir/util_setdir.php', true) . ");\n")
            . $code . "\n");
        return $file;
    }

    private function runPhp($file, $disableLog = false)
    {
        $mask = umask(022);
        try {
            // PHP 7.4 warns when the disabled fputs() is called; PHP 8 throws instead.
            $run = ExpectedDiagnostics::expect(
                ($disableLog && PHP_VERSION_ID < 80000) ? ['/^Warning: fputs\(\) has been disabled for security reasons /'] : [],
                function () use ($file, $disableLog) {
                    return PhpProcess::run(
                        array_merge(
                            PhpProcess::sameIni(),
                            $disableLog ? ['-d', 'disable_functions=fputs'] : [],
                            ['-d', 'display_errors=stderr'],
                            [$file],
                        ),
                        ['env' => ['LC_ALL' => 'C']],
                    );
                },
            );
        } finally {
            umask($mask);
        }
        return ['printed' => $run['out'], 'errors' => $run['err']];
    }

    private function makeDir($dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            throw new RuntimeException('Could not create fixture directory ' . $dir);
        }
    }

    private function repoRoot()
    {
        return realpath(__DIR__ . '/../../..');
    }

    private function bstr($s)
    {
        return strlen($s) . ':' . $s;
    }

    private function torrentBytes($root, $names)
    {
        $files = '';
        foreach ($names as $n) {
            $files .= 'd' . $this->bstr('length') . 'i12e' . $this->bstr('path') . 'l' . $this->bstr($n) . 'e' . 'e';
        }
        return 'd' . $this->bstr('announce') . $this->bstr('http://tracker.invalid/announce')
            . $this->bstr('info') . 'd' . $this->bstr('files') . 'l' . $files . 'e'
            . $this->bstr('name') . $this->bstr($root)
            . $this->bstr('piece length') . 'i32768e'
            . $this->bstr('pieces') . $this->bstr(str_repeat("\x01", 20)) . 'e' . 'e';
    }
}
