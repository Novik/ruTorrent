<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');
require_once(__DIR__ . '/../../php/xmlrpc.php');
require_once(__DIR__ . '/FakeRtorrentDaemon.php');

/**
 * run() reports a fault when the answer is one: a fault struct, with its
 * faultCode and faultString members, either as the whole answer or as one call
 * of a system.multicall. The text "faultCode" in a value is not one -- a
 * torrent's name, a file such as Extras/faultCode.nfo. rtorrent escapes "<" in
 * every string it sends, so a value cannot carry the member's markup, not even
 * a path that reads "<name>faultCode</name>".
 *
 * The answers were captured over the socket of rTorrent 0.16.27 (tinyxml2) and
 * 0.9.8 (xmlrpc-c), both holding one stopped torrent named "faultCode notes"
 * with the files Extras/faultCode.nfo and <name>faultCode</name>.
 */
class XMLRPCFaultDetectionTest extends TestCase
{
    public const HASH = '035FB2CA08B9F6DDAC6EA6666C79258D7797EF74';

    // d.multicall2 "" default d.hash= d.name=
    public const NAMES_016 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 291\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data>"
        . "<value><array><data>"
        . "<value><string>035FB2CA08B9F6DDAC6EA6666C79258D7797EF74</string></value>"
        . "<value><string>faultCode notes</string></value></data></array></value>"
        . "</data></array></value></param></params></methodResponse>";

    // f.multicall <hash> "" f.path=
    public const PATHS_016 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 333\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data>"
        . "<value><array><data><value><string>Extras/faultCode.nfo</string></value>"
        . "</data></array></value><value><array><data>"
        . "<value><string>&lt;name&gt;faultCode&lt;/name&gt;</string></value></data></array></value>"
        . "</data></array></value></param></params></methodResponse>";

    // system.multicall of d.name on the loaded hash and on an unknown one.
    public const MULTICALL_016 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 430\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\"?><methodResponse><params><param><value><array><data>"
        . "<value><array><data><value><string>faultCode notes</string></value></data></array></value>"
        . "<value><struct><member><name>faultCode</name><value><i8>-500</i8></value></member>"
        . "<member><name>faultString</name>"
        . "<value><string>invalid parameters: info-hash not found</string></value></member></struct></value>"
        . "</data></array></value></param></params></methodResponse>";

    // The same three on rTorrent 0.9.8.
    public const NAMES_098 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 330\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
        . "<methodResponse>\r\n"
        . "<params>\r\n"
        . "<param><value><array><data>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>035FB2CA08B9F6DDAC6EA6666C79258D7797EF74</string></value>\r\n"
        . "<value><string>faultCode notes</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "</data></array></value></param>\r\n"
        . "</params>\r\n"
        . "</methodResponse>\r\n";

    public const PATHS_098 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 376\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
        . "<methodResponse>\r\n"
        . "<params>\r\n"
        . "<param><value><array><data>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>Extras/faultCode.nfo</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>&lt;name&gt;faultCode&lt;/name&gt;</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "</data></array></value></param>\r\n"
        . "</params>\r\n"
        . "</methodResponse>\r\n";

    public const MULTICALL_098 = "Status: 200 OK\r\n"
        . "Content-Type: text/xml\r\n"
        . "Content-Length: 465\r\n"
        . "\r\n"
        . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
        . "<methodResponse>\r\n"
        . "<params>\r\n"
        . "<param><value><array><data>\r\n"
        . "<value><array><data>\r\n"
        . "<value><string>faultCode notes</string></value>\r\n"
        . "</data></array></value>\r\n"
        . "<value><struct>\r\n"
        . "<member><name>faultCode</name>\r\n"
        . "<value><i4>-501</i4></value></member>\r\n"
        . "<member><name>faultString</name>\r\n"
        . "<value><string>Could not find info-hash.</string></value></member>\r\n"
        . "</struct></value>\r\n"
        . "</data></array></value></param>\r\n"
        . "</params>\r\n"
        . "</methodResponse>\r\n";

    public const ANSWERS = [
        '0.16.27' => [self::NAMES_016, self::PATHS_016, self::MULTICALL_016,
            'invalid parameters: info-hash not found'],
        '0.9.8' => [self::NAMES_098, self::PATHS_098, self::MULTICALL_098, 'Could not find info-hash.'],
    ];

    private $previousSettings;
    private $previousGlobals;
    private $base;
    private $daemon;

