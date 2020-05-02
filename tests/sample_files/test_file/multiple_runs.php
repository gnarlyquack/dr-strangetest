<?php

namespace multiple_runs;

use easytest;


function setup_file() {
    return easytest\arglists(array(
        array(2, 4),
        array(8, 16)
    ));
}

function teardown_file($args) {
    echo __FUNCTION__;
    easytest\assert_identical(array(array(2, 4), array(8, 16)), $args);
}



function setup_functions($one, $two) {
    return array($one, $two, $one + $two);
}

function teardown_functions($one, $two, $three) {
    easytest\assert_identical($one + $two, $three);
}



function test_function_one($one, $two, $three) {
    easytest\assert_identical($one + $two, $three);
}

function test_function_two($one, $two) {
    easytest\assert_identical(2 * $one, $two);
}



class TestClass {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }

    public function test() {
        easytest\assert_identical(2 * $this->one, $this->two);
    }
}
