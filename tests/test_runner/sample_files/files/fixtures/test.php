<?php

namespace file_fixtures;

use easytest;


function setup_file() {
    return array(2, 4);
}

function teardown_file($one, $two) {
    echo "$one $two";
}


function setup_function($one, $two) {
    echo "$one $two";
    return array($one, $two);
}

function teardown_function($one, $two) {
    echo "$one $two";
}


function test_one($one, $two, easytest\Context $context) {
    $context->teardown(function () { echo "teardown 1\n"; });
    $context->teardown(function () { echo "teardown 2"; });
    easytest\assert_identical(2 * $one, $two);
}

function test_two($one, $two) {
    easytest\assert_identical(6 * $one, 3 * $two);
}


class test {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }


    public function setup_object() {
        echo "{$this->one} {$this->two}";
    }

    public function teardown_object() {
        echo "{$this->one} {$this->two}";
    }


    public function setup() {
        echo "{$this->one} {$this->two}";
    }

    public function teardown() {
        echo "{$this->one} {$this->two}";
    }


    public function test_one(easytest\Context $context) {
        $context->teardown(function () { echo "teardown 1\n"; });
        $context->teardown(function () { echo "teardown 2"; });
        easytest\assert_identical(2 * $this->one, $this->two);
    }

    public function test_two() {
        easytest\assert_identical(6 * $this->one, 3 * $this->two);
    }
}
