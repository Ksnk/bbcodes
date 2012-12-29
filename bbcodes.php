<?php
/**
 * -----------------------------------------------------------------
 * $Id: рекурсивный спуск для bb кодов.
 * ver: v0.2,
 * status : draft build.
 * GIT: origin	https://github.com/Ksnk/bbcodes (push)$
 * -----------------------------------------------------------------
 * License MIT - Serge Koriakin -  2012, sergekoriakin@gmail.com
 * -----------------------------------------------------------------
 */
class bb
{
    /** string @var - при возврате из parse_tag - имя закрывающего тега */
    private $closedTag;

    /** string @var - при анализе синонимов - оригинальный тег */
    private $openTag;

    /** string @var - при возврате из parse_tag - параметры закрывающего тега */
    private $closedTagArgs;

    /** string @var - при возврате из parse_tag - параметры закрывающего тега */
    private $breakTags = array('*');

    /**
     * Описатель тегов-синонимов.
     * - ключ массива - синоним.
     * - первый элемент массива - базовый тег
     * - второй параметр - массив дополнительных параметров для конкретизации синонима
     * @var array
     */
    private $synonyms = array(
        'a' => array('url', array()), // просто синоним без дополнительных параметров
        'p' => array('align', array()), //
        'center' => array('align', array('align' => 'center')), // синоним с параметрами
        'justify' => array('align', array('align' => 'justify')), // синоним с параметрами
        'left' => array('align', array('align' => 'left')), // синоним с параметрами
        'right' => array('align', array('align' => 'right')), // синоним с параметрами
    );

    /**
     * вспомогательная функция - форматированный вывод переменной, с учетом нотисов про
     * неопределенной переменной.
     * @param $v - значение
     * @param string $format - формат для sprintf
     * @param string $def - строка выведется при пустом или неопределенном значении
     * @return string
     */
    private function _(&$v, $format = '', $def = '')
    {
        if (empty($v)) return $def;
        if (!empty($format))
            return sprintf($format, $v);
        else
            return $v;
    }

//-------------------------------------------------------------------------
    /**
     * выдать информацию об ошибке парсинга.
     * В нормальной жизни - не нужно ничего выводить.
     * Парсинг должен выводить результат всегда. Так что
     * заткнуть вывод ошибки может быть правильным решением.
     * @param $msg
     * @param int $start - где в данный момент находимся для привязки
     * к тексту.
     */
    protected function error($msg, $start = 0)
    {
        echo $msg;
    }

    /**
     * трансформировать межтежный текст, ликвидировав слеши и расставив смайлики.
     * Смайлики нужно расставлять в такой же манере в классе-наследнике.
     * @param $text
     * @return string
     */
    protected function unslash($text)
    {
        return strtr($text, array(
            //"\r\n" => "\n",
            '\[' => '[', '[[' => '[', '\]' => ']', ']]' => ']'
        ));
    }

    /*******************************************************************************
     * парсинг, собственно
     */
    /**
     * пропустить текст до тега $tag
     * Используется для img, url, nobb тегов
     * @param $text
     * @param $start
     * @param $tag - имя тега
     * @return string - внутренее содержимое тега
     * ->skiptill($text,start,'nobb');
     */
    private function skiptill(&$text, &$start, $tag)
    {
        if (preg_match('#(.*?)\[/' . preg_quote($tag) . '([\s=][^\]]*)?\]#iu'
            , $text, $m, 0, $start)
        ) {
            $start += strlen($m[0]);
            $this->closedTag = $tag;
            $this->closedTagArgs = $m[2];
            return $m[1];
        } else {
            $pstart = $start;
            $start += strlen($text) - $start;
            return $this->unslash(substr($text, $pstart));
        }
    }

    /**
     * разбор параметров в стиле
     * =XXX yyy=zzz параметры разделены пробелами или взяты в разнообразные кавычки
     * @param $par
     * @param $synonims - набор синонимов для массива параметров
     * @return string
     * ->param('="ramb\"ler . ru" www=12345', array(0 => 'url',
     *         'target' => 'target', 'url' => 'url', 'www' => 'www'))
     */
    private function param($par, $synonims)
    {
        $start = 0;
        $result = array();
        $idx = 0;
        while (preg_match('#\s*(\w*)\s*(?:\=\s*([\'"])((?:\\\\\\\\|\\\\\\2|.)*)\\2|\=\s*(\S*)|)#us',
            $par, $m, 0, $start)) {
            if ($m[0] == '') break;
            $start += strlen($m[0]);
            if (!empty($m[3])) { // значение в кавычках
                $data = stripslashes($m[3]);
            } else if (!empty($m[4])) { // значение без кавычек
                $data = $m[4];
            } else if (!empty($m[1])) { // просто параметр
                $data = $m[1];
                $m[1] = '';
            } else
                break;
            if (empty($m[1])) {
                $m[1] = $idx++;
            } else {
                $m[1] = strtolower($m[1]);
            }
            if (array_key_exists($m[1], $synonims))
                $result[$synonims[$m[1]]] = $data;
        }
        return $result;
    }

