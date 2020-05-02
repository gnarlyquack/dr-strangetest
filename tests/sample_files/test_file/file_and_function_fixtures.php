<?php

namespace file_fixtures;

use easytest;


function setup_file_for_functions_and_classes() {
    return array(2, 4);
}

function teardown_file_for_everybody($one, $two) {
    easytest\assert_identical(2, $one);
    easytest\assert_identical(4, $two);
}



function setup_functions_only($one, $two) {
    return array($one, $two, $one + $two);
}

function teardown_functions_only($one, $two, $three) {
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

    public function test_one() {
        easytest\assert_identical(array(2, 4), array($this->one, $this->two));
    }
}
