<?php

/**
 * rRSS::fetch() keeps the ETag and Last-Modified of a feed's answer and sends
 * them back on the next poll as If-None-Match and If-Modified-Since. Snoopy
 * writes rawheaders by hand, and it writes them ahead of the content headers
 * and the Authorization header, so a validator carrying a line terminator
 * ends the header block of the poll that sends it and strands everything
 * behind it outside the request.
 *
 * The feed chooses those bytes, and the poll they ride is the one that carries
 * whatever credentials the feed url holds. The subject is therefore the bytes
 * of the second poll. The real rssFetchURL() and a real Snoopy are driven
 * against a local peer over 127.0.0.1, so no name is resolved and nothing
 * leaves the machine. The credentials are fake.
 */

$httpBlockPrivateNetworks = false;

require_once(__DIR__ . '/../../../plugins/rss/rss.php');

define('RS_USER', 'fakeuser');
define('RS_PASS', 'fakepass');
define('RS_AUTH', 'Authorization: Basic ' . base64_encode(RS_USER . ':' . RS_PASS));

function rsAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function rsFieldLines($wire)
{
    $pos = strpos($wire, "\r\n\r\n");
    $lines = explode("\r\n", $pos === false ? $wire : substr($wire, 0, $pos));
    array_shift($lines);
    return $lines;
}

function rsPeer(array $responses, callable $body)
{
    $worker = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { fwrite(STDERR, $error); exit(1); }
echo stream_socket_get_name($server, false), "\n"; flush();
$responses = json_decode(base64_decode($argv[1]), true);
$requests = array();
while (true) {
    $read = array($server, STDIN); $write = $except = array();
    if (stream_select($read, $write, $except, 5) !== 1) break;
    if (in_array(STDIN, $read, true)) break;
    $peer = stream_socket_accept($server, 0);
    if (!$peer) exit(3);
    stream_set_blocking($peer, false);
    $request = ''; $idle = 0; $deadline = microtime(true) + 2;
    while (microtime(true) < $deadline) {
        $chunk = fread($peer, 65536);
        if ($chunk !== false && $chunk !== '') { $request .= $chunk; $idle = 0; continue; }
        if ($request !== '' && ++$idle > 8) break;
        usleep(10000);
    }
    $requests[] = base64_encode($request);
    stream_set_blocking($peer, true);
    $response = $responses ? array_shift($responses) : base64_encode("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\nok");
    @fwrite($peer, base64_decode($response));
    fclose($peer);
}
fclose($server);
echo json_encode($requests), "\n";
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../../php-test.ini', '-r', $worker,
        base64_encode(json_encode($responses))),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    rsAssert(is_resource($process), 'The local HTTP peer starts');
    try {
        stream_set_timeout($pipes[1], 8);
        $address = fgets($pipes[1]);
        rsAssert(is_string($address) && preg_match('/^127\.0\.0\.1:([0-9]+)\s*$/D', $address, $match) === 1,
            'The peer announces its local port');
        $check = $body((int) $match[1]);
        fwrite($pipes[0], "done\n");
        $json = '';
        while (($line = fgets($pipes[1])) !== false) $json .= $line;
        $requests = json_decode(trim($json), true);
        rsAssert(is_array($requests), 'The peer reports the requests it received');
        $check(array_map('base64_decode', $requests));
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
}

// Two polls of the same feed: the first answer names the validators, the
// second is the poll that carries them back out.
function rsPoll($validatorLines, callable $check)
{
    $feed = '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'
        . '<link>http://example.test/</link></channel></rss>';
    $answer = function ($extra) use ($feed) {
        return base64_encode("HTTP/1.0 200 OK\r\n" . $extra . "Content-Type: text/xml\r\n"
            . "Content-Length: " . strlen($feed) . "\r\n\r\n" . $feed);
    };
    rsPeer(array($answer($validatorLines), $answer('')), function ($port) use ($check) {
        // The url carries credentials, which Snoopy writes after rawheaders.
        $feedUrl = 'http://' . RS_USER . ':' . RS_PASS . '@127.0.0.1:' . $port . '/feed.xml';
        $rss = new rRSS($feedUrl);
        $history = new rRSSHistory();
        rsAssert($rss->fetch($history), 'The first poll succeeds');
        $first = array('etag' => $rss->etag, 'lastModified' => $rss->lastModified);
        rsAssert($rss->fetch($history), 'The second poll succeeds');
        return function ($requests) use ($check, $first) { $check($requests, $first); };
    });
}

function rsWellFormed($wire, $what)
{
    rsAssert(preg_match('/\r(?!\n)/', $wire) !== 1, "Every CR of $what is part of a CRLF pair");
    rsAssert(preg_match('/(?<!\r)\n/', $wire) !== 1, "Every LF of $what is part of a CRLF pair");
    rsAssert(substr_count($wire, "\r\n\r\n") === 1, "$what ends its header block exactly once");
    rsAssert(substr($wire, -4) === "\r\n\r\n", "$what ends at that blank line");
}

