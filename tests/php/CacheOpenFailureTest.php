<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-cache-open-test-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/QuietCall.php');
require_once(__DIR__ . '/../../php/cache.php');

class CacheOpenFailurePayload
{
    public $hash = 'open-failure-test.dat';
    public $value = '';
}

// A cache file whose lock or temporary file cannot be opened is a failed
// store: set() and remove() return false and leave the file as it was. The
// reason goes to the ruTorrent log, and no PHP warning is raised, so nothing
// of it reaches a response or the web server's error log.
class CacheOpenFailureTest extends TestCase
{
    use QuietCall;

    private $profilePath;
    private $log;
    private $previousLog;

    public function setUp()
    {
        global $log_file;

        $this->profilePath = $_ENV['RU_PROFILE_PATH'];
        $this->log = $this->profilePath . '/application.log';
        $this->previousLog = $log_file;
        $log_file = $this->log;
    }

    public function tearDown()
    {
        global $log_file;

        $log_file = $this->previousLog;
        if (is_dir(FileUtil::getSettingsPath())) {
            chmod(FileUtil::getSettingsPath(), 0700);
        }
        $this->removeTree($this->profilePath);
    }

    public function testStoreWhoseLockIsADirectoryFailsWithoutAWarning()
    {
        $path = $this->seed('stored');
        $bytes = file_get_contents($path);
        $this->assertTrue(unlink($path . '.lock') && mkdir($path . '.lock', 0700), 'a directory stands where the lock file goes');

        $payload = new CacheOpenFailurePayload();
        $payload->value = 'replacement';
        list($stored, $errors) = self::quietly(function () use ($payload) {
            return (new rCache())->set($payload);
        });

        $this->assertEquals(false, $stored, 'the store is refused');
        $this->assertEquals([], $errors, 'no PHP diagnostic is raised: ' . json_encode($errors));
        $this->assertEquals($bytes, file_get_contents($path), 'the cache file is left as it was');
        $this->assertLogged('/^\\[[^]]+\\] rCache: open-failure-test\\.dat\\.lock cannot be opened: .*Is a directory$/m');
    }

    public function testRemoveWhoseLockIsADirectoryFailsWithoutAWarning()
    {
        $path = $this->seed('kept');
        $this->assertTrue(unlink($path . '.lock') && mkdir($path . '.lock', 0700), 'a directory stands where the lock file goes');

        list($removed, $errors) = self::quietly(function () {
            return (new rCache())->remove(new CacheOpenFailurePayload());
        });

        $this->assertEquals(false, $removed, 'the removal is refused');
        $this->assertEquals([], $errors, 'no PHP diagnostic is raised: ' . json_encode($errors));
        $this->assertTrue(is_file($path), 'the cache file is still there');
        $this->assertLogged('/^\\[[^]]+\\] rCache: open-failure-test\\.dat\\.lock cannot be opened: .*Is a directory$/m');
    }

    public function testStoreWhoseTemporaryFileCannotBeCreatedFailsWithoutAWarning()
    {
        $path = $this->seed('stored');
        $bytes = file_get_contents($path);
        // The lock file exists already and stays writable; only a new file in
        // the directory, the temporary copy, cannot be created.
        $settings = FileUtil::getSettingsPath();
        $this->assertTrue(chmod($settings, 0500), 'the settings directory is made read-only');
        $this->assertTrue(!is_writable($settings), 'precondition: not running as root');

        $payload = new CacheOpenFailurePayload();
        $payload->value = 'replacement';
        list($stored, $errors) = self::quietly(function () use ($payload) {
            return (new rCache())->set($payload);
        });
        chmod($settings, 0700);

        $this->assertEquals(false, $stored, 'the store is refused');
        $this->assertEquals([], $errors, 'no PHP diagnostic is raised: ' . json_encode($errors));
        $this->assertEquals($bytes, file_get_contents($path), 'the cache file is left as it was');
        $this->assertLogged('/^\\[[^]]+\\] rCache: open-failure-test\\.dat\\.[^ ]+\\.tmp cannot be opened: .*Permission denied$/m');
        $this->assertEquals(
            ['open-failure-test.dat', 'open-failure-test.dat.lock'],
            array_values(array_diff(scandir($settings), ['.', '..'])),
            'no temporary file is left behind',
        );
    }

    // The runner calls setUp() once per class, so each case starts from an
    // empty profile here. A good store comes first, so a refusal has something
    // to leave alone, and the lock file exists from then on.
    private function seed($value)
    {
        $this->removeTree($this->profilePath);
        FileUtil::makeDirectory(FileUtil::getSettingsPath());
        $payload = new CacheOpenFailurePayload();
        $payload->value = $value;
        $this->assertTrue((new rCache())->set($payload), 'the seeding store succeeds');
        return FileUtil::getSettingsPath() . '/' . $payload->hash;
    }

    private function assertLogged($pattern)
    {
        $lines = is_file($this->log) ? file_get_contents($this->log) : '';
        $this->assertTrue(
            preg_match($pattern, $lines) === 1,
            'the refusal is logged with the file it concerns and why: ' . json_encode($lines),
        );
        if (is_file($this->log)) {
            unlink($this->log);
        }
    }

    private function removeTree($path)
    {
        if (!is_dir($path) || is_link($path)) {
            if (file_exists($path) || is_link($path)) {
                unlink($path);
            }
            return;
        }
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $this->removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }
}
