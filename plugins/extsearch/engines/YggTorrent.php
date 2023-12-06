<?php

class YggTorrentEngine extends commonEngine
{
    const URL = 'https://www3.yggtorrent.qa';
    const MAX_PAGE = 10;
    const PAGE_SIZE = 50;

    public $defaults = array("public" => false, "page_size" => self::PAGE_SIZE, 'auth' => 1);

    public $categories = array(
        'Tout' => '',
        '|--Film/Vidéo' => '&category=2145',
        '|--F--Animation' => '&category=2145&sub_category=2178',
        '|--F--Animation Série' => '&category=2145&sub_category=2179',
        '|--F--Concert' => '&category=2145&sub_category=2180',
        '|--F--Documentaire' => '&category=2145&sub_category=2181',
        '|--F--Emission TV' => '&category=2145&sub_category=2182',
        '|--F--Film' => '&category=2145&sub_category=2183',
        '|--F--Série TV' => '&category=2145&sub_category=2184',
        '|--F--Spectacle' => '&category=2145&sub_category=2185',
        '|--F--Sport' => '&category=2145&sub_category=2186',
        '|--F--Vidéo-clips' => '&category=2145&sub_category=2187',
        '|--Audio' => '&category=2139',
        '|--A--Karaoké' => '&category=2139&sub_category=2147',
        '|--A--Musique' => '&category=2139&sub_category=2148',
        '|--A--Podcast Radio' => '&category=2139&sub_category=2150',
        '|--A--Samples' => '&category=2139&sub_category=2149',
        '|--Application' => '&category=2144',
        '|--A--Autre' => '&category=2144&sub_category=2177',
        '|--A--Formation' => '&category=2144&sub_category=2176',
        '|--A--Linux' => '&category=2144&sub_category=2171',
        '|--A--MacOS' => '&category=2144&sub_category=2172',
        '|--A--Smartphone' => '&category=2144&sub_category=2174',
        '|--A--Tablette' => '&category=2144&sub_category=2175',
        '|--A--Windows' => '&category=2144&sub_category=2173',
        '|--Jeu-vidéo' => '&category=2142',
        '|--J--Autre' => '&category=2142&sub_category=2167',
        '|--J--Linux' => '&category=2142&sub_category=2159',
        '|--J--MacOS' => '&category=2142&sub_category=2160',
        '|--J--Microsoft' => '&category=2142&sub_category=2162',
        '|--J--Nintendo' => '&category=2142&sub_category=2163',
        '|--J--Smartphone' => '&category=2142&sub_category=2165',
        '|--J--Sony' => '&category=2142&sub_category=2164',
        '|--J--Tablette' => '&category=2142&sub_category=2166',
        '|--J--Windows' => '&category=2142&sub_category=2161',
        '|--eBook' => '&category=2140',
        '|--E--Audio' => '&category=2140&sub_category=2151',
        '|--E--Bds' => '&category=2140&sub_category=2152',
        '|--E--Comics' => '&category=2140&sub_category=2153',
        '|--E--Livres' => '&category=2140&sub_category=2154',
        '|--E--Mangas' => '&category=2140&sub_category=2155',
        '|--E--Presse' => '&category=2140&sub_category=2156',
        '|--Nulled' => '&category=2300',
        '|--Nulled--Wordpress' => '&category=2300&sub_category=2301',
        '|--Nulled--Scripts PHP & CMS' => '&category=2300&sub_category=2302',
        '|--Nulled--Mobile' => '&category=2300&sub_category=2303',
        '|--Nulled--Divers' => '&category=2300&sub_category=2304',
        '|--Imprimante 3D' => '&category=2200',
        '|--Imprimante 3D--Objets' => '&category=2200&sub_category=2201',
        '|--Imprimante 3D--Personnages' => '&category=2200&sub_category=2202',
        '|--Emulation' => '&category=2141',
        '|--E--Emulateurs' => '&category=2141&sub_category=2157',
        '|--E--Roms' => '&category=2141&sub_category=2158',
        '|--GPS' => '&category=2143',
        '|--G--Applications' => '&category=2143&sub_category=2168',
        '|--G--Cartes' => '&category=2143&sub_category=2169',
        '|--G--Divers' => '&category=2143&sub_category=2170',
        '|--XXX' => '&category=2188',
        '|--X--Films' => '&category=2188&sub_category=2189',
        '|--X--Hentai' => '&category=2188&sub_category=2190',
        '|--X--Images' => '&category=2188&sub_category=2191',
    );