$tests = array(
    // The control: validators of the shape the grammars allow are kept and
    // sent back exactly as the feed wrote them -- case included, which is the
    // only way an entity tag can match the one the server issued.
    'validators a feed may issue ride the next poll unchanged' => function () {
        rsPoll("ETag: \"aB3-Xy\"\r\nLast-Modified: Wed, 21 Oct 2026 07:28:00 GMT\r\n",
            function ($requests, $first) {
                rsAssert(count($requests) === 2, 'The feed is polled twice');
                rsWellFormed($requests[1], 'the second poll');
                rsAssert($first['etag'] === '"aB3-Xy"', 'The entity tag is kept with its case');
                rsAssert($first['lastModified'] === 'Wed, 21 Oct 2026 07:28:00 GMT',
                    'The date is kept with its case');
                rsAssert(in_array('If-None-Match: "aB3-Xy"', rsFieldLines($requests[1]), true),
                    'The entity tag is one field line of the second poll');
                rsAssert(in_array('If-Modified-Since: Wed, 21 Oct 2026 07:28:00 GMT',
                    rsFieldLines($requests[1]), true),
                    'The date is one field line of the second poll');
                rsAssert(stripos($requests[1], 'If-Last-Modified') === false,
                    'and it rides no header a server does not answer');
                rsAssert(in_array(RS_AUTH, rsFieldLines($requests[1]), true),
                    'The credentials stay inside the header block');
            });
    },
    // A weak tag is an entity tag too, and it is what a feed behind a
    // transforming proxy issues.
    'a weak entity tag rides the next poll' => function () {
        rsPoll("ETag: W/\"aB3\"\r\n", function ($requests, $first) {
            rsAssert($first['etag'] === 'W/"aB3"', 'The weak tag is kept');
            rsAssert(in_array('If-None-Match: W/"aB3"', rsFieldLines($requests[1]), true),
                'The weak tag is one field line of the second poll');
        });
    },
    // fgets() ends a header line at an LF, so a bare CR is what an ETag can
    // still carry into the middle of a line. Behind the If-None-Match line
    // the writer puts the Authorization header.
    'a carriage return in the ETag reaches no poll' => function () {
        rsPoll("ETag: \"v1\rX-Injected: yes\"\r\n", function ($requests, $first) {
            foreach ($requests as $index => $wire) {
                rsWellFormed($wire, "poll $index");
                rsAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the carriage return reaches poll $index");
            }
            rsAssert(in_array(RS_AUTH, rsFieldLines($requests[1]), true),
                'The credentials stay inside the header block');
            rsAssert($first['etag'] === null, 'An ETag that is not an entity tag is not kept');
        });
    },
    'a carriage return in Last-Modified reaches no poll' => function () {
        rsPoll("Last-Modified: Wed, 21 Oct 2026 07:28:00 GMT\rX-Injected: yes\r\n",
            function ($requests, $first) {
                foreach ($requests as $index => $wire) {
                    rsWellFormed($wire, "poll $index");
                    rsAssert(strpos($wire, 'X-Injected') === false,
                        "Nothing behind the carriage return reaches poll $index");
                }
                rsAssert($first['lastModified'] === null, 'A date that is not a date is not kept');
            });
    },
    // The quotes are the whole of the delimiting, so a tag must end at one:
    // a validator that merely found an entity tag at the start of the value
    // would keep whatever the feed put behind it, line terminator included.
    'bytes behind a closing quote reach no poll' => function () {
        rsPoll("ETag: \"v1\"\rX-Injected: yes\r\n", function ($requests, $first) {
            foreach ($requests as $index => $wire) {
                rsWellFormed($wire, "poll $index");
                rsAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the closing quote reaches poll $index");
            }
            rsAssert($first['etag'] === null, 'A tag with bytes behind it is not an entity tag');
        });
    },
    // An unquoted token is the shape a validator that treats the quotes as
    // optional would accept, and it is bounded by nothing a feed must respect.
    'an ETag with no quotes at all is not kept' => function () {
        rsPoll("ETag: v1abc\r\n", function ($requests, $first) {
            rsAssert($first['etag'] === null, 'An unquoted token is not an entity tag');
            foreach (rsFieldLines($requests[1]) as $line)
                rsAssert(strpos($line, 'If-None-Match') !== 0,
                    'The second poll asks no conditional question');
        });
    },
    // An ETag is a quoted string. An unquoted one is not an entity tag, and
    // what it would carry is bounded by nothing at all.
    'an unquoted ETag is not kept' => function () {
        rsPoll("ETag: v1 and anything at all\r\n", function ($requests, $first) {
            rsAssert($first['etag'] === null, 'An unquoted ETag is not an entity tag');
            rsAssert(count($requests) === 2, 'The feed is polled twice');
            foreach (rsFieldLines($requests[1]) as $line)
                rsAssert(strpos($line, 'If-None-Match') !== 0,
                    'The second poll asks no conditional question');
        });
    },
    // A date is not a date because it has date-shaped bytes in it.
    'a Last-Modified that is not a date is not kept' => function () {
        rsPoll("Last-Modified: not a date at all\r\n", function ($requests, $first) {
            rsAssert($first['lastModified'] === null, 'A string that parses as no date is not kept');
            foreach (rsFieldLines($requests[1]) as $line)
                rsAssert(strpos($line, 'If-Modified-Since') !== 0,
                    'The second poll sends no date');
        });
    },
    // A date goes back out in If-Modified-Since. There is no If-Last-Modified
    // in HTTP, so a server reading the poll never saw the question and
    // answered the whole feed to every poll that asked it.
    'the date asks the question under the name a server answers' => function () {
        rsPoll("Last-Modified: Wed, 21 Oct 2026 07:28:00 GMT\r\n",
            function ($requests, $first) {
                $lines = rsFieldLines($requests[1]);
                rsAssert(in_array('If-Modified-Since: Wed, 21 Oct 2026 07:28:00 GMT', $lines, true),
                    'The poll asks If-Modified-Since: ' . json_encode($lines));
                foreach ($lines as $line)
                    rsAssert(stripos($line, 'If-Last-Modified') !== 0,
                        'and asks under no other name: ' . $line);
            });
    },
    // RFC 9110 5.6.7: a recipient must accept all three formats, and a
    // sender must generate the first of them. These two are what the other
    // two look like.
    'a date in the obsolete RFC 850 format is sent in the one required' => function () {
        rsPoll("Last-Modified: Sunday, 06-Nov-94 08:49:37 GMT\r\n",
            function ($requests, $first) {
                rsAssert($first['lastModified'] === 'Sun, 06 Nov 1994 08:49:37 GMT',
                    'The date is kept as the format the header requires: '
                        . var_export($first['lastModified'], true));
                rsAssert(in_array('If-Modified-Since: Sun, 06 Nov 1994 08:49:37 GMT',
                    rsFieldLines($requests[1]), true),
                    'and the poll asks with it: ' . json_encode(rsFieldLines($requests[1])));
            });
    },
    'a date in the asctime format is sent in the one required' => function () {
        rsPoll("Last-Modified: Sun Nov  6 08:49:37 1994\r\n",
            function ($requests, $first) {
                rsAssert($first['lastModified'] === 'Sun, 06 Nov 1994 08:49:37 GMT',
                    'The date is kept as the format the header requires: '
                        . var_export($first['lastModified'], true));
                rsAssert(in_array('If-Modified-Since: Sun, 06 Nov 1994 08:49:37 GMT',
                    rsFieldLines($requests[1]), true),
                    'and the poll asks with it: ' . json_encode(rsFieldLines($requests[1])));
            });
    },
    // A recipient must ignore an If-Modified-Since that is not an HTTP-date,
    // so a date-shaped string that is not one buys exactly what the wrong
    // header name bought.
    'a timestamp that is not an HTTP-date is not kept' => function () {
        foreach (array('2026-10-21 07:28:00', '21 Oct 2026 07:28:00 GMT',
            'Wed, 21 Oct 2026 07:28:00 +0000', 'Wed, 21 Oct 2026 07:28:00 GMT junk',
            '1761031680', 'Wed, 32 Oct 2026 07:28:00 GMT') as $value) {
            rsPoll("Last-Modified: " . $value . "\r\n", function ($requests, $first) use ($value) {
                rsAssert($first['lastModified'] === null,
                    '"' . $value . '" is not an HTTP-date: ' . var_export($first['lastModified'], true));
                foreach (rsFieldLines($requests[1]) as $line)
                    rsAssert(stripos($line, 'If-Modified-Since') !== 0,
                        'so the poll is unconditional on it: ' . $line);
            });
        }
    },
    // The day name is part of the date and names the same day. PHP reads a
    // mismatched one as "the next such weekday", which silently moves the
    // date the poll then asks about.
    'a date whose day name is not its own day is not kept' => function () {
        rsPoll("Last-Modified: Mon, 06 Nov 1994 08:49:37 GMT\r\n",
            function ($requests, $first) {
                rsAssert($first['lastModified'] === null,
                    'The 6th of November 1994 was a Sunday: '
                        . var_export($first['lastModified'], true));
            });
    },
    // obs-text is part of the grammar, so a tag carrying high bytes is kept:
    // the bound is on the bytes an entity tag may not hold, not on byte
    // values.
    'an entity tag of bytes over 0x7f is kept' => function () {
        rsPoll("ETag: \"\xc3\xa9tag\"\r\n", function ($requests, $first) {
            rsAssert($first['etag'] === "\"\xc3\xa9tag\"", 'The high bytes are kept');
            rsAssert(in_array("If-None-Match: \"\xc3\xa9tag\"", rsFieldLines($requests[1]), true),
                'The high bytes reach the second poll as the feed wrote them');
        });
    },
);

$failures = 0;
foreach ($tests as $name => $test) {
    try { $test(); echo 'ok - ', $name, "\n"; }
    catch (Throwable $error) { ++$failures; echo 'not ok - ', $name, ': ', $error->getMessage(), "\n"; }
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
