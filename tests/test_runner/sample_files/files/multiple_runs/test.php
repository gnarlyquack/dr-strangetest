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
    \print_r($args);
}



function setup_function($one, $two) {
    return array($one, $two, $one + $two);
}



function test_one($one, $two, $three) {
    easytest\assert_identical($one + $two, $three);
}

function test_two($one, $two) {
    easytest\assert_identical(2 * $one, $two);
}


class test {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }

    public function test_one() {
        easytest\assert_identical(2 * $this->one, $this->two);
    }
}
