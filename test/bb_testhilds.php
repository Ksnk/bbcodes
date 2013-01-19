<?php

if (!defined('PHPUnit_MAIN_METHOD')) {
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}

include ('header.inc.php');

/**
 * проверка функционирования типичного наследника BB парсера
 */
class bbChild extends   bb {
    // новый тег  - синоним

    // новый тег  - базовый

    // ошибки

    // смайлики

}


/**
 * тестирование парсинга синтаксически правильных конструкций
 */
class bb_testChilds extends PHPUnit_Framework_TestCase
{

    function get_bb(){
        static $bb;
        if(empty($bb)) $bb=new bbChild();
        return $bb;
    }

    function test_url()
    {
        $bb=$this->get_bb();
        $text = "[a]rambler.ru[/a] а вот - Рамблер
         [a=google.com title=Гугель-гугель!]Гугель[/a] - вот гугель
         [url google.com title=Гугель-гугель!]Гугель[/url] - еще один гугель
          [url url=google.com title=Гугель-гугель!]Гугель[/url] - еще один гугель
";
        $this->assertEquals('<a href="rambler.ru">rambler.ru</a> а вот - Рамблер
         <a href="google.com" title="Гугель-гугель!">Гугель</a> - вот гугель
         <a href="google">Гугель</a> - еще один гугель
          <a href="google.com" title="Гугель-гугель!">Гугель</a> - еще один гугель', $bb->parse($text));
    }

    function test_align()
    {
        $bb=$this->get_bb();
        $text = "[p]rambler.ru[/p] а вот - Рамблер
         [center]Гугель[p] - вот гугель
         [justify]Гугель[p] - еще один гугель
          [left]Гугель[right] - еще один гугель
";
        $this->assertEquals('<p>rambler.ru</p> а вот - Рамблер
         <p style="text-align:center;">Гугель</p> <p> - вот гугель
         </p> <p style="text-align:justify;">Гугель</p> <p> - еще один гугель
          </p> <p style="text-align:left;">Гугель</p> <p style="text-align:right;"> - еще один гугель</p>', $bb->parse($text));
    }

    function test_img()
    {
        $bb=$this->get_bb();
        $text = "[img]rambler.ru[/img] а вот - Рамблер
         [img Hello]Гугель[/img] - вот гугель
";
        $this->assertEquals('<img src="rambler.ru"> а вот - Рамблер
         <img src="Гугель" title="Hello"> - вот гугель', $bb->parse($text));
    }

    /*   function test_url()
{
    $bb=$this->get_bb();
    $text = "[center][b]«Князь Андрей [[b]встал и [b]подошел к [b]окну, чтобы[/b] отворить его.[/b] Как только он открыл ставни, лунный свет, [a=google.com title=google!]как будто[/a] он настороже у окна давно
ждал этого, ворвался в комнату.[/b] комнату.[/center][nobb] в комнату.[/b]  в комнату.[/b] комнату.[/nobb]комнату.
[list=I][*=3]Как[u]\[*]только[/u] он[*] открыл[*] ставни[*=10] last[/list]
[nobb] в комнату.[/b]  в комнату.[/b] комнату.
";

    $this->assertEquals("12345 <br>\n", $bb->parse($text));
}
    */
}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('bb_testChilds');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>