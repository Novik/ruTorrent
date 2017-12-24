<?php

class NyaaSukebeEngine extends commonEngine
{
    public $defaults = array( "public"=>true, "page_size"=>75 );

    public $categories = array(
        'All categories'=>'0_0',
        '> Art'=>'1_0',
        '-- Anime'=>'1_1',
        '-- Doujinshi'=>'1_2',
        '-- Games'=>'1_3',
        '-- Manga'=>'1_4',
        '-- Pictures'=>'1_5',
        '> Reallife'=>'2_0',
        '-- Photobooks & Pictures'=>'2_1',
        '-- Videos'=>'2_2'
        );

    public function makeClient($url)
    {
        $client = parent::makeClient($url);
        return($client);
    }

    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        $added = 0;
        $url = 'https://sukebei.nyaa.si';

        if ($useGlobalCats) {
            $categories = array(
            'all' => '0_0',
            'art' => '1_0',
            'reallife' => '2_0'
            );
        } else {
            $categories = &$this->categories;
        }

        if (!array_key_exists($cat, $categories)) {
            $cat = $categories['all'];
        } else {
            $cat = $categories[$cat];
        }

        $maxPage = 10;

        for ($pg = 1; $pg<=$maxPage; $pg++) {
            $search = $url . '/?c=' . $cat . '&q=' . $what . '&s=seeders&o=desc&p=' . $pg;
            $cli = $this->fetch($search);

            if (($cli == false) || (strpos($cli->results, ">No results found<") !== false)) {
                break;
            }

            $res = preg_match_all('`<tr class.*>.*'.
                '<td.*>.*<a.*title="(?P<cat>.*)">.*'.
                '<td.*>.*<a href="/view/(?P<id>\d+)".*>(?P<name>.*)</a>.*'.
                '<td.*>.*<a href="(?P<link>magnet.*)">.*'.
                '<td.*>(?P<size>.*)</td>.*'.
                '<td.*>(?P<date>.*)</td>.*'.
                '<td.*>(?P<seeds>.*)</td>.*'.
                '<td.*>(?P<peers>.*)</td>'.
                '`siU', $cli->results, $matches);

            if ($res) {
                for ($i = 0; $i < $res; $i++) {
                    $link = self::removeTags($matches['link'][$i]);
                    if (!array_key_exists($link, $ret)) {
                        $item = $this->getNewEntry();
                        $item["desc"] = $url."/view/".$matches["id"][$i];
                        $item["time"] = strtotime(self::removeTags($matches["date"][$i]).' '."UTC");
                        $item["name"] = self::toUTF(self::removeTags($matches["name"][$i]), "utf-8");
                        $item["size"] = self::formatSize($matches["size"][$i]);
                        $item["cat"] = self::removeTags($matches["cat"][$i]);
                        $item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
                        $item["peers"] = intval(self::removeTags($matches["peers"][$i]));
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
}