    /**
     * основная функция парсинга - поиск тега и его анализ посредством рекурсивного спуска
     * @param $text
     * @param $start
     * @return string
     */
    private function parse_tags(&$text, &$start)
    {
        $parsed = '';
        while (true) {
            //  find next tag
            if (preg_match('#((?:\\\\\[|\[\[|.)*?)\[(\/)?(\*|\w+)(.*?)\]#us'
                , $text, $m, 0, $start)
            ) {
                // is it closed?
                $parsed .= $this->unslash($m[1]);
                $start += strlen($m[0]);
                $m[3] = strtolower($m[3]);
                $data = array();
                if (array_key_exists($m[3], $this->synonyms)) {
                    $data = $this->synonyms[$m[3]][1];
                    $method = 'parse_tag_' . $this->synonyms[$m[3]][0];
                } else
                    $method = 'parse_tag_' . $m[3];
                if (method_exists($this, $method)) {
                    if (!empty($m[2])) {
                        // is it closed?
                        $this->closedTag = $m[3];
                        $this->closedTagArgs = $m[4];
                        return $parsed;
                    } else {
                        // it's opened
                        $this->openTag[] = $method;
                        $parsed .= $this->$method($text, $start, $m[4], $m[3], $data);
                        array_pop($this->openTag);
                    }
                } else if (in_array($m[3], $this->breakTags)) {
                    $this->closedTag = $m[3];
                    $this->closedTagArgs = $m[4];
                    return $parsed;
                } else {
                    $parsed .= '[' . $m[2] . $m[3] . $m[4] . ']';
                }
            } else {
                $parsed .= $this->unslash(substr($text, $start));
                $start += strlen($text) - $start;

                break;
            }
        }
        return $parsed;
    }

    /**
     * внешняя  функция. - Парсинг текста с вв кодами.
     * @param $text
     * @return mixed|string
     */
    public function parse($text)
    {
        $text = trim($text);
        $start = 0;
        $parsed = $this->parse_tags($text, $start);
        if ($start != strlen($text)) $this->error('wtf?', $start);
        return $parsed;
    }

    /*******************************************************************************
     * парсинг конкретных тегов
     * функция parse_tag_XXX - полный генератор смысла тега.
     */
    /**
     * Парсинг тега b
     * @param $text
     * @param $start
     * @param $par
     * @return mixed|null
     */
    private function parse_tag_b(&$text, &$start, $par)
    {
        $parsed = '<b>' . $this->parse_tags($text, $start) . '</b>';
        if ($this->closedTag != 'b') $this->error('tag B not closed', $start);
        return $parsed;
    }

    /**
     * Парсинг тега b
     * @param $text
     * @param $start
     * @param $par
     * @return mixed|null
     */
    private function parse_tag_u(&$text, &$start, $par)
    {
        $parsed = '<u>' . $this->parse_tags($text, $start) . '</u>';
        if ($this->closedTag != 'u') $this->error('tag U not closed', $start);
        return $parsed;
    }

    /**
     * Парсинг тега hr  - неописанны и неиспользуемый тег. Зачем он нужен - уа не приложу.
     * @param $text
     * @param $start
     * @param $par
     * @return mixed|null
     */
    private function parse_tag_hr(&$text, &$start, $par)
    {
        return '<hr>';
    }

    /**
     * Парсинг тега nobb
     * пропускаем все внутри тегов
     * @param $text
     * @param $start
     * @param $par
     * @return mixed|null
     */
    private function parse_tag_nobb(&$text, &$start, $par)
    {
        $parsed = $this->skiptill($text, $start, 'nobb');
        if ($this->closedTag != 'nobb') $this->error('tag nobb not closed', $start);
        return $parsed;
    }

    /**
     * Парсинг тега url
     * формируем ссылку из текста внутри тега
     * @param $text
     * @param $start
     * @param $par
     * @param string $tag
     * @param array $data
     * @return mixed|null
     */
    private function parse_tag_url(&$text, &$start, $par, $tag = '', $data = array())
    {
        $parsed = $this->skiptill($text, $start, $tag);

        $data = array_merge($this->param($par, array(
            0 => 'url', 'url' => 'url', 'src' => 'url', 'href' => 'url',
            'title' => 'title',
            'target' => 'target')), $data);
        if (empty($data['url']))
            $data['url'] = $parsed;
        if ($this->closedTag != $tag) $this->error('tag ' . $tag . ' not closed', $start);
        return '<a href="' . $data['url'] . '"' .
            $this->_($data['title'], ' title="%s"') .
            '>' . $parsed . '</a>';
    }

