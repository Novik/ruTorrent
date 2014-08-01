<?php

class Torrent411Engine extends commonEngine
{
    public $defaults = array("public" => false, "page_size" => 50, "cookies" => "www.t411.me|uid=XXX;pass=XXX;authKey=XXX");
    
    public $categories = array(
		'Tout' => '',
		'|--Audio' => '&cat=395',
		'|--|--Karaoke' => '&subcat=400',
		'|--|--Musique' => '&subcat=623',
		'|--|--Samples' => '&subcat=403',
		'|--eBook' => '&cat=404',
		'|--|--Audio' => '&subcat=405',
		'|--|--Bds' => '&subcat=406',
		'|--|--Comics' => '&subcat=407',
		'|--|--Livres' => '&subcat=408',
		'|--|--Mangas' => '&subcat=409',
		'|--|--Presse' => '&subcat=410',
		'|--Emulation' => '&cat=340',
		'|--|--Emulateurs' => '&subcat=342',
		'|--|--Roms' => '&subcat=344',
		'|--Jeu vidéo' => '&cat=624',
		'|--|--Linux' => '&subcat=239',
		'|--|--MacOS' => '&subcat=245',
		'|--|--Windows' => '&subcat=246',
		'|--|--Microsoft' => '&subcat=309',
		'|--|--|--Xbox' => '&term%5B36%5D%5B%5D=704',
		'|--|--|--Xbox 360' => '&term%5B36%5D%5B%5D=705',
		'|--|--|--Xbox One' => '&term%5B36%5D%5B%5D=1158',
		'|--|--Nintendo' => '&subcat=307',
		'|--|--|--3Ds' => '&term%5B37%5D%5B%5D=702',
		'|--|--|--Ds' => '&term%5B37%5D%5B%5D=701',
		'|--|--|--Gamecube' => '&term%5B37%5D%5B%5D=738',
		'|--|--|--Wii' => '&term%5B37%5D%5B%5D=703',
		'|--|--|--WiiU' => '&term%5B37%5D%5B%5D=1126',
		'|--|--Sony' => '&subcat=308',
		'|--|--|--Playstation' => '&term%5B18%5D%5B%5D=613',
		'|--|--|--Playstation2' => '&term%5B18%5D%5B%5D=614',
		'|--|--|--Playstation3' => '&term%5B18%5D%5B%5D=617',
		'|--|--|--Playstation4' => '&term%5B18%5D%5B%5D=1159',
		'|--|--|--Psp' => '&term%5B18%5D%5B%5D=615',
		'|--|--|--Vita' => '&term%5B18%5D%5B%5D=921',
		'|--|--Smartphone' => '&subcat=626',
		'|--|--Tablette' => '&subcat=628',
		'|--|--Autre' => '&subcat=630',
		'|--GPS' => '&cat=392',
		'|--|--Applications' => '&subcat=391',
		'|--|--Cartes' => '&subcat=393',
		'|--|--Divers' => '&subcat=394',
		'|--Application' => '&cat=233',
		'|--|--Linux' => '&subcat=234',
		'|--|--MacOS' => '&subcat=235',
		'|--|--Windows' => '&subcat=236',
		'|--|--Smartphone' => '&subcat=625',
		'|--|--Tablette' => '&subcat=627',
		'|--|--Formation' => '&subcat=638',
		'|--|--Autre' => '&subcat=629',
		'|--Film/Vidéo' => '&cat=210',
		'|--|--Animation' => '&subcat=455',
		'|--|--Animation Série' => '&subcat=637',
		'|--|--Concert' => '&subcat=633',
		'|--|--Documentaire' => '&subcat=634',
		'|--|--Emission TV' => '&subcat=639',
		'|--|--Film' => '&subcat=631',
		'|--|--Série TV' => '&subcat=433',
		'|--|--Spectacle' => '&subcat=635',
		'|--|--Sport' => '&subcat=636',
		'|--|--Vidéo-clips' => '&subcat=402'
	);
    
    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        $catid = array(
            '400' => 'Audio > Karaoke',
            '623' => 'Audio > Musique',
            '403' => 'Audio > Samples',
            '405' => 'eBook > Audio',
            '406' => 'eBook > Bds',
            '407' => 'eBook > Comics',
            '408' => 'eBook > Livres',
            '409' => 'eBook > Mangas',
            '410' => 'eBook > Presse',
            '342' => 'Emulation > Emulateurs',
            '344' => 'Emulation > Roms',
            '239' => 'Jeu vidéo > Linux',
            '245' => 'Jeu vidéo > MacOS',
            '246' => 'Jeu vidéo > Windows',
            '309' => 'Jeu vidéo > Microsoft',
            '307' => 'Jeu vidéo > Nintendo',
            '308' => 'Jeu vidéo > Sony',
            '626' => 'Jeu vidéo > Smartphone',
            '628' => 'Jeu vidéo > Tablette',
            '630' => 'Jeu vidéo > Autre',
            '391' => 'GPS > Applications',
            '393' => 'GPS > Cartes',
            '394' => 'GPS > Divers',
            '234' => 'Application > Linux',
            '235' => 'Application > MacOS',
            '236' => 'Application > Windows',
            '625' => 'Application > Smartphone',
            '627' => 'Application > Tablette',
            '638' => 'Application > Formation',
            '629' => 'Application > Autre',
            '455' => 'Film/Vidéo > Animation',
            '637' => 'Film/Vidéo > Animation Série',
            '633' => 'Film/Vidéo > Concert',
            '634' => 'Film/Vidéo > Documentaire',
            '639' => 'Film/Vidéo > Emission TV',
            '631' => 'Film/Vidéo > Film',
            '433' => 'Film/Vidéo > Série TV',
            '635' => 'Film/Vidéo > Spectacle',
            '636' => 'Film/Vidéo > Sport',
            '402' => 'Film/Vidéo > Vidéo-clips'
        );
        $added = 0;
        $url   = 'https://www.t411.me';
        if ($useGlobalCats)
            $categories = array(
                'all' => '',
                'movies' => '&cat=210',
                'music' => '&cat=395',
                'software' => '&cat=233',
                'books' => '&cat=404'
            );
        else
            $categories =& $this->categories;
        if (!array_key_exists($cat, $categories))
            $cat = $categories['all'];
        else
            $cat = $categories[$cat];
        $what = rawurlencode(self::fromUTF(rawurldecode($what), "ISO-8859-1"));
        for ($pg = 0; $pg < 11; $pg++) {
            if ($what === '%2A')
                $search = $url . '/torrents/search/?search=' . $cat . '&order=added&type=desc&page=' . $pg;
            else
                $search = $url . '/torrents/search/?search=' . $what . $cat . '&order=added&type=desc&page=' . $pg;
            $cli = $this->fetch($search);
            if (($cli == false) || (strpos($cli->results, ">Aucun R�sultat Aucun<") !== false))
                break;
            $res = preg_match_all(	
				'`<a href="/torrents/search/\?subcat=(?P<catid>\d+)">.*'.
				'<a href="(?P<desc>[^"]*)" title="(?P<name>.*)">.*<dl>.*'.
				'<dt>.*</dt>.*<dd>(?P<date>.*)</dd>.*<a href="/torrents/nfo/\?id=(?P<id>.*)".*'.
				'</td>.*<td.*>.*</td>.*<td.*>.*</td>.*<td.*>(?P<size>.*)</td>.*<td.*>.*</td>.*'.
				'<td.*>(?P<seeds>.*)</td>.*<td.*>(?P<leech>.*)'.
				'</td>`siU', $cli->results, $matches);
            if ($res) {
                for ($i = 0; $i < $res; $i++) {
                    $matches["date"][$i] = substr($matches["date"][$i], 0, strrpos($matches["date"][$i], ' '));
                    $link                = $url . "/torrents/download/?id=" . $matches["id"][$i];
                    if (!array_key_exists($link, $ret)) {
                        $item          = $this->getNewEntry();
                        $item["desc"]  = "https:" . $matches["desc"][$i];
                        $item["name"]  = self::toUTF(self::removeTags($matches["name"][$i]), "ISO-8859-1");
                        $item["size"]  = self::formatSize($matches["size"][$i]);
                        $item["cat"]   = $catid[$matches["catid"][$i]];
                        $item["time"]  = strtotime(self::removeTags($matches["date"][$i]));
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