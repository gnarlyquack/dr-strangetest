<?php

namespace dir_params;


function setup_file($one, $two) {
    echo "$one $two";
    return array($one, $two);
}

function teardown_file($one, $two) {
    echo "$one $two";
}


function setup_run0($one, $two) {
    echo __FUNCTION__;
    return array($one, 2 * $one);
}

function teardown_run0($one, $two) {
    echo "$one $two";
}


function setup_run1($one, $two) {
    echo __FUNCTION__;
    return array($two, $two / 2);
}

function teardown_run1($one, $two) {
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