    private $category_mapping = array(
        'filmvidéo' => 'Film/Vidéos'
    );

    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        if($useGlobalCats) {
            $categories = array('all' => '', 'movies' => "&category=2145", 'music' => "&category=2139", 'games' => "&category=2142", 'anime' => "&sub_category=2178", 'software' => "&category=2144", 'books' => "&category=2140");
            $defaultCat = 'all';
        } else {
            $categories = &$this->categories;
            $defaultCat = 'Tout';
        }

        if(!array_key_exists($cat,$categories)) {
            $catParameters = $categories[$defaultCat];
        } else {
            $catParameters = $categories[$cat];
        }

        $added = 0;
        $what = rawurlencode(rawurldecode($what));

        // Initial search to retrieve the page count
        $search = self::URL . '/engine/search/?name=' . $what . $catParameters . '&do=search&attempt=1';
        $cli = $this->fetch($search);
        // Check if we have results
        if ($cli == false) {
	    $item = $this->getNewEntry();
	    $item["name"] = "Fetch Error";
	    $ret[""] = $item;
            return;
	} else if (strpos($cli->results, "Aucun résultat !") !== false) {
	    $item = $this->getNewEntry();
	    $item["name"] = "No result found";
	    $ret[""] = $item;
            return;
        }

        $nbRet = preg_match_all('`>(?P<results>\d+) résultats trouvés`', $cli->results, $retPage);
	if (!$nbRet) 
	{
	    $item = $this->getNewEntry();
	    $item["name"] = "No result found";
	    $ret[""] = $item;
            return;
        }
        $nbResults = $retPage['results'][0];
        // Check if there is only one page
        if ($nbResults <= self::PAGE_SIZE) {
            $maxPage = 1;
        } else {
            // Retrieve the page count
            $nbPage = ceil($nbResults / self::PAGE_SIZE);
            $maxPage = $nbPage < self::MAX_PAGE ? $nbPage : self::MAX_PAGE;
        }

        for ($page = 1; $page <= $maxPage; $page++) {
            // We already have results for the first page
            if ($page !== 1) {
                $pg = ($page - 1) * self::PAGE_SIZE;
                $search = self::URL . '/engine/search/?name=' . $what . '&page=' . $pg . $catParameters . '&do=search';
                $cli = $this->fetch($search);
            }

            $res = preg_match_all(
                '`<td>\s*<div class="hidden">.*<a id="torrent_name" href="(?P<desc>.*)">(?P<name>.*)\s*</td>.*'.
                '<a target="(?P<id>.*)".*'.
                '<div class="hidden">(?P<timestamp>.*)</div>.*'.
                '<td>(?P<size>.*)</td>.*'.
                '<td>(?P<completed>.*)</td>.*'.
                '<td>(?P<seeder>.*)</td>.*'.
                '<td>(?P<leecher>.*)</td>'.
                '`siU',
                $cli->results,
                $matches
            );

            if ($res) {
                // Get current URL
                preg_match('`.+?(?=/torrent)`', $matches["desc"][0], $url);

                for ($i = 0; $i < $res; $i++) {
                    $link = $url[0] . "/engine/download_torrent?id=" . $matches["id"][$i];
                    if (!array_key_exists($link, $ret)) {
                        $item = $this->getNewEntry();
                        $item["desc"] = $matches["desc"][$i];
                        $name = self::removeTags($matches["name"][$i]);
                        // Remove useless space before some torrents names to have best name sort
                        $item["name"] = trim($name);

                        // The parsed size has the format XX.XXGB, we need to add a space to help a bit the formatSize method
                        $item["size"] = self::formatSize(preg_replace('/([0-9.]+)(\w+)/', '$1 $2', $matches["size"][$i]));

                        // To be able to display categories, we need to parse them directly from the torrent URL
                        $cat = preg_match_all('`' . $url[0] . '/torrent/(?P<cat1>.*)/(?P<cat2>.*)/`', $item['desc'], $catRes);
                        if ($cat) {
                            $cat1 = $this->getPrettyCategoryName($catRes['cat1'][0]);
                            $cat2 = $this->getPrettyCategoryName($catRes['cat2'][0]);
                            $item["cat"] = $cat1 . ' > ' . $cat2;
                        }

                        // We only have the time since the upload, so let's try to convert that...
                        $item["time"] = $matches["timestamp"][$i];

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
            return ucwords(str_replace('-', ' ', $input));
        }
    }
}
