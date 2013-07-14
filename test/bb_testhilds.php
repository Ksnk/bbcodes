<?php

if (!function_exists('phpunit_autoload')) {
    $GLOBALS['runit']=true;
    ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(dirname(__FILE__)));
    require 'PHPUnit/Autoload.php';
}


include ('header.inc.php');

/**
 * проверка функционирования типичного наследника BB парсера
 */
class bbChild extends   bb {

    function __construct(){
       //parent::__construct();
        $this->tags['quote']=array('quote', // функция парсинга  - parse_tag_quote
            'html'=>'div', // формирование тега
            '_param'=>array(    // правила парсинга параметров тега
                0 => 'id', 'id' => 'id', 'quote' => 'id'
            ),
            '_parce'=>array(  // правила формирование html тега
                '_'=>'class|quote',
                'id'=>'attr|data-id="%s"', // добавить в атрибут
            )
        );
    }
    // новый тег  - синоним

    // новый тег  - базовый
    protected function parse_tag_quote(&$text, &$start, $par, $tag = '', $data = array()){
        $parsed = $this->parse_tags($text, $start);
        $data = array_merge($this->param($par, $data['_param']
        ), $data);
        if ($this->closedTag != $tag)
            $this->error('tag [' . $tag . '] not closed', $start);
        return $this->html_openTag($data,$tag).$parsed.$this->html_closeTag($data,$tag);
    }

    // ошибки

    // смайлики

    protected function unslash($text){
        return strtr(parent::unslash($text), array(
            //"\r\n" => "\n",
            ':)' => '<img src="smiles/smile.gif">'
        ));
    }

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

    function test_quote()
    {
        $bb=$this->get_bb();
        $text = "[quote]rambler.[a]ru[/a][/quote] а вот - Рамблер
         [a=google.com title=Гугель-гугель!]Гугель[/a] - вот гугель

";
        $this->assertEquals('<div  class="quote">rambler.<a href="ru">ru</a></div> а вот - Рамблер
         <a href="google.com" title="Гугель-гугель!">Гугель</a> - вот гугель', $bb->parse($text));
    }

    function test_smiles()
    {
        $bb=$this->get_bb();
        $text = "[quote ]ramb :) ler.[a title=:)]ru[/a][/quote] а вот - Рамблер
         [a=google.com title=Гугель-гугель!]Гугель[/a] - вот гугель

";
        $this->assertEquals('<div>ramb <img src="smiles/smile.gif"> ler.<a href="ru" title=":)">ru</a></div> а вот - Рамблер
         <a href="google.com" title="Гугель-гугель!">Гугель</a> - вот гугель', $bb->parse($text));
    }

}

if (!defined('PHPUnit_MAIN_METHOD')) {
    $suite = new PHPUnit_Framework_TestSuite('bb_testChilds');
    PHPUnit_TextUI_TestRunner::run($suite);
}
?>