    public function setUp()
    {
        $this->previousGlobals = GlobalSettingsFixture::scgi();
        // run() asks the settings to adapt old command names and to size a
        // multicall; a fixed version keeps it from asking a daemon first.
        $settings = (new ReflectionClass('rTorrentSettings'))->newInstanceWithoutConstructor();
        $settings->iVersion = 0x904;
        $settings->apiVersion = 0;
        $settings->aliases = [];
        $this->previousSettings = self::swapSettings($settings);

        $GLOBALS['scgi_host'] = '127.0.0.1';
        $GLOBALS['rpcTimeOut'] = 5;
        $GLOBALS['rpcTransferTimeOut'] = 5;
        unset($GLOBALS['rpcMaxResponseBytes']);
        $GLOBALS['rpcLogCalls'] = false;
        $GLOBALS['rpcLogFaults'] = false;

        $this->base = sys_get_temp_dir() . '/rutorrent-fault-detection-' . getmypid();
        @mkdir($this->base, 0700, true);
        $GLOBALS['log_file'] = $this->base . '/errors.log';
        $GLOBALS['tempDirectory'] = $this->base;
        $GLOBALS['tempDirectory_init_done'] = false;
    }

    public function tearDown()
    {
        try {
            self::swapSettings($this->previousSettings);
            $this->stopDaemon();
            foreach (glob($this->base . '/*') as $file) {
                @unlink($file);
            }
            @rmdir($this->base);
        } finally {
            $this->previousGlobals->restore();
        }
    }

    public function testAValueThatSaysFaultCodeIsNotAFault()
    {
        foreach (self::ANSWERS as $version => $answers) {
            $this->daemon([FakeRtorrentDaemon::raw($answers[0])]);
            $request = new rXMLRPCRequest(new rXMLRPCCommand('d.multicall2', ['', 'default', 'd.hash=', 'd.name=']));
            $this->assertTrue(
                ($request->success() === true) && ($request->fault === false),
                $version . ': a torrent named "faultCode notes" is listed, not refused: ' . $request->faultString,
            );
            $this->assertTrue(
                $request->val === [self::HASH, 'faultCode notes'],
                $version . ': with its hash and its name: ' . json_encode($request->val),
            );
        }
    }

    public function testAPathThatReadsLikeTheFaultMarkupIsNotAFault()
    {
        foreach (self::ANSWERS as $version => $answers) {
            $this->daemon([FakeRtorrentDaemon::raw($answers[1])]);
            $request = new rXMLRPCRequest(new rXMLRPCCommand('f.multicall', [self::HASH, '', 'f.path=']));
            $this->assertTrue(
                ($request->success() === true) && ($request->fault === false),
                $version . ': the files are listed, not refused: ' . $request->faultString,
            );
            $this->assertTrue(
                $request->val === ['Extras/faultCode.nfo', '<name>faultCode</name>'],
                $version . ': both paths, as the torrent names them: ' . json_encode($request->val),
            );
        }
    }

    public function testAFailedCallOfAMulticallIsStillAFault()
    {
        foreach (self::ANSWERS as $version => $answers) {
            $this->daemon([FakeRtorrentDaemon::raw($answers[2])]);
            $request = new rXMLRPCRequest([
                new rXMLRPCCommand('d.name', self::HASH),
                new rXMLRPCCommand('d.name', str_repeat('0', 40)),
            ]);
            $this->assertTrue($request->run() === true, $version . ': the multicall is answered');
            $this->assertTrue(
                ($request->fault === true) && ($request->faultString === $answers[3]),
                $version . ': and its second call is reported as a fault, with its reason: '
                . $request->faultString,
            );
            $this->assertTrue(
                $this->daemon->calls() === ['d.name', 'd.name'],
                $version . ': both calls went out in one request: ' . implode(' ', $this->daemon->calls()),
            );
        }
    }

    /** Install $settings as rTorrentSettings::get()'s answer, returning the previous one. */
    private static function swapSettings($settings)
    {
        return Closure::bind(function ($settings) {
            $previous = rTorrentSettings::$theSettings;
            rTorrentSettings::$theSettings = $settings;
            return $previous;
        }, null, 'rTorrentSettings')->__invoke($settings);
    }

    private function stopDaemon()
    {
        if ($this->daemon !== null) {
            $this->daemon->stop();
            $this->daemon = null;
        }
    }

    /** A daemon that answers the next requests with $replies, in order. */
    private function daemon($replies)
    {
        $this->stopDaemon();
        $this->daemon = new FakeRtorrentDaemon($replies, $this->base . '/calls.log');
        $GLOBALS['scgi_port'] = $this->daemon->port();
    }
}
