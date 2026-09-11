<?php

class KinozalTVEngine extends commonEngine
{
    public $defaults = [ "public" => false, "page_size" => 40, "cookies" => "kinozal.guru|uid=XXX;pass=XXX;" ];

    public $categories = [ 'all' => '0',
        'Кино - Сериал' => '5',
        'Кино - Комедия' => '8',
        'Кино - Боевик / Военный' => '6',
        'Кино - Триллер / Детектив' => '15',
        'Кино - Драма' => '17',
        'Кино - Мелодрама' => '35',
        'Кино - Индийское' => '39',
        'Кино - Фантастика' => '13',
        'Кино - Фэнтези' => '14',
        'Кино - Ужас / Мистика' => '24',
        'Кино - Приключения' => '11',
        'Кино - Наше Кино' => '10',
        'Кино - Исторический' => '9',
        'Кино - Документальный' => '18',
        'Кино - Спорт' => '37',
        'Кино - Семейный' => '12',
        'Кино - Детский' => '19',
        'Кино - Классика' => '7',
        'Кино - Концерт / ТВ-шоу' => '36',
        'Кино - Театр, Опера, Балет' => '38',
        'Кино - Эротика' => '16',
        'Мульт - Буржуйский' => '21',
        'Мульт - Русский' => '22',
        'Мульт - Аниме' => '20',
        'Музыка - Буржуйская' => '3',
        'Музыка - Русская' => '4',
        'Музыка - Классическая' => '42',
        'Другое - АудиоКниги' => '2',
        'Другое - Видеоклипы' => '1',
        'Другое - Игры' => '23',
        'Другое - Программы' => '32',
        'Другое - Графика' => '40',
        'Другое - Библиотека' => '41' ];

    protected static function getInnerCategory($cat)
    {
        $categories = [
            '5' => 'Кино - Сериал',
            '8' => 'Кино - Комедия',
            '6' => 'Кино - Боевик / Военный',
            '15' => 'Кино - Триллер / Детектив',
            '17' => 'Кино - Драма',
            '35' => 'Кино - Мелодрама',
            '39' => 'Кино - Индийское',
            '13' => 'Кино - Фантастика',
            '14' => 'Кино - Фэнтези',
            '24' => 'Кино - Ужас / Мистика',
            '11' => 'Кино - Приключения',
            '10' => 'Кино - Наше Кино',
            '9' => 'Кино - Исторический',
            '18' => 'Кино - Документальный',
            '37' => 'Кино - Спорт',
            '12' => 'Кино - Семейный',
            '19' => 'Кино - Детский',
            '7' => 'Кино - Классика',
            '36' => 'Кино - Концерт / ТВ-шоу',
            '38' => 'Кино - Театр, Опера, Балет',
            '16' => 'Кино - Эротика',
            '21' => 'Мульт - Буржуйский',
            '22' => 'Мульт - Русский',
            '20' => 'Мульт - Аниме',
            '3' => 'Музыка - Буржуйская',
            '4' => 'Музыка - Русская',
            '42' => 'Музыка - Классическая',
            '2' => 'Другое - АудиоКниги',
            '1' => 'Другое - Видеоклипы',
            '23' => 'Другое - Игры',
            '32' => 'Другое - Программы',
            '40' => 'Другое - Графика',
            '41' => 'Другое - Библиотека' ];
        return(array_key_exists($cat, $categories) ? $categories[$cat] : '');
    }

    protected static function formatTime($time)
    {
        $search = [ ' ������ ', ' ������� ', ' ����� ', ' ������ ', ' ��� ', ' ���� ',
            ' ���� ', ' ������� ', ' �������� ', ' ������� ', ' ������ ', ' ������� ', '����� ', ' ������� ' ];
        $replace = [ '.01.', '.02.', '.03.', '.04.', '.05.', '.06.',
            '.07.', '.08.', '.09.', '.10.', '.11.', '.12.', '-1 day ', '0 day ' ];
        return(strtotime(str_replace($search, $replace, $time)));
    }

    public function action($what, $cat, &$ret, $limit, $useGlobalCats)
    {
        $added = 0;
        $url = 'https://kinozal.guru';
        if ($useGlobalCats) {
            $categories = [ 'all' => '0', 'tv' => '5', 'games' => '23', 'anime' => '20', 'software' => '32', 'pictures' => '40', 'books' => '41' ];
        } else {
            $categories = &$this->categories;
        }
        if (!array_key_exists($cat, $categories)) {
            $cat = $categories['all'];
        } else {
            $cat = $categories[$cat];
        }
        $what = rawurlencode(self::fromUTF(rawurldecode($what), "CP1251"));
        for ($pg = 0; $pg < 11; $pg++) {
            $cli = $this->fetch($url . '/browse.php?s=' . $what . '&a=3&page=' . $pg . '&c=' . $cat);
            if (($cli == false)
                || (strpos($cli->results, "<br><center><b>��� �������� ������") !== false)
                || (strpos($cli->results, '<input type=password size=48 name="password"') !== false)
            ) {
                break;
            }

            $res = preg_match_all('|<tr class=bg><td class="bt"><img src=.* onclick="cat(?P<cat>.*);".*'
                . '<a href="/details.php\?.*id=(?P<id>\d+)".*>(?P<name>.*)</a>.*'
                . '<td class=.*>\d+</td>.*'
                . '<td class=.*>(?P<size>.*)</td>.*'
                . '<td class=.*>(?P<leech>.*)</td>.*'
                . '<td class=.*>(?P<seeds>.*)</td>.*'
                . '<td class=.*>(?P<date>.*)</td>|siU', $cli->results, $matches);
            if ($res) {
                for ($i = 0; $i < $res; $i++) {
                    $link = $url . "/download.php?id=" . $matches["id"][$i];
                    if (!array_key_exists($link, $ret)) {
                        $item = $this->getNewEntry();
                        $item["cat"] = self::getInnerCategory($matches["cat"][$i]);
                        $item["desc"] = $url . "/details.php?id=" . $matches["id"][$i];
                        $item["name"] = self::toUTF(self::removeTags($matches["name"][$i], "CP1251"), "CP1251");
                        $item["size"] = self::formatSize(str_replace("<br>", " ", $matches["size"][$i]));
                        $item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
                        $item["peers"] = intval(self::removeTags($matches["leech"][$i]));
                        $item["time"] = self::formatTime(self::removeTags(str_replace("� ", "", $matches["date"][$i])));
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
