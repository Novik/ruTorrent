<?php

class YggTorrentEngine extends commonEngine
{
    const URL = 'https://yggtorrent.com';
    const MAX_PAGE = 10;
    const PAGE_SIZE = 25;

    public $defaults = array("public" => false, "page_size" => self::PAGE_SIZE, 'auth' => 1);

    public $categories = array(
        'Tout' => '',
        '|--Film/Vidéo' => '&category=2145',
        '|--F--Animation' => '&subcategory=2178',
        '|--F--Animation Série' => '&subcategory=2179',
        '|--F--Concert' => '&subcategory=2180',
        '|--F--Documentaire' => '&subcategory=2181',
        '|--F--Emission TV' => '&subcategory=2182',
        '|--F--Film' => '&subcategory=2183',
        '|--F--Série TV' => '&subcategory=2184',
        '|--F--Spectacle' => '&subcategory=2185',
        '|--F--Sport' => '&subcategory=2186',
        '|--F--Vidéo-clips' => '&subcategory=2187',
        '|--Audio' => '&category=2139',
        '|--A--Karaoké' => '&subcategory=2147',
        '|--A--Musique' => '&subcategory=2148',
        '|--A--Podcast Radio' => '&subcategory=2150',
        '|--A--Samples' => '&subcategory=2149',
        '|--Application' => '&category=2144',
        '|--A--Autre' => '&subcategory=2177',
        '|--A--Formation' => '&subcategory=2176',
        '|--A--Linux' => '&subcategory=2171',
        '|--A--MacOS' => '&subcategory=2172',
        '|--A--Smartphone' => '&subcategory=2174',
        '|--A--Tablette' => '&subcategory=2175',
        '|--A--Windows' => '&subcategory=2173',
        '|--Jeu-vidéo' => '&category=2142',
        '|--J--Autre' => '&subcategory=2167',
        '|--J--Linux' => '&subcategory=2159',
        '|--J--MacOS' => '&subcategory=2160',
        '|--J--Microsoft' => '&subcategory=2162',
        '|--J--Nintendo' => '&subcategory=2163',
        '|--J--Smartphone' => '&subcategory=2165',
        '|--J--Sony' => '&subcategory=2164',
        '|--J--Tablette' => '&subcategory=2166',
        '|--J--Windows' => '&subcategory=2161',
        '|--eBook' => '&category=2140',
        '|--E--Audio' => '&subcategory=2151',
        '|--E--Bds' => '&subcategory=2152',
        '|--E--Comics' => '&subcategory=2153',
        '|--E--Livres' => '&subcategory=2154',
        '|--E--Mangas' => '&subcategory=2155',
        '|--E--Presse' => '&subcategory=2156',
        '|--Emulation' => '&category=2141',
        '|--E--Emulateurs' => '&subcategory=2157',
        '|--E--Roms' => '&subcategory=2158',
        '|--GPS' => '&category=2143',
        '|--G--Applications' => '&subcategory=2168',
        '|--G--Cartes' => '&subcategory=2169',
        '|--G--Divers' => '&subcategory=2170',
        '|--XXX' => '&category=2188',
        '|--X--Films' => '&subcategory=2189',
        '|--X--Hentai' => '&subcategory=2190',
        '|--X--Images' => '&subcategory=2191',
    );

    protected static $seconds = array
    (
        'seconde'	=>1,
        'minute'	=>60,
        'heure'		=>3600,
        'jour'		=>86400,
        'mois'		=>2592000,
        'an'		=>31536000
    );

    protected static function getTime( $now, $ago, $unit )
    {
        $delta = (array_key_exists($unit,self::$seconds) ? self::$seconds[$unit] : 0);
        return( $now-($ago*$delta) );
    }

    private $category_mapping = array(
        'filmvidéo' => 'Film/Vidéos',
        'jeu-vidéo' => 'Jeux',
    );

    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        if($useGlobalCats) {
            $categories = array('all' => '', 'movies' => "&category=2145", 'music' => "&category=2139", 'games' => "&category=2142", 'anime' => "&subcategory=2178", 'software' => "&category=2144", 'books' => "&category=2140");
            $defaultCat = 'all';
        } else {
            $categories = &$this->categories;
            $defaultCat = 'Tout';
        }

