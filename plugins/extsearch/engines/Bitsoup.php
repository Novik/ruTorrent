<?php

class BitsoupEngine extends commonEngine {

    public $defaults = array(
        'public' => false,
        'page_size' => 50,
        'cookies' => 'www.bitsoup.me|PHPSESSID=xxxxx;__cfduid=yyyyy;uid=nnnnnn;pass=zzzzzzz;'
    );

    public $categories = array('all'=>'0', 'Anime' => '23', 'Appz/Misc' => '22', 'Appz/PC ISO' => '1', 'Audiobooks' => '5', 'Ebooks' => '24', 'Games/PC ISO' => '4', 'Games/PC Rips' => '21', 'Games/PS3' => '38', 'Games/Wii' => '35', 'Games/Xbox360' => '12', 'Mac' => '2', 'Mobile Apps' => '26', 'Movies/3D' => '17', 'Movies/BluRay' => '80', 'Movies/DVD-R' => '20', 'Movies/HD/x264' => '41', 'Movies/Packs' => '27', 'Movies/XviD' => '19', 'Music Videos' => '29', 'Music/Albums' => '6', 'Other/MISC' => '28', 'PSP/Handheld' => '30', 'TV-HDx264' => '42', 'TV-Packs' => '45', 'TV-SDx264' => '49', 'TV-XVID' => '7', 'XXX' => '9', 'XXX/0DAY' => '990');

    public $url = 'https://www.bitsoup.me';

    public function action($what, $cat, &$ret, $limit, $useGlobalCats) {
        $category = (!$useGlobalCats && $cat && array_key_exists($cat, $this->categories)) ? $this->categories[$cat] : $this->categories['all'];
        $searchUrl = $this->url . '/browse.php?search=' . $what . '&page=0&cat=' . $category;
        $cli = $this->fetch($searchUrl, false);
        if (!$cli) {
            $this->sendSimpleResult('error', 'Error connecting to bitsoup', $ret);
            return;
        }
        if (strpos($cli->results, '<h2>Search results for "' . rawurldecode($what) . '"</h2>') === false) {
            $this->sendSimpleResult('noresults', 'No results could be parsed from Bitsoup', $ret);
            return;
        }
        list ($totalRowCount, $rows) = $this->countRows($cli->results);
        if ($totalRowCount === null) {
            $this->sendSimpleResult('noresults', 'Result table could not be parsed from Bitsoup', $ret);
            return;
        }
        if ($totalRowCount === 0) {
            $this->sendSimpleResult('noresults', 'No results found', $ret);
            return;
        }
        list ($rowsParsed, $unMatchedRows) = $this->processResults($rows, $ret);
        if ($rowsParsed === null) {
            $this->sendSimpleResult('noresults', 'No results could be parsed from Bitsoup', $ret);
            return;
        }
        if ($rowsParsed < $totalRowCount) {
            $this->sendSimpleResult('resultCount', 'Warning: detected ' . $totalRowCount . ' but could parsed just ' . $rowsParsed . ' results', $ret, $this->getDataUrlForUnmatchedRows($unMatchedRows));
        }
    }

    private function countRows($results) {
        if (!preg_match_all(BitsoupEngine_Parser::getResultTablePattern(), $results, $matches)) {
            return array(null, null);
        }
        if (!preg_match_all(BitsoupEngine_Parser::getResultRowPattern(), $matches['table'][0], $matches)) {
            return array(null, null);
        }
        array_shift($matches['row']); // don't count header
        return array(count($matches['row']), $matches['row']);
    }

