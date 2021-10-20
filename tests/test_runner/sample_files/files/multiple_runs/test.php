<?php

namespace multiple_runs;

use strangetest;


function setup_run0() {
    return array(2, 4);
}

function teardown_run0($one, $two) {
    echo "$one $two";
}


function setup_run1() {
    return array(8, 16);
}

function teardown_run1($one, $two) {
    echo "$one $two";
}



function setup_function($one, $two) {
    return array($one, $two, $one + $two);
}



function test_one($one, $two, $three) {
    strangetest\assert_identical($one + $two, $three);
}

function test_two($one, $two) {
    strangetest\assert_identical(2 * $one, $two);
}


class test {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }

    public function test_one() {
        strangetest\assert_identical(2 * $this->one, $this->two);
    }
}
