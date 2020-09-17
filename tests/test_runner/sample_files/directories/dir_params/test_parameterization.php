<?php

namespace dir_params;

use easytest;


function setup_file($one, $two) {
    return easytest\make_argument_sets(array(
        array($one, 2 * $one),
        array($two, $two / 2)
    ));
}

function teardown_file($arglists) {
    $args = array();
    foreach ($arglists as $list) {
        $args = \array_merge($args, $list);
    }
    echo \implode(' ', $args);
}


function setup_run_for_file($one, $two) {
    echo "$one $two";
    return array($one, $two);
}

function teardown_run_for_file($one, $two) {
    echo "$one $two";
}


function test_function($one, $two) {
    echo "$one $two";
}


class TestClass {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }


    function test() {
        echo "{$this->one} {$this->two}";
    }
}