    /**
     * Парсинг тега img
     * формируем ссылку из текста внутри тега
     * @param $text
     * @param $start
     * @param $par
     * @param string $tag
     * @param array $data
     * @return mixed|null
     */
    private function parse_tag_img(&$text, &$start, $par, $tag = '', $data = array())
    {
        $parsed = $this->skiptill($text, $start, $tag);

        $data = array_merge($this->param($par, array(
            0 => 'title',
            'title' => 'title',
            'height' => 'height',
            'width' => 'width',
            'border' => 'border')), $data);
        if (empty($data['src']))
            $data['src'] = $parsed;
        return '<img src="' . $data['src'] . '"' .
            $this->_($data['height'], ' height="%s"') .
            $this->_($data['width'], ' width="%s"') .
            $this->_($data['title'], ' title="%s"') .
            '>';
    }

    /**
     * Парсинг тега align
     * формируем p с нужным алигном . Тег не является обязательно двойным, так что при наличии второго
     * такого-же тега или синонима на стеке - прекращаем тег и начинаем новый без парсинга.
     * @param $text
     * @param $start
     * @param $par
     * @param string $tag
     * @param array $data
     * @return mixed|null
     */
    private function parse_tag_align(&$text, &$start, $par, $tag = '', $data = array())
    {
        $data = array_merge($this->param($par, array(
            0 => 'align', 'align' => 'align')), $data);
        $key = array_search(__FUNCTION__, $this->openTag);
        if ($key !== false && $key < count($this->openTag) - 1) {
            // no closed tag
            // just place close-open and skip
            return "</p> <p" .
                $this->_($data['align'], ' style="text-align:%s;"') . '>';
        }
        $parsed = $this->parse_tags($text, $start);
        //if ($this->closedTag != $tag) $this->error('tag ' . $tag . ' not closed', $start);
        return '<p' .
            $this->_($data['align'], ' style="text-align:%s;"') .
            '>' . $parsed . '</p>';
    }

    /**
     * Парсинг тега list
     * пропускаем все внутри тегов
     * @param $text
     * @param $start
     * @param $par
     * @return mixed|null
     */
    private function parse_tag_list(&$text, &$start, $par)
    {
        $data = $this->param($par, array(
            0 => 'type', 'type' => 'type'));
        $list = 'ul';
        if (isset($data['type'])) {
            if (ctype_digit($data['type'])) {
                $list = 'ol';
                $data['start'] = $data['type'];
                $data['type'] = '1';
            } else if (in_array($data['type'], array('disc', 'circle', 'square'))) {
                $list = 'ul';
            } else if ('I' == $data['type']) {
                $list = 'ol';
                $data['type'] = 'I';
            } else if ('i' == $data['type']) {
                $list = 'ol';
                $data['type'] = 'i';
            } else if (preg_match('/^[A-Z]/', $data['type'])) {
                $list = 'ol';
                $data['start'] = ord($data['type']) - ord('A') + 1;
                $data['type'] = 'A';
            }
        }
        $li = array();
        $xli = $this->parse_tags($text, $start);
        if (trim($xli != '')) {
            $li[] = '<li'
                . $this->_($data['start'], ' value="%s"')
                . '>' . $xli . '</li>';
        }
        while ($this->closedTag == '*') {
            $li_par = array_merge($this->param($this->closedTagArgs, array(
                0 => 'start', 'start' => 'start')), $data);
            $li[] = '<li'
                . $this->_($li_par['start'], ' value="%s"')
                . '>' . $this->parse_tags($text, $start) . '</li>';
        }
        return '<' . $list
            . $this->_($data['type'], ' type="%s"')
            . '>' . implode("\n", $li) . '</' . $list . '>';

    }

}

/**
 * натыренные по интернетам хочушки и тудушки.  Частично уже нехочушки.
 *
 * тег [a] - синоним [url]
[a]адрес_ссылки[/a]
[a target=_blank]www.idealcountry.org.ua[/a]
[a=адрес_ссылки]текст ссылки[/a]
[a=www.idealcountry.org.ua title="Хороший форум"]Ідеальна Країна[/a]
[a href=адрес_ссылки]текст ссылки[/a]
[a href=toloka.org.ua]Толока[/a]
[a url=адрес_ссылки]текст ссылки[/a]
[a url=plus.minus.org.ua]Плюс.Мінус[/a]
[url=plus.minus.org.ua]Плюс.Мінус[/url]
 *
 * якоря в тексте
 * [a id=this /], [a name=this /] или [a anchor=this /]
 *
 * [align=right] ...[/align] [center] === [align=center]  [left] [right]  [justify]
 *
 * [bbcode] юютекст не транслируется иикодом [/bbcode]
 *
 * [code] - текст для програмных вставок
 *
 * [color='xxx']
 *
 * [img width=100 height=42]http://idealcountry.org.ua/img/site/top/logo.gif[/img]
 *
 * [list] [*]
 *
 * [nobb] [/nobb]
 *
 * [quote]
 */