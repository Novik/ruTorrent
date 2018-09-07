<?php

class JackettEngine extends commonEngine
{
    public $defaults = ["public" => false, "page_size" => 25, "auth" => 0];
    public $categories = ['All'              => 0,
                          'Console'          => 1000,
                          'Console/NDS'      => 1010,
                          'Console/PSP'      => 1020,
                          'Console/Wii'      => 1030,
                          'Console/Xbox 360' => 1050,
                          'Console/PS3'      => 1080,
                          'Console/Other'    => 1090,
                          'Movies'           => 2000,
                          'Movies/Foreign'   => 2010,
                          'Movies/SD'        => 2030,
                          'Movies/HD'        => 2040,
                          'Movies/3D'        => 2050,
                          'Movies/BluRay'    => 2060,
                          'Movies/DVD'       => 2070,
                          'Audio'            => 3000,
                          'Audio/MP3'        => 3010,
                          'Audio/Video'      => 3020,
                          'Audio/Audiobook'  => 3030,
                          'Audio/Lossless'   => 3040,
                          'PC/0day'          => 4010,
                          'PC/ISO'           => 4020,
                          'PC/Mac'           => 4030,
                          'PC/Phone-Other'   => 4040,
                          'PC/Games'         => 4050,
                          'TV'               => 5000,
                          'TV/WEB-DL'        => 5010,
                          'TV/FOREIGN'       => 5020,
                          'TV/SD'            => 5030,
                          'TV/HD'            => 5040,
                          'TV/Sport'         => 5060,
                          'TV/Anime'         => 5070,
                          'TV/Documentary'   => 5080,
                          'XXX'              => 6000,
                          'XXX/Other'        => 6050,
                          'XXX/Imageset'     => 6060,
                          'XXX/Packs'        => 6070,
                          'Other'            => 7000,
                          'Books'            => 8000,
                          'Books/Comics'     => 8020,
                          'Books/Magazines'  => 8030,
                          'Books/Other'      => 8050,
    ];


    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        eval(getPluginConf('extsearch'));
        if (!isset($jackett_url) || !isset($jackett_api_key)) {
            //Send some message to say edit the conf file?
            return;
        }

        $url = $jackett_url . '/api/v2.0/indexers/all/results';
        $query = ['apikey' => $jackett_api_key, 'Query' => urldecode($what)];
        if (array_key_exists($cat, $this->categories)) {
            if ($this->categories[$cat]) {
                $query['Category'] = $this->categories[$cat];
            }
        }

        $response = $this->fetch($url . '?' . http_build_query($query));
        if (!$response) {
            return;
        }

        $json = json_decode($response->results);
        foreach ($json->Results as $result) {
            $item = $this->getNewEntry();
            $item["cat"] = $result->Tracker . " - " . $result->CategoryDesc;
            $item["desc"] = $result->Comments;
            $item["name"] = $result->Title;
            $item["size"] = $result->Size;
            $item["time"] = $result->PublishDate;
            $item["seeds"] = $result->Seeders;
            $item["peers"] = $result->Peers;
            $ret[$result->Link] = $item;
        }

    }
}
