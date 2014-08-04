<?php

class CPasBienEngine extends commonEngine
{
    public $defaults = array("public" => true, "page_size" => 30);
    
    public $categories = array(
		'Tout' => '',
		'Films' => 'films/',
		'SГ©ries' => 'series/',
		'Musique' => 'musique/',
		'Ebook' => 'ebook/',
		'Logiciels' => 'logiciels/',
		'Jeux PC' => 'jeux-pc/',
		'Jeux Consoles' => 'jeux-consoles/',
	);
    
    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        $added = 0;
        $url   = 'http://www.cpasbien.pe';
        if ($useGlobalCats)
            $categories = array(
                'all' => '',
                'movies' => 'films/',
				'series' => 'series/',
                'music' => 'musique/',
                'software' => 'logiciels/',
                'books' => 'ebook/'
            );
        else
            $categories =& $this->categories;
        if (!array_key_exists($cat, $categories))
            $cat = $categories['all'];
        else
            $cat = $categories[$cat];
		$what = str_replace (' ', '+', $what);
        for ($pg = 0; $pg < 11; $pg++) {
            $cli = $this->fetch($url . '/recherche/' . $cat . $what . '/page-' . $pg);
            if (($cli == false) || (strpos($cli->results, "Pas de torrents disponibles correspondant Г  votre recherche") !== false))
                break;
            $res = preg_match_all(	
				'`<tr class="color\d">.*<td class="torrent-aff">.*'.
				'<a class="lien-rechercher" href="http:\/\/www\.cpasbien\.pe\/dl-torrent\/(?P<desc1>[^\/]*)\/(?P<desc2>[^\/]*)\/(?P<id>[^\/]*).html" title="(?P<cat1>.*)<br\/>(?P<cat2>.*)<br\/>(?P<date>.*)"> '.
				'<img src=".*"\/> (?P<name>.*)<\/a>.*<\/td>.*<td class=".*">(?P<size>.*)<\/td>.*<span .*>(?P<seeds>\d+)<\/span>'.
				'<\/td>.*gif"\/>(?P<leech>.*)<\/td>'.
				'`siU', $cli->results, $matches);
            if ($res) {
                for ($i = 0; $i < $res; $i++) {
                    $link                = $url . "/download-torrent/" . $matches["id"][$i] . '.torrent';
                    if (!array_key_exists($link, $ret)) {
                        $item          = $this->getNewEntry();
                        $item["desc"]  = $url . "/dl-torrent/" . $matches["desc1"][$i] . "/" . $matches["desc2"][$i] . "/" . $matches["id"][$i] . ".html";
                        $item["name"]  = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim(str_replace("o","B",$matches["size"][$i])));
                        $item["cat"]   = $matches["cat1"][$i] . ' > ' . $matches["cat2"][$i];
                        $item["time"]  = strtotime(self::removeTags(str_replace ('/', '-', $matches["date"][$i]))) + 1;
                        $item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
                        $item["peers"] = intval(self::removeTags($matches["leech"][$i]));
                        $ret[$link]    = $item;
                        $added++;
                        if ($added >= $limit)
                            return;
                    }
                }
            } else
                break;
        }
    }
}