    private function processResults(array $rows, &$ret) {
        $matched = 0;
        $unMatchedRows = array();
        foreach ($rows as $row) {
            if (preg_match_all(BitsoupEngine_Parser::getResultPattern(), $row, $matches)) {
                $matched++;
            } else {
                $unMatchedRows[] = $row;
                continue;
            }
            $item = $this->getNewEntry();
            $item['cat'] = $matches['cat'][0];
            $item['desc'] = $this->url . '/' . $matches['desc'][0];
            $item['name'] = $matches['name'][0];
            $item['size'] = (int) $matches['size'][0]
                    * ($matches['sizeUnit'][0] == 'KB' ? 1024 : (
                      ($matches['sizeUnit'][0] == 'MB' ? 1024*1024 : (
                      ($matches['sizeUnit'][0] == 'GB' ? 1024*1024*1024 : (
                      ($matches['sizeUnit'][0] == 'TB' ? 1024*1024*1024 : (
                      1
                    ))))))));
            $item['seeds'] = (int) $matches['seeders'][0] > 0 ? (int) $matches['seeders'][0] : 0;
            $item['peers'] = (int) $matches['leechers'][0] > 0 ? (int) $matches['leechers'][0] : 0;
            $item['time'] = strtotime($matches['date'][0] . ' ' . $matches['time'][0]);
            $link = $this->url . '/' . $matches['link'][0];
            $ret[$link] = $item;
        }
        return array($matched, $unMatchedRows);
    }

    private function sendSimpleResult($cat, $name, &$ret, $desc = null) {
        $item = $this->getNewEntry();
        $item['cat'] = $cat;
        $item['name'] = $name;
        $item['seeds'] = 999999;
        if ($desc) {
            $item['desc'] = $desc;
        }
        $ret[$cat] = $item;
    }

    private function getDataUrlForUnmatchedRows(array $unMatchedRows) {
        if (!$unMatchedRows) {
            return null;
        }
        $output =
            '<html><body>' .
            'Current regexp used:<br><br>' .
            '<pre>' . htmlentities(BitsoupEngine_Parser::getResultPattern()) . '</pre>' .
            '<br>Go to <a href="https://regex101.com/">https://regex101.com/</a> and paste it with the following rows:<br>:'
        ;
        foreach ($unMatchedRows as $row) {
            $output .= '<h3>Unmatched row:</h3><pre>' . htmlentities($row) . '</pre><br>';
        }
        $output .= '</body></html>';
        return 'data:text/html;charset=utf-8,' . $output;
    }
}


class BitsoupEngine_Parser {
    const oTR = '<tr\s*>\s*';
    const cTR = '<\s*\/tr\s*>\s*';
    const oTD = '<td\s+(?:[^>]*)>\s*';
    const cTD = '<\/td>\s*';
    const oA = '<a\s+(?:[^>]*)';
    const oAc = '>\s*';
    const HREF = 'href=["]{0,1}(?P<HREF>[^"]+)["]{0,1}\s*';
    const cA = '<\/a>\s*';
    const oIMG = '<img\s+(?:[^>]*)';
    const cIMG = '[\/]{0,1}>\s*';
    const oB = '<b\s*>\s*';
    const cB = '<\/b>\s*';
    const CAT = 'alt="(?P<cat>[^"]*)"\s*';
    const BR = '<br\s*[\/]{0,1}>\s*';
    const oNOBR = '<nobr\s*>\s*';
    const cNOBR = '<\/nobr>\s*';
    const oFONT = '<font\s+(?:[^>]*)>\s*';
    const cFONT = '<\/font>\s*';

    const oRESULTS_TABLE = '<table\s+(?:[^>]*)class="koptekst"(?:[^>]*)>';
    const cRESULTS_TABLE = '<\s*\/table\s*>\s*';

    const FILES = '(?P<files>[0-9]+)\s*';
    const COMMENTS = '(?P<comments>[0-9]+)\s*';
    const NO_COMMENTS = '(?P<nocomments>[0]{1})\s*';
    const DATE = '(?P<date>[0-9-]*+)\s*';
    const TIME = '(?P<time>[0-9:]+)\s*';
    const SIZE = '(?<size>[0-9\.,]+)\s*';
    const SIZE_UNIT = '(?P<sizeUnit>[a-zA-Z]+)\s*';
    const DOWNLOADS = '(?P<downloads>[0-9,]+)\s*<br\s*[\/]{0,1}>\s*times\s*';
    const SEEDERS = '<b>\s*<a (?:[^>]*)>\s*<font (?:[^>]*)>(?P<seeders>[0-9]+)\s*<\s*\/font>\s*<\s*\/a>\s*<\s*\/b>\s*';
    const NO_SEEDERS = '<span\s+class="red"\s*>\s*(?P<noseeders>[0-9]+)\s*<\s*\/span>\s*';
    const LEECHERS = '<b><a\s+(?:[^>]*)>\s*(?P<leechers>[0-9]+)\s*<\s*\/b>\s*';
    const NO_LEECHERS = '(?P<noleechers>[0]{1})\s*';