        if(!array_key_exists($cat,$categories)) {
            $cat = $categories[$defaultCat];
        } else {
            $cat = $categories[$cat];
        }

        $added = 0;
        $what = rawurlencode(rawurldecode($what));

        // Initial search to retrieve the page count
        $search = self::URL . '/engine/search?q=' . $what . $cat;
        $cli = $this->fetch($search);

        // Check if we have results
        if (($cli == false) || (strpos($cli->results, "download_torrent") === false)) {
            return;
        }

        // Check if there is only one page
        if (strpos($cli->results, '<ul class="pagination">') === false) {
            $maxPage = 1;
        } else {
            // Retrieve the page count
            $nbRet = preg_match_all('`data-ci-pagination-page="(?P<page>\d+)"`', $cli->results, $retPage);
            if ($nbRet) {
                $nbPage = max($retPage['page']);
                $maxPage = $nbPage < self::MAX_PAGE ? $nbPage : self::MAX_PAGE;
            } else {
                return;
            }
        }

        for ($page = 1; $page <= $maxPage; $page++) {
            // We already have results for the first page
            if ($page !== 1) {
                $pg = ($page - 1) * self::PAGE_SIZE;
                $search = self::URL . '/engine/search?q=' . $what . '&page=' . $pg . $cat;
                $cli = $this->fetch($search);
            }

            $res = preg_match_all(
                '`<tr>.*<a class="torrent-name" href="(?P<desc>.*)">(?P<name>.*)</a>' .
                '.*<a.*/download_torrent\?id=(?P<id>.*)">.*<td><i.*>.*</i>.*(?P<ago>\d+) (?P<unit>(seconde|minute|heure|jour|mois|an)).*</td>.*<td>(?P<size>.*)</td>' .
                '.*<td.*>(?P<seeder>.*)</td.*>.*<td.*>(?P<leecher>.*)</td.*>.*</tr>`siU',
                $cli->results,
                $matches
            );

            if ($res) {
                $now = time();
                for ($i = 0; $i < $res; $i++) {
                    $link = self::URL . "/engine/download_torrent?id=" . $matches["id"][$i];
                    if (!array_key_exists($link, $ret)) {
                        $item = $this->getNewEntry();
                        $item["desc"] = $matches["desc"][$i];
                        $item["name"] = self::removeTags($matches["name"][$i]);

                        // The parsed size has the format XX.XXGB, we need to add a space to help a bit the formatSize method
                        $item["size"] = self::formatSize(preg_replace('/([0-9.]+)(\w+)/', '$1 $2', $matches["size"][$i]));

                        // To be able to display categories, we need to parse them directly from the torrent URL
                        $cat = preg_match_all('`' . self::URL . '/torrent/(?P<cat1>.*)/(?P<cat2>.*)/`', $item['desc'], $catRes);
                        if ($cat) {
                            $cat1 = $this->getPrettyCategoryName($catRes['cat1'][0]);
                            $cat2 = $this->getPrettyCategoryName($catRes['cat2'][0]);
                            $item["cat"] = $cat1 . ' > ' . $cat2;
                        }

                        // We only have the time since the upload, so let's try to convert that...
                        $item["time"] = self::getTime( $now, $matches["ago"][$i], $matches["unit"][$i] );

                        $item["seeds"] = intval(self::removeTags($matches["seeder"][$i]));
                        $item["peers"] = intval(self::removeTags($matches["leecher"][$i]));
                        $ret[$link] = $item;
                        $added++;
                        if ($added >= $limit) {
                            return;
                        }
                    }
                }
            } else {
                break;
            }
        }
    }

    private function getPrettyCategoryName($input)
    {
        if (array_key_exists($input, $this->category_mapping)) {
            return $this->category_mapping[$input];
        } else {
            return ucwords($input);
        }
    }
}
