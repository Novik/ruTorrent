<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/BencodeReaderFixture.php');
require_once(__DIR__ . '/../../php/Torrent.php');

/**
 * Integers past PHP_INT_MAX of a 32-bit build, which a file larger than
 * 2 GiB carries as its length.
 *
 * On such a build intval() saturates at 2147483647, so the value is read as
 * a float instead. A float is only an answer when it prints back as the
 * digits that were read: then the torrent written back, and its info hash,
 * are the ones that were read. Anything else is reported, as
 * TorrentIntegerRoundTripTest requires of every integer.
 *
 * Every case here holds on a 64-bit build as well, where these values are
 * ints, so the file runs in the whole suite and on its own under a 32-bit
 * PHP.
 */
class TorrentIntegerWordSizeTest extends TestCase
{
    use BencodeReaderFixture;

    /** Sizes past 2 GiB, in a single-file torrent and in a file list. */
    public function testSizesPastTwoGibibytesAreWrittenBack()
    {
        foreach ([
            '2^31' => '2147483648',
            '3 GiB' => '3221225472',
            '4 GiB + 1' => '4294967297',
            'a terabyte' => '1099511627776',
            'fourteen digits' => '99999999999999',
            '-2^31 - 1' => '-2147483649',
        ] as $what => $digits) {
            foreach (['single file', 'file list'] as $layout) {
                $bytes = $this->metainfoWithLength($digits, $layout);
                $torrent = new Torrent($bytes);
                $where = $what . ', ' . $layout;
                $errors = $torrent->errors();
                $this->assertTrue(
                    $errors === false,
                    $where . ': nothing is reported' . ($errors === false ? '' : ': ' . $this->messages($errors)),
                );
                $this->assertTrue(
                    (string) $torrent === $bytes,
                    $where . ': the bytes written back are the bytes read'
                    . ' (wrote ' . $this->printable((string) $torrent) . ')',
                );
                $info = substr($bytes, strpos($bytes, '4:infod') + 6, -1);
                $this->assertTrue(
                    $torrent->hash_info() === strtoupper(sha1($info)),
                    $where . ': its info hash is the SHA-1 of the info dictionary as it was read',
                );
            }
        }
    }

    /**
     * Bodies that are not integers although a float prints some of them
     * back unchanged: 1.5 reads back as "1.5". Written back they would be
     * i1.5e, which is not a bencoded integer.
     */
    public function testNonIntegersAreReported()
    {
        foreach ([
            'a decimal point' => '1.5',
            'a negative decimal' => '-1.5',
            'a fraction past 2 GiB' => '3221225472.5',
            'a zero fraction past 2 GiB' => '3221225472.0',
            'a plus sign past 2 GiB' => '+3221225472',
            'a leading space' => ' 3221225472',
        ] as $what => $digits) {
            foreach (['single file', 'file list'] as $layout) {
                $torrent = new Torrent($this->metainfoWithLength($digits, $layout));
                $this->assertTrue(
                    strpos($this->messages($torrent->errors()), 'integer') !== false,
                    $what . ', ' . $layout . ': it is reported as an integer that cannot be written back: '
                    . $this->messages($torrent->errors()),
                );
            }
        }
    }

    /**
     * Metainfo whose one file is $digits long, carried verbatim: a literal
     * past PHP_INT_MAX would already be a float in this file.
     */
    private function metainfoWithLength($digits, $layout)
    {
        $file = ['length' => 0];
        $info = ['name' => 'x', 'piece length' => 16384, 'pieces' => sha1('x', true)];
        if ($layout === 'file list') {
            $info['files'] = [$this->dict(['length' => 0, 'path' => ['x.bin']])];
        } else {
            $info = array_merge($info, $file);
        }
        $bytes = $this->bencode($this->dict([
            'announce' => 'http://tracker.invalid/announce',
            'info' => $this->dict($info),
        ]));
        $this->assertTrue(substr_count($bytes, '6:lengthi0e') === 1, 'the fixture carries one length');
        return str_replace('6:lengthi0e', '6:lengthi' . $digits . 'e', $bytes);
    }

    private function printable($bytes)
    {
        return addcslashes($bytes, "\0..\37\177..\377\\");
    }

    private function messages($errors)
    {
        $out = [];
        foreach ((array) $errors as $error) {
            $out[] = ($error instanceof Exception) ? $error->getMessage() : json_encode($error);
        }
        return implode('; ', $out);
    }
}