    static function generateKeyIfNeeded($dest) {
        static $c = 0;
        return $dest === null ? 'key' . ++$c : $dest;
    }

    static function anything($key = null) {
        return '(?P<' . self::generateKeyIfNeeded($key) . '>.*)';
    }

    static function tr($content) {
        return self::oTR . $content . self::cTR;
    }

    static function td($content) {
        return self::oTD . $content . self::cTD;
    }

    static function href($content, $key = null) {
        $href = str_replace('<HREF>', '<' . self::generateKeyIfNeeded($key) . '>', self::HREF);
        return self::oA . $href . self::oAc . $content . self::cA;
    }

    static function a($content) {
        return self::oA . self::oAc . $content . self::cA;
    }

    static function bold($content) {
        return self::oB . $content . self::cB;
    }

    static function font($content) {
        return self::oFONT . $content . self::cFONT;
    }

    static function string($key) {
        return '(?P<' . $key . '>[^<]+)';
    }

    static function img($content = '') {
        return self::oIMG . $content . self::cIMG;
    }

    static function nobr($content) {
        return self::oNOBR . $content . self::cNOBR;
    }

    static function logicalOr($content1, $content2) {
        return '(?:' . $content1 . '|' . $content2 . ')';
    }

    static function getResultRowPattern() {
        return '/' . BitsoupEngine_Parser::tr(BitsoupEngine_Parser::anything('row')) . '/siU';
    }

    static function getResultTablePattern() {
        return '/' .
            BitsoupEngine_Parser::oRESULTS_TABLE .
                BitsoupEngine_Parser::anything('table') .
            BitsoupEngine_Parser::cRESULTS_TABLE .
        '/siU';
    }

    static function getResultPattern() {
        return '/' .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::href(
                    BitsoupEngine_Parser::img(BitsoupEngine_Parser::CAT)
                )
            ) .
            BitsoupEngine_Parser::oTD .
                BitsoupEngine_Parser::href(BitsoupEngine_Parser::bold(BitsoupEngine_Parser::string('name')), 'desc') .
                BitsoupEngine_Parser::BR .
                BitsoupEngine_Parser::td(
                    BitsoupEngine_Parser::href(BitsoupEngine_Parser::img(), 'link')
                ) .
                BitsoupEngine_Parser::td(
                    BitsoupEngine_Parser::href(BitsoupEngine_Parser::img(), 'nfo')
                ) .
                BitsoupEngine_Parser::td(
                    BitsoupEngine_Parser::href(
                        BitsoupEngine_Parser::img()
                    )
                ) .
            BitsoupEngine_Parser::cTD .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::bold(
                    BitsoupEngine_Parser::href(
                        BitsoupEngine_Parser::FILES
                    )
                )
            ) .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::logicalOr(
                    BitsoupEngine_Parser::bold(
                        BitsoupEngine_Parser::href(
                            BitsoupEngine_Parser::COMMENTS
                        )
                    ),
                    BitsoupEngine_Parser::NO_COMMENTS
                )
            ) .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::nobr(
                    BitsoupEngine_Parser::DATE .
                    BitsoupEngine_Parser::BR .
                    BitsoupEngine_Parser::TIME
                )
            ) .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::SIZE .
                BitsoupEngine_Parser::BR .
                BitsoupEngine_Parser::SIZE_UNIT
            ) .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::DOWNLOADS
            ) .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::logicalOr(BitsoupEngine_Parser::SEEDERS, BitsoupEngine_Parser::NO_SEEDERS)
            ) .
            BitsoupEngine_Parser::td(
                BitsoupEngine_Parser::logicalOr(BitsoupEngine_Parser::LEECHERS, BitsoupEngine_Parser::NO_LEECHERS)
            ) .
        '/siU';
    }
}
