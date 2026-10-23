<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/PhpProcess.php');
require_once(__DIR__ . '/TestTree.php');
require_once(__DIR__ . '/TestHttpServer.php');
require_once(__DIR__ . '/../../php/publicsuffix.php');

class PublicSuffixTest extends TestCase
{
    private function same($expected, $actual, $message)
    {
        if ($expected !== $actual) throw new RuntimeException($message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }

    private function replaceOne($search, $replacement, $subject)
    {
        $result = str_replace($search, $replacement, $subject, $count);
        if ($count !== 1) throw new RuntimeException('Cookie fault fixture did not replace exactly one expression');
        return $result;
    }

    public function testIcannPrivateWildcardExceptionAndUnknownSuffixes()
    {
        foreach (array('com' => true, 'example.com' => false, 'co.uk' => true, 'example.co.uk' => false,
            'github.io' => true, 'user.github.io' => false, 'a.ck' => true, 'b.a.ck' => false,
            'www.ck' => false, 'b.www.ck' => false, 'unknown-tld-for-this-test' => true) as $domain => $expected)
            $this->same($expected, PublicSuffix::isPublicSuffix($domain), $domain);
    }

    public function testUnsafeDomainsAreRejectedVisibly()
    {
        foreach (array('', '.co.uk', 'co.uk.', 'bad..example', '-bad.example', 'bad-.example',
            'bad/example', '127.0.0.1', '127.000.0.1', '0x7f.0.0.1', '127.0.0.0x1',
            '0177.0.0.1', '2130706433', '0x7f000001', 'täst.example', 'xn--tst-qla.example') as $domain) {
            $threw = false;
            try { PublicSuffix::isPublicSuffix($domain); }
            catch (InvalidArgumentException $error) { $threw = true; }
            $this->assertTrue($threw, 'Unsafe domain must be rejected: ' . $domain);
        }
    }

    // A private copy provides unavailable/damaged files without editing the app.
    // Reflection enters only the cookie owner here; the other suite exercises
    // capture through fetch and both writers, including a real HTTP peer.
    private function failureCase($mode, $disabled = '')
    {
        $root = sys_get_temp_dir() . '/psl-failure-' . getmypid() . '-' . bin2hex(random_bytes(4));
        try {
            mkdir($root . '/profile/settings', 0700, true);
            mkdir($root . '/utility', 0700);
            if (!@copy(__DIR__ . '/../../php/Snoopy.class.inc', $root . '/Snoopy.class.inc')) throw new RuntimeException('Could not copy cookie fixture source');
            symlink(realpath(__DIR__ . '/../../php/utility/urlhost.php'), $root . '/utility/urlhost.php');
            $helper = file_get_contents(__DIR__ . '/../../php/publicsuffix.php');
            // The upstream harness substitutes directory constants even inside strings.
            $readExpression = "@file_get_contents(" . '__' . "DIR__ . '/public_suffix_list.dat')";
            if ($mode === 'null') $helper = $this->replaceOne($readExpression, 'null', $helper);
            if ($mode === 'throw') $helper = $this->replaceOne($readExpression, "(function () { throw new Error('read failed'); })()", $helper);
            if (@file_put_contents($root . '/publicsuffix.php', $helper) !== strlen($helper)) throw new RuntimeException('Could not write PSL fixture');
            if ($mode === 'valid') copy(__DIR__ . '/../../php/public_suffix_list.dat', $root . '/public_suffix_list.dat');
            elseif ($mode === 'unreadable') mkdir($root . '/public_suffix_list.dat', 0700);
            elseif ($mode === 'invalid') file_put_contents($root . '/public_suffix_list.dat', 'invalid');
            elseif ($mode === 'partial') {
                $data = file_get_contents(__DIR__ . '/../../php/public_suffix_list.dat');
                file_put_contents($root . '/public_suffix_list.dat', str_replace("github.io\n", '', $data));
            }
            $program = <<<'CHILD'
    $_ENV['RU_PROFILE_PATH'] = $argv[1] . '/profile';
    $_ENV['RU_LOG_FILE'] = $argv[1] . '/refusal.log';
    require $argv[1] . '/Snoopy.class.inc';
    $client = new Snoopy();
    $client->cookies = array('manual' => 'yes');
    $client->headers = array('Set-Cookie: host=kept; Path=/', 'Set-Cookie: parent=refused; Domain=tracker.example; Path=/');
    $capture = new ReflectionMethod(Snoopy::class, 'captureResponseCookies');
    $header = new ReflectionMethod(Snoopy::class, 'cookieHeaderForRequest');
    if (PHP_VERSION_ID < 80100) { $capture->setAccessible(true); $header->setAccessible(true); }
    $capture->invoke($client, 'https://tracker.example/');
    if ($header->invoke($client, 'https://tracker.example/') !== 'Cookie: manual=yes; host=kept') throw new RuntimeException('PSL failure lost manual/host-only cookies or admitted Domain scope');
    echo "cookie-failure-closed\n";
    CHILD;
            if ($mode === 'null' || ($mode === 'valid' && in_array($disabled, array('file_get_contents', 'hash'), true))) {
                $program = $this->replaceOne('$client = new Snoopy();', 'try { PublicSuffix::isPublicSuffix("tracker.example"); throw new LogicException("Unavailable PSL was accepted"); } catch (RuntimeException $expected) {} $client = new Snoopy();', $program);
            }
            if ($mode === 'expiry') {
                $program = $this->replaceOne("'Set-Cookie: parent=refused; Domain=tracker.example; Path=/'", "'Set-Cookie: calendar=refused; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/', 'Set-Cookie: age=kept; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=60; Path=/'", $program);
                $program = $this->replaceOne('Cookie: manual=yes; host=kept', 'Cookie: manual=yes; host=kept; age=kept', $program);
            }
            if ($mode === 'repair') {
                $program = $this->replaceOne('echo "cookie-failure-closed\\n";', 'copy($argv[2], $argv[1] . "/public_suffix_list.dat"); if (!PublicSuffix::isPublicSuffix("github.io") || !PublicSuffix::isPublicSuffix("a.ck") || PublicSuffix::isPublicSuffix("www.ck")) throw new RuntimeException("Repair did not restore PSL rules"); $capture->invoke($client, "https://tracker.example/"); if ($header->invoke($client, "https://tracker.example/") !== "Cookie: manual=yes; host=kept; parent=refused") throw new RuntimeException("Repair did not recover Domain cookies"); $client->headers = array("Set-Cookie: private=bad; Domain=github.io; Path=/"); $capture->invoke($client, "https://a.github.io/"); if ($header->invoke($client, "https://b.github.io/") !== "Cookie: manual=yes") throw new RuntimeException("Repair did not restore PRIVATE cookie refusal"); echo "cookie-failure-closed\\n";', $program);
            }
            $args = array('-c', __DIR__ . '/../php-test.ini');
            if ($disabled !== '') array_push($args, '-d', 'disable_functions=' . $disabled);
            array_push($args, '-r', $program, $root, realpath(__DIR__ . '/../../php/public_suffix_list.dat'));
            $result = PhpProcess::run($args, array('cwd' => __DIR__ . '/../../php'));
            $this->same(0, $result['code'], $mode . ' child exits cleanly: ' . $result['out'] . $result['err']);
            $this->same(false, $result['timedOut'], 'PSL failure never stalls');
            $this->same("cookie-failure-closed\n", $result['out'], 'PSL failure remains isolated to Domain cookies');
            $this->same('', $result['err'], 'No uncaught disabled function or read error');
            $this->assertTrue(strpos(file_get_contents($root . '/refusal.log'), ($mode === 'expiry' ? 'response-cookie-expiry-unavailable' : 'response-cookie-domain-validation-unavailable')) !== false,
                'The refusal is visible without logging cookie values');
        } finally { TestTree::remove($root); }
    }

    public function testMissingUnreadableInvalidPartialNullAndThrowingListReads()
    {
        foreach (array('missing', 'unreadable', 'invalid', 'partial', 'null', 'throw', 'repair') as $mode)
            $this->failureCase($mode);
    }

    public function testDisabledFileReadAndDigestKeepHostOnlyAndManualRequestsUsable()
    {
        foreach (array('file_get_contents', 'hash', 'filter_var') as $disabled)
            $this->failureCase('valid', $disabled);
    }

    public function testDisabledExpiryParserRefusesCalendarCookiesButHonorsMaxAge()
    {
        $this->failureCase('expiry', 'strtotime');
    }

    public function testAdvisoryLoggerFailuresPreserveHostOnlyAndManualCookies()
    {
        $root = sys_get_temp_dir() . '/cookie-log-fault-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $server = null;
        try {
            mkdir($root . '/profile/settings', 0700, true);
            file_put_contents($root . '/router.php', '<?php header("Set-Cookie: host=kept; Path=/", false); header("Set-Cookie: bad=refused; Domain=com", false); echo $_SERVER["HTTP_COOKIE"] ?? "";');
            $server = TestHttpServer::start($root, array(), PhpProcess::sameIni(), $root . '/router.php');
            $program = <<<'LOGCHILD'
$_ENV['RU_PROFILE_PATH'] = $argv[1] . '/profile';
$_ENV['RU_LOG_FILE'] = $argv[1] . '/application.log';
require $argv[2] . '/php/Snoopy.class.inc';
if (strpos($argv[4], 'wrapper-') === 0) {
    class CookieAdvisoryLog {
        public $context;
        public function url_stat($path, $flags) { return false; }
        public function stream_open($path, $mode, $options, &$openedPath) {
            if ($GLOBALS['logFault'] === 'wrapper-throw') throw new RuntimeException('log-open-failed');
            if ($GLOBALS['logFault'] === 'wrapper-error') throw new Error('log-open-error');
            return $GLOBALS['logFault'] !== 'wrapper-false';
        }
        public function stream_write($data) { return min(3, strlen($data)); }
    }
    $GLOBALS['logFault'] = $argv[4];
    stream_wrapper_register('cookieadvisory', 'CookieAdvisoryLog');
    $log_file = 'cookieadvisory://log';
}
$client = new Snoopy();
$client->block_private = false;
$client->cookies = array('manual' => 'yes');
if (!$client->fetch($argv[3])) throw new RuntimeException('The source fetch was aborted by advisory logging');
$client->setcookies();
if (!$client->fetch($argv[3])) throw new RuntimeException('The reused fetch was aborted by advisory logging');
if ($client->results !== 'manual=yes; host=kept') throw new RuntimeException('A logging failure changed cookie admission or request scope');
echo "advisory-failure-isolated\n";
LOGCHILD;
            foreach (array('fopen', 'fputs', 'touch', 'chmod', 'date_create', 'wrapper-throw', 'wrapper-error', 'wrapper-false', 'wrapper-short') as $fault) {
                if (is_file($root . '/application.log')) unlink($root . '/application.log');
                $args = PhpProcess::sameIni();
                if (strpos($fault, 'wrapper-') !== 0) array_push($args, '-d', 'disable_functions=' . $fault);
                array_push($args, '-r', $program, $root, realpath(__DIR__ . '/../..'), 'http://127.0.0.1:' . $server->port() . '/', $fault);
                $result = PhpProcess::run($args);
                $this->same(0, $result['code'], $fault . ' child exits cleanly: ' . $result['out'] . $result['err']);
                $this->same(false, $result['timedOut'], $fault . ' never stalls a fetch');
                $this->same("advisory-failure-isolated\n", $result['out'], $fault . ' preserves public HTTP behavior');
                $this->same('', $result['err'], $fault . ' emits no uncaught logging warning or failure');
            }
        } finally {
            if ($server !== null) $server->close();
            TestTree::remove($root);
        }
    }

    public function testFixtureSetupFailuresRemoveTheirOwnedTrees()
    {
        $program = <<<'SETUPCHILD'
$caught = false;
try {
    require $argv[1];
    if ($argv[2] === 'psl') {
        $case = new PublicSuffixTest();
        $fixture = new ReflectionMethod(PublicSuffixTest::class, 'failureCase');
        if (PHP_VERSION_ID < 80100) $fixture->setAccessible(true);
        $fixture->invoke($case, 'missing');
    }
} catch (Throwable $error) { $caught = true; }
if (!$caught) throw new RuntimeException('The fixture did not surface its setup failure');
$left = array_values(array_diff(scandir(sys_get_temp_dir()), array('.', '..')));
if ($left) throw new RuntimeException('The fixture leaked ' . count($left) . ' owned setup entries');
echo "setup-failure-cleaned\n";
SETUPCHILD;
        foreach (array(array('psl', 'copy'), array('psl', 'file_put_contents'), array('wire', 'file_put_contents')) as $fault) {
            $root = sys_get_temp_dir() . '/cookie-setup-fault-' . getmypid() . '-' . bin2hex(random_bytes(4));
            try {
                mkdir($root, 0700);
                $file = $fault[0] === 'psl' ? 'PublicSuffixTest.php' : 'SnoopyResponseCookieTest.php';
                $args = array_merge(PhpProcess::sameIni(), array('-d', 'disable_functions=' . $fault[1], '-r', $program, __DIR__ . '/' . $file, $fault[0]));
                $result = PhpProcess::run($args, array('env' => array('TMPDIR' => $root)));
                $this->same(0, $result['code'], $fault[0] . '/' . $fault[1] . ' setup releases its tree: ' . $result['out'] . $result['err']);
                $this->same("setup-failure-cleaned\n", $result['out'], 'Setup failure remains visible and leaves no owned entries');
                $this->same('', $result['err'], 'The expected setup failure is caught by the fixture caller');
            } finally { TestTree::remove($root); }
        }
    }

    public function testAsciiDomainLengthLimit()
    {
        $domain = implode('.', array_fill(0, 4, str_repeat('a', 63)));
        $threw = false;
        try { PublicSuffix::isPublicSuffix($domain); }
        catch (InvalidArgumentException $error) { $threw = true; }
        $this->same(true, $threw, 'A 255-byte ASCII domain is refused');
    }

    public function testBareWildcardSuffixIsPublic()
    {
        $this->same(true, PublicSuffix::isPublicSuffix('ck'), 'The wildcard does not make its bare suffix registrable');
    }

    public function testRaisedCookieLimitsAdmitLargeSessionsOnRealHttp()
    {
        $root = sys_get_temp_dir() . '/cookie-operator-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $server = null;
        try {
            mkdir($root . '/profile/settings', 0700, true);
            mkdir($root . '/utility', 0700);
            $source = file_get_contents(__DIR__ . '/../../php/Snoopy.class.inc');
            foreach (array('MAX_RESPONSE_COOKIES' => 300, 'MAX_DOMAIN_COOKIES' => 50, 'MAX_COOKIE_BYTES' => 4096,
                'MAX_COOKIE_HEADERS' => 100, 'MAX_COOKIE_HEADER_BYTES' => 8192) as $name => $default)
                $source = $this->replaceOne('const ' . $name . ' = ' . $default . ';', 'const ' . $name . ' = PHP_INT_MAX;', $source);
            file_put_contents($root . '/Snoopy.class.inc', $source);
            copy(__DIR__ . '/../../php/publicsuffix.php', $root . '/publicsuffix.php');
            copy(__DIR__ . '/../../php/public_suffix_list.dat', $root . '/public_suffix_list.dat');
            symlink(realpath(__DIR__ . '/../../php/utility/urlhost.php'), $root . '/utility/urlhost.php');
            file_put_contents($root . '/router.php', '<?php if ($_SERVER["REQUEST_URI"] === "/seed") { for ($i = 0; $i < 150; $i++) header("Set-Cookie: c" . $i . "=v; Path=/", false); header("Set-Cookie: sid=" . str_repeat("x", 12000) . "; Path=/", false); } echo $_SERVER["HTTP_COOKIE"] ?? "";');
            $server = TestHttpServer::start($root, array(), PhpProcess::sameIni(), $root . '/router.php');
            $program = <<<'OPERATOR'
$_ENV['RU_PROFILE_PATH'] = $argv[1] . '/profile';
$_ENV['RU_LOG_FILE'] = $argv[1] . '/application.log';
require $argv[1] . '/Snoopy.class.inc';
$client = new Snoopy(); $client->block_private = false; $client->_maxlinelen = 65536;
if (!$client->fetch($argv[2] . '/seed') || !$client->fetch($argv[2] . '/next')) throw new RuntimeException('The raised-limit HTTP session did not complete');
$fields = explode('; ', $client->results);
if (count($fields) !== 151 || !in_array('c149=v', $fields, true) || !in_array('sid=' . str_repeat('x', 12000), $fields, true)) throw new RuntimeException('A documented parser or jar cap still silently restricts the larger session');
echo "raised-limits-151-cookies\n";
OPERATOR;
            $result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-r', $program, $root, 'http://127.0.0.1:' . $server->port())), array('cwd' => __DIR__ . '/../../php'));
            $this->same(0, $result['code'], 'The operator path accepts the old large workload: ' . $result['out'] . $result['err']);
            $this->same("raised-limits-151-cookies\n", $result['out'], 'All 150 short cookies and the 12000-byte value reach the real peer');
            $this->same('', $result['err'], 'PHP_INT_MAX parser and jar limits remain usable');
        } finally {
            if ($server !== null) $server->close();
            TestTree::remove($root);
        }
    }

}
