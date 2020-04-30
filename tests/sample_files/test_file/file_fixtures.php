<?php

namespace file_fixtures;

use easytest;

function setup_file() {
    return easytest\args(2, 4);
}

function teardown_file($one, $two) {
    easytest\assert_identical(2, $one);
    easytest\assert_identical(4, $two);
}


function test_one($one, $two) {
    easytest\assert_identical(2 * $one, $two);
}

function test_two($one, $two) {
    easytest\assert_identical(6 * $one, 3 * $two);
}


class Test {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }

    public function test_one() {
        easytest\assert_identical([2, 4], [$this->one, $this->two]);
    }
}
