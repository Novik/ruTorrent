<?php

class YggTorrentEngine extends commonEngine
{
    const URL = 'https://www2.yggtorrent.se';
    const MAX_PAGE = 10;
    const PAGE_SIZE = 50;

    public $defaults = array("public" => false, "page_size" => self::PAGE_SIZE, 'auth' => 1);

    public $categories = array(
        'Tout' => '',
        '|--Film/Vidéo' => '&category=2145',
        '|--F--Animation' => '&sub_category=2178',
        '|--F--Animation Série' => '&sub_category=2179',
        '|--F--Concert' => '&sub_category=2180',
        '|--F--Documentaire' => '&sub_category=2181',
        '|--F--Emission TV' => '&sub_category=2182',
        '|--F--Film' => '&sub_category=2183',
        '|--F--Série TV' => '&sub_category=2184',
        '|--F--Spectacle' => '&sub_category=2185',
        '|--F--Sport' => '&sub_category=2186',
        '|--F--Vidéo-clips' => '&sub_category=2187',
        '|--Audio' => '&category=2139',
        '|--A--Karaoké' => '&sub_category=2147',
        '|--A--Musique' => '&sub_category=2148',
        '|--A--Podcast Radio' => '&sub_category=2150',
        '|--A--Samples' => '&sub_category=2149',
        '|--Application' => '&category=2144',
        '|--A--Autre' => '&sub_category=2177',
        '|--A--Formation' => '&sub_category=2176',
        '|--A--Linux' => '&sub_category=2171',
        '|--A--MacOS' => '&sub_category=2172',
        '|--A--Smartphone' => '&sub_category=2174',
        '|--A--Tablette' => '&sub_category=2175',
        '|--A--Windows' => '&sub_category=2173',
        '|--Jeu-vidéo' => '&category=2142',
        '|--J--Autre' => '&sub_category=2167',
        '|--J--Linux' => '&sub_category=2159',
        '|--J--MacOS' => '&sub_category=2160',
        '|--J--Microsoft' => '&sub_category=2162',
        '|--J--Nintendo' => '&sub_category=2163',
        '|--J--Smartphone' => '&sub_category=2165',
        '|--J--Sony' => '&sub_category=2164',
        '|--J--Tablette' => '&sub_category=2166',
        '|--J--Windows' => '&sub_category=2161',
        '|--eBook' => '&category=2140',
        '|--E--Audio' => '&sub_category=2151',
        '|--E--Bds' => '&sub_category=2152',
        '|--E--Comics' => '&sub_category=2153',
        '|--E--Livres' => '&sub_category=2154',
        '|--E--Mangas' => '&sub_category=2155',
        '|--E--Presse' => '&sub_category=2156',
        '|--Emulation' => '&category=2141',
        '|--E--Emulateurs' => '&sub_category=2157',
        '|--E--Roms' => '&sub_category=2158',
        '|--GPS' => '&category=2143',
        '|--G--Applications' => '&sub_category=2168',
        '|--G--Cartes' => '&sub_category=2169',
        '|--G--Divers' => '&sub_category=2170',
        '|--XXX' => '&category=2188',
        '|--X--Films' => '&sub_category=2189',
        '|--X--Hentai' => '&sub_category=2190',
        '|--X--Images' => '&sub_category=2191',
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
        $search = self::URL . '/engine/search/?name=' . $what . $catParameters . '&do=search';
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
                '`<td><div class="hidden">.*<a id="torrent_name" href="(?P<desc>.*)">(?P<name>.*)</td>.*'.
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
